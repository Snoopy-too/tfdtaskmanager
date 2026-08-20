/**
 * Editor Core Module
 * Initializes FabricJS canvas, coordinates modules, and handles canvas save/load lifecycle.
 */
(function() {
    'use strict';

    // Global override to prevent FabricJS from scrolling viewport when focusing hidden textarea
    const originalFocus = HTMLTextAreaElement.prototype.focus;
    HTMLTextAreaElement.prototype.focus = function(options) {
        if (this.hasAttribute && this.hasAttribute('data-fabric-hiddentextarea')) {
            options = options || {};
            options.preventScroll = true;
        }
        return originalFocus.call(this, options);
    };

    let canvas;
    let isSaving = false;
    let saveTimeout = null;

    function initCanvas() {
        if (fabric) {
            if (fabric.IText) {
                fabric.IText.prototype.hiddenTextareaContainer = document.body;
            }
            if (fabric.Text) {
                fabric.Text.prototype._setTextStyles = function(ctx, charStyle, forMeasuring) {
                    ctx.textBaseline = 'alphabetic';
                    if (this.path) {
                        switch (this.pathAlign) {
                            case 'center': ctx.textBaseline = 'middle'; break;
                            case 'ascender': ctx.textBaseline = 'top'; break;
                            case 'descender': ctx.textBaseline = 'bottom'; break;
                        }
                    }
                    ctx.font = this._getFontDeclaration(charStyle, forMeasuring);
                };
            }
            if (fabric.Textbox) {
                const origSplit = fabric.Textbox.prototype._splitTextIntoLines;
                fabric.Textbox.prototype._splitTextIntoLines = function(text) {
                    const result = origSplit.call(this, text);
                    if (result && result.lines && result.lines.length > 1) {
                        const joined = result.lines.join('\n');
                        if (this.text !== joined) {
                            this.text = joined;
                        }
                    }
                    return result;
                };
            }
        }

        const width = window.studioConfig.canvasWidth;
        const height = window.studioConfig.canvasHeight;
        
        const wrapper = document.getElementById('canvas-container-wrapper');
        if (wrapper) {
            wrapper.style.width = width + 'px';
            wrapper.style.height = height + 'px';
        }

        canvas = new fabric.Canvas('editor-canvas', {
            width: width,
            height: height,
            backgroundColor: '#ffffff',
            preserveObjectStacking: true
        });

        window.editorCanvas = canvas;

        if (window.editorViewport && typeof window.editorViewport.syncControlAppearance === 'function') {
            window.editorViewport.syncControlAppearance();
        }

        if (window.studioConfig.isViewMode) {
            document.body.classList.add('view-only-mode');
            canvas.selection = false;

            canvas.on('object:added', function(e) {
                const obj = e.target;
                if (obj.id !== 'safe-zone-guide' && obj.id !== 'bleed-zone-guide') {
                    obj.selectable = false;
                    obj.evented = false;
                    obj.lockMovementX = true;
                    obj.lockMovementY = true;
                    obj.lockScalingX = true;
                    obj.lockScalingY = true;
                    obj.lockRotation = true;
                    obj.hoverCursor = 'default';
                }
            });

            canvas.on('after:render', function() {
                canvas.getObjects().forEach(obj => {
                    if (obj.id !== 'safe-zone-guide' && obj.id !== 'bleed-zone-guide') {
                        obj.selectable = false;
                        obj.evented = false;
                        obj.lockMovementX = true;
                        obj.lockMovementY = true;
                        obj.lockScalingX = true;
                        obj.lockScalingY = true;
                        obj.lockRotation = true;
                        obj.hoverCursor = 'default';
                    }
                });
            });
        } else {
            canvas.on('object:added', function(e) {
                const obj = e.target;
                if (obj && obj.id !== 'safe-zone-guide' && obj.id !== 'bleed-zone-guide') {
                    const effectiveZoom = (window.editorViewport ? window.editorViewport.getZoomLevel() : 1.0) || 1.0;
                    obj.cornerSize = Math.max(12, Math.round(15 / effectiveZoom));
                    obj.touchCornerSize = Math.max(20, Math.round(26 / effectiveZoom));
                    obj.borderScaleFactor = Math.max(1, Math.round(2 / effectiveZoom));
                    obj.padding = Math.max(2, Math.round(5 / effectiveZoom));
                    obj.transparentCorners = false;
                    obj.cornerColor = '#ffffff';
                    obj.cornerStrokeColor = '#4f46e5';
                    obj.borderColor = '#6366f1';
                    obj.cornerStyle = 'rect';
                    obj.setCoords();
                }
                triggerAutoSave();
            });

            canvas.on('object:modified', triggerAutoSave);
            canvas.on('object:removed', triggerAutoSave);
        }
        
        canvas.on('selection:created', (e) => {
            if (window.editorViewport) window.editorViewport.syncControlAppearance();
            onSelectionChanged(e);
        });
        canvas.on('selection:updated', (e) => {
            if (window.editorViewport) window.editorViewport.syncControlAppearance();
            onSelectionChanged(e);
        });
        canvas.on('selection:cleared', onSelectionCleared);

        loadCanvas();
        if (window.editorViewport) window.editorViewport.setupZoomControls();
    }

    function triggerAutoSave() {
        if (window.editorHistory) window.editorHistory.pushState();

        if (saveTimeout) clearTimeout(saveTimeout);
        
        setSaveStatus('Saving changes...', 'pulse');
        
        saveTimeout = setTimeout(() => {
            saveCanvas();
        }, 1500);
    }

    function saveCanvas() {
        if (isSaving || !canvas) return Promise.resolve();
        isSaving = true;

        const canvasJson = JSON.stringify(canvas.toJSON(['id', 'name', 'layerType', 'variable_binding', 'properties', 'is_locked']));
        
        const layers = [];
        canvas.getObjects().forEach((obj, index) => {
            if (obj.id === 'safe-zone-guide' || obj.id === 'bleed-zone-guide') return;

            let textVal = '';
            let layerType = 'shape';
            let properties = {};

            if (obj.type === 'i-text' || obj.type === 'text' || obj.type === 'textbox') {
                textVal = obj.text;
                layerType = 'text';
                properties = {
                    fontSize: obj.fontSize,
                    fill: obj.fill,
                    fontFamily: obj.fontFamily,
                    bold: obj.fontWeight === 'bold',
                    italic: obj.fontStyle === 'italic',
                    align: obj.textAlign
                };
            } else if (obj.type === 'image') {
                layerType = 'image';
                properties = {
                    src: obj.src || '',
                    original_filename: obj.original_filename || ''
                };
            } else if (obj.layerType === 'dropzone') {
                layerType = 'dropzone';
            }

            layers.push({
                name: obj.name || (obj.type.charAt(0).toUpperCase() + obj.type.slice(1) + ' ' + (index + 1)),
                layer_type: layerType,
                z_index: index,
                x_pos: obj.left,
                y_pos: obj.top,
                width: obj.width * obj.scaleX,
                height: obj.height * obj.scaleY,
                rotation: obj.angle || 0,
                opacity: obj.opacity || 1.0,
                properties: properties,
                variable_binding: obj.variable_binding || null,
                is_visible: obj.visible,
                is_locked: obj.lockMovementX || false,
                text: textVal
            });
        });

        const formData = new FormData();
        formData.append('csrf_token', window.studioConfig.csrfToken);
        formData.append('template_id', window.studioConfig.templateId.toString());
        formData.append('canvas_json', canvasJson);
        formData.append('layers', JSON.stringify(layers));

        return fetch('api.php?action=save_canvas', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': window.studioConfig.csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            isSaving = false;
            if (data.success) {
                setSaveStatus('All changes saved', 'saved');
                if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                    window.layerManager.renderLayersList();
                }
                return data;
            } else {
                setSaveStatus('Error saving changes', 'error');
                console.error(data.error);
                throw new Error(data.error || 'Failed to save template canvas.');
            }
        })
        .catch(err => {
            isSaving = false;
            setSaveStatus('Error saving changes', 'error');
            console.error(err);
            throw err;
        });
    }

    function upgradeLegacyTextLayers() {
        if (!canvas) return;
        const legacyTextObjects = canvas.getObjects().filter(obj => obj.type === 'i-text' || obj.type === 'text');
        if (legacyTextObjects.length === 0) return;

        legacyTextObjects.forEach(obj => {
            const textVal = obj.text || '';
            const defaultWidth = (obj.width && obj.width > 50) ? obj.width : Math.round(canvas.width * 0.8);
            const options = obj.toObject(['name', 'variable_binding', 'id', 'lockMovementX', 'lockMovementY', 'originX', 'originY']);
            delete options.type;
            options.width = defaultWidth;
            const newTextbox = new fabric.Textbox(textVal, options);
            const index = canvas.getObjects().indexOf(obj);
            canvas.remove(obj);
            canvas.insertAt(newTextbox, index, false);
        });
    }

    function refreshCanvasTextLayers() {
        if (!canvas || !document.fonts) return;
        const textObjects = canvas.getObjects().filter(obj => obj.type === 'i-text' || obj.type === 'text' || obj.type === 'textbox');
        
        if (typeof fabric !== 'undefined') {
            fabric.charWidthsCache = {};
            if (fabric.util) fabric.util.charWidthsCache = {};
        }

        textObjects.forEach(obj => {
            if (!obj.fontFamily) return;
            document.fonts.load(`1em "${obj.fontFamily}"`).then(() => {
                obj.dirty = true;
                if (typeof obj.initDimensions === 'function') {
                    obj.initDimensions();
                }
                obj.setCoords();
                canvas.requestRenderAll();
            }).catch(err => {
                console.warn(`Font load failed for family: ${obj.fontFamily}`, err);
            });
        });
    }

    function loadCanvas() {
        if (!canvas) return;
        setSaveStatus('Loading canvas...', 'pulse');
        
        fetch(`api.php?action=load_canvas&template_id=${window.studioConfig.templateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.canvas_json) {
                canvas.loadFromJSON(data.canvas_json, () => {
                    upgradeLegacyTextLayers();

                    if (window.guideRenderer && typeof window.guideRenderer.renderGuides === 'function') {
                        window.guideRenderer.renderGuides();
                    }
                    
                    refreshCanvasTextLayers();

                    if (window.editorViewport && typeof window.editorViewport.syncControlAppearance === 'function') {
                        window.editorViewport.syncControlAppearance();
                    }

                    canvas.renderAll();
                    setSaveStatus('All changes saved', 'saved');
                    
                    if (window.editorHistory) window.editorHistory.pushStateImmediate();

                    if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                        window.layerManager.renderLayersList();
                    }

                    if (window.propertyInspector && typeof window.propertyInspector.syncCanvasBgInputs === 'function') {
                        window.propertyInspector.syncCanvasBgInputs();
                    }
                });
            } else {
                if (window.guideRenderer && typeof window.guideRenderer.renderGuides === 'function') {
                    window.guideRenderer.renderGuides();
                }
                setSaveStatus('All changes saved', 'saved');
                
                if (window.editorHistory) window.editorHistory.pushStateImmediate();

                if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                    window.layerManager.renderLayersList();
                }

                if (window.propertyInspector && typeof window.propertyInspector.syncCanvasBgInputs === 'function') {
                    window.propertyInspector.syncCanvasBgInputs();
                }
            }
        })
        .catch(err => {
            setSaveStatus('Load failed', 'error');
            console.error(err);
        });
    }

    function setSaveStatus(text, state) {
        const indicator = document.getElementById('save-status');
        if (!indicator) return;
        const dot = indicator.querySelector('span');
        const textSpan = document.getElementById('save-status-text');

        if (textSpan) textSpan.textContent = text;
        if (dot) {
            dot.className = 'h-2 w-2 rounded-full';
            if (state === 'pulse') {
                dot.classList.add('bg-indigo-400', 'animate-pulse');
            } else if (state === 'saved') {
                dot.classList.add('bg-emerald-500');
            } else if (state === 'error') {
                dot.classList.add('bg-rose-500');
            }
        }
    }

    function onSelectionChanged(e) {
        const activeObject = canvas.getActiveObject();
        if (activeObject && activeObject.id !== 'safe-zone-guide' && activeObject.id !== 'bleed-zone-guide') {
            if (window.propertyInspector && typeof window.propertyInspector.inspect === 'function') {
                window.propertyInspector.inspect(activeObject);
            }
            if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                window.layerManager.renderLayersList();
            }
        } else {
            canvas.discardActiveObject();
            onSelectionCleared();
        }
    }

    function onSelectionCleared() {
        if (window.propertyInspector && typeof window.propertyInspector.clearInspect === 'function') {
            window.propertyInspector.clearInspect();
        }
        if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
            window.layerManager.renderLayersList();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initCanvas();
        if (window.editorHistory) window.editorHistory.setupHistoryControls();

        if (window.assetPicker && typeof window.assetPicker.loadAssets === 'function') {
            window.assetPicker.loadAssets();
        }

        if (document.fonts) {
            document.fonts.ready.then(refreshCanvasTextLayers);
            document.fonts.addEventListener('loadingdone', refreshCanvasTextLayers);
        }

        window.addEventListener('load', refreshCanvasTextLayers);

        if (window.editorImporter && typeof window.editorImporter.setupImportTemplateControls === 'function') {
            window.editorImporter.setupImportTemplateControls();
        }

        if (!window.studioConfig.isViewMode) {
            setInterval(() => {
                const formData = new FormData();
                formData.append('csrf_token', window.studioConfig.csrfToken);
                formData.append('template_id', window.studioConfig.templateId.toString());
                
                fetch('api.php?action=heartbeat_lock', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-CSRF-Token': window.studioConfig.csrfToken }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.locked) {
                        alert("This design template has been locked by another user or session expired. Entering read-only mode.");
                        window.location.reload();
                    }
                })
                .catch(err => console.error('Lock heartbeat failed:', err));
            }, 20000);

            window.addEventListener('beforeunload', () => {
                const formData = new FormData();
                formData.append('csrf_token', window.studioConfig.csrfToken);
                formData.append('template_id', window.studioConfig.templateId.toString());
                navigator.sendBeacon('api.php?action=release_lock', formData);
            });
        }
    });

    window.toggleCanvasOrientation = function() {
        if (window.editorViewport && typeof window.editorViewport.toggleCanvasOrientation === 'function') {
            window.editorViewport.toggleCanvasOrientation();
        }
    };

    window.editorCore = {
        saveCanvas,
        triggerAutoSave,
        loadCanvas,
        setSaveStatus,
        undo: () => (window.editorHistory ? window.editorHistory.undo() : null),
        redo: () => (window.editorHistory ? window.editorHistory.redo() : null),
        pushState: () => (window.editorHistory ? window.editorHistory.pushState() : null),
        duplicateObject: (obj) => (window.editorHistory ? window.editorHistory.duplicateObject(obj) : null),
        refreshCanvasTextLayers,
        importTemplateToCanvas: (sId, name, grp, row) => (window.editorImporter ? window.editorImporter.importTemplateToCanvas(sId, name, grp, row) : null),
        toggleCanvasOrientation: window.toggleCanvasOrientation,
        getZoomLevel: () => (window.editorViewport ? window.editorViewport.getZoomLevel() : 1.0)
    };
})();

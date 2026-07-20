/**
 * Editor Core Module
 * Initializes FabricJS canvas, handles zoom/pan and auto-save syncing.
 */
(function() {
    'use strict';
    // Global override to prevent FabricJS from scrolling the viewport when focusing the hidden textarea
    const originalFocus = HTMLTextAreaElement.prototype.focus;
    HTMLTextAreaElement.prototype.focus = function(options) {
        if (this.hasAttribute && this.hasAttribute('data-fabric-hiddentextarea')) {
            options = options || {};
            options.preventScroll = true;
        }
        return originalFocus.call(this, options);
    };

    let canvas;
    let zoomLevel = 1.0;
    let isSaving = false;
    let saveTimeout = null;
    let clipboard = null;

    // History undo/redo state variables
    const historyStack = [];
    const redoStack = [];
    let isUndoingRedoing = false;
    let historyTimeout = null;
    const maxHistorySize = 50;

    // Initialize Canvas
    function initCanvas() {
        // Force FabricJS to append the hidden textarea to the document body instead of the transformed wrapper.
        // This is CRITICAL to prevent massive scroll jumps when entering edit mode inside a scaled container.
        if (fabric) {
            if (fabric.IText) {
                fabric.IText.prototype.hiddenTextareaContainer = document.body;
            }
            if (fabric.Text) {
                fabric.Text.prototype._setTextStyles = function(ctx, charStyle, forMeasuring) {
                    ctx.textBaseline = 'alphabetic';
                    if (this.path) {
                        switch (this.pathAlign) {
                            case 'center':
                                ctx.textBaseline = 'middle';
                                break;
                            case 'ascender':
                                ctx.textBaseline = 'top';
                                break;
                            case 'descender':
                                ctx.textBaseline = 'bottom';
                                break;
                        }
                    }
                    ctx.font = this._getFontDeclaration(charStyle, forMeasuring);
                };
            if (fabric.Textbox) {
                // ponytail: fix FabricJS 5.3.1 Textbox space-stripping bug that causes ghost trailing characters (" e r") at end of wrapped lines
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
        
        // Size wrapper container
        const wrapper = document.getElementById('canvas-container-wrapper');
        wrapper.style.width = width + 'px';
        wrapper.style.height = height + 'px';

        canvas = new fabric.Canvas('editor-canvas', {
            width: width,
            height: height,
            backgroundColor: '#ffffff',
            preserveObjectStacking: true // Keep z-order ordering consistent
        });

        window.editorCanvas = canvas;

        if (window.studioConfig.isViewMode) {
            document.body.classList.add('view-only-mode');
            canvas.selection = false;

            // Make any newly added or rendered objects non-selectable
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
            // Auto-save on modify
            canvas.on('object:modified', triggerAutoSave);
            canvas.on('object:added', triggerAutoSave);
            canvas.on('object:removed', triggerAutoSave);
        }
        
        // Listen to selection changes for Property Inspector
        canvas.on('selection:created', onSelectionChanged);
        canvas.on('selection:updated', onSelectionChanged);
        canvas.on('selection:cleared', onSelectionCleared);

        // Hidden textarea focus is now handled by the HTMLTextAreaElement.prototype.focus override.

        loadCanvas();
        setupZoomControls();
    }

    // Debounce Save Trigger
    function triggerAutoSave() {
        pushState(); // Push to local undo/redo stack

        if (saveTimeout) clearTimeout(saveTimeout);
        
        setSaveStatus('Saving changes...', 'pulse');
        
        saveTimeout = setTimeout(() => {
            saveCanvas();
        }, 1500); // 1.5s debounce
    }

    // Save Canvas state to database
    function saveCanvas() {
        if (isSaving) return Promise.resolve();
        isSaving = true;

        const canvasJson = JSON.stringify(canvas.toJSON(['id', 'name', 'layerType', 'variable_binding', 'properties', 'is_locked']));
        
        // Simplified layer metadata sync
        const layers = [];
        canvas.getObjects().forEach((obj, index) => {
            // Exclude guides overlay layers
            if (obj.id === 'safe-zone-guide' || obj.id === 'bleed-zone-guide') {
                return;
            }

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
                // Reload layer list in sidebar
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

    // ponytail: upgrade legacy i-text objects to fabric.Textbox for automatic word wrapping
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

// Helper to refresh all text layers once their fonts are loaded
    function refreshCanvasTextLayers() {
        if (!canvas || !document.fonts) return;
        const textObjects = canvas.getObjects().filter(obj => obj.type === 'i-text' || obj.type === 'text' || obj.type === 'textbox');
        
        // Clear FabricJS character width cache to force dynamic re-measurement
        if (typeof fabric !== 'undefined') {
            fabric.charWidthsCache = {};
            if (fabric.util) {
                fabric.util.charWidthsCache = {};
            }
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

    // Load Canvas state
    function loadCanvas() {
        loadTemplateCanvas();
    }

    function loadTemplateCanvas() {
        if (!canvas) return;
        setSaveStatus('Loading canvas...', 'pulse');
        
        fetch(`api.php?action=load_canvas&template_id=${window.studioConfig.templateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.canvas_json) {
                canvas.loadFromJSON(data.canvas_json, () => {
                    upgradeLegacyTextLayers();

                    // Re-render guides on top after load
                    if (window.guideRenderer && typeof window.guideRenderer.renderGuides === 'function') {
                        window.guideRenderer.renderGuides();
                    }
                    
                    // Recalculate dimensions of all text objects once fonts are ready
                    refreshCanvasTextLayers();

                    canvas.renderAll();
                    setSaveStatus('All changes saved', 'saved');
                    
                    pushStateImmediate(); // Push base state

                    if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                        window.layerManager.renderLayersList();
                    }

                    if (window.propertyInspector && typeof window.propertyInspector.syncCanvasBgInputs === 'function') {
                        window.propertyInspector.syncCanvasBgInputs();
                    }
                });
            } else {
                // Initialize clean slate canvas with guides
                if (window.guideRenderer && typeof window.guideRenderer.renderGuides === 'function') {
                    window.guideRenderer.renderGuides();
                }
                setSaveStatus('All changes saved', 'saved');
                
                pushStateImmediate(); // Push base state

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

    // Update Status Indicator UI
    function setSaveStatus(text, state) {
        const indicator = document.getElementById('save-status');
        const dot = indicator.querySelector('span');
        const textSpan = document.getElementById('save-status-text');

        textSpan.textContent = text;
        dot.className = 'h-2 w-2 rounded-full';

        if (state === 'pulse') {
            dot.classList.add('bg-indigo-400', 'animate-pulse');
        } else if (state === 'saved') {
            dot.classList.add('bg-emerald-500');
        } else if (state === 'error') {
            dot.classList.add('bg-rose-500');
        }
    }

    // Selection Events
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
            // Guides or multiple selections
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

    // Zoom setup
    function setupZoomControls() {
        const viewport = document.querySelector('.canvas-viewport');
        const wrapper = document.getElementById('canvas-container-wrapper');
        const zoomContainer = document.getElementById('canvas-zoom-container');
        const zoomInput = document.getElementById('zoom-value');
        
        function applyZoom() {
            const width = window.studioConfig.canvasWidth;
            const height = window.studioConfig.canvasHeight;
            
            if (zoomContainer) {
                zoomContainer.style.width = (width * zoomLevel) + 'px';
                zoomContainer.style.height = (height * zoomLevel) + 'px';
            }
            
            wrapper.style.transform = `scale(${zoomLevel})`;
            wrapper.style.transformOrigin = '0 0';
            if (zoomInput) {
                zoomInput.value = Math.round(zoomLevel * 100) + '%';
            }
        }

        document.getElementById('btn-zoom-in').addEventListener('click', () => {
            zoomLevel = Math.min(zoomLevel + 0.1, 3.0);
            applyZoom();
        });

        document.getElementById('btn-zoom-out').addEventListener('click', () => {
            zoomLevel = Math.max(zoomLevel - 0.1, 0.2);
            applyZoom();
        });

        document.getElementById('btn-zoom-fit').addEventListener('click', fitToView);

        if (zoomInput) {
            zoomInput.addEventListener('change', () => {
                let val = parseFloat(zoomInput.value.replace(/[^0-9.]/g, ''));
                if (!isNaN(val)) {
                    val = Math.max(10, Math.min(val, 300));
                    zoomLevel = val / 100;
                }
                applyZoom();
            });

            zoomInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    zoomInput.blur();
                }
            });

            zoomInput.addEventListener('focus', () => {
                zoomInput.select();
            });
        }

        function fitToView() {
            const containerWidth = viewport.clientWidth - 64;
            const containerHeight = viewport.clientHeight - 64;
            const widthScale = containerWidth / window.studioConfig.canvasWidth;
            const heightScale = containerHeight / window.studioConfig.canvasHeight;
            
            zoomLevel = Math.min(widthScale, heightScale, 1.0);
            applyZoom();
        }

        // Trigger initial fit to view
        setTimeout(fitToView, 200);
    }

    // Push canvas state to local history stack with a small debounce
    function pushState() {
        if (isUndoingRedoing || !canvas) return;

        if (historyTimeout) clearTimeout(historyTimeout);

        historyTimeout = setTimeout(() => {
            const json = JSON.stringify(canvas.toJSON(['id', 'name', 'layerType', 'variable_binding', 'properties', 'is_locked']));
            if (historyStack.length > 0 && historyStack[historyStack.length - 1] === json) return;

            historyStack.push(json);
            if (historyStack.length > maxHistorySize) {
                historyStack.shift();
            }
            
            // Clear redo stack on new action
            redoStack.length = 0;
            updateHistoryButtons();
        }, 300); // 300ms debounce
    }

    // Push canvas state to local history stack immediately
    function pushStateImmediate() {
        if (isUndoingRedoing || !canvas) return;

        const json = JSON.stringify(canvas.toJSON(['id', 'name', 'layerType', 'variable_binding', 'properties', 'is_locked']));
        if (historyStack.length > 0 && historyStack[historyStack.length - 1] === json) return;

        historyStack.push(json);
        if (historyStack.length > maxHistorySize) {
            historyStack.shift();
        }
        updateHistoryButtons();
    }

    function undo() {
        if (historyStack.length <= 1) return;

        isUndoingRedoing = true;
        const currentState = historyStack.pop();
        redoStack.push(currentState);

        const previousState = historyStack[historyStack.length - 1];
        canvas.loadFromJSON(previousState, () => {
            if (window.guideRenderer && typeof window.guideRenderer.renderGuides === 'function') {
                window.guideRenderer.renderGuides();
            }
            canvas.renderAll();
            isUndoingRedoing = false;

            triggerAutoSave();

            // Refresh properties inspector selection if any
            const activeObj = canvas.getActiveObject();
            if (activeObj) {
                if (window.propertyInspector && typeof window.propertyInspector.inspect === 'function') {
                    window.propertyInspector.inspect(activeObj);
                }
            } else {
                if (window.propertyInspector && typeof window.propertyInspector.clearInspect === 'function') {
                    window.propertyInspector.clearInspect();
                }
            }
            updateHistoryButtons();
        });
    }

    function redo() {
        if (redoStack.length === 0) return;

        isUndoingRedoing = true;
        const nextState = redoStack.pop();
        historyStack.push(nextState);

        canvas.loadFromJSON(nextState, () => {
            if (window.guideRenderer && typeof window.guideRenderer.renderGuides === 'function') {
                window.guideRenderer.renderGuides();
            }
            canvas.renderAll();
            isUndoingRedoing = false;

            triggerAutoSave();

            // Refresh properties inspector selection if any
            const activeObj = canvas.getActiveObject();
            if (activeObj) {
                if (window.propertyInspector && typeof window.propertyInspector.inspect === 'function') {
                    window.propertyInspector.inspect(activeObj);
                }
            } else {
                if (window.propertyInspector && typeof window.propertyInspector.clearInspect === 'function') {
                    window.propertyInspector.clearInspect();
                }
            }
            updateHistoryButtons();
        });
    }

    function updateHistoryButtons() {
        const btnUndo = document.getElementById('btn-undo');
        const btnRedo = document.getElementById('btn-redo');

        if (btnUndo) {
            btnUndo.disabled = (historyStack.length <= 1);
        }
        if (btnRedo) {
            btnRedo.disabled = (redoStack.length === 0);
        }
    }

    function setupHistoryControls() {
        const btnUndo = document.getElementById('btn-undo');
        if (btnUndo) {
            btnUndo.addEventListener('click', undo);
        }
        
        const btnRedo = document.getElementById('btn-redo');
        if (btnRedo) {
            btnRedo.addEventListener('click', redo);
        }

        // Keyboard Shortcuts
        window.addEventListener('keydown', (e) => {
            const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
            if (activeTag === 'input' || activeTag === 'textarea' || (document.activeElement && document.activeElement.isContentEditable)) {
                return;
            }

            // ponytail: delete active canvas object/selection on Delete key
            if (e.key === 'Delete') {
                const activeObj = canvas.getActiveObject();
                if (activeObj && !activeObj.isEditing) {
                    e.preventDefault();
                    if (activeObj.id === 'safe-zone-guide' || activeObj.id === 'bleed-zone-guide') {
                        return;
                    }

                    if (activeObj.type === 'activeSelection') {
                        activeObj.forEachObject((obj) => {
                            canvas.remove(obj);
                        });
                        canvas.discardActiveObject();
                    } else {
                        canvas.remove(activeObj);
                        canvas.discardActiveObject();
                    }
                    canvas.renderAll();
                    if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                        window.layerManager.renderLayersList();
                    }
                    triggerAutoSave();
                    if (window.propertyInspector && typeof window.propertyInspector.clearInspect === 'function') {
                        window.propertyInspector.clearInspect();
                    }
                }
            }

            const isCtrl = e.ctrlKey || e.metaKey;

            // Ctrl + Z (Undo)
            if (isCtrl && e.key.toLowerCase() === 'z') {
                if (e.shiftKey) {
                    // Ctrl + Shift + Z (Redo)
                    e.preventDefault();
                    redo();
                } else {
                    e.preventDefault();
                    undo();
                }
            }

            // Ctrl + Y (Redo)
            if (isCtrl && e.key.toLowerCase() === 'y') {
                e.preventDefault();
                redo();
            }

            // Ctrl + C (Copy)
            if (isCtrl && e.key.toLowerCase() === 'c') {
                const activeObj = canvas.getActiveObject();
                if (activeObj && !activeObj.isEditing) {
                    e.preventDefault();
                    copyObjects(activeObj);
                }
            }

            // Ctrl + V (Paste)
            if (isCtrl && e.key.toLowerCase() === 'v') {
                const activeObj = canvas.getActiveObject();
                if (!activeObj || !activeObj.isEditing) {
                    e.preventDefault();
                    pasteObjects();
                }
            }

            // Ctrl + D (Duplicate)
            if (isCtrl && e.key.toLowerCase() === 'd') {
                e.preventDefault();
                const activeObj = canvas.getActiveObject();
                if (activeObj) {
                    duplicateObject(activeObj);
                }
            }

            // Arrow keys nudge navigation
            const activeObj = canvas.getActiveObject();
            if (activeObj && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
                e.preventDefault(); // Prevent browser scrolling
                const step = e.shiftKey ? 10 : 1;
                
                switch (e.key) {
                    case 'ArrowUp':
                        activeObj.set('top', activeObj.top - step);
                        break;
                    case 'ArrowDown':
                        activeObj.set('top', activeObj.top + step);
                        break;
                    case 'ArrowLeft':
                        activeObj.set('left', activeObj.left - step);
                        break;
                    case 'ArrowRight':
                        activeObj.set('left', activeObj.left + step);
                        break;
                }
                
                activeObj.setCoords();
                canvas.renderAll();
                triggerAutoSave();
                
                // Live update properties inspector form
                if (window.propertyInspector && typeof window.propertyInspector.inspect === 'function') {
                    window.propertyInspector.inspect(activeObj);
                }
            }
        });
    }

    // Duplicate an object and offset its position slightly
    function duplicateObject(obj) {
        if (!obj || obj.id === 'safe-zone-guide' || obj.id === 'bleed-zone-guide') return;

        obj.clone((clonedObj) => {
            canvas.discardActiveObject();
            
            clonedObj.set({
                left: obj.left + 30, // Offset by 30px X and Y
                top: obj.top + 30,
                evented: true
            });

            if (clonedObj.type === 'activeSelection') {
                clonedObj.canvas = canvas;
                clonedObj.forEachObject((o) => {
                    o.set({
                        name: o.name ? (o.name + ' Copy') : (o.type.charAt(0).toUpperCase() + o.type.slice(1) + ' Copy'),
                        evented: true
                    });
                    canvas.add(o);
                });
                clonedObj.setCoords();
                canvas.setActiveObject(clonedObj);
            } else {
                clonedObj.set({
                    name: clonedObj.name ? (clonedObj.name + ' Copy') : (clonedObj.type.charAt(0).toUpperCase() + clonedObj.type.slice(1) + ' Copy')
                });
                canvas.add(clonedObj);
                canvas.setActiveObject(clonedObj);
            }

            canvas.renderAll();
            triggerAutoSave();

            // Refresh layer list
            if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                window.layerManager.renderLayersList();
            }
        }, ['id', 'name', 'layerType', 'variable_binding', 'properties', 'original_filename', 'stored_filename', 'is_locked']);
    }

    // Copy active object(s)
    function copyObjects(obj) {
        if (!obj || obj.id === 'safe-zone-guide' || obj.id === 'bleed-zone-guide') return;

        obj.clone((cloned) => {
            clipboard = cloned;
        }, ['id', 'name', 'layerType', 'variable_binding', 'properties', 'original_filename', 'stored_filename', 'is_locked']);
    }

    // Paste copied object(s)
    function pasteObjects() {
        if (!clipboard) return;

        clipboard.clone((clonedObj) => {
            canvas.discardActiveObject();

            clonedObj.set({
                left: clonedObj.left + 30,
                top: clonedObj.top + 30,
                evented: true
            });

            if (clonedObj.type === 'activeSelection') {
                clonedObj.canvas = canvas;
                clonedObj.forEachObject((obj) => {
                    obj.set({
                        name: obj.name ? (obj.name + ' Copy') : (obj.type.charAt(0).toUpperCase() + obj.type.slice(1) + ' Copy'),
                        evented: true
                    });
                    canvas.add(obj);
                });
                clonedObj.setCoords();
                canvas.setActiveObject(clonedObj);
            } else {
                clonedObj.set({
                    name: clonedObj.name ? (clonedObj.name + ' Copy') : (clonedObj.type.charAt(0).toUpperCase() + clonedObj.type.slice(1) + ' Copy')
                });
                canvas.add(clonedObj);
                canvas.setActiveObject(clonedObj);
            }

            // Offset clipboard for subsequent paste operations
            clipboard.top += 30;
            clipboard.left += 30;

            canvas.renderAll();
            triggerAutoSave();

            // Refresh layer list
            if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                window.layerManager.renderLayersList();
            }
        }, ['id', 'name', 'layerType', 'variable_binding', 'properties', 'original_filename', 'stored_filename', 'is_locked']);
    }

    document.addEventListener('DOMContentLoaded', () => {
        initCanvas();
        setupHistoryControls();

        // Register and pre-load project assets/custom fonts immediately on startup
        if (window.assetPicker && typeof window.assetPicker.loadAssets === 'function') {
            window.assetPicker.loadAssets();
        }

        // Listen for browser font load completion events to handle asynchronous stylesheet loads (e.g. Google Fonts latency)
        if (document.fonts) {
            document.fonts.ready.then(() => {
                refreshCanvasTextLayers();
            });
            document.fonts.addEventListener('loadingdone', () => {
                refreshCanvasTextLayers();
            });
        }

        window.addEventListener('load', () => {
            refreshCanvasTextLayers();
        });

        setupImportTemplateControls();

        if (!window.studioConfig.isViewMode) {
            // Heartbeat lock refresh
            setInterval(() => {
                const formData = new FormData();
                formData.append('csrf_token', window.studioConfig.csrfToken);
                formData.append('template_id', window.studioConfig.templateId.toString());
                
                fetch('api.php?action=heartbeat_lock', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-Token': window.studioConfig.csrfToken
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.locked) {
                        alert("This design template has been locked by another user or session expired. Entering read-only mode.");
                        window.location.reload();
                    }
                })
                .catch(err => {
                    console.error('Lock heartbeat failed:', err);
                });
            }, 20000); // every 20 seconds

            // Release lock on page unload
            window.addEventListener('beforeunload', () => {
                const formData = new FormData();
                formData.append('csrf_token', window.studioConfig.csrfToken);
                formData.append('template_id', window.studioConfig.templateId.toString());
                navigator.sendBeacon('api.php?action=release_lock', formData);
            });
        }
    });

    // Template Import Component Handler
    function setupImportTemplateControls() {
        const btnImport = document.getElementById('btn-import-template');
        const modal = document.getElementById('modal-import-template');
        const btnClose = document.getElementById('btn-close-import-modal');
        const btnCancel = document.getElementById('btn-cancel-import');
        const btnConfirm = document.getElementById('btn-confirm-import');
        const select = document.getElementById('import-template-select');
        const chkGroup = document.getElementById('import-as-group');

        if (!btnImport || !modal) return;

        function openModal() {
            if (!select) return;
            select.innerHTML = '<option value="">Loading templates...</option>';
            modal.classList.remove('hidden');

            const projectId = window.studioConfig.projectId;
            const currentTemplateId = window.studioConfig.templateId;

            fetch(`api.php?action=list_templates&project_id=${projectId}&exclude_id=${currentTemplateId}`)
                .then(r => r.json())
                .then(templates => {
                    select.innerHTML = '';
                    if (!templates || templates.length === 0) {
                        select.innerHTML = '<option value="">No other templates found in this project</option>';
                        btnConfirm.disabled = true;
                        return;
                    }
                    btnConfirm.disabled = false;
                    templates.forEach(t => {
                        const opt = document.createElement('option');
                        opt.value = t.id;
                        opt.textContent = `${t.name} (${t.width}x${t.height}px)`;
                        select.appendChild(opt);
                    });
                })
                .catch(err => {
                    console.error('Failed to load project templates:', err);
                    select.innerHTML = '<option value="">Error loading templates</option>';
                    btnConfirm.disabled = true;
                });
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

        btnImport.addEventListener('click', openModal);
        if (btnClose) btnClose.addEventListener('click', closeModal);
        if (btnCancel) btnCancel.addEventListener('click', closeModal);

        if (btnConfirm) {
            btnConfirm.addEventListener('click', () => {
                const sourceTemplateId = select ? select.value : null;
                const asGroup = chkGroup ? chkGroup.checked : true;
                const selectedOption = select ? select.options[select.selectedIndex] : null;
                const templateName = selectedOption ? selectedOption.textContent.split(' (')[0] : 'Imported Component';

                if (!sourceTemplateId) {
                    alert('Please select a template to import.');
                    return;
                }

                closeModal();
                importTemplateToCanvas(parseInt(sourceTemplateId, 10), templateName, asGroup);
            });
        }
    }

    function importTemplateToCanvas(sourceTemplateId, templateName, groupAsSingleComponent) {
        setSaveStatus('Importing template component...', 'pulse');

        fetch(`api.php?action=load_canvas&template_id=${sourceTemplateId}`)
            .then(r => r.json())
            .then(data => {
                if (!data || !data.canvas_json) {
                    alert('The selected template contains no canvas data.');
                    setSaveStatus('Import failed', 'error');
                    return;
                }

                let parsed;
                try {
                    parsed = JSON.parse(data.canvas_json);
                } catch (e) {
                    console.error('Failed to parse target template canvas JSON:', e);
                    alert('Invalid canvas data format in selected template.');
                    setSaveStatus('Import failed', 'error');
                    return;
                }

                if (!parsed.objects || !Array.isArray(parsed.objects) || parsed.objects.length === 0) {
                    alert('The selected template has no elements to import.');
                    setSaveStatus('Selected template is empty', 'error');
                    return;
                }

                // Filter out guide lines / overlays
                const filteredObjects = parsed.objects.filter(o => o.id !== 'safe-zone-guide' && o.id !== 'bleed-zone-guide');

                if (filteredObjects.length === 0) {
                    alert('The selected template has no importable elements.');
                    setSaveStatus('Selected template is empty', 'error');
                    return;
                }

                fabric.util.enlivenObjects(filteredObjects, (enlivenedObjects) => {
                    if (!enlivenedObjects || enlivenedObjects.length === 0) {
                        alert('Failed to reconstruct objects from template.');
                        setSaveStatus('Import failed', 'error');
                        return;
                    }

                    canvas.discardActiveObject();

                    if (groupAsSingleComponent && enlivenedObjects.length > 1) {
                        const group = new fabric.Group(enlivenedObjects, {
                            name: `Component: ${templateName}`,
                            left: (canvas.width - 200) / 2,
                            top: (canvas.height - 200) / 2
                        });
                        canvas.add(group);
                        canvas.setActiveObject(group);
                    } else if (groupAsSingleComponent && enlivenedObjects.length === 1) {
                        const singleObj = enlivenedObjects[0];
                        singleObj.set({
                            name: singleObj.name ? `${singleObj.name} (${templateName})` : templateName,
                            left: (canvas.width - (singleObj.width || 100)) / 2,
                            top: (canvas.height - (singleObj.height || 100)) / 2
                        });
                        canvas.add(singleObj);
                        canvas.setActiveObject(singleObj);
                    } else {
                        // Import as separate un-grouped objects
                        const createdObjects = [];
                        enlivenedObjects.forEach((obj, idx) => {
                            obj.set({
                                name: obj.name ? `${obj.name}` : `Layer ${idx + 1} (${templateName})`,
                                left: (obj.left || 0) + 20,
                                top: (obj.top || 0) + 20
                            });
                            canvas.add(obj);
                            createdObjects.push(obj);
                        });
                        if (createdObjects.length > 0) {
                            const sel = new fabric.ActiveSelection(createdObjects, { canvas: canvas });
                            canvas.setActiveObject(sel);
                        }
                    }

                    canvas.renderAll();
                    triggerAutoSave();

                    if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                        window.layerManager.renderLayersList();
                    }

                    setSaveStatus('Template component imported', 'saved');
                });
            })
            .catch(err => {
                console.error('Error importing template:', err);
                alert('Failed to load target template data.');
                setSaveStatus('Import failed', 'error');
            });
    }

    // Expose functions globally
    window.editorCore = {
        saveCanvas: saveCanvas,
        triggerAutoSave: triggerAutoSave,
        loadCanvas: loadCanvas,
        setSaveStatus: setSaveStatus,
        undo: undo,
        redo: redo,
        pushState: pushState,
        duplicateObject: duplicateObject,
        refreshCanvasTextLayers: refreshCanvasTextLayers,
        importTemplateToCanvas: importTemplateToCanvas,
        getZoomLevel: () => zoomLevel
    };
})();

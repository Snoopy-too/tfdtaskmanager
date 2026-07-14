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
        if (fabric && fabric.IText) {
            fabric.IText.prototype.hiddenTextareaContainer = document.body;
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

        // Auto-save on modify
        canvas.on('object:modified', triggerAutoSave);
        canvas.on('object:added', triggerAutoSave);
        canvas.on('object:removed', triggerAutoSave);
        
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
        if (isSaving) return;
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

            if (obj.type === 'i-text' || obj.type === 'text') {
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

        fetch('api.php?action=save_canvas', {
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
            } else {
                setSaveStatus('Error saving changes', 'error');
                console.error(data.error);
            }
        })
        .catch(err => {
            isSaving = false;
            setSaveStatus('Error saving changes', 'error');
            console.error(err);
        });
    }

    // Load Canvas state
    function loadCanvas() {
        setSaveStatus('Loading canvas...', 'pulse');
        
        fetch(`api.php?action=load_canvas&template_id=${window.studioConfig.templateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.canvas_json) {
                canvas.loadFromJSON(data.canvas_json, () => {
                    // Re-render guides on top after load
                    if (window.guideRenderer && typeof window.guideRenderer.renderGuides === 'function') {
                        window.guideRenderer.renderGuides();
                    }
                    canvas.renderAll();
                    setSaveStatus('All changes saved', 'saved');
                    
                    pushStateImmediate(); // Push base state

                    if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                        window.layerManager.renderLayersList();
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
        
        function applyZoom() {
            const width = window.studioConfig.canvasWidth;
            const height = window.studioConfig.canvasHeight;
            
            if (zoomContainer) {
                zoomContainer.style.width = (width * zoomLevel) + 'px';
                zoomContainer.style.height = (height * zoomLevel) + 'px';
            }
            
            wrapper.style.transform = `scale(${zoomLevel})`;
            wrapper.style.transformOrigin = '0 0';
            document.getElementById('zoom-value').textContent = Math.round(zoomLevel * 100) + '%';
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
                name: obj.name ? (obj.name + ' Copy') : (obj.type.charAt(0).toUpperCase() + obj.type.slice(1) + ' Copy'),
                evented: true
            });

            canvas.add(clonedObj);
            canvas.setActiveObject(clonedObj);
            canvas.renderAll();

            triggerAutoSave();

            // Refresh layer list
            if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                window.layerManager.renderLayersList();
            }
        }, ['id', 'name', 'layerType', 'variable_binding', 'properties', 'original_filename', 'stored_filename', 'is_locked']);
    }

    // DOM Ready
    document.addEventListener('DOMContentLoaded', () => {
        initCanvas();
        setupHistoryControls();
    });

    // Expose functions globally
    window.editorCore = {
        saveCanvas: saveCanvas,
        triggerAutoSave: triggerAutoSave,
        loadCanvas: loadCanvas,
        setSaveStatus: setSaveStatus,
        undo: undo,
        redo: redo,
        pushState: pushState,
        duplicateObject: duplicateObject
    };
})();

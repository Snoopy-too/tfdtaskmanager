/**
 * Editor History & Clipboard Module
 * Handles local undo/redo stacks, keyboard shortcuts (delete, copy, paste, duplicate, nudge).
 */
(function() {
    'use strict';

    let clipboard = null;
    const historyStack = [];
    const redoStack = [];
    let isUndoingRedoing = false;
    let historyTimeout = null;
    const maxHistorySize = 50;

    function pushState() {
        const canvas = window.editorCanvas;
        if (isUndoingRedoing || !canvas) return;

        if (historyTimeout) clearTimeout(historyTimeout);

        historyTimeout = setTimeout(() => {
            const json = JSON.stringify(canvas.toJSON(['id', 'name', 'layerType', 'variable_binding', 'properties', 'is_locked']));
            if (historyStack.length > 0 && historyStack[historyStack.length - 1] === json) return;

            historyStack.push(json);
            if (historyStack.length > maxHistorySize) {
                historyStack.shift();
            }
            
            redoStack.length = 0;
            updateHistoryButtons();
        }, 300);
    }

    function pushStateImmediate() {
        const canvas = window.editorCanvas;
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
        const canvas = window.editorCanvas;
        if (!canvas || historyStack.length <= 1) return;

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

            if (window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
                window.editorCore.triggerAutoSave();
            }

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
        const canvas = window.editorCanvas;
        if (!canvas || redoStack.length === 0) return;

        isUndoingRedoing = true;
        const nextState = redoStack.pop();
        historyStack.push(nextState);

        canvas.loadFromJSON(nextState, () => {
            if (window.guideRenderer && typeof window.guideRenderer.renderGuides === 'function') {
                window.guideRenderer.renderGuides();
            }
            canvas.renderAll();
            isUndoingRedoing = false;

            if (window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
                window.editorCore.triggerAutoSave();
            }

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

    function duplicateObject(obj) {
        const canvas = window.editorCanvas;
        if (!canvas || !obj || obj.id === 'safe-zone-guide' || obj.id === 'bleed-zone-guide') return;

        obj.clone((clonedObj) => {
            canvas.discardActiveObject();
            
            clonedObj.set({
                left: obj.left + 30,
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
            if (window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
                window.editorCore.triggerAutoSave();
            }

            if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                window.layerManager.renderLayersList();
            }
        }, ['id', 'name', 'layerType', 'variable_binding', 'properties', 'original_filename', 'stored_filename', 'is_locked']);
    }

    function copyObjects(obj) {
        if (!obj || obj.id === 'safe-zone-guide' || obj.id === 'bleed-zone-guide') return;

        obj.clone((cloned) => {
            clipboard = cloned;
        }, ['id', 'name', 'layerType', 'variable_binding', 'properties', 'original_filename', 'stored_filename', 'is_locked']);
    }

    function pasteObjects() {
        const canvas = window.editorCanvas;
        if (!canvas || !clipboard) return;

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

            clipboard.top += 30;
            clipboard.left += 30;

            canvas.renderAll();
            if (window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
                window.editorCore.triggerAutoSave();
            }

            if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                window.layerManager.renderLayersList();
            }
        }, ['id', 'name', 'layerType', 'variable_binding', 'properties', 'original_filename', 'stored_filename', 'is_locked']);
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

        window.addEventListener('keydown', (e) => {
            const canvas = window.editorCanvas;
            if (!canvas) return;

            const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
            if (activeTag === 'input' || activeTag === 'textarea' || (document.activeElement && document.activeElement.isContentEditable)) {
                return;
            }

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
                    if (window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
                        window.editorCore.triggerAutoSave();
                    }
                    if (window.propertyInspector && typeof window.propertyInspector.clearInspect === 'function') {
                        window.propertyInspector.clearInspect();
                    }
                }
            }

            const isCtrl = e.ctrlKey || e.metaKey;

            if (isCtrl && e.key.toLowerCase() === 'z') {
                if (e.shiftKey) {
                    e.preventDefault();
                    redo();
                } else {
                    e.preventDefault();
                    undo();
                }
            }

            if (isCtrl && e.key.toLowerCase() === 'y') {
                e.preventDefault();
                redo();
            }

            if (isCtrl && e.key.toLowerCase() === 'c') {
                const activeObj = canvas.getActiveObject();
                if (activeObj && !activeObj.isEditing) {
                    e.preventDefault();
                    copyObjects(activeObj);
                }
            }

            if (isCtrl && e.key.toLowerCase() === 'v') {
                const activeObj = canvas.getActiveObject();
                if (!activeObj || !activeObj.isEditing) {
                    e.preventDefault();
                    pasteObjects();
                }
            }

            if (isCtrl && e.key.toLowerCase() === 'd') {
                e.preventDefault();
                const activeObj = canvas.getActiveObject();
                if (activeObj) {
                    duplicateObject(activeObj);
                }
            }

            const activeObj = canvas.getActiveObject();
            if (activeObj && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
                e.preventDefault();
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
                if (window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
                    window.editorCore.triggerAutoSave();
                }
                
                if (window.propertyInspector && typeof window.propertyInspector.inspect === 'function') {
                    window.propertyInspector.inspect(activeObj);
                }
            }
        });
    }

    window.editorHistory = {
        pushState,
        pushStateImmediate,
        undo,
        redo,
        duplicateObject,
        copyObjects,
        pasteObjects,
        setupHistoryControls
    };
})();

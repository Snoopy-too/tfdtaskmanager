/**
 * Property Inspector Module
 * Synchronizes selected FabricJS object properties with the sidebar form controls.
 */
(function() {
    'use strict';

    let activeObj = null;
    let isUpdatingForm = false;

    function initInspector() {
        const form = document.getElementById('inspector-form');
        if (!form) return;

        // Common properties
        document.getElementById('prop-name').addEventListener('input', (e) => updateActiveProp('name', e.target.value));
        document.getElementById('prop-left').addEventListener('input', (e) => updateActiveProp('left', parseFloat(e.target.value) || 0));
        document.getElementById('prop-top').addEventListener('input', (e) => updateActiveProp('top', parseFloat(e.target.value) || 0));
        document.getElementById('prop-width').addEventListener('input', (e) => updateActiveScaleWidth(parseFloat(e.target.value) || 0));
        document.getElementById('prop-height').addEventListener('input', (e) => updateActiveScaleHeight(parseFloat(e.target.value) || 0));

        // Millimeter width and height controls
        document.getElementById('prop-width-mm').addEventListener('input', (e) => {
            const mmVal = parseFloat(e.target.value) || 0;
            const pxVal = Math.round((mmVal / 25.4) * 300);
            document.getElementById('prop-width').value = pxVal;
            updateActiveScaleWidth(pxVal);
        });
        document.getElementById('prop-height-mm').addEventListener('input', (e) => {
            const mmVal = parseFloat(e.target.value) || 0;
            const pxVal = Math.round((mmVal / 25.4) * 300);
            document.getElementById('prop-height').value = pxVal;
            updateActiveScaleHeight(pxVal);
        });
        document.getElementById('prop-rotation').addEventListener('input', (e) => updateActiveProp('angle', parseFloat(e.target.value) || 0));
        document.getElementById('prop-opacity').addEventListener('input', (e) => updateActiveProp('opacity', (parseFloat(e.target.value) || 0) / 100));

        // Alignment Buttons
        document.getElementById('btn-align-h').addEventListener('click', () => {
            if (!activeObj || !window.editorCanvas) return;
            const canvas = window.editorCanvas;
            if (activeObj.originX === 'center') {
                activeObj.set('left', canvas.width / 2);
            } else {
                activeObj.centerH();
            }
            activeObj.setCoords();
            document.getElementById('prop-left').value = Math.round(activeObj.left);
            canvas.renderAll();
            window.editorCore.triggerAutoSave();
        });
        document.getElementById('btn-align-v').addEventListener('click', () => {
            if (!activeObj || !window.editorCanvas) return;
            const canvas = window.editorCanvas;
            if (activeObj.originY === 'center') {
                activeObj.set('top', canvas.height / 2);
            } else {
                activeObj.centerV();
            }
            activeObj.setCoords();
            document.getElementById('prop-top').value = Math.round(activeObj.top);
            canvas.renderAll();
            window.editorCore.triggerAutoSave();
        });

        // Text properties & inline formatting toolbar
        document.getElementById('prop-text-val').addEventListener('input', (e) => {
            applyTextContentChange(e.target.value);
        });

        if (window.inspectorText && typeof window.inspectorText.bindTextToolbar === 'function') {
            window.inspectorText.bindTextToolbar(() => activeObj, () => isUpdatingForm);
        }
        
        const bindSelect = document.getElementById('prop-text-bind');
        if (bindSelect) {
            bindSelect.addEventListener('change', (e) => {
                updateActiveProp('variable_binding', e.target.value || null);
                // Re-inspect to sync bound row value in textarea
                if (activeObj) inspect(activeObj);
                // Also trigger rendering update in template engine
                if (window.templateEngine && typeof window.templateEngine.applyBindings === 'function') {
                    window.templateEngine.applyBindings();
                }
            });
        }

        document.getElementById('prop-font-size').addEventListener('input', (e) => updateActiveProp('fontSize', parseInt(e.target.value) || 12));
        document.getElementById('prop-font-family').addEventListener('change', (e) => {
            const font = e.target.value;
            updateActiveProp('fontFamily', font);
            if (document.fonts) {
                document.fonts.load(`1em "${font}"`).then(() => {
                    // Clear character width cache to force dynamic re-measurement
                    if (typeof fabric !== 'undefined') {
                        fabric.charWidthsCache = {};
                        if (fabric.util) {
                            fabric.util.charWidthsCache = {};
                        }
                    }
                    if (activeObj && (activeObj.type === 'i-text' || activeObj.type === 'text')) {
                        activeObj.dirty = true;
                        if (typeof activeObj.initDimensions === 'function') {
                            activeObj.initDimensions();
                        }
                        activeObj.setCoords();
                    }
                    if (window.editorCanvas) {
                        window.editorCanvas.requestRenderAll();
                    }
                });
            }
        });
        document.getElementById('prop-text-color').addEventListener('input', (e) => updateActiveProp('fill', e.target.value));
        document.getElementById('prop-text-align').addEventListener('change', (e) => updateActiveProp('textAlign', e.target.value));
        
        document.getElementById('prop-font-bold').addEventListener('change', (e) => updateActiveProp('fontWeight', e.target.checked ? 'bold' : 'normal'));
        document.getElementById('prop-font-italic').addEventListener('change', (e) => updateActiveProp('fontStyle', e.target.checked ? 'italic' : 'normal'));

        // Shape properties
        document.getElementById('prop-fill-color').addEventListener('input', (e) => {
            if (!activeObj || isUpdatingForm) return;
            const fillOpacity = document.getElementById('prop-fill-opacity');
            const alpha = (parseInt(fillOpacity.value) || 0) / 100;
            const isTransparent = document.getElementById('prop-fill-transparent').checked;
            
            if (isTransparent) {
                document.getElementById('prop-fill-transparent').checked = false;
            }
            
            const rgbaColor = hexToRgba(e.target.value, alpha);
            updateActiveProp('fill', rgbaColor);
        });
        
        const fillTrans = document.getElementById('prop-fill-transparent');
        if (fillTrans) {
            fillTrans.addEventListener('change', (e) => {
                if (e.target.checked) {
                    updateActiveProp('fill', 'transparent');
                    document.getElementById('prop-fill-opacity').value = 0;
                } else {
                    const colorInput = document.getElementById('prop-fill-color');
                    const fillOpacity = document.getElementById('prop-fill-opacity');
                    let alphaVal = parseInt(fillOpacity.value) || 0;
                    if (alphaVal === 0) {
                        alphaVal = 100;
                        fillOpacity.value = 100;
                    }
                    const rgbaColor = hexToRgba(colorInput.value || '#000000', alphaVal / 100);
                    updateActiveProp('fill', rgbaColor);
                }
            });
        }

        const fillOpacityInput = document.getElementById('prop-fill-opacity');
        if (fillOpacityInput) {
            fillOpacityInput.addEventListener('input', (e) => {
                if (!activeObj || isUpdatingForm) return;
                const alpha = (parseInt(e.target.value) || 0) / 100;
                const colorInput = document.getElementById('prop-fill-color');
                const hexColor = colorInput.value || '#000000';
                
                if (alpha === 0) {
                    updateActiveProp('fill', 'transparent');
                    document.getElementById('prop-fill-transparent').checked = true;
                } else {
                    const rgbaColor = hexToRgba(hexColor, alpha);
                    updateActiveProp('fill', rgbaColor);
                    document.getElementById('prop-fill-transparent').checked = false;
                }
            });
        }

        document.getElementById('prop-stroke-color').addEventListener('input', (e) => updateActiveProp('stroke', e.target.value));
        document.getElementById('prop-stroke-width').addEventListener('input', (e) => updateActiveProp('strokeWidth', parseInt(e.target.value) || 0));

        // ponytail: corners rounding for Rect layers
        const rectRxInput = document.getElementById('prop-rect-rx');
        if (rectRxInput) {
            rectRxInput.addEventListener('input', (e) => {
                if (!activeObj || isUpdatingForm) return;
                const val = Math.max(0, parseInt(e.target.value) || 0);
                activeObj.set({
                    rx: val,
                    ry: val
                });
                if (window.editorCanvas) {
                    window.editorCanvas.renderAll();
                }
                if (window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
                    window.editorCore.triggerAutoSave();
                }
            });
        }

        const changeImgBtn = document.getElementById('btn-inspector-change-image');
        if (changeImgBtn) {
            changeImgBtn.addEventListener('click', () => {
                // Switch sidebar tab to Assets
                document.getElementById('tab-assets-btn').click();
            });
        }

        // Image source binding dropdown (mirrors text binding pattern)
        const imgBindSelect = document.getElementById('prop-image-bind');
        if (imgBindSelect) {
            imgBindSelect.addEventListener('change', (e) => {
                updateActiveProp('variable_binding', e.target.value || null);
                // Trigger rendering update in template engine for live preview
                if (window.templateEngine && typeof window.templateEngine.applyBindings === 'function') {
                    window.templateEngine.applyBindings();
                }
            });
        }

        // Shape dataset binding dropdown
        const shapeBindSelect = document.getElementById('prop-shape-bind');
        if (shapeBindSelect) {
            shapeBindSelect.addEventListener('change', (e) => {
                updateActiveProp('variable_binding', e.target.value || null);
                if (window.templateEngine && typeof window.templateEngine.applyBindings === 'function') {
                    window.templateEngine.applyBindings();
                }
            });
        }

        // Image crop controls
        const btnCropImage = document.getElementById('btn-crop-image');
        if (btnCropImage) {
            btnCropImage.addEventListener('click', () => {
                if (activeObj && activeObj.type === 'image') startImageCrop(activeObj);
            });
        }
        const btnCropApply = document.getElementById('btn-crop-apply');
        if (btnCropApply) btnCropApply.addEventListener('click', applyImageCrop);

        const btnCropCancel = document.getElementById('btn-crop-cancel');
        if (btnCropCancel) btnCropCancel.addEventListener('click', cancelImageCrop);

        const btnFitContain = document.getElementById('btn-inspector-fit-contain');
        if (btnFitContain) {
            btnFitContain.addEventListener('click', () => {
                if (window.inspectorCanvas) window.inspectorCanvas.fitObject(activeObj, 'contain');
            });
        }

        const btnFitCover = document.getElementById('btn-inspector-fit-cover');
        if (btnFitCover) {
            btnFitCover.addEventListener('click', () => {
                if (window.inspectorCanvas) window.inspectorCanvas.fitObject(activeObj, 'cover');
            });
        }

    }

    // Apply values to canvas object
    function updateActiveProp(property, value) {
        if (!activeObj || isUpdatingForm) return;

        // ponytail: map textAlign to originX so the text anchor remains consistent for dynamic data bindings
        if (property === 'textAlign' && (activeObj.type === 'i-text' || activeObj.type === 'text' || activeObj.type === 'textbox')) {
            const oldOriginX = activeObj.originX || 'left';
            let newOriginX = value; // 'left', 'center', 'right', 'justify'
            if (newOriginX === 'justify') {
                newOriginX = 'left';
            }
            if (oldOriginX !== newOriginX) {
                let centerLeft;
                const width = activeObj.width * activeObj.scaleX;
                if (oldOriginX === 'left') {
                    centerLeft = activeObj.left + width / 2;
                } else if (oldOriginX === 'right') {
                    centerLeft = activeObj.left - width / 2;
                } else {
                    centerLeft = activeObj.left;
                }

                let newLeft;
                if (newOriginX === 'left') {
                    newLeft = centerLeft - width / 2;
                } else if (newOriginX === 'right') {
                    newLeft = centerLeft + width / 2;
                } else {
                    newLeft = centerLeft;
                }

                activeObj.set({
                    originX: newOriginX,
                    left: newLeft
                });
            }
        }

        activeObj.set(property, value);
        
        // Propagate colors/strokes to group children if editing SVG vector groups
        if (activeObj.type === 'group' && (property === 'fill' || property === 'stroke' || property === 'strokeWidth')) {
            if (activeObj.getObjects) {
                activeObj.getObjects().forEach(child => {
                    child.set(property, value);
                });
            }
        }

        if (activeObj.type === 'i-text' || activeObj.type === 'text' || activeObj.type === 'textbox') {
            if (typeof activeObj.initDimensions === 'function') {
                activeObj.initDimensions();
            }
            activeObj.setCoords();
        }
        window.editorCanvas.renderAll();
        window.editorCore.triggerAutoSave();
    }

    // Handle scaling logic when width/height inputs change
    function updateActiveScaleWidth(width) {
        if (!activeObj || isUpdatingForm) return;

        const rawW = (activeObj.width && activeObj.width > 0) ? activeObj.width : 1;
        if (activeObj.type === 'textbox') {
            activeObj.set({ width: width, scaleX: 1 });
        } else {
            const scaleX = width / rawW;
            activeObj.set('scaleX', scaleX);
        }
        activeObj.setCoords();
        window.editorCanvas.renderAll();
        window.editorCore.triggerAutoSave();

        // Sync corresponding MM input
        const wMm = (width * 25.4) / 300;
        document.getElementById('prop-width-mm').value = Math.round(wMm * 10) / 10;
    }

    function updateActiveScaleHeight(height) {
        if (!activeObj || isUpdatingForm) return;

        const rawH = (activeObj.height && activeObj.height > 0) ? activeObj.height : 1;
        const scaleY = height / rawH;
        activeObj.set('scaleY', scaleY);
        activeObj.setCoords();
        window.editorCanvas.renderAll();
        window.editorCore.triggerAutoSave();

        // Sync corresponding MM input
        const hMm = (height * 25.4) / 300;
        document.getElementById('prop-height-mm').value = Math.round(hMm * 10) / 10;
    }

    function applyTextContentChange(newVal) {
        if (window.inspectorText) {
            window.inspectorText.applyTextContentChange(activeObj, newVal, isUpdatingForm);
        }
    }


    // Set form fields based on active selection
    function inspect(obj) {
        // ponytail: skip temp crop overlay objects — they shouldn't update the inspector
        if (obj && (obj.id === '_crop_box' || obj.id === '_crop_bg')) return;
        activeObj = obj;
        isUpdatingForm = true;

        if (window.inspectorPopulate && typeof window.inspectorPopulate.autoCorrectOriginX === 'function') {
            window.inspectorPopulate.autoCorrectOriginX(obj);
        }

        const noneSelected = document.getElementById('inspector-none-selected');
        const form = document.getElementById('inspector-form');
        
        noneSelected.classList.add('hidden');
        form.classList.remove('hidden');

        // Populate common properties
        document.getElementById('prop-name').value = obj.name || '';
        document.getElementById('prop-left').value = Math.round(obj.left);
        document.getElementById('prop-top').value = Math.round(obj.top);
        const wPx = obj.width * obj.scaleX;
        const hPx = obj.height * obj.scaleY;
        document.getElementById('prop-width').value = Math.round(wPx);
        document.getElementById('prop-height').value = Math.round(hPx);
        
        // Convert to millimeters and round to 1 decimal place
        const wMm = (wPx * 25.4) / 300;
        const hMm = (hPx * 25.4) / 300;
        document.getElementById('prop-width-mm').value = Math.round(wMm * 10) / 10;
        document.getElementById('prop-height-mm').value = Math.round(hMm * 10) / 10;
        document.getElementById('prop-rotation').value = Math.round(obj.angle || 0);
        document.getElementById('prop-opacity').value = Math.round((obj.opacity || 1.0) * 100);

        // Hide specific sections by default
        const textSec = document.getElementById('inspector-text-section');
        const shapeSec = document.getElementById('inspector-shape-section');
        const imgSec = document.getElementById('inspector-image-section');
        
        if (textSec) textSec.classList.add('hidden');
        if (shapeSec) shapeSec.classList.add('hidden');
        if (imgSec) imgSec.classList.add('hidden');

        // Render type-specific sections
        if (window.inspectorPopulate) {
            if (obj.type === 'i-text' || obj.type === 'text' || obj.type === 'textbox') {
                window.inspectorPopulate.populateTextSection(obj);
            } else if (obj.type === 'rect' || obj.type === 'circle' || obj.type === 'line' || obj.type === 'group' || obj.type === 'path') {
                window.inspectorPopulate.populateShapeSection(obj);
            } else if (obj.type === 'image') {
                window.inspectorPopulate.populateImageSection(obj);
            }
        }

        isUpdatingForm = false;
    }

    function hexToRgba(hex, alpha) {
        return window.inspectorCanvas ? window.inspectorCanvas.hexToRgba(hex, alpha) : `rgba(0,0,0,${alpha})`;
    }

    function parseRgba(rgbaStr) {
        return window.inspectorCanvas ? window.inspectorCanvas.parseRgba(rgbaStr) : { hex: '#000000', alpha: 1.0 };
    }

    // Clear Inspector state
    function clearInspect() {
        if (window.inspectorCrop && window.inspectorCrop.isCropMode()) return;
        activeObj = null;
        document.getElementById('inspector-none-selected').classList.remove('hidden');
        document.getElementById('inspector-form').classList.add('hidden');
        
        if (window.propertyInspector && typeof window.propertyInspector.syncCanvasBgInputs === 'function') {
            window.propertyInspector.syncCanvasBgInputs();
        }
    }

    // Listen to canvas transform updates to live-sync form fields
    document.addEventListener('DOMContentLoaded', () => {
        initInspector();
        
        const checkCanvasInterval = setInterval(() => {
            const canvas = window.editorCanvas;
            if (canvas) {
                clearInterval(checkCanvasInterval);
                canvas.on('object:moving', () => { if (activeObj) inspect(activeObj); });
                canvas.on('object:scaling', () => { if (activeObj) inspect(activeObj); });
                canvas.on('mouse:dblclick', (options) => {
                    if (options.target && options.target.type === 'image' && window.inspectorCrop && !window.inspectorCrop.isCropMode()) {
                        window.inspectorCrop.startImageCrop(options.target);
                    }
                });
                
                if (window.inspectorCanvas && typeof window.inspectorCanvas.initCanvasInspector === 'function') {
                    window.inspectorCanvas.initCanvasInspector();
                }
            }
        }, 100);
    });

    function startImageCrop(img) {
        if (window.inspectorCrop) window.inspectorCrop.startImageCrop(img);
    }
    function applyImageCrop() {
        if (window.inspectorCrop) window.inspectorCrop.applyImageCrop();
    }
    function cancelImageCrop() {
        if (window.inspectorCrop) window.inspectorCrop.cancelImageCrop();
    }


    function updateDatasetColumns(columnMap) {
        if (window.inspectorPopulate) {
            window.inspectorPopulate.updateDatasetColumns(columnMap);
        }
    }


    window.propertyInspector = {
        inspect: inspect,
        clearInspect: clearInspect,
        updateDatasetColumns: updateDatasetColumns,
        syncCanvasBgInputs: null // populated by initCanvasInspector
    };
})();

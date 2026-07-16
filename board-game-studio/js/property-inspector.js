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
            activeObj.set({ originX: 'center', left: window.editorCanvas.width / 2 });
            document.getElementById('prop-left').value = Math.round(window.editorCanvas.width / 2);
            window.editorCanvas.renderAll();
            window.editorCore.triggerAutoSave();
        });
        document.getElementById('btn-align-v').addEventListener('click', () => {
            if (!activeObj || !window.editorCanvas) return;
            activeObj.set({ originY: 'center', top: window.editorCanvas.height / 2 });
            document.getElementById('prop-top').value = Math.round(window.editorCanvas.height / 2);
            window.editorCanvas.renderAll();
            window.editorCore.triggerAutoSave();
        });

        // Text properties
        document.getElementById('prop-text-val').addEventListener('input', (e) => updateActiveProp('text', e.target.value));
        
        const bindSelect = document.getElementById('prop-text-bind');
        if (bindSelect) {
            bindSelect.addEventListener('change', (e) => {
                updateActiveProp('variable_binding', e.target.value || null);
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

        // Image controls
        const changeImgBtn = document.getElementById('btn-inspector-change-image');
        if (changeImgBtn) {
            changeImgBtn.addEventListener('click', () => {
                // Switch sidebar tab to Assets
                document.getElementById('tab-assets-btn').click();
            });
        }

        const btnFitContain = document.getElementById('btn-inspector-fit-contain');
        if (btnFitContain) {
            btnFitContain.addEventListener('click', () => {
                if (!activeObj || activeObj.type !== 'image' || !window.editorCanvas) return;
                
                activeObj.set({
                    angle: 0,
                    originX: 'center',
                    originY: 'center',
                    left: window.editorCanvas.width / 2,
                    top: window.editorCanvas.height / 2
                });
                
                const scale = Math.min(window.editorCanvas.width / activeObj.width, window.editorCanvas.height / activeObj.height);
                activeObj.set({
                    scaleX: scale,
                    scaleY: scale
                });
                
                activeObj.setCoords();
                window.editorCanvas.renderAll();
                window.editorCore.triggerAutoSave();
                inspect(activeObj);
            });
        }

        const btnFitCover = document.getElementById('btn-inspector-fit-cover');
        if (btnFitCover) {
            btnFitCover.addEventListener('click', () => {
                if (!activeObj || activeObj.type !== 'image' || !window.editorCanvas) return;
                
                activeObj.set({
                    angle: 0,
                    originX: 'center',
                    originY: 'center',
                    left: window.editorCanvas.width / 2,
                    top: window.editorCanvas.height / 2
                });
                
                const scale = Math.max(window.editorCanvas.width / activeObj.width, window.editorCanvas.height / activeObj.height);
                activeObj.set({
                    scaleX: scale,
                    scaleY: scale
                });
                
                activeObj.setCoords();
                window.editorCanvas.renderAll();
                window.editorCore.triggerAutoSave();
                inspect(activeObj);
            });
        }
    }

    // Apply values to canvas object
    function updateActiveProp(property, value) {
        if (!activeObj || isUpdatingForm) return;

        activeObj.set(property, value);
        
        // Propagate colors/strokes to group children if editing SVG vector groups
        if (activeObj.type === 'group' && (property === 'fill' || property === 'stroke' || property === 'strokeWidth')) {
            if (activeObj.getObjects) {
                activeObj.getObjects().forEach(child => {
                    child.set(property, value);
                });
            }
        }

        if (activeObj.type === 'i-text' || activeObj.type === 'text') {
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

        const scaleX = width / activeObj.width;
        activeObj.set('scaleX', scaleX);
        window.editorCanvas.renderAll();
        window.editorCore.triggerAutoSave();

        // Sync corresponding MM input
        const wMm = (width * 25.4) / 300;
        document.getElementById('prop-width-mm').value = Math.round(wMm * 10) / 10;
    }

    function updateActiveScaleHeight(height) {
        if (!activeObj || isUpdatingForm) return;

        const scaleY = height / activeObj.height;
        activeObj.set('scaleY', scaleY);
        window.editorCanvas.renderAll();
        window.editorCore.triggerAutoSave();

        // Sync corresponding MM input
        const hMm = (height * 25.4) / 300;
        document.getElementById('prop-height-mm').value = Math.round(hMm * 10) / 10;
    }

    // Set form fields based on active selection
    function inspect(obj) {
        activeObj = obj;
        isUpdatingForm = true;

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
        
        textSec.classList.add('hidden');
        shapeSec.classList.add('hidden');
        imgSec.classList.add('hidden');

        // Render type-specific sections
        if (obj.type === 'i-text' || obj.type === 'text') {
            textSec.classList.remove('hidden');
            document.getElementById('prop-text-val').value = obj.text || '';
            
            const bindSelect = document.getElementById('prop-text-bind');
            if (bindSelect) {
                bindSelect.value = obj.variable_binding || '';
            }
            
            document.getElementById('prop-font-size').value = obj.fontSize || 12;
            document.getElementById('prop-font-family').value = obj.fontFamily || 'Plus Jakarta Sans';
            
            // Standardize hex color mapping for color picker
            const color = obj.fill || '#000000';
            if (color.startsWith('#')) {
                document.getElementById('prop-text-color').value = color;
            }
            
            document.getElementById('prop-text-align').value = obj.textAlign || 'left';
            document.getElementById('prop-font-bold').checked = obj.fontWeight === 'bold';
            document.getElementById('prop-font-italic').checked = obj.fontStyle === 'italic';

        } else if (obj.type === 'rect' || obj.type === 'circle' || obj.type === 'line' || obj.type === 'group' || obj.type === 'path') {
            shapeSec.classList.remove('hidden');
            
            // Toggle fill options visibility if it is a Line layer
            const fillGroup = document.getElementById('prop-shape-fill-group');
            const opacityGroup = document.getElementById('prop-shape-opacity-group');
            if (obj.type === 'line') {
                if (fillGroup) fillGroup.classList.add('hidden');
                if (opacityGroup) opacityGroup.classList.add('hidden');
            } else {
                if (fillGroup) fillGroup.classList.remove('hidden');
                if (opacityGroup) opacityGroup.classList.remove('hidden');
            }
            
            let fillVal = obj.fill || '';
            let strokeVal = obj.stroke || '';
            let strokeWidthVal = obj.strokeWidth || 0;

            if (obj.type === 'group' && obj.getObjects && obj.getObjects().length > 0) {
                const firstChild = obj.getObjects()[0];
                fillVal = fillVal || firstChild.fill || '';
                strokeVal = strokeVal || firstChild.stroke || '';
                strokeWidthVal = strokeWidthVal !== undefined ? strokeWidthVal : firstChild.strokeWidth;
            }

            const isTransparent = fillVal === 'transparent' || fillVal === '' || fillVal === 'none';
            document.getElementById('prop-fill-transparent').checked = isTransparent;
            
            if (isTransparent) {
                document.getElementById('prop-fill-opacity').value = 0;
            } else if (fillVal.startsWith('rgba')) {
                const parsed = parseRgba(fillVal);
                document.getElementById('prop-fill-color').value = parsed.hex;
                document.getElementById('prop-fill-opacity').value = Math.round(parsed.alpha * 100);
            } else if (fillVal.startsWith('#')) {
                document.getElementById('prop-fill-color').value = fillVal;
                document.getElementById('prop-fill-opacity').value = 100;
            } else {
                document.getElementById('prop-fill-opacity').value = 100;
            }

            if (strokeVal && strokeVal.startsWith('#')) {
                document.getElementById('prop-stroke-color').value = strokeVal;
            }
            document.getElementById('prop-stroke-width').value = strokeWidthVal || 0;

        } else if (obj.type === 'image') {
            imgSec.classList.remove('hidden');
            document.getElementById('prop-image-filename').textContent = obj.original_filename || 'Uploaded Image';
        }

        isUpdatingForm = false;
    }

    // Helper to convert hex to rgba
    function hexToRgba(hex, alpha) {
        hex = hex.replace('#', '');
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    // Helper to parse rgb or rgba string
    function parseRgba(rgbaStr) {
        const match = rgbaStr.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
        if (!match) return { hex: '#000000', alpha: 1.0 };
        
        const r = parseInt(match[1]);
        const g = parseInt(match[2]);
        const b = parseInt(match[3]);
        const alpha = match[4] !== undefined ? parseFloat(match[4]) : 1.0;
        
        const toHex = (c) => {
            const hex = c.toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        };
        
        return {
            hex: `#${toHex(r)}${toHex(g)}${toHex(b)}`,
            alpha: alpha
        };
    }

    // Clear Inspector state
    function clearInspect() {
        activeObj = null;
        document.getElementById('inspector-none-selected').classList.remove('hidden');
        document.getElementById('inspector-form').classList.add('hidden');
        
        if (window.propertyInspector && typeof window.propertyInspector.syncCanvasBgInputs === 'function') {
            window.propertyInspector.syncCanvasBgInputs();
        }
    }

    // Canvas properties inspector and binder
    function initCanvasInspector() {
        const bgPicker = document.getElementById('prop-canvas-bg');
        const bgHex = document.getElementById('prop-canvas-bg-hex');
        const transparentCheck = document.getElementById('prop-canvas-transparent');
        
        if (!bgPicker || !bgHex || !transparentCheck) return;

        function updateCanvasBg(color, isTransparent) {
            const canvas = window.editorCanvas;
            if (!canvas) return;

            if (isTransparent) {
                canvas.backgroundColor = 'transparent';
            } else {
                canvas.backgroundColor = color;
            }
            
            canvas.renderAll();
            if (window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
                window.editorCore.triggerAutoSave();
            }
            
            syncInputs();
        }

        function syncInputs() {
            const canvas = window.editorCanvas;
            if (!canvas) return;

            const currentBg = canvas.backgroundColor;
            if (!currentBg || currentBg === 'transparent') {
                transparentCheck.checked = true;
                bgHex.value = '#FFFFFF';
                bgPicker.value = '#ffffff';
            } else {
                transparentCheck.checked = false;
                let hexColor = '#ffffff';
                if (typeof currentBg === 'string') {
                    if (currentBg.startsWith('#')) {
                        hexColor = currentBg;
                    } else if (currentBg.startsWith('rgb')) {
                        const parsed = parseRgba(currentBg);
                        hexColor = parsed.hex;
                    }
                }
                bgHex.value = hexColor;
                bgPicker.value = hexColor;
            }
        }

        bgPicker.addEventListener('input', (e) => {
            updateCanvasBg(e.target.value, false);
        });

        bgHex.addEventListener('input', (e) => {
            const val = e.target.value;
            if (val.match(/^#[0-9A-F]{6}$/i)) {
                updateCanvasBg(val, false);
            }
        });

        transparentCheck.addEventListener('change', (e) => {
            updateCanvasBg(bgHex.value, e.target.checked);
        });

        window.propertyInspector.syncCanvasBgInputs = syncInputs;

        // Automatically sync inputs when selection is cleared
        canvas.on('selection:cleared', syncInputs);
        syncInputs();
    }

    // Listen to canvas transform updates to live-sync form fields (like dragging)
    document.addEventListener('DOMContentLoaded', () => {
        initInspector();
        
        const checkCanvasInterval = setInterval(() => {
            const canvas = window.editorCanvas;
            if (canvas) {
                clearInterval(checkCanvasInterval);
                canvas.on('object:moving', () => { if (activeObj) inspect(activeObj); });
                canvas.on('object:scaling', () => { if (activeObj) inspect(activeObj); });
                canvas.on('object:rotating', () => { if (activeObj) inspect(activeObj); });
                
                // Initialize canvas properties inspector once canvas is ready
                initCanvasInspector();
            }
        }, 100);
    });

    window.propertyInspector = {
        inspect: inspect,
        clearInspect: clearInspect,
        syncCanvasBgInputs: null // populated by initCanvasInspector
    };
})();

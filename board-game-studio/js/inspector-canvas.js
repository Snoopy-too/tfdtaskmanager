/**
 * Inspector Canvas Background & Color Helpers
 */
(function() {
    'use strict';

    function hexToRgba(hex, alpha) {
        hex = hex.replace('#', '');
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

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

    function initCanvasInspector() {
        const bgPicker = document.getElementById('prop-canvas-bg');
        const bgHex = document.getElementById('prop-canvas-bg-hex');
        const transparentCheck = document.getElementById('prop-canvas-transparent');
        
        if (!bgPicker || !bgHex || !transparentCheck) return;

        const canvas = window.editorCanvas;
        if (!canvas) return;

        function updateCanvasBg(color, isTransparent) {
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

        if (window.propertyInspector) {
            window.propertyInspector.syncCanvasBgInputs = syncInputs;
        }

        canvas.on('selection:cleared', syncInputs);
        syncInputs();
    }

    function fitObject(activeObj, mode) {
        if (!activeObj || !window.editorCanvas) return;
        const canvas = window.editorCanvas;

        const rawEl = (typeof activeObj.getElement === 'function') ? activeObj.getElement() : null;
        const rawW = (activeObj.width && activeObj.width > 0)
            ? activeObj.width
            : ((rawEl && rawEl.naturalWidth > 0) ? rawEl.naturalWidth : (activeObj.getScaledWidth ? (activeObj.getScaledWidth() / (activeObj.scaleX || 1)) : 300));
        const rawH = (activeObj.height && activeObj.height > 0)
            ? activeObj.height
            : ((rawEl && rawEl.naturalHeight > 0) ? rawEl.naturalHeight : (activeObj.getScaledHeight ? (activeObj.getScaledHeight() / (activeObj.scaleY || 1)) : 300));

        if (!activeObj.width || activeObj.width <= 0) activeObj.set('width', rawW);
        if (!activeObj.height || activeObj.height <= 0) activeObj.set('height', rawH);

        let scale = (mode === 'cover')
            ? Math.max(canvas.width / rawW, canvas.height / rawH)
            : Math.min(canvas.width / rawW, canvas.height / rawH);

        if (!isFinite(scale) || scale <= 0) scale = 1.0;

        activeObj.set({
            angle: 0,
            originX: 'center',
            originY: 'center',
            left: canvas.width / 2,
            top: canvas.height / 2,
            scaleX: scale,
            scaleY: scale
        });

        activeObj.setCoords();
        activeObj.bringToFront();

        if (window.guideRenderer && typeof window.guideRenderer.renderGuides === 'function') {
            window.guideRenderer.renderGuides();
        }

        canvas.renderAll();
        if (window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
            window.editorCore.triggerAutoSave();
        }
        if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
            window.layerManager.renderLayersList();
        }
        if (window.propertyInspector && typeof window.propertyInspector.inspect === 'function') {
            window.propertyInspector.inspect(activeObj);
        }
    }

    window.inspectorCanvas = {
        hexToRgba,
        parseRgba,
        initCanvasInspector,
        fitObject
    };
})();

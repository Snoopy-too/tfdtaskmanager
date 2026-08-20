/**
 * Editor Viewport Module
 * Handles zoom level scaling, container sizing, orientation switching, and dynamic control handle sizing.
 */
(function() {
    'use strict';

    let zoomLevel = 1.0;

    function syncControlAppearance() {
        if (!fabric || !fabric.Object) return;

        const effectiveZoom = zoomLevel || 1.0;
        const cSize = Math.max(12, Math.round(15 / effectiveZoom));
        const touchSize = Math.max(20, Math.round(26 / effectiveZoom));
        const bScale = Math.max(1, Math.round(2 / effectiveZoom));
        const pad = Math.max(2, Math.round(5 / effectiveZoom));

        fabric.Object.prototype.cornerSize = cSize;
        fabric.Object.prototype.touchCornerSize = touchSize;
        fabric.Object.prototype.borderScaleFactor = bScale;
        fabric.Object.prototype.padding = pad;
        fabric.Object.prototype.transparentCorners = false;
        fabric.Object.prototype.cornerColor = '#ffffff';
        fabric.Object.prototype.cornerStrokeColor = '#4f46e5';
        fabric.Object.prototype.borderColor = '#6366f1';
        fabric.Object.prototype.cornerStyle = 'rect';

        if (fabric.Object.prototype.setControlVisible) {
            fabric.Object.prototype.setControlVisible('tl', true);
            fabric.Object.prototype.setControlVisible('tr', true);
            fabric.Object.prototype.setControlVisible('bl', true);
            fabric.Object.prototype.setControlVisible('br', true);
            fabric.Object.prototype.setControlVisible('ml', true);
            fabric.Object.prototype.setControlVisible('mr', true);
            fabric.Object.prototype.setControlVisible('mt', true);
            fabric.Object.prototype.setControlVisible('mb', true);
            fabric.Object.prototype.setControlVisible('mtr', true);
        }

        const canvas = window.editorCanvas;
        if (canvas) {
            canvas.getObjects().forEach(obj => {
                if (obj.id === 'safe-zone-guide' || obj.id === 'bleed-zone-guide') return;
                obj.cornerSize = cSize;
                obj.touchCornerSize = touchSize;
                obj.borderScaleFactor = bScale;
                obj.padding = pad;
                obj.transparentCorners = false;
                obj.cornerColor = '#ffffff';
                obj.cornerStrokeColor = '#4f46e5';
                obj.borderColor = '#6366f1';
                obj.cornerStyle = 'rect';
                obj.setCoords();
            });
            canvas.requestRenderAll();
        }
    }

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
            
            if (wrapper) {
                wrapper.style.transform = `scale(${zoomLevel})`;
                wrapper.style.transformOrigin = '0 0';
            }
            if (zoomInput) {
                zoomInput.value = Math.round(zoomLevel * 100) + '%';
            }

            syncControlAppearance();
        }

        const btnIn = document.getElementById('btn-zoom-in');
        if (btnIn) {
            btnIn.addEventListener('click', () => {
                zoomLevel = Math.min(zoomLevel + 0.1, 3.0);
                applyZoom();
            });
        }

        const btnOut = document.getElementById('btn-zoom-out');
        if (btnOut) {
            btnOut.addEventListener('click', () => {
                zoomLevel = Math.max(zoomLevel - 0.1, 0.2);
                applyZoom();
            });
        }

        const btnFit = document.getElementById('btn-zoom-fit');
        if (btnFit) {
            btnFit.addEventListener('click', fitToView);
        }

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
            if (!viewport) return;
            const containerWidth = viewport.clientWidth - 64;
            const containerHeight = viewport.clientHeight - 64;
            const widthScale = containerWidth / window.studioConfig.canvasWidth;
            const heightScale = containerHeight / window.studioConfig.canvasHeight;
            
            zoomLevel = Math.min(widthScale, heightScale, 1.0);
            applyZoom();
        }

        setTimeout(fitToView, 200);
    }

    function toggleCanvasOrientation() {
        if (window.studioConfig.isViewMode) return;

        const canvas = window.editorCanvas;
        if (!canvas) return;

        const curW = window.studioConfig.canvasWidth;
        const curH = window.studioConfig.canvasHeight;
        const newW = curH;
        const newH = curW;
        const newOrientation = (newW > newH) ? 'landscape' : 'portrait';

        const formData = new FormData();
        formData.append('csrf_token', window.studioConfig.csrfToken);
        formData.append('template_id', window.studioConfig.templateId);
        formData.append('target_orientation', newOrientation);

        if (window.editorCore && typeof window.editorCore.setSaveStatus === 'function') {
            window.editorCore.setSaveStatus('Changing orientation...', 'pulse');
        }

        fetch('api.php?action=toggle_orientation', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': window.studioConfig.csrfToken
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                if (window.editorCore) window.editorCore.setSaveStatus('Orientation change failed', 'error');
                return;
            }

            window.studioConfig.canvasWidth = data.canvasWidth;
            window.studioConfig.canvasHeight = data.canvasHeight;
            window.studioConfig.orientation = data.orientation;

            canvas.setWidth(data.canvasWidth);
            canvas.setHeight(data.canvasHeight);

            const wrapper = document.getElementById('canvas-container-wrapper');
            if (wrapper) {
                wrapper.style.width = data.canvasWidth + 'px';
                wrapper.style.height = data.canvasHeight + 'px';
            }

            if (window.guideRenderer && typeof window.guideRenderer.renderGuides === 'function') {
                window.guideRenderer.renderGuides();
            }

            const badge = document.getElementById('template-orientation-badge');
            if (badge) {
                if (data.orientation === 'landscape') {
                    badge.textContent = 'Landscape';
                    badge.className = 'text-xs font-semibold px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20';
                } else {
                    badge.textContent = 'Portrait';
                    badge.className = 'text-xs font-semibold px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700';
                }
            }

            const btnText = document.getElementById('orient-btn-text');
            if (btnText) {
                btnText.textContent = data.orientation === 'landscape' ? 'Switch to Portrait' : 'Switch to Landscape';
            }

            const sizeDisplay = document.getElementById('template-size-display');
            if (sizeDisplay) {
                const wMm = Math.round((data.canvasWidth / 300 * 25.4) * 10) / 10;
                const hMm = Math.round((data.canvasHeight / 300 * 25.4) * 10) / 10;
                sizeDisplay.textContent = `${wMm}x${hMm} mm (${data.canvasWidth}x${data.canvasHeight} px)`;
            }

            const viewport = document.getElementById('canvas-viewport');
            if (viewport && wrapper) {
                const containerWidth = viewport.clientWidth - 64;
                const containerHeight = viewport.clientHeight - 64;
                const widthScale = containerWidth / data.canvasWidth;
                const heightScale = containerHeight / data.canvasHeight;
                zoomLevel = Math.min(widthScale, heightScale, 1.0);
                const zoomValElem = document.getElementById('zoom-value');
                if (zoomValElem) zoomValElem.value = Math.round(zoomLevel * 100) + '%';
                wrapper.style.transform = `scale(${zoomLevel})`;
            }

            canvas.renderAll();
            if (window.editorCore) {
                window.editorCore.pushState();
                window.editorCore.triggerAutoSave();
                window.editorCore.setSaveStatus(`Switched to ${data.orientation.charAt(0).toUpperCase() + data.orientation.slice(1)}`, 'saved');
            }
        })
        .catch(err => {
            console.error('Error switching orientation:', err);
            alert('Failed to update orientation. Please try again.');
            if (window.editorCore) window.editorCore.setSaveStatus('Orientation change failed', 'error');
        });
    }

    window.editorViewport = {
        syncControlAppearance,
        setupZoomControls,
        toggleCanvasOrientation,
        getZoomLevel: () => zoomLevel
    };
})();

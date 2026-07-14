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
        document.getElementById('prop-font-family').addEventListener('change', (e) => updateActiveProp('fontFamily', e.target.value));
        document.getElementById('prop-text-color').addEventListener('input', (e) => updateActiveProp('fill', e.target.value));
        document.getElementById('prop-text-align').addEventListener('change', (e) => updateActiveProp('textAlign', e.target.value));
        
        document.getElementById('prop-font-bold').addEventListener('change', (e) => updateActiveProp('fontWeight', e.target.checked ? 'bold' : 'normal'));
        document.getElementById('prop-font-italic').addEventListener('change', (e) => updateActiveProp('fontStyle', e.target.checked ? 'italic' : 'normal'));

        // Shape properties
        document.getElementById('prop-fill-color').addEventListener('input', (e) => updateActiveProp('fill', e.target.value));
        
        const fillTrans = document.getElementById('prop-fill-transparent');
        if (fillTrans) {
            fillTrans.addEventListener('change', (e) => {
                if (e.target.checked) {
                    updateActiveProp('fill', 'transparent');
                } else {
                    const colorInput = document.getElementById('prop-fill-color');
                    updateActiveProp('fill', colorInput.value || '#000000');
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
    }

    // Apply values to canvas object
    function updateActiveProp(property, value) {
        if (!activeObj || isUpdatingForm) return;

        activeObj.set(property, value);
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
    }

    function updateActiveScaleHeight(height) {
        if (!activeObj || isUpdatingForm) return;

        const scaleY = height / activeObj.height;
        activeObj.set('scaleY', scaleY);
        window.editorCanvas.renderAll();
        window.editorCore.triggerAutoSave();
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
        document.getElementById('prop-width').value = Math.round(obj.width * obj.scaleX);
        document.getElementById('prop-height').value = Math.round(obj.height * obj.scaleY);
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

        } else if (obj.type === 'rect' || obj.type === 'circle') {
            shapeSec.classList.remove('hidden');
            
            const isTransparent = obj.fill === 'transparent' || obj.fill === '';
            document.getElementById('prop-fill-transparent').checked = isTransparent;
            if (!isTransparent && obj.fill && obj.fill.startsWith('#')) {
                document.getElementById('prop-fill-color').value = obj.fill;
            }

            if (obj.stroke && obj.stroke.startsWith('#')) {
                document.getElementById('prop-stroke-color').value = obj.stroke;
            }
            document.getElementById('prop-stroke-width').value = obj.strokeWidth || 0;

        } else if (obj.type === 'image') {
            imgSec.classList.remove('hidden');
            document.getElementById('prop-image-filename').textContent = obj.original_filename || 'Uploaded Image';
        }

        isUpdatingForm = false;
    }

    // Clear Inspector state
    function clearInspect() {
        activeObj = null;
        document.getElementById('inspector-none-selected').classList.remove('hidden');
        document.getElementById('inspector-form').classList.add('hidden');
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
            }
        }, 100);
    });

    window.propertyInspector = {
        inspect: inspect,
        clearInspect: clearInspect
    };
})();

/**
 * Inspector Form Population Module
 * Populates layer-specific sidebar controls (Text, Shapes, Images) when a Fabric object is selected.
 */
(function() {
    'use strict';

    function autoCorrectOriginX(obj) {
        if (!obj || (obj.type !== 'i-text' && obj.type !== 'text' && obj.type !== 'textbox')) return;
        const alignVal = obj.textAlign || 'left';
        const expectedOriginX = alignVal === 'justify' ? 'left' : alignVal;
        if (obj.originX !== expectedOriginX) {
            let centerLeft;
            const width = obj.width * obj.scaleX;
            const oldOriginX = obj.originX || 'left';
            if (oldOriginX === 'left') {
                centerLeft = obj.left + width / 2;
            } else if (oldOriginX === 'right') {
                centerLeft = obj.left - width / 2;
            } else {
                centerLeft = obj.left;
            }

            let newLeft;
            if (expectedOriginX === 'left') {
                newLeft = centerLeft - width / 2;
            } else if (expectedOriginX === 'right') {
                newLeft = centerLeft + width / 2;
            } else {
                newLeft = centerLeft;
            }

            obj.set({
                originX: expectedOriginX,
                left: newLeft
            });
            obj.setCoords();
            if (window.editorCanvas) window.editorCanvas.renderAll();
            if (window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
                window.editorCore.triggerAutoSave();
            }
        }
    }

    function populateTextSection(obj) {
        const textSec = document.getElementById('inspector-text-section');
        if (!textSec) return;
        textSec.classList.remove('hidden');

        let currentText = obj.text || '';
        const bindBadge = document.getElementById('text-bind-badge');

        if (obj.variable_binding && window.templateEngine && typeof window.templateEngine.getCurrentRowData === 'function') {
            const row = window.templateEngine.getCurrentRowData();
            const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '').trim();
            if (row && row[colName] !== undefined) {
                currentText = row[colName];
                if (bindBadge) {
                    const rowIdx = window.templateEngine.getCurrentRowIndex ? window.templateEngine.getCurrentRowIndex() + 1 : '';
                    bindBadge.textContent = `Row ${rowIdx} • {{${colName}}}`;
                    bindBadge.classList.remove('hidden');
                }
            } else {
                if (bindBadge) bindBadge.classList.add('hidden');
            }
        } else {
            if (window.templateEngine && typeof window.templateEngine.getTextTemplate === 'function') {
                const tmpl = window.templateEngine.getTextTemplate(obj);
                if (tmpl !== undefined && tmpl !== null) currentText = tmpl;
            }
            if (bindBadge) bindBadge.classList.add('hidden');
        }

        document.getElementById('prop-text-val').value = currentText;
        
        const bindSelect = document.getElementById('prop-text-bind');
        if (bindSelect) bindSelect.value = obj.variable_binding || '';
        
        document.getElementById('prop-font-size').value = obj.fontSize || 12;
        document.getElementById('prop-font-family').value = obj.fontFamily || 'Plus Jakarta Sans';
        
        const color = obj.fill || '#000000';
        if (typeof color === 'string' && color.startsWith('#')) {
            document.getElementById('prop-text-color').value = color;
        }
        
        document.getElementById('prop-text-align').value = obj.textAlign || 'left';
        document.getElementById('prop-font-bold').checked = obj.fontWeight === 'bold';
        document.getElementById('prop-font-italic').checked = obj.fontStyle === 'italic';
    }

    function populateShapeSection(obj) {
        const shapeSec = document.getElementById('inspector-shape-section');
        if (!shapeSec) return;
        shapeSec.classList.remove('hidden');
        
        const fillGroup = document.getElementById('prop-shape-fill-group');
        const opacityGroup = document.getElementById('prop-shape-opacity-group');
        if (obj.type === 'line') {
            if (fillGroup) fillGroup.classList.add('hidden');
            if (opacityGroup) opacityGroup.classList.add('hidden');
        } else {
            if (fillGroup) fillGroup.classList.remove('hidden');
            if (opacityGroup) opacityGroup.classList.remove('hidden');
        }

        const cornersGroup = document.getElementById('prop-rect-corners-group');
        if (cornersGroup) {
            if (obj.type === 'rect') {
                cornersGroup.classList.remove('hidden');
                document.getElementById('prop-rect-rx').value = obj.rx || 0;
            } else {
                cornersGroup.classList.add('hidden');
            }
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
        } else if (typeof fillVal === 'string' && fillVal.startsWith('rgba') && window.inspectorCanvas) {
            const parsed = window.inspectorCanvas.parseRgba(fillVal);
            document.getElementById('prop-fill-color').value = parsed.hex;
            document.getElementById('prop-fill-opacity').value = Math.round(parsed.alpha * 100);
        } else if (typeof fillVal === 'string' && fillVal.startsWith('#')) {
            document.getElementById('prop-fill-color').value = fillVal;
            document.getElementById('prop-fill-opacity').value = 100;
        } else {
            document.getElementById('prop-fill-opacity').value = 100;
        }

        if (strokeVal && typeof strokeVal === 'string' && strokeVal.startsWith('#')) {
            document.getElementById('prop-stroke-color').value = strokeVal;
        }
        document.getElementById('prop-stroke-width').value = strokeWidthVal || 0;

        const shapeBindSelect = document.getElementById('prop-shape-bind');
        if (shapeBindSelect) shapeBindSelect.value = obj.variable_binding || '';
    }

    function populateImageSection(obj) {
        const imgSec = document.getElementById('inspector-image-section');
        if (!imgSec) return;
        imgSec.classList.remove('hidden');
        document.getElementById('prop-image-filename').textContent = obj.original_filename || 'Uploaded Image';

        const imgBindSelect = document.getElementById('prop-image-bind');
        if (imgBindSelect) imgBindSelect.value = obj.variable_binding || '';
    }

    function updateDatasetColumns(columnMap) {
        const textSelect = document.getElementById('prop-text-bind');
        const imageSelect = document.getElementById('prop-image-bind');
        const shapeSelect = document.getElementById('prop-shape-bind');
        const bindHint = document.getElementById('prop-text-bind-hint');

        const hasColumns = Array.isArray(columnMap) && columnMap.length > 0;
        if (bindHint) {
            if (hasColumns) {
                bindHint.classList.add('hidden');
            } else {
                bindHint.classList.remove('hidden');
            }
        }

        [textSelect, imageSelect, shapeSelect].forEach(select => {
            if (!select) return;
            const currentVal = select.value;
            select.innerHTML = '';

            const defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            if (select.id === 'prop-text-bind') {
                defaultOpt.textContent = 'No Binding (Static Text)';
            } else if (select.id === 'prop-image-bind') {
                defaultOpt.textContent = 'No Binding (Static Image)';
            } else {
                defaultOpt.textContent = 'No Binding (Always Visible)';
            }
            select.appendChild(defaultOpt);

            if (hasColumns) {
                columnMap.forEach(col => {
                    const opt = document.createElement('option');
                    opt.value = `{{${col}}}`;
                    opt.textContent = `{{${col}}}`;
                    select.appendChild(opt);
                });
            }

            select.value = currentVal;
        });
    }

    window.inspectorPopulate = {
        autoCorrectOriginX,
        populateTextSection,
        populateShapeSection,
        populateImageSection,
        updateDatasetColumns
    };
})();

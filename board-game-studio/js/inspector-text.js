/**
 * Text Toolbar & Tag Manipulation for Property Inspector
 */
(function() {
    'use strict';

    function applyTextContentChange(activeObj, newVal, isUpdatingForm) {
        if (!activeObj || isUpdatingForm) return;

        if (activeObj.variable_binding && window.templateEngine && typeof window.templateEngine.getCurrentRowData === 'function') {
            const row = window.templateEngine.getCurrentRowData();
            const colName = activeObj.variable_binding.replace(/\{\{|\}\}/g, '').trim();
            if (row && colName) {
                window.templateEngine.updateDatasetCell(colName, newVal);
                return;
            }
        }

        if (window.templateEngine && typeof window.templateEngine.updateTextTemplate === 'function') {
            window.templateEngine.updateTextTemplate(activeObj, newVal);
        } else {
            activeObj.set('text', newVal);
            activeObj.setCoords();
            if (window.editorCanvas) window.editorCanvas.renderAll();
            if (window.editorCore) window.editorCore.triggerAutoSave();
        }
    }

    function wrapTextSelectionWithTag(activeObj, openTag, closeTag, isUpdatingForm) {
        const textarea = document.getElementById('prop-text-val');
        if (!textarea || !activeObj) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const val = textarea.value || '';

        let newVal;
        let newCursorPos;

        if (start !== undefined && end !== undefined && start !== end) {
            const selected = val.substring(start, end);
            newVal = val.substring(0, start) + openTag + selected + closeTag + val.substring(end);
            newCursorPos = start + openTag.length + selected.length + closeTag.length;
        } else {
            const pos = start !== undefined ? start : val.length;
            newVal = val.substring(0, pos) + openTag + closeTag + val.substring(pos);
            newCursorPos = pos + openTag.length;
        }

        textarea.value = newVal;
        textarea.focus();
        if (start !== end) {
            textarea.setSelectionRange(start, newCursorPos);
        } else {
            textarea.setSelectionRange(newCursorPos, newCursorPos);
        }

        applyTextContentChange(activeObj, newVal, isUpdatingForm);
    }

    function clearTextTags(activeObj, isUpdatingForm) {
        const textarea = document.getElementById('prop-text-val');
        if (!textarea || !activeObj) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const val = textarea.value || '';

        let newVal;
        if (start !== undefined && end !== undefined && start !== end) {
            const selected = val.substring(start, end);
            const cleaned = selected.replace(/<\/?([a-zA-Z0-9_\-#:=]+)>/g, '');
            newVal = val.substring(0, start) + cleaned + val.substring(end);
            textarea.value = newVal;
            textarea.setSelectionRange(start, start + cleaned.length);
        } else {
            newVal = val.replace(/<\/?([a-zA-Z0-9_\-#:=]+)>/g, '');
            textarea.value = newVal;
        }

        textarea.focus();
        applyTextContentChange(activeObj, newVal, isUpdatingForm);
    }

    function bindTextToolbar(getActiveObj, isUpdatingFormFn) {
        document.querySelectorAll('.btn-text-color-tag').forEach(btn => {
            btn.addEventListener('mousedown', (e) => e.preventDefault());
            btn.addEventListener('click', () => {
                const tag = btn.dataset.tag;
                if (tag) wrapTextSelectionWithTag(getActiveObj(), `<${tag}>`, `</${tag}>`, isUpdatingFormFn());
            });
        });

        const customColorPicker = document.getElementById('picker-text-custom-color');
        if (customColorPicker) {
            customColorPicker.addEventListener('change', (e) => {
                const hex = e.target.value;
                if (hex) wrapTextSelectionWithTag(getActiveObj(), `<color:${hex}>`, '</color>', isUpdatingFormFn());
            });
        }

        const btnTagBold = document.getElementById('btn-tag-bold');
        if (btnTagBold) {
            btnTagBold.addEventListener('mousedown', (e) => e.preventDefault());
            btnTagBold.addEventListener('click', () => wrapTextSelectionWithTag(getActiveObj(), '<b>', '</b>', isUpdatingFormFn()));
        }

        const btnTagItalic = document.getElementById('btn-tag-italic');
        if (btnTagItalic) {
            btnTagItalic.addEventListener('mousedown', (e) => e.preventDefault());
            btnTagItalic.addEventListener('click', () => wrapTextSelectionWithTag(getActiveObj(), '<i>', '</i>', isUpdatingFormFn()));
        }

        const btnTagClear = document.getElementById('btn-tag-clear');
        if (btnTagClear) {
            btnTagClear.addEventListener('mousedown', (e) => e.preventDefault());
            btnTagClear.addEventListener('click', () => clearTextTags(getActiveObj(), isUpdatingFormFn()));
        }
    }

    window.inspectorText = {
        applyTextContentChange,
        wrapTextSelectionWithTag,
        clearTextTags,
        bindTextToolbar
    };
})();

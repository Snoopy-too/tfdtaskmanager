/**
 * Editor Template Importer Module
 * Handles importing components and templates into the active canvas with optional dataset row substitutions.
 */
(function() {
    'use strict';

    function applyRowDataToCanvasObjects(objectsList, rowData) {
        if (!objectsList || !Array.isArray(objectsList) || !rowData) return;

        function getRowValue(row, rawBinding) {
            if (!row || !rawBinding) return undefined;
            const colName = String(rawBinding).replace(/\{\{|\}\}/g, '').trim();
            if (!colName) return undefined;
            if (row[colName] !== undefined) return row[colName];
            
            const lowerCol = colName.toLowerCase();
            const matchKey = Object.keys(row).find(k => k.toLowerCase().trim() === lowerCol);
            return matchKey !== undefined ? row[matchKey] : undefined;
        }

        objectsList.forEach(obj => {
            if (obj.type === 'group' && Array.isArray(obj.objects)) {
                applyRowDataToCanvasObjects(obj.objects, rowData);
            }

            if ((obj.type === 'text' || obj.type === 'i-text' || obj.type === 'textbox') && (obj.text || obj.variable_binding)) {
                let templateText = obj.variable_binding || obj.text || '';
                let substitutedText = templateText;
                const matches = templateText.match(/\{\{([a-zA-Z0-9_\-]+)\}\}/g);
                if (matches) {
                    matches.forEach(placeholder => {
                        const colName = placeholder.replace(/\{\{|\}\}/g, '').trim();
                        const val = getRowValue(rowData, colName);
                        const replacement = val !== undefined ? val : placeholder;
                        substitutedText = substitutedText.replaceAll(placeholder, replacement);
                    });
                } else if (obj.variable_binding) {
                    const val = getRowValue(rowData, obj.variable_binding);
                    if (val !== undefined) {
                        substitutedText = val;
                    }
                }
                obj.text = substitutedText;
            }

            if (obj.type === 'image' && obj.variable_binding) {
                const filename = getRowValue(rowData, obj.variable_binding);
                if (filename && window.assetPicker && typeof window.assetPicker.getAssetUrlByFilename === 'function') {
                    const assetUrl = window.assetPicker.getAssetUrlByFilename(filename);
                    if (assetUrl) {
                        obj.src = assetUrl;
                    }
                }
            }

            if (obj.variable_binding && obj.type !== 'text' && obj.type !== 'i-text' && obj.type !== 'textbox' && obj.type !== 'image') {
                const rawVal = getRowValue(rowData, obj.variable_binding);
                if (rawVal !== undefined && rawVal !== null) {
                    const val = String(rawVal).trim();
                    if (val && window.assetPicker && typeof window.assetPicker.getAssetUrlByFilename === 'function') {
                        const assetUrl = window.assetPicker.getAssetUrlByFilename(val);
                        if (assetUrl) {
                            obj.src = assetUrl;
                        }
                    }
                    const lowerVal = val.toLowerCase();
                    const hideValues = ['transparent.png', '0', 'false', 'none', 'hidden', 'hide'];
                    if (hideValues.includes(lowerVal)) {
                        obj.opacity = 0;
                        obj.visible = false;
                    } else {
                        obj.visible = true;
                        if (obj.opacity === 0) obj.opacity = 1;
                    }
                }
            }
        });
    }

    function importTemplateToCanvas(sourceTemplateId, templateName, groupAsSingleComponent, selectedRowData = null) {
        const canvas = window.editorCanvas;
        if (!canvas) return;

        if (window.editorCore && typeof window.editorCore.setSaveStatus === 'function') {
            window.editorCore.setSaveStatus('Importing template component...', 'pulse');
        }

        fetch(`api.php?action=load_canvas&template_id=${sourceTemplateId}`)
            .then(r => r.json())
            .then(data => {
                if (!data || !data.canvas_json) {
                    alert('The selected template contains no canvas data.');
                    if (window.editorCore) window.editorCore.setSaveStatus('Import failed', 'error');
                    return;
                }

                let parsed;
                try {
                    parsed = JSON.parse(data.canvas_json);
                } catch (e) {
                    console.error('Failed to parse target template canvas JSON:', e);
                    alert('Invalid canvas data format in selected template.');
                    if (window.editorCore) window.editorCore.setSaveStatus('Import failed', 'error');
                    return;
                }

                if (!parsed.objects || !Array.isArray(parsed.objects) || parsed.objects.length === 0) {
                    alert('The selected template has no elements to import.');
                    if (window.editorCore) window.editorCore.setSaveStatus('Selected template is empty', 'error');
                    return;
                }

                if (selectedRowData) {
                    applyRowDataToCanvasObjects(parsed.objects, selectedRowData);
                }

                const filteredObjects = (parsed.objects || []).filter(o => o.id !== 'safe-zone-guide' && o.id !== 'bleed-zone-guide');

                fabric.util.enlivenObjects(filteredObjects, (enlivenedObjects) => {
                    if (!enlivenedObjects) enlivenedObjects = [];

                    const sourceW = data.width || parsed.width || 200;
                    const sourceH = data.height || parsed.height || 200;

                    let bgFill = 'transparent';
                    if (typeof parsed.backgroundColor === 'string' && parsed.backgroundColor) {
                        bgFill = parsed.backgroundColor;
                    } else if (parsed.backgroundColor && typeof parsed.backgroundColor === 'object' && parsed.backgroundColor.color) {
                        bgFill = parsed.backgroundColor.color;
                    } else if (typeof parsed.background === 'string' && parsed.background) {
                        bgFill = parsed.background;
                    }

                    const hasExistingFullBg = enlivenedObjects.some(obj => 
                        (obj.type === 'rect' || obj.type === 'image') &&
                        Math.abs(obj.left || 0) < 5 &&
                        Math.abs(obj.top || 0) < 5 &&
                        Math.abs((obj.width * (obj.scaleX || 1)) - sourceW) < 10 &&
                        Math.abs((obj.height * (obj.scaleY || 1)) - sourceH) < 10
                    );

                    if (!hasExistingFullBg) {
                        const bgRect = new fabric.Rect({
                            id: `card-bg-${Date.now()}`,
                            name: `Card Background (${templateName})`,
                            left: 0,
                            top: 0,
                            width: sourceW,
                            height: sourceH,
                            fill: bgFill,
                            stroke: '#64748b',
                            strokeWidth: 1,
                            strokeUniform: true,
                            selectable: true
                        });
                        enlivenedObjects.unshift(bgRect);
                    }

                    canvas.discardActiveObject();

                    if (groupAsSingleComponent && enlivenedObjects.length > 1) {
                        const group = new fabric.Group(enlivenedObjects, {
                            name: `Component: ${templateName}`,
                            left: (canvas.width - sourceW) / 2,
                            top: (canvas.height - sourceH) / 2
                        });
                        canvas.add(group);
                        canvas.setActiveObject(group);
                    } else if (groupAsSingleComponent && enlivenedObjects.length === 1) {
                        const singleObj = enlivenedObjects[0];
                        singleObj.set({
                            name: singleObj.name ? `${singleObj.name} (${templateName})` : templateName,
                            left: (canvas.width - sourceW) / 2,
                            top: (canvas.height - sourceH) / 2
                        });
                        canvas.add(singleObj);
                        canvas.setActiveObject(singleObj);
                    } else {
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
                    if (window.editorCore && typeof window.editorCore.triggerAutoSave === 'function') {
                        window.editorCore.triggerAutoSave();
                    }

                    if (window.layerManager && typeof window.layerManager.renderLayersList === 'function') {
                        window.layerManager.renderLayersList();
                    }

                    if (window.editorCore && typeof window.editorCore.setSaveStatus === 'function') {
                        window.editorCore.setSaveStatus('Template component imported', 'saved');
                    }
                });
            })
            .catch(err => {
                console.error('Error importing template:', err);
                alert('Failed to load target template data.');
                if (window.editorCore) window.editorCore.setSaveStatus('Import failed', 'error');
            });
    }

    function setupImportTemplateControls() {
        const btnImport = document.getElementById('btn-import-template');
        const modal = document.getElementById('modal-import-template');
        const btnClose = document.getElementById('btn-close-import-modal');
        const btnCancel = document.getElementById('btn-cancel-import');
        const btnConfirm = document.getElementById('btn-confirm-import');
        const select = document.getElementById('import-template-select');
        const chkGroup = document.getElementById('import-as-group');

        const rowContainer = document.getElementById('import-dataset-row-container');
        const rowSelect = document.getElementById('import-dataset-row-select');
        const dsNameEl = document.getElementById('import-dataset-name');
        const dsCountEl = document.getElementById('import-dataset-row-count');

        let currentImportDataset = null;

        if (!btnImport || !modal) return;

        function onTemplateChange() {
            if (!select) return;
            const templateId = select.value;
            currentImportDataset = null;
            if (rowContainer) rowContainer.classList.add('hidden');
            if (rowSelect) rowSelect.innerHTML = '<option value="raw">Template Default (Unsubstituted {{Placeholders}})</option>';

            if (!templateId) return;

            fetch(`api.php?action=load_canvas&template_id=${templateId}`)
                .then(r => r.json())
                .then(data => {
                    if (data && data.dataset_id) {
                        return fetch(`api.php?action=get_dataset&dataset_id=${data.dataset_id}`);
                    }
                    return null;
                })
                .then(r => r ? r.json() : null)
                .then(dataset => {
                    if (dataset && dataset.rowData && dataset.rowData.length > 0) {
                        currentImportDataset = dataset;
                        if (dsNameEl) dsNameEl.textContent = dataset.name || 'Bound Dataset';
                        if (dsCountEl) dsCountEl.textContent = `${dataset.rowData.length} items`;

                        if (rowSelect) {
                            rowSelect.innerHTML = '<option value="raw">Template Default (Unsubstituted {{Placeholders}})</option>';
                            dataset.rowData.forEach((row, idx) => {
                                let label = '';
                                const priorityKeys = ['name', 'title', 'fighter', 'card_name', 'card name', 'character', 'header'];
                                for (const pk of priorityKeys) {
                                    const matchKey = Object.keys(row).find(k => k.toLowerCase().trim() === pk);
                                    if (matchKey && row[matchKey] && String(row[matchKey]).trim() !== '') {
                                        label = String(row[matchKey]).trim();
                                        break;
                                    }
                                }
                                if (!label) {
                                    const firstVal = Object.values(row).find(v => v !== null && v !== undefined && String(v).trim() !== '');
                                    if (firstVal) label = String(firstVal).trim();
                                }

                                const opt = document.createElement('option');
                                opt.value = idx.toString();
                                opt.textContent = `Row ${idx + 1}` + (label ? `: ${label}` : '');
                                rowSelect.appendChild(opt);
                            });
                        }
                        if (rowContainer) rowContainer.classList.remove('hidden');
                    }
                })
                .catch(err => console.error('Failed to load dataset for import template:', err));
        }

        if (select) {
            select.addEventListener('change', onTemplateChange);
        }

        function openModal() {
            if (!select) return;
            select.innerHTML = '<option value="">Loading templates...</option>';
            modal.classList.remove('hidden');
            currentImportDataset = null;
            if (rowContainer) rowContainer.classList.add('hidden');

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
                    onTemplateChange();
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
                let templateName = selectedOption ? selectedOption.textContent.split(' (')[0] : 'Imported Component';

                const selectedRowVal = rowSelect ? rowSelect.value : 'raw';
                let selectedRowData = null;

                if (selectedRowVal !== 'raw' && currentImportDataset && currentImportDataset.rowData) {
                    const idx = parseInt(selectedRowVal, 10);
                    if (!isNaN(idx) && currentImportDataset.rowData[idx]) {
                        selectedRowData = currentImportDataset.rowData[idx];
                        const rowOpt = rowSelect.options[rowSelect.selectedIndex];
                        const rowLabel = rowOpt ? rowOpt.textContent : `Row ${idx + 1}`;
                        templateName = `${templateName} (${rowLabel})`;
                    }
                }

                if (!sourceTemplateId) {
                    alert('Please select a template to import.');
                    return;
                }

                closeModal();
                importTemplateToCanvas(parseInt(sourceTemplateId, 10), templateName, asGroup, selectedRowData);
            });
        }
    }

    window.editorImporter = {
        setupImportTemplateControls,
        importTemplateToCanvas,
        applyRowDataToCanvasObjects
    };
})();

/**
 * Template Engine Module
 * Manages dataset row rendering, variable bindings, and dynamic live previews.
 */
(function() {
    'use strict';

    let dataset = null;
    let currentRowIndex = 0;
    let activeRowIndices = [];
    
    // Original template strings stored to prevent loss on row changes
    const textTemplates = new Map();

    // Original image sources stored to restore when binding is removed
    const imageOriginalSrcs = new Map();

    function parseRowFilter(filterStr, totalRows) {
        return window.textStyleParser ? window.textStyleParser.parseRowFilter(filterStr, totalRows) : Array.from({ length: totalRows || 0 }, (_, i) => i);
    }


    function initTemplateEngine() {
        setupFilterInputListener();

        if (!window.studioConfig.datasetId) {
            return;
        }

        // Fetch dataset
        fetch(`api.php?action=get_dataset&dataset_id=${window.studioConfig.datasetId}`)
        .then(response => response.json())
        .then(data => {
            if (data.rowData && data.rowData.length > 0) {
                dataset = data;
                activeRowIndices = parseRowFilter(window.studioConfig.rowFilter, dataset.rowData.length);
                currentRowIndex = 0;
                
                document.getElementById('row-total').textContent = activeRowIndices.length.toString() + (activeRowIndices.length < dataset.rowData.length ? ` (Filtered from ${dataset.rowData.length})` : '');
                
                setupNavControls();
                
                // Wait for canvas to load before initial binding application
                const checkCanvas = setInterval(() => {
                    if (window.editorCanvas && window.editorCanvas.getObjects().length > 0) {
                        clearInterval(checkCanvas);
                        applyBindings();
                    }
                }, 200);
            }
        })
        .catch(err => {
            console.error('Failed to load dataset for editor binding:', err);
        });
    }

    function setupFilterInputListener() {
        const filterInput = document.getElementById('template-row-filter');
        if (!filterInput || filterInput.dataset.bound) return;
        filterInput.dataset.bound = 'true';

        filterInput.addEventListener('change', (e) => {
            const val = e.target.value.trim();
            window.studioConfig.rowFilter = val;

            const formData = new FormData();
            formData.append('template_id', window.studioConfig.templateId);
            formData.append('row_filter', val);
            if (window.studioConfig.csrfToken) {
                formData.append('csrf_token', window.studioConfig.csrfToken);
            }

            fetch('api.php?action=update_template_row_filter', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success && dataset && dataset.rowData) {
                    activeRowIndices = parseRowFilter(window.studioConfig.rowFilter, dataset.rowData.length);
                    currentRowIndex = 0;
                    const totalEl = document.getElementById('row-total');
                    if (totalEl) {
                        totalEl.textContent = activeRowIndices.length.toString() + (activeRowIndices.length < dataset.rowData.length ? ` (Filtered from ${dataset.rowData.length})` : '');
                    }
                    applyBindings();
                }
            })
            .catch(err => console.error('Failed to update template row filter:', err));
        });
    }

    function setupNavControls() {
        const btnPrev = document.getElementById('btn-row-prev');
        const btnNext = document.getElementById('btn-row-next');
        
        if (!btnPrev || !btnNext) return;

        btnPrev.addEventListener('click', () => {
            if (currentRowIndex > 0) {
                currentRowIndex--;
                applyBindings();
            }
        });

        btnNext.addEventListener('click', () => {
            if (currentRowIndex < activeRowIndices.length - 1) {
                currentRowIndex++;
                applyBindings();
            }
        });
    }

    function parseStyledText(rawText) {
        return window.textStyleParser ? window.textStyleParser.parseStyledText(rawText) : { cleanText: rawText || '', charStyles: [] };
    }

    function applyStyledTextToObject(obj, rawText) {
        if (window.textStyleParser) {
            window.textStyleParser.applyStyledTextToObject(obj, rawText);
        } else {
            obj.set('text', rawText || '');
        }
    }


    // Apply variable substitution to canvas text/image layers
    function applyBindings() {
        const canvas = window.editorCanvas;
        if (!canvas) return;

        let row = null;
        let actualRowIndex = 0;

        if (dataset && dataset.rowData && dataset.rowData.length > 0) {
            if (!activeRowIndices || activeRowIndices.length === 0) {
                activeRowIndices = parseRowFilter(window.studioConfig.rowFilter, dataset.rowData.length);
            }

            if (currentRowIndex >= activeRowIndices.length) {
                currentRowIndex = Math.max(0, activeRowIndices.length - 1);
            }

            actualRowIndex = activeRowIndices[currentRowIndex] !== undefined ? activeRowIndices[currentRowIndex] : currentRowIndex;
            row = dataset.rowData[actualRowIndex];

            const rowIndicator = document.getElementById('row-indicator');
            if (rowIndicator) {
                rowIndicator.textContent = `Row ${actualRowIndex + 1} (${currentRowIndex + 1} of ${activeRowIndices.length})`;
            }
        }

        const objects = canvas.getObjects();
        let needsRender = false;
        const imageSwapPromises = [];

        objects.forEach(obj => {
            if (obj.type === 'i-text' || obj.type === 'text' || obj.type === 'textbox') {
                // Initialize original template store
                if (!textTemplates.has(obj)) {
                    textTemplates.set(obj, obj.text || '');
                }

                let templateText = obj.variable_binding || textTemplates.get(obj);
                let substitutedText = templateText;
                
                // Replace any double brackets syntax {{ColumnName}}
                if (row) {
                    const matches = templateText.match(/\{\{([a-zA-Z0-9_\-]+)\}\}/g);
                    
                    if (matches) {
                        matches.forEach(placeholder => {
                            const colName = placeholder.replace(/\{\{|\}\}/g, '');
                            const replacement = row[colName] !== undefined ? row[colName] : placeholder;
                            substitutedText = substitutedText.replaceAll(placeholder, replacement);
                        });
                    } else if (obj.variable_binding) {
                        // If direct binding dropdown is set but text doesn't match bracket regex, fallback to direct swap
                        const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '');
                        if (row[colName] !== undefined) {
                            substitutedText = row[colName];
                        }
                    }
                }

                applyStyledTextToObject(obj, substitutedText);
                needsRender = true;
            } else if (obj.type === 'image' && obj.variable_binding) {
                // Image binding: swap image src based on dataset column value
                if (!imageOriginalSrcs.has(obj)) {
                    imageOriginalSrcs.set(obj, {
                        src: obj.getSrc ? obj.getSrc() : (obj._element ? obj._element.src : ''),
                        scaleX: obj.scaleX,
                        scaleY: obj.scaleY,
                        width: obj.width,
                        height: obj.height
                    });
                }

                if (row) {
                    const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '').trim();
                    const filename = row[colName];

                    if (filename && window.assetPicker && typeof window.assetPicker.getAssetUrlByFilename === 'function') {
                        const assetUrl = window.assetPicker.getAssetUrlByFilename(filename);
                        if (assetUrl) {
                            const currentSrc = obj.getSrc ? obj.getSrc() : '';
                            // Only swap if URL actually changed to avoid unnecessary reloads
                            if (!currentSrc.endsWith(assetUrl) && currentSrc !== assetUrl) {
                                const targetWidth = (obj.width || 0) * (obj.scaleX !== undefined ? obj.scaleX : 1);
                                const targetHeight = (obj.height || 0) * (obj.scaleY !== undefined ? obj.scaleY : 1);

                                const prepPromise = window.prepareSvgSource ? window.prepareSvgSource(assetUrl) : Promise.resolve(assetUrl);
                                const swapPromise = prepPromise.then(resolvedUrl => {
                                    return new Promise((resolve) => {
                                        obj.setSrc(resolvedUrl, () => {
                                            obj.asset_url = assetUrl;
                                            if (targetWidth > 0 && obj.width > 0) {
                                                obj.set('scaleX', targetWidth / obj.width);
                                            }
                                            if (targetHeight > 0 && obj.height > 0) {
                                                obj.set('scaleY', targetHeight / obj.height);
                                            }
                                            obj.setCoords();
                                            resolve();
                                        }, { crossOrigin: 'anonymous' });
                                    });
                                });
                                imageSwapPromises.push(swapPromise);
                            }
                        }
                    }
                }
            } else if (obj.type === 'image' && !obj.variable_binding && imageOriginalSrcs.has(obj)) {
                const orig = imageOriginalSrcs.get(obj);
                const origSrc = (typeof orig === 'string') ? orig : (orig ? orig.src : '');
                imageOriginalSrcs.delete(obj);
                if (origSrc) {
                    const targetWidth = (obj.width || 0) * (obj.scaleX !== undefined ? obj.scaleX : 1);
                    const targetHeight = (obj.height || 0) * (obj.scaleY !== undefined ? obj.scaleY : 1);
                    const prepPromise = window.prepareSvgSource ? window.prepareSvgSource(origSrc) : Promise.resolve(origSrc);
                    const swapPromise = prepPromise.then(resolvedUrl => {
                        return new Promise((resolve) => {
                            obj.setSrc(resolvedUrl, () => {
                                obj.asset_url = origSrc;
                                if (targetWidth > 0 && obj.width > 0) {
                                    obj.set('scaleX', targetWidth / obj.width);
                                }
                                if (targetHeight > 0 && obj.height > 0) {
                                    obj.set('scaleY', targetHeight / obj.height);
                                }
                                obj.setCoords();
                                resolve();
                            }, { crossOrigin: 'anonymous' });
                        });
                    });
                    imageSwapPromises.push(swapPromise);
                }
            } else if (obj.variable_binding) {
                // Shape / Object visibility and dataset binding (including SVG layer substitution)
                if (row) {
                    const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '').trim();
                    const val = row[colName] !== undefined ? String(row[colName]).trim() : '';

                    // Check if value is an image/SVG asset filename
                    if (val && window.assetPicker && typeof window.assetPicker.getAssetUrlByFilename === 'function') {
                        const assetUrl = window.assetPicker.getAssetUrlByFilename(val);
                        if (assetUrl) {
                            const targetWidth = (obj.width || 0) * (obj.scaleX !== undefined ? obj.scaleX : 1);
                            const targetHeight = (obj.height || 0) * (obj.scaleY !== undefined ? obj.scaleY : 1);
                            if (obj.setSrc && typeof obj.setSrc === 'function') {
                                const currentSrc = obj.getSrc ? obj.getSrc() : '';
                                if (!currentSrc.endsWith(assetUrl) && currentSrc !== assetUrl) {
                                    const prepPromise = window.prepareSvgSource ? window.prepareSvgSource(assetUrl) : Promise.resolve(assetUrl);
                                    const swapPromise = prepPromise.then(resolvedUrl => {
                                        return new Promise((resolve) => {
                                            obj.setSrc(resolvedUrl, () => {
                                                obj.asset_url = assetUrl;
                                                if (targetWidth > 0 && obj.width > 0) {
                                                    obj.set('scaleX', targetWidth / obj.width);
                                                }
                                                if (targetHeight > 0 && obj.height > 0) {
                                                    obj.set('scaleY', targetHeight / obj.height);
                                                }
                                                obj.setCoords();
                                                resolve();
                                            }, { crossOrigin: 'anonymous' });
                                        });
                                    });
                                    imageSwapPromises.push(swapPromise);
                                }
                            } else {
                                // Swap legacy group/path SVG with dynamic fabric.Image
                                const prepPromise = window.prepareSvgSource ? window.prepareSvgSource(assetUrl) : Promise.resolve(assetUrl);
                                const swapPromise = prepPromise.then(resolvedUrl => {
                                    return new Promise((resolve) => {
                                        fabric.Image.fromURL(resolvedUrl, (newImg) => {
                                            if (!newImg) return resolve();
                                            const scaleX = (targetWidth > 0 && newImg.width > 0) ? (targetWidth / newImg.width) : (obj.scaleX || 1);
                                            const scaleY = (targetHeight > 0 && newImg.height > 0) ? (targetHeight / newImg.height) : (obj.scaleY || 1);
                                            newImg.set({
                                                left: obj.left,
                                                top: obj.top,
                                                originX: obj.originX || 'center',
                                                originY: obj.originY || 'center',
                                                scaleX: scaleX,
                                                scaleY: scaleY,
                                                angle: obj.angle || 0,
                                                opacity: obj.opacity !== undefined ? obj.opacity : 1,
                                                name: val,
                                                variable_binding: obj.variable_binding,
                                                id: obj.id,
                                                original_filename: val,
                                                asset_url: assetUrl
                                            });
                                            const index = canvas.getObjects().indexOf(obj);
                                            canvas.remove(obj);
                                            if (index >= 0) {
                                                canvas.insertAt(newImg, index, false);
                                            } else {
                                                canvas.add(newImg);
                                            }
                                            newImg.setCoords();
                                            resolve();
                                        }, { crossOrigin: 'anonymous' });
                                    });
                                });
                                imageSwapPromises.push(swapPromise);
                            }
                            return;
                        }
                    }

                    if (obj._originalOpacity === undefined) {
                        obj._originalOpacity = obj.opacity !== undefined ? obj.opacity : 1;
                    }

                    if (val === 'transparent.png' || val === '0' || val === 'false' || val === 'none' || val === '' || val === 'hidden') {
                        obj.set('opacity', 0);
                        obj.set('visible', false);
                    } else {
                        obj.set('opacity', obj._originalOpacity || 1);
                        obj.set('visible', true);
                    }
                    obj.setCoords();
                    needsRender = true;
                }
            }
        });

        if (imageSwapPromises.length > 0) {
            // Wait for all image swaps to complete, then render
            Promise.all(imageSwapPromises).then(() => {
                canvas.renderAll();
                if (window.propertyInspector && typeof window.propertyInspector.inspect === 'function' && canvas.getActiveObject()) {
                    window.propertyInspector.inspect(canvas.getActiveObject());
                }
            });
        } else if (needsRender) {
            canvas.renderAll();
            if (window.propertyInspector && typeof window.propertyInspector.inspect === 'function' && canvas.getActiveObject()) {
                window.propertyInspector.inspect(canvas.getActiveObject());
            }
        }
    }

    // Public method to reset template text when user edits it in inspector
    function updateTextTemplate(obj, rawText) {
        textTemplates.set(obj, rawText);
        applyBindings();
    }

    let datasetSaveDebounce = null;
    function updateDatasetCell(colName, value) {
        if (!dataset || !dataset.rowData) return;
        const actualRowIndex = activeRowIndices[currentRowIndex] !== undefined ? activeRowIndices[currentRowIndex] : currentRowIndex;
        if (!dataset.rowData[actualRowIndex]) return;

        dataset.rowData[actualRowIndex][colName] = value;
        applyBindings();

        // Debounced background API save to database
        if (window.studioConfig && window.studioConfig.datasetId) {
            clearTimeout(datasetSaveDebounce);
            datasetSaveDebounce = setTimeout(() => {
                const formData = new FormData();
                formData.append('dataset_id', window.studioConfig.datasetId);
                formData.append('row_index', actualRowIndex);
                formData.append('column_name', colName);
                formData.append('value', value);
                if (window.studioConfig.csrfToken) {
                    formData.append('csrf_token', window.studioConfig.csrfToken);
                }

                fetch('api.php?action=update_dataset_cell', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-Token': window.studioConfig.csrfToken
                    }
                }).then(r => r.json()).then(res => {
                    if (res.error) {
                        console.error('Failed to save dataset cell:', res.error);
                    }
                }).catch(e => console.error('Dataset save error:', e));
            }, 350);
        }
    }

    function switchDataset(newDatasetId) {
        const templateId = window.studioConfig ? window.studioConfig.templateId : null;
        const csrfToken = window.studioConfig?.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

        if (!templateId) return;

        const formData = new FormData();
        formData.append('template_id', templateId);
        formData.append('dataset_id', newDatasetId || '');
        if (csrfToken) formData.append('csrf_token', csrfToken);

        fetch('api.php?action=bind_template_dataset', {
            method: 'POST',
            body: formData,
            headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {}
        })
        .then(r => r.json())
        .then(res => {
            if (res.error) {
                alert(res.error);
                return;
            }

            window.studioConfig.datasetId = res.dataset_id ? parseInt(res.dataset_id) : null;

            const navControls = document.getElementById('dataset-nav-controls');
            const totalContainer = document.getElementById('dataset-total-container');
            const filterContainer = document.getElementById('dataset-filter-container');
            const statusDot = document.getElementById('dataset-status-dot');

            if (res.dataset) {
                dataset = res.dataset;
                currentRowIndex = 0;
                const totalRows = (dataset.rowData && Array.isArray(dataset.rowData)) ? dataset.rowData.length : 0;

                if (navControls) navControls.classList.remove('hidden');
                if (totalContainer) totalContainer.classList.remove('hidden');
                if (filterContainer) filterContainer.classList.remove('hidden');
                if (statusDot) {
                    statusDot.classList.remove('bg-slate-600');
                    statusDot.classList.add('bg-violet-400');
                }

                const rowTotal = document.getElementById('row-total');
                if (rowTotal) rowTotal.textContent = totalRows.toString();

                setupNavControls();
            } else {
                dataset = null;
                currentRowIndex = 0;

                if (navControls) navControls.classList.add('hidden');
                if (totalContainer) totalContainer.classList.add('hidden');
                if (filterContainer) filterContainer.classList.add('hidden');
                if (statusDot) {
                    statusDot.classList.remove('bg-violet-400');
                    statusDot.classList.add('bg-slate-600');
                }
            }

            if (window.propertyInspector && typeof window.propertyInspector.updateDatasetColumns === 'function') {
                window.propertyInspector.updateDatasetColumns(dataset ? dataset.columnMap : []);
            }

            applyBindings();
        })
        .catch(err => {
            console.error('Error switching dataset:', err);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initTemplateEngine();
    });

    window.templateEngine = {
        applyBindings: applyBindings,
        updateTextTemplate: updateTextTemplate,
        updateDatasetCell: updateDatasetCell,
        parseStyledText: parseStyledText,
        getTextTemplate: (obj) => textTemplates.get(obj),
        switchDataset: switchDataset,
        getCurrentRowIndex: () => activeRowIndices[currentRowIndex] !== undefined ? activeRowIndices[currentRowIndex] : currentRowIndex,
        getCurrentRowData: () => {
            if (!dataset || !dataset.rowData) return null;
            const actualRowIndex = activeRowIndices[currentRowIndex] !== undefined ? activeRowIndices[currentRowIndex] : currentRowIndex;
            return dataset.rowData[actualRowIndex] || null;
        },
        getDataset: () => dataset,
        setRowIndex: (idx) => {
            if (dataset && idx >= 0 && idx < dataset.rowData.length) {
                currentRowIndex = idx;
                applyBindings();
            }
        }
    };
})();

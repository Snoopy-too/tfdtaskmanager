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

    // ponytail: parse user row filter expression (e.g. "1-42", "43-82", "1-10, 15, 20-30") into valid 0-based indices
    function parseRowFilter(filterStr, totalRows) {
        if (!totalRows || totalRows <= 0) return [];
        if (!filterStr || !filterStr.trim()) {
            return Array.from({ length: totalRows }, (_, i) => i);
        }
        const indices = new Set();
        const parts = filterStr.split(',');
        parts.forEach(part => {
            const trimmed = part.trim();
            if (trimmed.includes('-')) {
                const range = trimmed.split('-');
                const start = parseInt(range[0], 10);
                const end = parseInt(range[1], 10);
                if (!isNaN(start) && !isNaN(end)) {
                    const min = Math.min(start, end);
                    const max = Math.max(start, end);
                    for (let r = min; r <= max; r++) {
                        if (r >= 1 && r <= totalRows) {
                            indices.add(r - 1);
                        }
                    }
                }
            } else {
                const single = parseInt(trimmed, 10);
                if (!isNaN(single) && single >= 1 && single <= totalRows) {
                    indices.add(single - 1);
                }
            }
        });
        const sorted = Array.from(indices).sort((a, b) => a - b);
        return sorted.length > 0 ? sorted : Array.from({ length: totalRows }, (_, i) => i);
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

    // Replace bindings in text layers dynamically
    function applyBindings() {
        const canvas = window.editorCanvas;
        if (!canvas || !dataset) return;

        if (!activeRowIndices || activeRowIndices.length === 0) {
            activeRowIndices = parseRowFilter(window.studioConfig.rowFilter, dataset.rowData.length);
        }

        if (currentRowIndex >= activeRowIndices.length) {
            currentRowIndex = Math.max(0, activeRowIndices.length - 1);
        }

        const actualRowIndex = activeRowIndices[currentRowIndex] !== undefined ? activeRowIndices[currentRowIndex] : currentRowIndex;
        const row = dataset.rowData[actualRowIndex];

        const rowIndicator = document.getElementById('row-indicator');
        if (rowIndicator) {
            rowIndicator.textContent = `Row ${actualRowIndex + 1} (${currentRowIndex + 1} of ${activeRowIndices.length})`;
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
                
                // Replace any double brackets syntax {{ColumnName}}
                if (row) {
                    // Match and replace all placeholders
                    let substitutedText = templateText;
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
                    
                    obj.set('text', substitutedText);
                    if (typeof obj.initDimensions === 'function') {
                        obj.initDimensions();
                    }
                    obj.setCoords();
                    needsRender = true;
                }
            } else if (obj.type === 'image' && obj.variable_binding) {
                // Image binding: swap image src based on dataset column value
                if (!imageOriginalSrcs.has(obj)) {
                    imageOriginalSrcs.set(obj, obj.getSrc ? obj.getSrc() : (obj._element ? obj._element.src : ''));
                }

                if (row) {
                    const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '');
                    const filename = row[colName];

                    if (filename && window.assetPicker && typeof window.assetPicker.getAssetUrlByFilename === 'function') {
                        const assetUrl = window.assetPicker.getAssetUrlByFilename(filename);
                        if (assetUrl) {
                            const currentSrc = obj.getSrc ? obj.getSrc() : '';
                            // Only swap if URL actually changed to avoid unnecessary reloads
                            if (!currentSrc.endsWith(assetUrl) && currentSrc !== assetUrl) {
                                const swapPromise = new Promise((resolve) => {
                                    obj.setSrc(assetUrl, () => {
                                        obj.setCoords();
                                        resolve();
                                    }, { crossOrigin: 'anonymous' });
                                });
                                imageSwapPromises.push(swapPromise);
                            }
                        }
                    }
                }
            } else if (obj.variable_binding) {
                // Shape / Object visibility and dataset binding
                if (row) {
                    const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '');
                    const val = row[colName] !== undefined ? String(row[colName]).trim() : '';

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
            });
        } else if (needsRender) {
            canvas.renderAll();
        }
    }

    // Public method to reset template text when user edits it in inspector
    function updateTextTemplate(obj, rawText) {
        textTemplates.set(obj, rawText);
        applyBindings();
    }

    function switchDataset(newDatasetId) {
        const templateId = window.studioConfig ? window.studioConfig.templateId : null;
        const csrfToken = window.studioConfig ? window.studioConfig.csrfToken : '';

        if (!templateId) return;

        const formData = new FormData();
        formData.append('template_id', templateId);
        formData.append('dataset_id', newDatasetId || '');
        if (csrfToken) formData.append('csrf_token', csrfToken);

        fetch('api.php?action=bind_template_dataset', {
            method: 'POST',
            body: formData
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
            const statusDot = document.getElementById('dataset-status-dot');

            if (res.dataset && res.dataset.rowData && res.dataset.rowData.length > 0) {
                dataset = res.dataset;
                currentRowIndex = 0;

                if (navControls) navControls.classList.remove('hidden');
                if (totalContainer) totalContainer.classList.remove('hidden');
                if (statusDot) {
                    statusDot.classList.remove('bg-slate-600');
                    statusDot.classList.add('bg-violet-400');
                }

                const rowTotal = document.getElementById('row-total');
                if (rowTotal) rowTotal.textContent = dataset.rowData.length.toString();

                setupNavControls();
            } else {
                dataset = null;
                currentRowIndex = 0;

                if (navControls) navControls.classList.add('hidden');
                if (totalContainer) totalContainer.classList.add('hidden');
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
        switchDataset: switchDataset,
        getCurrentRowData: () => dataset ? dataset.rowData[currentRowIndex] : null,
        getDataset: () => dataset,
        setRowIndex: (idx) => {
            if (dataset && idx >= 0 && idx < dataset.rowData.length) {
                currentRowIndex = idx;
                applyBindings();
            }
        }
    };
})();

/**
 * Template Engine Module
 * Manages dataset row rendering, variable bindings, and dynamic live previews.
 */
(function() {
    'use strict';

    let dataset = null;
    let currentRowIndex = 0;
    
    // Original template strings stored to prevent loss on row changes
    const textTemplates = new Map();

    // Original image sources stored to restore when binding is removed
    const imageOriginalSrcs = new Map();

    function initTemplateEngine() {
        if (!window.studioConfig.datasetId) {
            return;
        }

        // Fetch dataset
        fetch(`api.php?action=get_dataset&dataset_id=${window.studioConfig.datasetId}`)
        .then(response => response.json())
        .then(data => {
            if (data.rowData && data.rowData.length > 0) {
                dataset = data;
                currentRowIndex = 0;
                
                document.getElementById('row-total').textContent = data.rowData.length.toString();
                
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
            if (currentRowIndex < dataset.rowData.length - 1) {
                currentRowIndex++;
                applyBindings();
            }
        });
    }

    // Replace bindings in text layers dynamically
    function applyBindings() {
        const canvas = window.editorCanvas;
        if (!canvas) return;

        const rowIndicator = document.getElementById('row-indicator');
        if (rowIndicator && dataset) {
            rowIndicator.textContent = `Row ${currentRowIndex + 1} of ${dataset.rowData.length}`;
        }

        const objects = canvas.getObjects();
        let needsRender = false;
        const imageSwapPromises = [];

        objects.forEach(obj => {
            if (obj.type === 'i-text' || obj.type === 'text') {
                // Initialize original template store
                if (!textTemplates.has(obj)) {
                    textTemplates.set(obj, obj.text || '');
                }

                let templateText = obj.variable_binding || textTemplates.get(obj);
                
                // Replace any double brackets syntax {{ColumnName}}
                if (dataset && dataset.rowData[currentRowIndex]) {
                    const row = dataset.rowData[currentRowIndex];
                    
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

                if (dataset && dataset.rowData[currentRowIndex]) {
                    const row = dataset.rowData[currentRowIndex];
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

    document.addEventListener('DOMContentLoaded', () => {
        initTemplateEngine();
    });

    window.templateEngine = {
        applyBindings: applyBindings,
        updateTextTemplate: updateTextTemplate,
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

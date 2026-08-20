/**
 * Export Handler Module
 * Performs offscreen batch rendering of card templates, substituting dataset rows,
 * and compiles output as tiled PDFs with crop marks (jsPDF) or TTS sprite sheet zip packages (JSZip).
 */
(function() {
    'use strict';

    let canvas = null;
    let templateJson = '';
    let dataset = null;
    let isRunning = false;

    // Load libraries namespace checks
    const { jsPDF } = window.jspdf || {};

    function initExport() {
        // Toggle configuration visual sections
        const formatSelect = document.getElementById('export_format');
        const pdfSettings = document.getElementById('pdf-settings');
        const ttsSettings = document.getElementById('tts-settings');

         if (formatSelect) {
            formatSelect.addEventListener('change', (e) => {
                if (e.target.value === 'pdf') {
                    pdfSettings.classList.remove('hidden');
                    ttsSettings.classList.add('hidden');
                    checkTilingVisibility();
                } else {
                    ttsSettings.classList.remove('hidden');
                    pdfSettings.classList.add('hidden');
                }
            });
        }

        const pageSizeSelect = document.getElementById('pdf_page_size');
        const orientationSelect = document.getElementById('pdf_orientation');
        if (orientationSelect && window.studioConfig) {
            const w = window.studioConfig.widthMm || window.studioConfig.canvasWidth || 0;
            const h = window.studioConfig.heightMm || window.studioConfig.canvasHeight || 0;
            if (w > 0 && h > 0) {
                orientationSelect.value = (w > h) ? 'landscape' : 'portrait';
            }
        }
        const tilingSelect = document.getElementById('pdf_tiling');
        if (pageSizeSelect) pageSizeSelect.addEventListener('change', checkTilingVisibility);
        if (orientationSelect) orientationSelect.addEventListener('change', checkTilingVisibility);
        if (tilingSelect) tilingSelect.addEventListener('change', checkTilingVisibility);

        const runBtn = document.getElementById('btn-run-export');
        if (runBtn) {
            runBtn.addEventListener('click', runExportProcess);
        }

        // Initialize invisible canvas
        canvas = new fabric.Canvas('offscreen-canvas', {
            width: window.studioConfig.canvasWidth,
            height: window.studioConfig.canvasHeight,
            backgroundColor: '#ffffff',
            preserveObjectStacking: true
        });

        checkTilingVisibility();
    }

    function checkTilingVisibility() {
        if (window.exportPdf && typeof window.exportPdf.checkTilingVisibility === 'function') {
            window.exportPdf.checkTilingVisibility();
        }
    }


    // Trigger full batch render
    function runExportProcess() {
        if (isRunning) return;
        isRunning = true;

        document.getElementById('export-progress-container').classList.remove('hidden');
        document.getElementById('btn-run-export').disabled = true;
        updateProgress('Loading template metadata...', 5);

        // Fetch template canvas json
        fetch(`api.php?action=load_canvas&template_id=${window.studioConfig.templateId}`)
        .then(response => response.json())
        .then(data => {
            let json = data.canvas_json;
            if (typeof json === 'string') {
                json = json.replaceAll('"alphabetical"', '"alphabetic"');
            } else if (json && typeof json === 'object') {
                json = JSON.parse(JSON.stringify(json).replaceAll('"alphabetical"', '"alphabetic"'));
            }
            templateJson = json;
            updateProgress('Loading dataset...', 15);

            // Fetch dataset if bound
            if (window.studioConfig.datasetId) {
                return fetch(`api.php?action=get_dataset&dataset_id=${window.studioConfig.datasetId}`)
                    .then(r => r.json())
                    .then(d => {
                        dataset = d;
                    });
            } else {
                dataset = null; // Single card export
            }
        })
        .then(() => {
            updateProgress('Loading project asset mappings...', 18);
            if (window.assetPicker && typeof window.assetPicker.loadAssets === 'function') {
                return window.assetPicker.loadAssets();
            }
        })
        .then(() => {
            updateProgress('Pre-loading fonts & typography...', 20);
            if (document.fonts && typeof document.fonts.load === 'function') {
                const fontNames = [
                    'Inter', 'Plus Jakarta Sans', 'Montserrat', 'Rajdhani', 'Lora', 
                    'EB Garamond', 'Cinzel', 'Playfair Display', 'Outfit', 'Courier Prime', 
                    'Special Elite', 'Share Tech Mono', 'Bangers', 'Fredoka', 'Luckiest Guy', 
                    'MedievalSharp', 'Almendra', 'Orbitron'
                ];

                if (typeof templateJson === 'string') {
                    const fontMatches = templateJson.match(/"fontFamily"\s*:\s*"([^"]+)"/g);
                    if (fontMatches) {
                        fontMatches.forEach(m => {
                            const name = m.split(':')[1].replace(/"/g, '').trim();
                            if (name && !fontNames.includes(name)) fontNames.push(name);
                        });
                    }
                }

                const fontPromises = [];
                fontNames.forEach(f => {
                    fontPromises.push(document.fonts.load(`400 16px "${f}"`).catch(() => {}));
                    fontPromises.push(document.fonts.load(`700 16px "${f}"`).catch(() => {}));
                    fontPromises.push(document.fonts.load(`bold 16px "${f}"`).catch(() => {}));
                    fontPromises.push(document.fonts.load(`italic 16px "${f}"`).catch(() => {}));
                    fontPromises.push(document.fonts.load(`bold 39px "${f}"`).catch(() => {}));
                    fontPromises.push(document.fonts.load(`700 39px "${f}"`).catch(() => {}));
                });

                return Promise.all(fontPromises).then(() => {
                    return document.fonts.ready ? document.fonts.ready : Promise.resolve();
                });
            }
        })
        .then(() => {
            updateProgress('Starting batch render...', 25);
            return renderCards();
        })
        .then(cardImages => {
            const format = document.getElementById('export_format').value;
            if (format === 'pdf') {
                return generatePdf(cardImages);
            } else {
                return generateTtsSheet(cardImages);
            }
        })
        .then(() => {
            updateProgress('Export completed!', 100);
            setTimeout(() => {
                document.getElementById('export-progress-container').classList.add('hidden');
                document.getElementById('btn-run-export').disabled = false;
                isRunning = false;
            }, 3000);
        })
        .catch(err => {
            console.error('Export Engine Failure:', err);
            window.studioAlert('Failed to generate export: ' + err.message, 'Export Error');
            document.getElementById('export-progress-container').classList.add('hidden');
            document.getElementById('btn-run-export').disabled = false;
            isRunning = false;
        });
    }

    function parseExportRowFilter(filterStr, totalRows) {
        return window.textStyleParser ? window.textStyleParser.parseRowFilter(filterStr, totalRows) : Array.from({ length: totalRows || 0 }, (_, i) => i);
    }


    // Dynamic offscreen rendering loop
    function renderCards() {
        return new Promise((resolve, reject) => {
            const images = [];
            let rows = dataset ? dataset.rowData : [{}]; // If no dataset, render once
            if (dataset && dataset.rowData && window.studioConfig && window.studioConfig.rowFilter) {
                const filterIndices = parseExportRowFilter(window.studioConfig.rowFilter, dataset.rowData.length);
                rows = filterIndices.map(i => dataset.rowData[i]);
            }
            let index = 0;

            function renderNext() {
                if (index >= rows.length) {
                    resolve(images);
                    return;
                }

                const percent = Math.round(25 + ((index / rows.length) * 50));
                updateProgress(`Rendering layer templates: Card ${index + 1} of ${rows.length}...`, percent);

                // Ensure document fonts are loaded before rendering text on offscreen canvas
                if (document.fonts && typeof document.fonts.ready !== 'undefined') {
                    document.fonts.ready.then(() => doRenderCanvas());
                } else {
                    doRenderCanvas();
                }

                function doRenderCanvas() {
                    // Load base canvas JSON
                    canvas.loadFromJSON(templateJson, () => {
                    // Substitute values
                    const row = rows[index];
                    const objects = canvas.getObjects();

                    // Remove guides unless bleed checkbox is checked (or they are marked to exclude)
                    const drawBleedEl = document.getElementById('pdf_draw_bleed');
                    const drawBleedCheckbox = drawBleedEl ? drawBleedEl.checked : false;
                    
                    const toRemove = [];
                    const imageSwapPromises = [];

                    function getRowValue(r, rawBinding) {
                        if (!r || !rawBinding) return undefined;
                        const colName = String(rawBinding).replace(/\{\{|\}\}/g, '').trim();
                        if (!colName) return undefined;
                        if (r[colName] !== undefined) return r[colName];
                        const lowerCol = colName.toLowerCase();
                        const matchKey = Object.keys(r).find(k => k.toLowerCase().trim() === lowerCol);
                        return matchKey !== undefined ? r[matchKey] : undefined;
                    }

                    function applyStyledTextToObject(obj, rawText) {
                        if (window.textStyleParser) {
                            window.textStyleParser.applyStyledTextToObject(obj, rawText);
                        } else {
                            obj.set('text', rawText || '');
                        }
                    }


    function processExportObjects(objectsList) {
        objectsList.forEach(obj => {
            if (obj.id === 'safe-zone-guide') {
                toRemove.push(obj);
            } else if (obj.id === 'bleed-zone-guide' && !drawBleedCheckbox) {
                toRemove.push(obj);
            }

            if (obj.type === 'group' && typeof obj.getObjects === 'function') {
                processExportObjects(obj.getObjects());
            }

            // Substitute variables in text layers and parse styled tags
            if (obj.type === 'i-text' || obj.type === 'text' || obj.type === 'textbox') {
                let rawText = obj.variable_binding || obj.text;
                let subText = rawText;
                
                const matches = rawText ? String(rawText).match(/\{\{([a-zA-Z0-9_\-]+)\}\}/g) : null;
                if (matches) {
                    matches.forEach(placeholder => {
                        const colName = placeholder.replace(/\{\{|\}\}/g, '').trim();
                        const val = getRowValue(row, colName);
                        const replacement = val !== undefined ? val : placeholder;
                        subText = String(subText).replaceAll(placeholder, String(replacement));
                    });
                } else if (obj.variable_binding) {
                    const val = getRowValue(row, obj.variable_binding);
                    if (val !== undefined) {
                        subText = String(val);
                    }
                }
                
                applyStyledTextToObject(obj, subText !== undefined && subText !== null ? subText : '');
            } else if (obj.type === 'image' && obj.variable_binding) {
                // Substitute image source for bound image layers
                const filename = getRowValue(row, obj.variable_binding);

                if (filename && window.assetPicker && typeof window.assetPicker.getAssetUrlByFilename === 'function') {
                    const assetUrl = window.assetPicker.getAssetUrlByFilename(filename);
                    if (assetUrl) {
                        const targetWidth = (obj.width || 0) * (obj.scaleX !== undefined ? obj.scaleX : 1);
                        const targetHeight = (obj.height || 0) * (obj.scaleY !== undefined ? obj.scaleY : 1);

                        const prepPromise = window.prepareSvgSource ? window.prepareSvgSource(assetUrl) : Promise.resolve(assetUrl);
                        const swapPromise = prepPromise.then(resolvedUrl => {
                            return new Promise((imgResolve) => {
                                obj.setSrc(resolvedUrl, () => {
                                    if (targetWidth > 0 && obj.width > 0) {
                                        obj.set('scaleX', targetWidth / obj.width);
                                    }
                                    if (targetHeight > 0 && obj.height > 0) {
                                        obj.set('scaleY', targetHeight / obj.height);
                                    }
                                    obj.setCoords();
                                    imgResolve();
                                }, { crossOrigin: 'anonymous' });
                            });
                        });
                        imageSwapPromises.push(swapPromise);
                    }
                }
            } else if (obj.variable_binding) {
                // Shape / Generic object dataset visibility or image binding
                const rawVal = getRowValue(row, obj.variable_binding);
                if (rawVal !== undefined && rawVal !== null) {
                    const val = String(rawVal).trim();

                    // Check if value is an image/SVG asset filename
                    if (val && window.assetPicker && typeof window.assetPicker.getAssetUrlByFilename === 'function') {
                        const assetUrl = window.assetPicker.getAssetUrlByFilename(val);
                        if (assetUrl) {
                            const targetWidth = (obj.width || 0) * (obj.scaleX !== undefined ? obj.scaleX : 1);
                            const targetHeight = (obj.height || 0) * (obj.scaleY !== undefined ? obj.scaleY : 1);
                            if (obj.setSrc && typeof obj.setSrc === 'function') {
                                const prepPromise = window.prepareSvgSource ? window.prepareSvgSource(assetUrl) : Promise.resolve(assetUrl);
                                const swapPromise = prepPromise.then(resolvedUrl => {
                                    return new Promise((imgResolve) => {
                                        obj.setSrc(resolvedUrl, () => {
                                            if (targetWidth > 0 && obj.width > 0) {
                                                obj.set('scaleX', targetWidth / obj.width);
                                            }
                                            if (targetHeight > 0 && obj.height > 0) {
                                                obj.set('scaleY', targetHeight / obj.height);
                                            }
                                            obj.setCoords();
                                            imgResolve();
                                        }, { crossOrigin: 'anonymous' });
                                    });
                                });
                                imageSwapPromises.push(swapPromise);
                            } else {
                                const prepPromise = window.prepareSvgSource ? window.prepareSvgSource(assetUrl) : Promise.resolve(assetUrl);
                                const swapPromise = prepPromise.then(resolvedUrl => {
                                    return new Promise((imgResolve) => {
                                        fabric.Image.fromURL(resolvedUrl, (newImg) => {
                                            if (!newImg) return imgResolve();
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
                                                original_filename: val
                                            });
                                        const idx = cardCanvas.getObjects().indexOf(obj);
                                        cardCanvas.remove(obj);
                                        if (idx >= 0) {
                                            cardCanvas.insertAt(newImg, idx);
                                        } else {
                                            cardCanvas.add(newImg);
                                        }
                                        newImg.setCoords();
                                        imgResolve();
                                    }, { crossOrigin: 'anonymous' });
                                });
                                imageSwapPromises.push(swapPromise);
                            }
                            return;
                        }
                    }

                                    const lowerVal = val.toLowerCase();
                                    const hideValues = ['transparent.png', '0', 'false', 'none', 'hidden', 'hide'];
                                    if (hideValues.includes(lowerVal)) {
                                        obj.set('opacity', 0);
                                        obj.set('visible', false);
                                    } else {
                                        obj.set('opacity', 1);
                                        obj.set('visible', true);
                                    }
                                }
                            }
                        });
                    }

                    processExportObjects(objects);

                    // Perform removals
                    toRemove.forEach(o => canvas.remove(o));

                    // Wait for image swaps (if any) before rendering to PNG
                    const afterImages = imageSwapPromises.length > 0
                        ? Promise.all(imageSwapPromises)
                        : Promise.resolve();

                    afterImages.then(() => {
                        canvas.getObjects().forEach(o => {
                            if (o.type === 'textbox' || o.type === 'text' || o.type === 'i-text') {
                                if (o._clearCache) o._clearCache();
                                if (typeof o.initDimensions === 'function') o.initDimensions();
                                o.setCoords();
                                o.dirty = true;
                            }
                        });
                        canvas.renderAll();

                        // Export data URL PNG
                        const dataUrl = canvas.toDataURL({
                            format: 'png',
                            quality: 1.0
                        });
                        
                        images.push({
                            dataUrl: dataUrl,
                            name: row.name || `Card ${index + 1}`
                        });

                        index++;
                        // Delay slightly to prevent browser freezing
                        setTimeout(renderNext, 20);
                    });
                });
            }
        }

        renderNext();
    });
}

    // Export PDF Tiled Generation
    function generatePdf(cardImages) {
        if (window.exportPdf && typeof window.exportPdf.generatePdf === 'function') {
            return window.exportPdf.generatePdf(cardImages, updateProgress);
        }
        return Promise.reject(new Error('exportPdf module not loaded'));
    }

    // Export Tabletop Simulator Grid Sheets + JSON Zip Package
    function generateTtsSheet(cardImages) {
        if (window.exportTts && typeof window.exportTts.generateTtsSheet === 'function') {
            return window.exportTts.generateTtsSheet(cardImages, updateProgress);
        }
        return Promise.reject(new Error('exportTts module not loaded'));
    }

    // Helper to update progress UI
    function updateProgress(actionText, percentValue) {
        document.getElementById('progress-action').textContent = actionText;
        document.getElementById('progress-percent').textContent = percentValue + '%';
        document.getElementById('progress-bar').style.width = percentValue + '%';
    }


    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initExport);
    } else {
        initExport();
    }
})();

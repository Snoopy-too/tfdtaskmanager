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
        const printModeSelect = document.getElementById('pdf_print_mode');
        if (pageSizeSelect) pageSizeSelect.addEventListener('change', checkTilingVisibility);
        if (orientationSelect) orientationSelect.addEventListener('change', checkTilingVisibility);
        if (tilingSelect) tilingSelect.addEventListener('change', checkTilingVisibility);
        if (printModeSelect) printModeSelect.addEventListener('change', checkTilingVisibility);

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
            const formatEl = document.getElementById('export_format');
            const printModeEl = document.getElementById('pdf_print_mode');
            const isStencilOnly = (formatEl && formatEl.value === 'pdf') && (printModeEl && printModeEl.value === 'cutout_stencil');

            let rows = (dataset && !isStencilOnly) ? dataset.rowData : [{}]; // If no dataset or stencil-only, render once
            if (dataset && !isStencilOnly && dataset.rowData && window.studioConfig && window.studioConfig.rowFilter) {
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
                    const formatEl = document.getElementById('export_format');
                    const printModeEl = document.getElementById('pdf_print_mode');
                    const isStencilPrint = (formatEl && formatEl.value === 'pdf') && (printModeEl && printModeEl.value === 'cutout_stencil');
                    const hasBoxDieline = objects.some(o => Boolean(o.isBoxDieline || (o.id && String(o.id).startsWith('box-dieline-'))));

                    if (isStencilPrint) {
                        canvas.setBackgroundColor('#ffffff', () => {});
                    }
                    
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

                    function applyExportCrop(targetObj, tW, tH) {
                        if (!targetObj || targetObj.type !== 'image') return;
                        if (window.inspectorCrop && typeof window.inspectorCrop.applyCropToFabricImage === 'function') {
                            window.inspectorCrop.applyCropToFabricImage(targetObj, tW, tH);
                            return;
                        }

                        const imgEl = (typeof targetObj.getElement === 'function') ? targetObj.getElement() : targetObj._element;
                        const natW  = (imgEl && imgEl.naturalWidth)  || (targetObj._element && targetObj._element.naturalWidth)  || targetObj.width  || 1;
                        const natH  = (imgEl && imgEl.naturalHeight) || (targetObj._element && targetObj._element.naturalHeight) || targetObj.height || 1;

                        let crop = targetObj.crop_data;
                        if (!crop) {
                            const hasLegacyCrop = (targetObj.cropX && targetObj.cropX > 0) ||
                                                  (targetObj.cropY && targetObj.cropY > 0) ||
                                                  (targetObj.width && natW > 1 && targetObj.width < natW - 1) ||
                                                  (targetObj.height && natH > 1 && targetObj.height < natH - 1);
                            if (hasLegacyCrop) {
                                crop = {
                                    relX: (targetObj.cropX || 0) / natW,
                                    relY: (targetObj.cropY || 0) / natH,
                                    relW: (targetObj.width || natW) / natW,
                                    relH: (targetObj.height || natH) / natH
                                };
                                targetObj.crop_data = crop;
                            }
                        }

                        const visW = (tW && tW > 0) ? tW : ((targetObj.width || 0) * (targetObj.scaleX !== undefined ? targetObj.scaleX : 1));
                        const visH = (tH && tH > 0) ? tH : ((targetObj.height || 0) * (targetObj.scaleY !== undefined ? targetObj.scaleY : 1));

                        if (crop) {
                            let cropX = Math.round(crop.relX * natW);
                            let cropY = Math.round(crop.relY * natH);
                            let cropW = Math.round(crop.relW * natW);
                            let cropH = Math.round(crop.relH * natH);

                            cropX = Math.max(0, Math.min(cropX, natW - 1));
                            cropY = Math.max(0, Math.min(cropY, natH - 1));
                            if (cropX + cropW > natW) cropW = natW - cropX;
                            if (cropY + cropH > natH) cropH = natH - cropY;
                            cropW = Math.max(1, cropW);
                            cropH = Math.max(1, cropH);

                            targetObj.set({
                                cropX: cropX,
                                cropY: cropY,
                                width: cropW,
                                height: cropH,
                                scaleX: (visW > 0 && cropW > 0) ? (visW / cropW) : (targetObj.scaleX || 1),
                                scaleY: (visH > 0 && cropH > 0) ? (visH / cropH) : (targetObj.scaleY || 1)
                            });
                        } else {
                            targetObj.set({
                                cropX: 0,
                                cropY: 0,
                                width: natW,
                                height: natH,
                                scaleX: (visW > 0 && natW > 0) ? (visW / natW) : (targetObj.scaleX || 1),
                                scaleY: (visH > 0 && natH > 0) ? (visH / natH) : (targetObj.scaleY || 1)
                            });
                        }
                    }

                    function applyStyledTextToObject(obj, rawText) {
                        if (window.textStyleParser) {
                            window.textStyleParser.applyStyledTextToObject(obj, rawText);
                        } else {
                            obj.set('text', rawText || '');
                        }
                    }


    function processExportObjects(objectsList) {
        const drawMeasurementsEl = document.getElementById('pdf_measurements');
        const drawMeasurements = drawMeasurementsEl ? drawMeasurementsEl.checked : false;

        objectsList.forEach(obj => {
            if (obj.id === 'safe-zone-guide') {
                if (!drawMeasurements) {
                    toRemove.push(obj);
                } else {
                    obj.set({
                        visible: true,
                        opacity: 0.7,
                        stroke: '#4f46e5',
                        strokeDashArray: [4, 4]
                    });
                }
                return;
            } else if (obj.id === 'bleed-zone-guide' && !drawBleedCheckbox && !drawMeasurements) {
                toRemove.push(obj);
                return;
            }

            const isDielineObj = Boolean(obj.isBoxDieline || (obj.id && String(obj.id).startsWith('box-dieline-')));

            if (isStencilPrint && hasBoxDieline) {
                if (!isDielineObj || obj.excludeFromStencil) {
                    toRemove.push(obj);
                    return;
                }
                if (obj.id === 'box-dieline-fill' || obj.dielineRole === 'fill') {
                    obj.set({
                        fill: '#ffffff',
                        stroke: 'transparent',
                        opacity: 1,
                        visible: true
                    });
                } else if (obj.id === 'box-dieline-cut' || obj.dielineRole === 'cut') {
                    obj.set({
                        stroke: '#000000',
                        strokeWidth: Math.max(obj.strokeWidth || 4, 4),
                        opacity: 1,
                        visible: true
                    });
                } else if (obj.id === 'box-dieline-fold' || obj.dielineRole === 'fold') {
                    obj.set({
                        stroke: '#1e293b',
                        strokeWidth: Math.max(obj.strokeWidth || 3, 3),
                        opacity: 1,
                        visible: true
                    });
                } else if (obj.dielineRole === 'label' || (obj.id && String(obj.id).startsWith('box-dieline-label-'))) {
                    obj.set({
                        fill: '#334155',
                        opacity: 0.9,
                        visible: true
                    });
                }
            } else if (!isStencilPrint && hasBoxDieline) {
                if (obj._preStencilVisible !== undefined) {
                    obj.set('visible', obj._preStencilVisible);
                } else if (!isDielineObj && obj.visible === false && !obj.variable_binding) {
                    obj.set('visible', true);
                }
            }

            if (isDielineObj) {
                return;
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
                                    applyExportCrop(obj, targetWidth, targetHeight);
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
                                            applyExportCrop(obj, targetWidth, targetHeight);
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
                                            newImg.crop_data = obj.crop_data ? { ...obj.crop_data } : null;
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
                                            applyExportCrop(newImg, targetWidth, targetHeight);
                                            const idx = canvas.getObjects().indexOf(obj);
                                            canvas.remove(obj);
                                            if (idx >= 0) {
                                                canvas.insertAt(newImg, idx, false);
                                            } else {
                                                canvas.add(newImg);
                                            }
                                            newImg.setCoords();
                                            imgResolve();
                                        }, { crossOrigin: 'anonymous' });
                                    });
                                });
                                imageSwapPromises.push(swapPromise);
                            }
                            return;
                        }
                    }

                    const lowerVal = val.toLowerCase();
                    const hideValues = ['transparent.png', '0', 'false', 'none', 'hidden', 'hide', ''];
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
                        const drawMeasurementsEl = document.getElementById('pdf_measurements');
                        const drawMeasurements = drawMeasurementsEl ? drawMeasurementsEl.checked : false;

                        if (hasBoxDieline) {
                            const currentObjs = canvas.getObjects();
                            const fillObj = currentObjs.find(o => o.id === 'box-dieline-fill' || o.dielineRole === 'fill');
                            const foldObj = currentObjs.find(o => o.id === 'box-dieline-fold' || o.dielineRole === 'fold');
                            const cutObj = currentObjs.find(o => o.id === 'box-dieline-cut' || o.dielineRole === 'cut');
                            const labelObjs = currentObjs.filter(o => o.dielineRole === 'label' || (o.id && String(o.id).startsWith('box-dieline-label-')));

                            if (fillObj) canvas.sendToBack(fillObj);
                            if (foldObj) canvas.bringToFront(foldObj);
                            if (cutObj) canvas.bringToFront(cutObj);
                            labelObjs.forEach(l => canvas.bringToFront(l));

                            if (drawMeasurements && window.boxMeasurements) {
                                let cfg = (window.studioConfig && window.studioConfig.boxConfig) ? window.studioConfig.boxConfig : null;
                                if (!cfg) {
                                    const boxObj = canvas.getObjects().find(o => o.boxConfig);
                                    if (boxObj) cfg = boxObj.boxConfig;
                                }
                                if (!cfg && typeof templateJson === 'string') {
                                    try {
                                        const parsed = JSON.parse(templateJson);
                                        cfg = parsed.boxConfig || (parsed.objects ? parsed.objects.find(o => o.boxConfig)?.boxConfig : null);
                                    } catch (e) {}
                                } else if (!cfg && templateJson && typeof templateJson === 'object') {
                                    cfg = templateJson.boxConfig || (templateJson.objects ? templateJson.objects.find(o => o.boxConfig)?.boxConfig : null);
                                }

                                if (cfg) {
                                    window.boxMeasurements.renderMeasurementsOnCanvas(canvas, cfg);
                                }
                            } else if (!drawMeasurements && window.boxMeasurements) {
                                window.boxMeasurements.removeMeasurementsFromCanvas(canvas);
                            }
                        }

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

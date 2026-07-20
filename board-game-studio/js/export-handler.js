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
        if (pageSizeSelect) pageSizeSelect.addEventListener('change', checkTilingVisibility);
        if (orientationSelect) orientationSelect.addEventListener('change', checkTilingVisibility);

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
        const formatSelect = document.getElementById('export_format');
        if (!formatSelect || formatSelect.value !== 'pdf') return;

        const pageSize = document.getElementById('pdf_page_size').value;
        const orientation = document.getElementById('pdf_orientation').value;

        const pageDims = {
            a4: { w: 210, h: 297 },
            letter: { w: 215.9, h: 279.4 }
        };

        const selectedDims = pageDims[pageSize];
        const pageW = orientation === 'portrait' ? selectedDims.w : selectedDims.h;
        const pageH = orientation === 'portrait' ? selectedDims.h : selectedDims.w;

        const margin = 10;
        const availW = pageW - (margin * 2);
        const availH = pageH - (margin * 2);

        const cardW = window.studioConfig.widthMm;
        const cardH = window.studioConfig.heightMm;

        const tilingContainer = document.getElementById('pdf-tiling-container');
        if (tilingContainer) {
            if (cardW > availW || cardH > availH) {
                tilingContainer.classList.remove('hidden');
            } else {
                tilingContainer.classList.add('hidden');
            }
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
            templateJson = data.canvas_json;
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

    // Dynamic offscreen rendering loop
    function renderCards() {
        return new Promise((resolve, reject) => {
            const images = [];
            const rows = dataset ? dataset.rowData : [{}]; // If no dataset, render once
            let index = 0;

            function renderNext() {
                if (index >= rows.length) {
                    resolve(images);
                    return;
                }

                const percent = Math.round(25 + ((index / rows.length) * 50));
                updateProgress(`Rendering layer templates: Card ${index + 1} of ${rows.length}...`, percent);

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

                            // Substitute variables in text layers
                            if (obj.type === 'i-text' || obj.type === 'text') {
                                let rawText = obj.variable_binding || obj.text;
                                let subText = rawText;
                                
                                const matches = rawText ? rawText.match(/\{\{([a-zA-Z0-9_\-]+)\}\}/g) : null;
                                if (matches) {
                                    matches.forEach(placeholder => {
                                        const colName = placeholder.replace(/\{\{|\}\}/g, '');
                                        const replacement = row[colName] !== undefined ? row[colName] : placeholder;
                                        subText = subText.replaceAll(placeholder, replacement);
                                    });
                                } else if (obj.variable_binding) {
                                    const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '');
                                    if (row[colName] !== undefined) {
                                        subText = row[colName];
                                    }
                                }
                                obj.set('text', subText);
                            }

                            // Substitute image source for bound image layers
                            if (obj.type === 'image' && obj.variable_binding) {
                                const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '');
                                const filename = row[colName];

                                if (filename && window.assetPicker && typeof window.assetPicker.getAssetUrlByFilename === 'function') {
                                    const assetUrl = window.assetPicker.getAssetUrlByFilename(filename);
                                    if (assetUrl) {
                                        const swapPromise = new Promise((imgResolve) => {
                                            obj.setSrc(assetUrl, () => {
                                                obj.setCoords();
                                                imgResolve();
                                            }, { crossOrigin: 'anonymous' });
                                        });
                                        imageSwapPromises.push(swapPromise);
                                    }
                                }
                            }

                            // Shape / Generic object dataset visibility binding
                            if (obj.variable_binding && obj.type !== 'text' && obj.type !== 'i-text' && obj.type !== 'image') {
                                const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '');
                                const val = row[colName] !== undefined ? String(row[colName]).trim() : '';
                                if (val === 'transparent.png' || val === '0' || val === 'false' || val === 'none' || val === '' || val === 'hidden') {
                                    obj.set('opacity', 0);
                                    obj.set('visible', false);
                                } else {
                                    obj.set('opacity', 1);
                                    obj.set('visible', true);
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

            renderNext();
        });
    }

    // Export PDF Tiled Generation
    function generatePdf(cardImages) {
        return new Promise(async (resolve) => {
            updateProgress('Compiling Print-and-Play PDF sheets...', 80);

            const pageSize = document.getElementById('pdf_page_size').value;
            const orientation = document.getElementById('pdf_orientation').value;
            const drawCropMarks = document.getElementById('pdf_crop_marks').checked;

            // Dimensions in mm
            const pageDims = {
                a4: { w: 210, h: 297 },
                letter: { w: 215.9, h: 279.4 }
            };

            const selectedDims = pageDims[pageSize];
            const pageW = orientation === 'portrait' ? selectedDims.w : selectedDims.h;
            const pageH = orientation === 'portrait' ? selectedDims.h : selectedDims.w;

            // Card size in mm
            const cardW = window.studioConfig.widthMm;
            const cardH = window.studioConfig.heightMm;

            // Initialize PDF
            const pdf = new jsPDF({
                orientation: orientation,
                unit: 'mm',
                format: pageSize
            });

            // Calculate grid layout
            const margin = 10; // Page margin
            const gap = 2;    // Gap between cards
            
            // Available page dimensions
            const availW = pageW - (margin * 2);
            const availH = pageH - (margin * 2);

            let drawW = cardW;
            let drawH = cardH;
            let scaleFactor = 1.0;

            let cols = Math.floor((availW + gap) / (drawW + gap));
            let rows = Math.floor((availH + gap) / (drawH + gap));
            
            let splitCols = 1;
            let splitRows = 1;
            let isTiled = false;

            if (cols === 0 || rows === 0) {
                const tiling = document.getElementById('pdf_tiling').value;
                if (tiling !== 'fit') {
                    isTiled = true;
                    if (tiling === 'split_2') {
                        if (cardW >= cardH) {
                            splitCols = 2;
                            splitRows = 1;
                        } else {
                            splitCols = 1;
                            splitRows = 2;
                        }
                    } else if (tiling === 'split_3') {
                        if (cardW >= cardH) {
                            splitCols = 3;
                            splitRows = 1;
                        } else {
                            splitCols = 1;
                            splitRows = 3;
                        }
                    } else if (tiling === 'split_4') {
                        splitCols = 2;
                        splitRows = 2;
                    }
                }

                // Slices dimensions
                const pieceW = cardW / splitCols;
                const pieceH = cardH / splitRows;

                scaleFactor = Math.min(availW / pieceW, availH / pieceH);
                drawW = pieceW * scaleFactor;
                drawH = pieceH * scaleFactor;
                cols = 1;
                rows = 1;
            }

            const cardsPerPage = cols * rows;

            // Calculate starting offsets to center grid on page
            const gridW = (cols * drawW) + ((cols - 1) * gap);
            const gridH = (rows * drawH) + ((rows - 1) * gap);
            const startX = margin + ((availW - gridW) / 2);
            const startY = margin + ((availH - gridH) / 2);

            let pageIndex = 0;
            for (let index = 0; index < cardImages.length; index++) {
                const img = cardImages[index];

                if (!isTiled) {
                    if (index > 0 && index % cardsPerPage === 0) {
                        pdf.addPage();
                    }

                    const pageCardIndex = index % cardsPerPage;
                    const col = pageCardIndex % cols;
                    const row = Math.floor(pageCardIndex / cols);

                    const x = startX + (col * (drawW + gap));
                    const y = startY + (row * (drawH + gap));

                    // Draw card image
                    pdf.addImage(img.dataUrl, 'PNG', x, y, drawW, drawH);

                    // Draw Crop Marks
                    if (drawCropMarks) {
                        drawPageCropMarks(pdf, x, y, drawW, drawH);
                    }
                } else {
                    const sourceW = window.studioConfig.canvasWidth;
                    const sourceH = window.studioConfig.canvasHeight;
                    const chunkSourceW = sourceW / splitCols;
                    const chunkSourceH = sourceH / splitRows;

                    const htmlImg = await loadImage(img.dataUrl);

                    for (let r = 0; r < splitRows; r++) {
                        for (let c = 0; c < splitCols; c++) {
                            if (pageIndex > 0) {
                                pdf.addPage();
                            }
                            pageIndex++;

                            const x = margin + ((availW - drawW) / 2);
                            const y = margin + ((availH - drawH) / 2);

                            const chunkSourceX = c * chunkSourceW;
                            const chunkSourceY = r * chunkSourceH;

                            const tempCanvas = document.createElement('canvas');
                            tempCanvas.width = chunkSourceW;
                            tempCanvas.height = chunkSourceH;
                            const tempCtx = tempCanvas.getContext('2d');

                            tempCtx.drawImage(htmlImg, chunkSourceX, chunkSourceY, chunkSourceW, chunkSourceH, 0, 0, chunkSourceW, chunkSourceH);
                            const slicedDataUrl = tempCanvas.toDataURL('image/png');

                            pdf.addImage(slicedDataUrl, 'PNG', x, y, drawW, drawH);

                            if (drawCropMarks) {
                                drawPageCropMarks(pdf, x, y, drawW, drawH);
                            }

                            drawOverlapGuidelines(pdf, x, y, drawW, drawH, c, r, splitCols, splitRows);
                        }
                    }
                }
            }

            pdf.save(`${window.studioConfig.templateName.replace(/[^a-zA-Z0-9_\-]/g, '_')}_print_play.pdf`);
            resolve();
        });
    }

    // Export Tabletop Simulator Grid Sheets + JSON Zip Package
    function generateTtsSheet(cardImages) {
        return new Promise((resolve, reject) => {
            updateProgress('Compiling TTS texture sheets...', 80);

            let gridCols = parseInt(document.getElementById('tts_grid_cols').value) || 10;
            let gridRows = parseInt(document.getElementById('tts_grid_rows').value) || 7;
            
            // Automatically shrink grid if we have fewer cards to prevent massive empty black space
            const totalCards = cardImages.length;
            if (totalCards < gridCols * gridRows) {
                gridCols = Math.min(gridCols, totalCards);
                gridRows = Math.ceil(totalCards / gridCols);
            }

            const maxCardsPerSheet = gridCols * gridRows;

            const cardW = window.studioConfig.canvasWidth;
            const cardH = window.studioConfig.canvasHeight;

            const zip = new JSZip();
            const sheetsCount = Math.ceil(cardImages.length / maxCardsPerSheet);
            let sheetIndex = 0;

            function compileSheet() {
                if (sheetIndex >= sheetsCount) {
                    // Generate ZIP file for download
                    zip.generateAsync({ type: 'blob' })
                    .then(content => {
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(content);
                        link.download = `${window.studioConfig.templateName.replace(/[^a-zA-Z0-9_\-]/g, '_')}_tts_pack.zip`;
                        link.click();
                        resolve();
                    })
                    .catch(reject);
                    return;
                }

                updateProgress(`Building sprite sheet texture ${sheetIndex + 1} of ${sheetsCount}...`, 80 + Math.round((sheetIndex / sheetsCount) * 15));

                // Calculate original packed dimensions
                let packW = gridCols * cardW;
                let packH = gridRows * cardH;

                // Enforce Tabletop Simulator maximum texture size (8192x8192) to prevent browser memory crashes and broken PNGs
                const MAX_TEX_SIZE = 8192;
                let scaleFactor = 1.0;
                let drawW = cardW;
                let drawH = cardH;

                if (packW > MAX_TEX_SIZE || packH > MAX_TEX_SIZE) {
                    scaleFactor = Math.min(MAX_TEX_SIZE / packW, MAX_TEX_SIZE / packH);
                    drawW = Math.floor(cardW * scaleFactor);
                    drawH = Math.floor(cardH * scaleFactor);
                    packW = gridCols * drawW;
                    packH = gridRows * drawH;
                }

                // Create offscreen canvas for sprite packing
                const packCanvas = document.createElement('canvas');
                packCanvas.width = packW;
                packCanvas.height = packH;
                const ctx = packCanvas.getContext('2d');

                // Fill black background for TTS transparency support
                ctx.fillStyle = '#000000';
                ctx.fillRect(0, 0, packCanvas.width, packCanvas.height);

                const manifest = {
                    spriteSheet: `spritesheet_${sheetIndex + 1}.png`,
                    cardWidth: cardW,
                    cardHeight: cardH,
                    columns: gridCols,
                    rows: gridRows,
                    cards: []
                };

                const startIdx = sheetIndex * maxCardsPerSheet;
                const endIdx = Math.min(startIdx + maxCardsPerSheet, cardImages.length);
                let loadedCount = 0;

                for (let i = startIdx; i < endIdx; i++) {
                    const cardImg = cardImages[i];
                    const pageCardIdx = i - startIdx;
                    const col = pageCardIdx % gridCols;
                    const row = Math.floor(pageCardIdx / gridCols);

                    const x = col * drawW;
                    const y = row * drawH;

                    const htmlImg = new Image();
                    htmlImg.onload = function() {
                        ctx.drawImage(htmlImg, x, y, drawW, drawH);
                        
                        manifest.cards.push({
                            name: cardImg.name,
                            sheetIndex: pageCardIdx,
                            x: x,
                            y: y
                        });

                        loadedCount++;
                        if (loadedCount === (endIdx - startIdx)) {
                            // Convert sprite sheet to blob and add to ZIP
                            packCanvas.toBlob(blob => {
                                zip.file(`spritesheet_${sheetIndex + 1}.png`, blob);
                                zip.file(`manifest_${sheetIndex + 1}.json`, JSON.stringify(manifest, null, 2));

                                sheetIndex++;
                                compileSheet();
                            }, 'image/png');
                        }
                    };
                    htmlImg.src = cardImg.dataUrl;
                }
            }

            compileSheet();
        });
    }

    // Helper to update progress UI
    function updateProgress(actionText, percentValue) {
        document.getElementById('progress-action').textContent = actionText;
        document.getElementById('progress-percent').textContent = percentValue + '%';
        document.getElementById('progress-bar').style.width = percentValue + '%';
    }



    // Helper to load image as a Promise
    function loadImage(src) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = reject;
            img.src = src;
        });
    }

    // Helper to draw crop marks
    function drawPageCropMarks(pdf, x, y, w, h) {
        pdf.setDrawColor(180, 180, 180);
        pdf.setLineWidth(0.1);

        const markLen = 5; // Length of crop marks
        const offset = 2;  // Offset distance from card border

        // Top-Left corner
        pdf.line(x, y - offset, x, y - offset - markLen); // vertical
        pdf.line(x - offset, y, x - offset - markLen, y); // horizontal

        // Top-Right corner
        pdf.line(x + w, y - offset, x + w, y - offset - markLen); // vertical
        pdf.line(x + w + offset, y, x + w + offset + markLen, y); // horizontal

        // Bottom-Left corner
        pdf.line(x, y + h + offset, x, y + h + offset + markLen); // vertical
        pdf.line(x - offset, y + h, x - offset - markLen, y + h); // horizontal

        // Bottom-Right corner
        pdf.line(x + w, y + h + offset, x + w, y + h + offset + markLen); // vertical
        pdf.line(x + w + offset, y + h, x + w + offset + markLen, y + h); // horizontal
    }

    // Helper to draw alignment borders
    function drawOverlapGuidelines(pdf, x, y, w, h, col, row, totalCols, totalRows) {
        pdf.setDrawColor(200, 200, 200);
        pdf.setLineWidth(0.2);
        pdf.setLineDashPattern([2, 1], 0);

        pdf.setFontSize(6);
        pdf.setTextColor(150, 150, 150);

        // If adjacent left
        if (col > 0) {
            pdf.line(x, y, x, y + h);
            pdf.text("GLUE / TAPE LINE", x + 1.5, y + 10, { angle: 90 });
        }
        // If adjacent right
        if (col < totalCols - 1) {
            pdf.line(x + w, y, x + w, y + h);
            pdf.text("GLUE / TAPE LINE", x + w - 3.5, y + 10, { angle: 90 });
        }
        // If adjacent top
        if (row > 0) {
            pdf.line(x, y, x + w, y);
            pdf.text("GLUE / TAPE LINE", x + 10, y + 3);
        }
        // If adjacent bottom
        if (row < totalRows - 1) {
            pdf.line(x, y + h, x + w, y + h);
            pdf.text("GLUE / TAPE LINE", x + 10, y + h - 1.5);
        }

        pdf.setLineDashPattern([], 0);
    }

    document.addEventListener('DOMContentLoaded', () => {
        initExport();
    });
})();

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
        const formatSelect = document.getElementById('export_format');
        if (!formatSelect || formatSelect.value !== 'pdf') return;

        const pageSize = document.getElementById('pdf_page_size').value;
        const orientation = document.getElementById('pdf_orientation').value;
        const tilingSelect = document.getElementById('pdf_tiling');
        const selectedOption = tilingSelect ? tilingSelect.value : 'split_2';

        const pageDims = {
            a4: { w: 210, h: 297 },
            letter: { w: 215.9, h: 279.4 },
            a3: { w: 297, h: 420 }
        };

        const selectedDims = pageDims[pageSize] || pageDims.a4;
        const pageW = orientation === 'portrait' ? selectedDims.w : selectedDims.h;
        const pageH = orientation === 'portrait' ? selectedDims.h : selectedDims.w;

        const margin = 10;
        const availW = pageW - (margin * 2);
        const availH = pageH - (margin * 2);

        const cardW = window.studioConfig.widthMm || 297;
        const cardH = window.studioConfig.heightMm || 210;

        const scaleW = availW / cardW;
        const scaleH = availH / cardH;
        const fitScalePercent = Math.round(Math.min(scaleW, scaleH, 1) * 1000) / 10;

        const tilingContainer = document.getElementById('pdf-tiling-container');
        const warningBox = document.getElementById('pdf-tiling-warning');

        if (tilingContainer) {
            if (cardW > availW || cardH > availH) {
                tilingContainer.classList.remove('hidden');

                if (warningBox) {
                    if (selectedOption === 'actual_1page') {
                        warningBox.className = "p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-xs text-emerald-300 space-y-1";
                        warningBox.innerHTML = `
                            <div class="font-bold flex items-center gap-1.5 text-emerald-400">
                                <span>✅ 100% Actual 1:1 Physical Scale (1 Sheet)</span>
                            </div>
                            <p>Exports at <strong>100% 1:1 physical size</strong> on 1 single sheet of paper (full-bleed). When printing your PDF, select <strong>"Actual Size / 100%"</strong> in your printer dialog (or Borderless printing). Cut-out cards will match perfectly!</p>
                        `;
                    } else if (selectedOption === 'fit') {
                        warningBox.className = "p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-xs text-amber-300 space-y-1";
                        warningBox.innerHTML = `
                            <div class="font-bold flex items-center gap-1.5 text-amber-400">
                                <span>⚠️ Scaling Warning (${fitScalePercent}% Scale)</span>
                            </div>
                            <p>Scale to Fit will shrink your <strong>${cardW}x${cardH}mm</strong> component down to <strong>${fitScalePercent}%</strong> size to squeeze inside 10mm printer margins. Printed cut-out cards will be <strong>larger</strong> than board rectangles!</p>
                            <p class="text-[11px] text-amber-200/80 mt-1">👉 To preserve 100% 1:1 card size on 1 sheet, select <strong>"100% Actual Size — 1 Page (Full-Bleed)"</strong>.</p>
                        `;
                    } else {
                        warningBox.className = "p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-xs text-emerald-300 space-y-1";
                        warningBox.innerHTML = `
                            <div class="font-bold flex items-center gap-1.5 text-emerald-400">
                                <span>✅ 100% Actual 1:1 Physical Scale (Multi-Page Split)</span>
                            </div>
                            <p>Exports at <strong>100% 1:1 physical size</strong> split across multiple pages with 10mm printer margins. Cut-out cards will line up with board rectangles perfectly.</p>
                        `;
                    }
                }
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
            if (document.fonts && typeof document.fonts.load === 'function' && typeof templateJson === 'string') {
                const fontMatches = templateJson.match(/"fontFamily"\s*:\s*"([^"]+)"/g);
                if (fontMatches) {
                    const fontNames = [...new Set(fontMatches.map(m => m.split(':')[1].replace(/"/g, '').trim()))];
                    const fontPromises = fontNames.map(f => document.fonts.load(`16px "${f}"`).catch(() => {}));
                    return Promise.all(fontPromises);
                }
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

    // ponytail: parse inline color/format tags (<red>, <gold>, <color:#hex>, <b>, <i>, <u>) into FabricJS character styles for export
    function parseStyledText(rawText) {
        if (rawText === null || rawText === undefined) return { cleanText: '', styles: {} };
        rawText = String(rawText);

        const colorMap = {
            'red': '#ef4444',
            'gold': '#f59e0b',
            'yellow': '#eab308',
            'amber': '#d97706',
            'blue': '#3b82f6',
            'sky': '#0ea5e9',
            'indigo': '#6366f1',
            'green': '#22c55e',
            'emerald': '#10b981',
            'purple': '#a855f7',
            'violet': '#8b5cf6',
            'orange': '#f97316',
            'rose': '#f43f5e',
            'pink': '#ec4899',
            'white': '#ffffff',
            'black': '#000000',
            'cyan': '#06b6d4',
            'gray': '#9ca3af',
            'grey': '#9ca3af'
        };

        if (!rawText.includes('<')) {
            return { cleanText: rawText, styles: {} };
        }

        const lines = rawText.split('\n');
        const allStyles = {};
        const cleanLines = [];
        const tagRegex = /<\/?([a-zA-Z0-9_\-#:=]+)>/g;

        lines.forEach((line, lineIdx) => {
            let cleanLine = '';
            const lineStyles = {};
            const styleStack = [];

            function getEffectiveStyle() {
                const eff = {};
                styleStack.forEach(s => Object.assign(eff, s));
                return eff;
            }

            let lastIndex = 0;
            let match;
            tagRegex.lastIndex = 0;

            while ((match = tagRegex.exec(line)) !== null) {
                const textBefore = line.substring(lastIndex, match.index);
                const currentStyle = getEffectiveStyle();
                const hasStyle = Object.keys(currentStyle).length > 0;

                for (let i = 0; i < textBefore.length; i++) {
                    const charPos = cleanLine.length;
                    cleanLine += textBefore[i];
                    if (hasStyle) {
                        lineStyles[charPos] = { ...currentStyle };
                    }
                }

                const rawTag = match[1];
                const isClosing = match[0].startsWith('</');

                if (isClosing) {
                    styleStack.pop();
                } else {
                    const newStyle = {};
                    const lowerTag = rawTag.toLowerCase();

                    if (lowerTag.startsWith('color:') || lowerTag.startsWith('color=')) {
                        const colorVal = rawTag.substring(6).trim();
                        newStyle.fill = colorMap[colorVal.toLowerCase()] || colorVal;
                    } else if (colorMap[lowerTag]) {
                        newStyle.fill = colorMap[lowerTag];
                    } else if (lowerTag.startsWith('#') || lowerTag.startsWith('rgb')) {
                        newStyle.fill = rawTag;
                    } else if (lowerTag === 'b' || lowerTag === 'strong') {
                        newStyle.fontWeight = 'bold';
                    } else if (lowerTag === 'i' || lowerTag === 'em') {
                        newStyle.fontStyle = 'italic';
                    } else if (lowerTag === 'u') {
                        newStyle.underline = true;
                    }

                    if (Object.keys(newStyle).length > 0) {
                        styleStack.push(newStyle);
                    }
                }

                lastIndex = tagRegex.lastIndex;
            }

            const remainingText = line.substring(lastIndex);
            const remainingStyle = getEffectiveStyle();
            const hasRemainingStyle = Object.keys(remainingStyle).length > 0;

            for (let i = 0; i < remainingText.length; i++) {
                const charPos = cleanLine.length;
                cleanLine += remainingText[i];
                if (hasRemainingStyle) {
                    lineStyles[charPos] = { ...remainingStyle };
                }
            }

            cleanLines.push(cleanLine);
            if (Object.keys(lineStyles).length > 0) {
                allStyles[lineIdx] = lineStyles;
            }
        });

        return {
            cleanText: cleanLines.join('\n'),
            styles: allStyles
        };
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
                
                const parsed = parseStyledText(subText !== undefined && subText !== null ? subText : '');
                obj.set('styles', parsed.styles);
                obj.set('text', parsed.cleanText);
                if (typeof obj.initDimensions === 'function') {
                    obj.initDimensions();
                }
                obj.setCoords();
                obj.set('dirty', true);
            } else if (obj.type === 'image' && obj.variable_binding) {
                                // Substitute image source for bound image layers
                                const filename = getRowValue(row, obj.variable_binding);

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
                            } else if (obj.variable_binding) {
                                // Shape / Generic object dataset visibility or image binding
                                const rawVal = getRowValue(row, obj.variable_binding);
                                if (rawVal !== undefined && rawVal !== null) {
                                    const val = String(rawVal).trim();

                                    // Check if value is an image/SVG asset filename
                                    if (val && window.assetPicker && typeof window.assetPicker.getAssetUrlByFilename === 'function') {
                                        const assetUrl = window.assetPicker.getAssetUrlByFilename(val);
                                        if (assetUrl) {
                                            if (obj.setSrc && typeof obj.setSrc === 'function') {
                                                const swapPromise = new Promise((imgResolve) => {
                                                    obj.setSrc(assetUrl, () => {
                                                        obj.setCoords();
                                                        imgResolve();
                                                    }, { crossOrigin: 'anonymous' });
                                                });
                                                imageSwapPromises.push(swapPromise);
                                            } else {
                                                const swapPromise = new Promise((imgResolve) => {
                                                    fabric.Image.fromURL(assetUrl, (newImg) => {
                                                        if (!newImg) return imgResolve();
                                                        newImg.set({
                                                            left: obj.left,
                                                            top: obj.top,
                                                            originX: obj.originX || 'center',
                                                            originY: obj.originY || 'center',
                                                            scaleX: obj.scaleX,
                                                            scaleY: obj.scaleY,
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
                const tiling = document.getElementById('pdf_tiling') ? document.getElementById('pdf_tiling').value : 'fit';
                if (tiling === 'actual_1page') {
                    isTiled = false;
                    scaleFactor = 1.0;
                    drawW = cardW;
                    drawH = cardH;
                    cols = 1;
                    rows = 1;
                } else if (tiling !== 'fit') {
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
                    const pieceW = cardW / splitCols;
                    const pieceH = cardH / splitRows;
                    scaleFactor = Math.min(availW / pieceW, availH / pieceH);
                    drawW = pieceW * scaleFactor;
                    drawH = pieceH * scaleFactor;
                    cols = 1;
                    rows = 1;
                } else {
                    scaleFactor = Math.min(availW / cardW, availH / cardH);
                    drawW = cardW * scaleFactor;
                    drawH = cardH * scaleFactor;
                    cols = 1;
                    rows = 1;
                }
            }

            const cardsPerPage = cols * rows;

            // Calculate starting offsets to center grid on page
            const tilingContainer = document.getElementById('pdf-tiling-container');
            const isTilingVisible = tilingContainer && !tilingContainer.classList.contains('hidden');
            const tilingMode = (isTilingVisible && document.getElementById('pdf_tiling')) ? document.getElementById('pdf_tiling').value : 'fit';

            const gridW = (cols * drawW) + ((cols - 1) * gap);
            const gridH = (rows * drawH) + ((rows - 1) * gap);
            const startX = (isTilingVisible && tilingMode === 'actual_1page') ? (pageW - drawW) / 2 : margin + ((availW - gridW) / 2);
            const startY = (isTilingVisible && tilingMode === 'actual_1page') ? (pageH - drawH) / 2 : margin + ((availH - gridH) / 2);

            let pageIndex = 0;
            for (let index = 0; index < cardImages.length; index++) {
                const img = cardImages[index];

                if (!isTiled) {
                    if (index > 0 && index % cardsPerPage === 0) {
                        pdf.addPage(pageSize, orientation);
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
                        drawPageCropMarks(pdf, x, y, drawW, drawH, col, row, cols, rows, gap);
                    }
                } else {
                    const sourceW = window.studioConfig.canvasWidth;
                    const sourceH = window.studioConfig.heightMm;
                    const chunkSourceW = sourceW / splitCols;
                    const chunkSourceH = sourceH / splitRows;

                    const htmlImg = await loadImage(img.dataUrl);

                    for (let r = 0; r < splitRows; r++) {
                        for (let c = 0; c < splitCols; c++) {
                            if (pageIndex > 0) {
                                pdf.addPage(pageSize, orientation);
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

    // Helper to draw crop marks without overlapping neighboring cards
    function drawPageCropMarks(pdf, x, y, w, h, col = 0, row = 0, totalCols = 1, totalRows = 1, gap = 2) {
        pdf.setDrawColor(160, 160, 160);
        pdf.setLineWidth(0.15);

        const markLen = 4;   // Length of crop marks into margin
        const offset = 1.5;  // Offset distance from card border for outer marks

        const isLeftEdge = (col === 0);
        const isRightEdge = (col === totalCols - 1);
        const isTopEdge = (row === 0);
        const isBottomEdge = (row === totalRows - 1);

        // Fill distance for internal gaps (never cross into neighbor card)
        const gapFill = Math.min(markLen, Math.max(0.5, gap / 2));

        // --- TOP-LEFT CORNER ---
        // Vertical line (pointing UP)
        if (isTopEdge) {
            pdf.line(x, y - offset, x, y - offset - markLen);
        } else {
            pdf.line(x, y, x, y - gapFill);
        }
        // Horizontal line (pointing LEFT)
        if (isLeftEdge) {
            pdf.line(x - offset, y, x - offset - markLen, y);
        } else {
            pdf.line(x, y, x - gapFill, y);
        }

        // --- TOP-RIGHT CORNER ---
        // Vertical line (pointing UP)
        if (isTopEdge) {
            pdf.line(x + w, y - offset, x + w, y - offset - markLen);
        } else {
            pdf.line(x + w, y, x + w, y - gapFill);
        }
        // Horizontal line (pointing RIGHT)
        if (isRightEdge) {
            pdf.line(x + w + offset, y, x + w + offset + markLen, y);
        } else {
            pdf.line(x + w, y, x + w + gapFill, y);
        }

        // --- BOTTOM-LEFT CORNER ---
        // Vertical line (pointing DOWN)
        if (isBottomEdge) {
            pdf.line(x, y + h + offset, x, y + h + offset + markLen);
        } else {
            pdf.line(x, y + h, x, y + h + gapFill);
        }
        // Horizontal line (pointing LEFT)
        if (isLeftEdge) {
            pdf.line(x - offset, y + h, x - offset - markLen, y + h);
        } else {
            pdf.line(x, y + h, x - gapFill, y + h);
        }

        // --- BOTTOM-RIGHT CORNER ---
        // Vertical line (pointing DOWN)
        if (isBottomEdge) {
            pdf.line(x + w, y + h + offset, x + w, y + h + offset + markLen);
        } else {
            pdf.line(x + w, y + h, x + w, y + h + gapFill);
        }
        // Horizontal line (pointing RIGHT)
        if (isRightEdge) {
            pdf.line(x + w + offset, y + h, x + w + offset + markLen, y + h);
        } else {
            pdf.line(x + w, y + h, x + w + gapFill, y + h);
        }
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initExport);
    } else {
        initExport();
    }
})();

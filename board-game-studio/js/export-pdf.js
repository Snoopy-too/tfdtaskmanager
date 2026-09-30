/**
 * PDF Export Generator for Board Game Studio
 * Handles tiled PDF compilation, page layout calculations, crop marks, and overlap guidelines.
 */
(function() {
    'use strict';

    const { jsPDF } = window.jspdf || {};

    // Helper to load image as a Promise
    function loadImage(src) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = reject;
            img.src = src;
        });
    }

    // Helper to rotate image 90 degrees if portrait card placed in landscape cell
    function rotateImage90(imgDataUrl) {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const tempCanvas = document.createElement('canvas');
                tempCanvas.width = img.height;
                tempCanvas.height = img.width;
                const ctx = tempCanvas.getContext('2d', { alpha: false });
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
                ctx.translate(tempCanvas.width / 2, tempCanvas.height / 2);
                ctx.rotate((90 * Math.PI) / 180);
                ctx.drawImage(img, -img.width / 2, -img.height / 2);
                resolve(tempCanvas.toDataURL('image/jpeg', 0.92));
            };
            img.onerror = () => resolve(imgDataUrl);
            img.src = imgDataUrl;
        });
    }

    // Flatten PNG dataUrl onto opaque white print background and encode as compact 300 DPI JPEG
    function toPrintJpegDataUrl(imgDataUrl) {
        if (typeof imgDataUrl === 'string' && imgDataUrl.startsWith('data:image/jpeg')) {
            return Promise.resolve(imgDataUrl);
        }
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const w = img.naturalWidth || img.width || 1;
                const h = img.naturalHeight || img.height || 1;
                const tempCanvas = document.createElement('canvas');
                tempCanvas.width = w;
                tempCanvas.height = h;
                const ctx = tempCanvas.getContext('2d', { alpha: false });
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);
                resolve(tempCanvas.toDataURL('image/jpeg', 0.92));
            };
            img.onerror = () => resolve(imgDataUrl);
            img.src = imgDataUrl;
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

        // Fill distance for internal gaps (never cross into neighbor card; skip if gap=0)
        const gapFill = gap > 0 ? Math.min(markLen, Math.max(0.5, gap / 2)) : 0;

        // --- TOP-LEFT CORNER ---
        if (isTopEdge) {
            pdf.line(x, y - offset, x, y - offset - markLen);
        } else if (gapFill > 0) {
            pdf.line(x, y, x, y - gapFill);
        }
        if (isLeftEdge) {
            pdf.line(x - offset, y, x - offset - markLen, y);
        } else if (gapFill > 0) {
            pdf.line(x, y, x - gapFill, y);
        }

        // --- TOP-RIGHT CORNER ---
        if (isTopEdge) {
            pdf.line(x + w, y - offset, x + w, y - offset - markLen);
        } else if (gapFill > 0) {
            pdf.line(x + w, y, x + w, y - gapFill);
        }
        if (isRightEdge) {
            pdf.line(x + w + offset, y, x + w + offset + markLen, y);
        } else if (gapFill > 0) {
            pdf.line(x + w, y, x + w + gapFill, y);
        }

        // --- BOTTOM-LEFT CORNER ---
        if (isBottomEdge) {
            pdf.line(x, y + h + offset, x, y + h + offset + markLen);
        } else if (gapFill > 0) {
            pdf.line(x, y + h, x, y + h + gapFill);
        }
        if (isLeftEdge) {
            pdf.line(x - offset, y + h, x - offset - markLen, y + h);
        } else if (gapFill > 0) {
            pdf.line(x, y + h, x - gapFill, y + h);
        }

        // --- BOTTOM-RIGHT CORNER ---
        if (isBottomEdge) {
            pdf.line(x + w, y + h + offset, x + w, y + h + offset + markLen);
        } else if (gapFill > 0) {
            pdf.line(x + w, y + h, x + w, y + h + gapFill);
        }
        if (isRightEdge) {
            pdf.line(x + w + offset, y + h, x + w + offset + markLen, y + h);
        } else if (gapFill > 0) {
            pdf.line(x + w, y + h, x + w + gapFill, y + h);
        }
    }

    // Helper to resolve multi-sheet split grid (cols x rows) for a given tiling mode
    function resolveSplitGrid(tilingMode, cardW, cardH) {
        let splitCols = 1;
        let splitRows = 1;
        if (tilingMode === 'split_2') {
            if (cardW >= cardH) {
                splitCols = 2;
                splitRows = 1;
            } else {
                splitCols = 1;
                splitRows = 2;
            }
        } else if (tilingMode === 'split_3') {
            if (cardW >= cardH) {
                splitCols = 3;
                splitRows = 1;
            } else {
                splitCols = 1;
                splitRows = 3;
            }
        } else if (tilingMode === 'split_4') {
            splitCols = 2;
            splitRows = 2;
        } else if (tilingMode === 'split_6') {
            if (cardW >= cardH) {
                splitCols = 3;
                splitRows = 2;
            } else {
                splitCols = 2;
                splitRows = 3;
            }
        } else if (tilingMode === 'split_9') {
            splitCols = 3;
            splitRows = 3;
        }
        return { splitCols, splitRows };
    }

    // Helper to draw alignment registration crosshairs at shared seam corners
    function drawRegistrationCrosshair(pdf, cx, cy) {
        const r = 1.6;
        const arm = 3.0;
        pdf.setDrawColor(80, 80, 80);
        pdf.setLineWidth(0.2);
        pdf.setLineDashPattern([], 0);
        pdf.circle(cx, cy, r, 'S');
        pdf.line(cx - arm, cy, cx + arm, cy);
        pdf.line(cx, cy - arm, cx, cy + arm);
    }

    // Helper to draw alignment borders, registration crosshairs, and sheet assembly labels
    function drawOverlapGuidelines(pdf, x, y, w, h, col, row, totalCols, totalRows, sheetNum, totalSheets) {
        pdf.setDrawColor(140, 140, 140);
        pdf.setLineWidth(0.22);
        pdf.setLineDashPattern([2, 1], 0);

        pdf.setFontSize(6);
        pdf.setTextColor(110, 110, 110);

        if (col > 0) {
            pdf.line(x, y, x, y + h);
            pdf.text("TRIM / TAPE SEAM (JOIN LEFT)", x + 1.8, y + Math.min(14, h / 2), { angle: 90 });
        }
        if (col < totalCols - 1) {
            pdf.line(x + w, y, x + w, y + h);
            pdf.text("TRIM / TAPE SEAM (JOIN RIGHT)", x + w - 3.2, y + Math.min(14, h / 2), { angle: 90 });
        }
        if (row > 0) {
            pdf.line(x, y, x + w, y);
            pdf.text("TRIM / TAPE SEAM (JOIN TOP)", x + Math.min(10, w / 4), y + 2.8);
        }
        if (row < totalRows - 1) {
            pdf.line(x, y + h, x + w, y + h);
            pdf.text("TRIM / TAPE SEAM (JOIN BOTTOM)", x + Math.min(10, w / 4), y + h - 1.5);
        }

        pdf.setLineDashPattern([], 0);

        // Draw corner registration crosshairs on shared interior seams
        if (col > 0 || row > 0) {
            drawRegistrationCrosshair(pdf, x, y);
        }
        if (col < totalCols - 1 || row > 0) {
            drawRegistrationCrosshair(pdf, x + w, y);
        }
        if (col > 0 || row < totalRows - 1) {
            drawRegistrationCrosshair(pdf, x, y + h);
        }
        if (col < totalCols - 1 || row < totalRows - 1) {
            drawRegistrationCrosshair(pdf, x + w, y + h);
        }

        // Draw sheet assembly identification header
        if (sheetNum && totalSheets) {
            pdf.setFontSize(6.5);
            pdf.setTextColor(90, 90, 90);
            const labelY = Math.max(4.5, y - 2.5);
            const labelText = `Sheet ${sheetNum} of ${totalSheets} [Row ${row + 1}/${totalRows}, Col ${col + 1}/${totalCols}]  •  100% 1:1 Scale  •  Align crosshairs & dashed seams to assemble`;
            pdf.text(labelText, Math.max(6, x), labelY);
        }
    }

    // Export PDF Tiled Generation
    function generatePdf(cardImages, updateProgress) {
        return new Promise(async (resolve, reject) => {
            try {
                if (typeof updateProgress === 'function') {
                    updateProgress('Compiling Print-and-Play PDF sheets...', 80);
                }

                const pageSize = document.getElementById('pdf_page_size').value;
                const orientation = document.getElementById('pdf_orientation') ? document.getElementById('pdf_orientation').value : 'portrait';
                const drawCropMarks = document.getElementById('pdf_crop_marks').checked;

                const isF10A4 = (pageSize === 'f10a4_1');
                const is51215 = (pageSize === 'a_one_51215');
                const isPrecutSheet = isF10A4 || is51215;
                const pdfFormat = isPrecutSheet ? 'a4' : pageSize;
                let pdfOrientation = isPrecutSheet ? 'portrait' : orientation;

                const pageDims = {
                    a4: { w: 210, h: 297 },
                    a5: { w: 148, h: 210 },
                    a3: { w: 297, h: 420 },
                    letter: { w: 215.9, h: 279.4 }
                };

                const selectedDims = pageDims[pdfFormat] || pageDims.a4;
                let pageW = pdfOrientation === 'portrait' ? selectedDims.w : selectedDims.h;
                let pageH = pdfOrientation === 'portrait' ? selectedDims.h : selectedDims.w;

                const cardW = window.studioConfig.widthMm;
                const cardH = window.studioConfig.heightMm;

                let margin = 10;
                let gap = 2;
                let drawW = cardW;
                let drawH = cardH;
                let cols = 1;
                let rows = 1;
                let startX = 0;
                let startY = 0;
                let splitCols = 1;
                let splitRows = 1;
                let isTiled = false;
                let availW = pageW - (margin * 2);
                let availH = pageH - (margin * 2);

                const tilingContainer = document.getElementById('pdf-tiling-container');
                const isTilingVisible = tilingContainer && !tilingContainer.classList.contains('hidden');
                const tilingMode = (isTilingVisible && document.getElementById('pdf_tiling')) ? document.getElementById('pdf_tiling').value : 'fit';

                if (isF10A4) {
                    // A-one F10A4-1 fixed standard layout (10 cards: 2x5 grid, 91x55mm, 14mm sides, 11mm top/bottom, 0mm gap)
                    cols = 2;
                    rows = 5;
                    drawW = 91;
                    drawH = 55;
                    startX = 14;
                    startY = 11;
                    gap = 0;
                } else if (is51215) {
                    // A-one 51215 / F8A4-5 layout (8 cards: 2x4 grid, 97x69mm, 8mm sides, 10.5mm top/bottom, 0mm gap)
                    cols = 2;
                    rows = 4;
                    drawW = 97;
                    drawH = 69;
                    startX = 8;
                    startY = 10.5;
                    gap = 0;
                } else {
                    let scaleFactor = 1.0;

                    cols = Math.floor((availW + gap) / (drawW + gap));
                    rows = Math.floor((availH + gap) / (drawH + gap));

                    // Apply multi-sheet or single-sheet tiling if the component is larger than 1 page OR if the user explicitly chose a multi-sheet split
                    if (cols === 0 || rows === 0 || (isTilingVisible && tilingMode && tilingMode.startsWith('split_'))) {
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
                            const grid = resolveSplitGrid(tiling, cardW, cardH);
                            splitCols = grid.splitCols;
                            splitRows = grid.splitRows;
                            const pieceW = cardW / splitCols;
                            const pieceH = cardH / splitRows;
                            scaleFactor = 1.0;
                            drawW = pieceW;
                            drawH = pieceH;
                            cols = 1;
                            rows = 1;

                            // Auto-orient the sheet if the split tile fits cleanly in the alternate orientation
                            const fitsCurrent = (drawW <= pageW - 8) && (drawH <= pageH - 8);
                            const fitsRotated = (drawW <= pageH - 8) && (drawH <= pageW - 8);
                            if (!fitsCurrent && fitsRotated) {
                                pdfOrientation = (pdfOrientation === 'portrait') ? 'landscape' : 'portrait';
                                pageW = pdfOrientation === 'portrait' ? selectedDims.w : selectedDims.h;
                                pageH = pdfOrientation === 'portrait' ? selectedDims.h : selectedDims.w;
                                availW = pageW - (margin * 2);
                                availH = pageH - (margin * 2);
                            }
                        } else {
                            scaleFactor = Math.min(availW / cardW, availH / cardH);
                            drawW = cardW * scaleFactor;
                            drawH = cardH * scaleFactor;
                            cols = 1;
                            rows = 1;
                        }
                    }

                    const gridW = (cols * drawW) + ((cols - 1) * gap);
                    const gridH = (rows * drawH) + ((rows - 1) * gap);
                    startX = (isTilingVisible && tilingMode === 'actual_1page') ? (pageW - drawW) / 2 : margin + ((availW - gridW) / 2);
                    startY = (isTilingVisible && tilingMode === 'actual_1page') ? (pageH - drawH) / 2 : margin + ((availH - gridH) / 2);
                }

                const pdf = new jsPDF({
                    orientation: pdfOrientation,
                    unit: 'mm',
                    format: pdfFormat,
                    compress: true
                });

                const cardsPerPage = cols * rows;

                let pageIndex = 0;
                for (let index = 0; index < cardImages.length; index++) {
                    const img = cardImages[index];

                    if (!isTiled) {
                        if (index > 0 && index % cardsPerPage === 0) {
                            pdf.addPage(pdfFormat, pdfOrientation);
                        }

                        const pageCardIndex = index % cardsPerPage;
                        const col = pageCardIndex % cols;
                        const row = Math.floor(pageCardIndex / cols);

                        const x = startX + (col * (drawW + gap));
                        const y = startY + (row * (drawH + gap));

                        let cardDataUrl = img.dataUrl;
                        // ponytail: auto-rotate 90° if portrait card (e.g. 55x91mm or 69x97mm) is placed on horizontal pre-cut slot
                        if (isPrecutSheet && cardW < cardH) {
                            cardDataUrl = await rotateImage90(img.dataUrl);
                        } else {
                            cardDataUrl = await toPrintJpegDataUrl(img.dataUrl);
                        }

                        const imgFormat = cardDataUrl.startsWith('data:image/jpeg') ? 'JPEG' : 'PNG';
                        pdf.addImage(cardDataUrl, imgFormat, x, y, drawW, drawH, undefined, 'FAST');

                        if (drawCropMarks) {
                            drawPageCropMarks(pdf, x, y, drawW, drawH, col, row, cols, rows, gap);
                        }
                    } else {
                        const htmlImg = await loadImage(img.dataUrl);
                        const sourceW = htmlImg.naturalWidth || htmlImg.width || window.studioConfig.canvasWidth;
                        const sourceH = htmlImg.naturalHeight || htmlImg.height || window.studioConfig.canvasHeight;
                        const chunkSourceW = sourceW / splitCols;
                        const chunkSourceH = sourceH / splitRows;
                        const totalSheets = splitCols * splitRows;
                        let sheetCounter = 0;

                        for (let r = 0; r < splitRows; r++) {
                            for (let c = 0; c < splitCols; c++) {
                                if (pageIndex > 0) {
                                    pdf.addPage(pdfFormat, pdfOrientation);
                                }
                                pageIndex++;
                                sheetCounter++;

                                const x = (pageW - drawW) / 2;
                                const y = (pageH - drawH) / 2;

                                const chunkSourceX = c * chunkSourceW;
                                const chunkSourceY = r * chunkSourceH;

                                const tileW = Math.max(1, Math.round(chunkSourceW));
                                const tileH = Math.max(1, Math.round(chunkSourceH));

                                const tempCanvas = document.createElement('canvas');
                                tempCanvas.width = tileW;
                                tempCanvas.height = tileH;
                                const tempCtx = tempCanvas.getContext('2d', { alpha: false });

                                tempCtx.fillStyle = '#ffffff';
                                tempCtx.fillRect(0, 0, tileW, tileH);
                                tempCtx.drawImage(htmlImg, chunkSourceX, chunkSourceY, chunkSourceW, chunkSourceH, 0, 0, tileW, tileH);
                                const slicedDataUrl = tempCanvas.toDataURL('image/jpeg', 0.92);

                                pdf.addImage(slicedDataUrl, 'JPEG', x, y, drawW, drawH, undefined, 'FAST');

                                if (drawCropMarks) {
                                    drawPageCropMarks(pdf, x, y, drawW, drawH);
                                }

                                drawOverlapGuidelines(pdf, x, y, drawW, drawH, c, r, splitCols, splitRows, sheetCounter, totalSheets);
                            }
                        }
                    }
                }

                const printModeEl = document.getElementById('pdf_print_mode');
                const suffix = (printModeEl && printModeEl.value === 'cutout_stencil') ? '_box_stencil' : '_print_play';
                pdf.save(`${window.studioConfig.templateName.replace(/[^a-zA-Z0-9_\-]/g, '_')}${suffix}.pdf`);
                resolve();
            } catch (err) {
                reject(err);
            }
        });
    }

    function checkTilingVisibility() {
        const formatSelect = document.getElementById('export_format');
        if (!formatSelect || formatSelect.value !== 'pdf') return;

        const pageSize = document.getElementById('pdf_page_size').value;
        const orientation = document.getElementById('pdf_orientation') ? document.getElementById('pdf_orientation').value : 'portrait';
        const tilingSelect = document.getElementById('pdf_tiling');
        const selectedOption = tilingSelect ? tilingSelect.value : 'split_2';
        const printModeEl = document.getElementById('pdf_print_mode');
        const isStencilMode = printModeEl && printModeEl.value === 'cutout_stencil';

        const f10Badge = document.getElementById('f10a4-info-badge');
        const f8Badge = document.getElementById('f8a4-info-badge');
        const orientContainer = document.getElementById('pdf-orientation-container');
        const isPrecut = (pageSize === 'f10a4_1' || pageSize === 'a_one_51215');

        if (f10Badge) {
            if (pageSize === 'f10a4_1') f10Badge.classList.remove('hidden');
            else f10Badge.classList.add('hidden');
        }
        if (f8Badge) {
            if (pageSize === 'a_one_51215') f8Badge.classList.remove('hidden');
            else f8Badge.classList.add('hidden');
        }
        if (orientContainer) {
            if (isPrecut) orientContainer.classList.add('hidden');
            else orientContainer.classList.remove('hidden');
        }

        const tilingContainer = document.getElementById('pdf-tiling-container');
        const warningBox = document.getElementById('pdf-tiling-warning');

        if (isPrecut) {
            if (tilingContainer) tilingContainer.classList.add('hidden');
            return;
        }

        const pageDims = {
            a4: { w: 210, h: 297 },
            a5: { w: 148, h: 210 },
            a3: { w: 297, h: 420 },
            letter: { w: 215.9, h: 279.4 }
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

        if (tilingContainer) {
            if (cardW > availW || cardH > availH || isStencilMode) {
                tilingContainer.classList.remove('hidden');

                if (warningBox) {
                    if (selectedOption === 'actual_1page') {
                        const exceedsSheet = (cardW > pageW || cardH > pageH);
                        if (exceedsSheet) {
                            warningBox.className = "p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-xs text-amber-300 space-y-1";
                            warningBox.innerHTML = `
                                <div class="font-bold flex items-center gap-1.5 text-amber-400">
                                    <span>⚠️ Template (${cardW}×${cardH} mm) Exceeds 1 ${pageSize.toUpperCase()} Sheet (${pageW}×${pageH} mm)</span>
                                </div>
                                <p>Your unfolded template is larger than a single ${pageSize.toUpperCase()} sheet and will clip at the edges. Select <strong>"2 Sheets"</strong> or <strong>"4 Sheets (2×2 Grid)"</strong> above to print across multiple sheets and assemble them!</p>
                            `;
                        } else {
                            warningBox.className = "p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-xs text-emerald-300 space-y-1";
                            warningBox.innerHTML = `
                                <div class="font-bold flex items-center gap-1.5 text-emerald-400">
                                    <span>✅ 100% Actual 1:1 Physical Scale (1 Sheet)</span>
                                </div>
                                <p>Exports at <strong>100% 1:1 physical size</strong> on 1 single ${pageSize.toUpperCase()} sheet. When printing your PDF, select <strong>"Actual Size / 100%"</strong> in your printer dialog.</p>
                            `;
                        }
                    } else if (selectedOption === 'fit') {
                        warningBox.className = "p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-xs text-amber-300 space-y-1";
                        warningBox.innerHTML = `
                            <div class="font-bold flex items-center gap-1.5 text-amber-400">
                                <span>⚠️ Scaling Warning (${fitScalePercent}% Scale)</span>
                            </div>
                            <p>Scale to Fit will shrink your <strong>${cardW}×${cardH} mm</strong> template down to <strong>${fitScalePercent}%</strong> size to squeeze inside 10mm printer margins. Do not use if you need 1:1 physical box dimensions!</p>
                            <p class="text-[11px] text-amber-200/80 mt-1">👉 To preserve 100% 1:1 physical box dimensions, select <strong>"2 Sheets"</strong> or <strong>"4 Sheets (2×2 Grid)"</strong>.</p>
                        `;
                    } else {
                        const grid = resolveSplitGrid(selectedOption, cardW, cardH);
                        const totalSheets = grid.splitCols * grid.splitRows;
                        const pieceW = Math.round((cardW / grid.splitCols) * 10) / 10;
                        const pieceH = Math.round((cardH / grid.splitRows) * 10) / 10;
                        const fitsSheet = (pieceW <= selectedDims.w && pieceH <= selectedDims.h) || (pieceW <= selectedDims.h && pieceH <= selectedDims.w);

                        if (!fitsSheet) {
                            warningBox.className = "p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-xs text-amber-300 space-y-1";
                            warningBox.innerHTML = `
                                <div class="font-bold flex items-center gap-1.5 text-amber-400">
                                    <span>⚠️ Tile Size (${pieceW}×${pieceH} mm) Exceeds ${pageSize.toUpperCase()} Sheet (${selectedDims.w}×${selectedDims.h} mm)</span>
                                </div>
                                <p>Splitting <strong>${cardW}×${cardH} mm</strong> into ${totalSheets} sheets (${grid.splitCols}×${grid.splitRows}) creates ${pieceW}×${pieceH} mm tiles, which are still larger than a single ${pageSize.toUpperCase()} sheet. Choose <strong>4 Sheets</strong>, <strong>6 Sheets</strong>, or <strong>9 Sheets</strong> so every part fits without clipping.</p>
                            `;
                        } else {
                            warningBox.className = "p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-xs text-emerald-300 space-y-1";
                            warningBox.innerHTML = `
                                <div class="font-bold flex items-center gap-1.5 text-emerald-400">
                                    <span>✅ 100% Actual 1:1 Physical Scale (${totalSheets} Sheets — ${grid.splitCols}×${grid.splitRows} Assembly Grid)</span>
                                </div>
                                <p>Splits your <strong>${cardW}×${cardH} mm</strong> template into <strong>${totalSheets} sheets</strong> (${pieceW}×${pieceH} mm per sheet) at exact <strong>100% 1:1 physical size</strong> with registration crosshairs and dashed join lines for easy taping and cardboard cutout.</p>
                            `;
                        }
                    }
                }
            } else {
                tilingContainer.classList.add('hidden');
            }
        }
    }

    window.exportPdf = {
        generatePdf,
        checkTilingVisibility,
        drawPageCropMarks,
        drawOverlapGuidelines,
        loadImage
    };
})();

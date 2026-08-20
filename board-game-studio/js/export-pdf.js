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
        if (isTopEdge) {
            pdf.line(x, y - offset, x, y - offset - markLen);
        } else {
            pdf.line(x, y, x, y - gapFill);
        }
        if (isLeftEdge) {
            pdf.line(x - offset, y, x - offset - markLen, y);
        } else {
            pdf.line(x, y, x - gapFill, y);
        }

        // --- TOP-RIGHT CORNER ---
        if (isTopEdge) {
            pdf.line(x + w, y - offset, x + w, y - offset - markLen);
        } else {
            pdf.line(x + w, y, x + w, y - gapFill);
        }
        if (isRightEdge) {
            pdf.line(x + w + offset, y, x + w + offset + markLen, y);
        } else {
            pdf.line(x + w, y, x + w + gapFill, y);
        }

        // --- BOTTOM-LEFT CORNER ---
        if (isBottomEdge) {
            pdf.line(x, y + h + offset, x, y + h + offset + markLen);
        } else {
            pdf.line(x, y + h, x, y + h + gapFill);
        }
        if (isLeftEdge) {
            pdf.line(x - offset, y + h, x - offset - markLen, y + h);
        } else {
            pdf.line(x, y + h, x - gapFill, y + h);
        }

        // --- BOTTOM-RIGHT CORNER ---
        if (isBottomEdge) {
            pdf.line(x + w, y + h + offset, x + w, y + h + offset + markLen);
        } else {
            pdf.line(x + w, y + h, x + w, y + h + gapFill);
        }
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

        if (col > 0) {
            pdf.line(x, y, x, y + h);
            pdf.text("GLUE / TAPE LINE", x + 1.5, y + 10, { angle: 90 });
        }
        if (col < totalCols - 1) {
            pdf.line(x + w, y, x + w, y + h);
            pdf.text("GLUE / TAPE LINE", x + w - 3.5, y + 10, { angle: 90 });
        }
        if (row > 0) {
            pdf.line(x, y, x + w, y);
            pdf.text("GLUE / TAPE LINE", x + 10, y + 3);
        }
        if (row < totalRows - 1) {
            pdf.line(x, y + h, x + w, y + h);
            pdf.text("GLUE / TAPE LINE", x + 10, y + h - 1.5);
        }

        pdf.setLineDashPattern([], 0);
    }

    // Export PDF Tiled Generation
    function generatePdf(cardImages, updateProgress) {
        return new Promise(async (resolve) => {
            if (typeof updateProgress === 'function') {
                updateProgress('Compiling Print-and-Play PDF sheets...', 80);
            }

            const pageSize = document.getElementById('pdf_page_size').value;
            const orientation = document.getElementById('pdf_orientation').value;
            const drawCropMarks = document.getElementById('pdf_crop_marks').checked;

            const pageDims = {
                a4: { w: 210, h: 297 },
                letter: { w: 215.9, h: 279.4 }
            };

            const selectedDims = pageDims[pageSize] || pageDims.a4;
            const pageW = orientation === 'portrait' ? selectedDims.w : selectedDims.h;
            const pageH = orientation === 'portrait' ? selectedDims.h : selectedDims.w;

            const cardW = window.studioConfig.widthMm;
            const cardH = window.studioConfig.heightMm;

            const pdf = new jsPDF({
                orientation: orientation,
                unit: 'mm',
                format: pageSize
            });

            const margin = 10;
            const gap = 2;
            
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

                    pdf.addImage(img.dataUrl, 'PNG', x, y, drawW, drawH);

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

    window.exportPdf = {
        generatePdf,
        checkTilingVisibility,
        drawPageCropMarks,
        drawOverlapGuidelines,
        loadImage
    };
})();

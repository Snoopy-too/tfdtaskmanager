/**
 * Tabletop Simulator Export Generator for Board Game Studio
 * Generates sprite sheet textures and JSON deck manifests packed into a downloadable ZIP archive.
 */
(function() {
    'use strict';

    function generateTtsSheet(cardImages, updateProgress) {
        return new Promise((resolve, reject) => {
            if (typeof updateProgress === 'function') {
                updateProgress('Compiling TTS texture sheets...', 80);
            }

            let gridCols = parseInt(document.getElementById('tts_grid_cols').value) || 10;
            let gridRows = parseInt(document.getElementById('tts_grid_rows').value) || 7;
            
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

                if (typeof updateProgress === 'function') {
                    updateProgress(`Building sprite sheet texture ${sheetIndex + 1} of ${sheetsCount}...`, 80 + Math.round((sheetIndex / sheetsCount) * 15));
                }

                let packW = gridCols * cardW;
                let packH = gridRows * cardH;

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

                const packCanvas = document.createElement('canvas');
                packCanvas.width = packW;
                packCanvas.height = packH;
                const ctx = packCanvas.getContext('2d');

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

    window.exportTts = {
        generateTtsSheet
    };
})();

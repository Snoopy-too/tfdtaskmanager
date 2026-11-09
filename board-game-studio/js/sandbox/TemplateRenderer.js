/**
 * Board Game Studio Playtest Sandbox Template Renderer.
 * Dynamically converts BGS Fabric.js design templates into high-fidelity image data URLs
 * for game boards, player mats, cards, and custom tokens.
 */
(function () {
    'use strict';

    const renderedCache = new Map();
    const inFlightPromises = new Map();

    function getAssetMap() {
        return (
            (window.sandboxBootstrap &&
                window.sandboxBootstrap.projectAssets &&
                window.sandboxBootstrap.projectAssets.importedAssets &&
                window.sandboxBootstrap.projectAssets.importedAssets.assetMap) ||
            {}
        );
    }

    function resolveAssetFilename(rawVal) {
        if (!rawVal) return null;
        const clean = String(rawVal).replace(/^\[|\]$/g, '').trim();
        if (!clean) return null;
        if (clean.startsWith('http') || clean.startsWith('../') || clean.startsWith('icons/') || clean.startsWith('uploads/')) {
            return clean;
        }
        const map = getAssetMap();
        const lower = clean.toLowerCase();
        if (map[lower]) return map[lower];
        const noExt = lower.replace(/\.[a-z0-9]+$/i, '');
        return map[noExt] || null;
    }

    function getRowValue(row, rawBinding) {
        if (!row || !rawBinding) return undefined;
        const colName = String(rawBinding).replace(/\{\{|\}\}/g, '').trim();
        if (!colName) return undefined;
        if (row[colName] !== undefined) return row[colName];
        const lowerCol = colName.toLowerCase();
        const matchKey = Object.keys(row).find((k) => k.toLowerCase().trim() === lowerCol);
        return matchKey !== undefined ? row[matchKey] : undefined;
    }

    /**
     * Renders a Fabric.js template JSON string into a crisp image Data URL.
     */
    function renderTemplateAsync(templateId, canvasJson, rowData = null, nativeWidth = 1000, nativeHeight = 700, cacheKeyOverride = null) {
        if (!canvasJson) {
            return Promise.resolve(null);
        }

        const cacheKey = cacheKeyOverride || `tpl_${templateId}_${rowData ? JSON.stringify(rowData) : 'base'}`;
        if (renderedCache.has(cacheKey)) {
            return Promise.resolve(renderedCache.get(cacheKey));
        }

        if (inFlightPromises.has(cacheKey)) {
            return inFlightPromises.get(cacheKey);
        }

        const renderPromise = new Promise((resolve) => {
            if (typeof fabric === 'undefined') {
                console.warn('[SandboxTemplateRenderer] Fabric.js is not loaded.');
                resolve(null);
                return;
            }

            let parsed;
            try {
                if (typeof canvasJson === 'string') {
                    const normalized = canvasJson.replaceAll('"alphabetical"', '"alphabetic"');
                    parsed = JSON.parse(normalized);
                } else if (typeof canvasJson === 'object') {
                    parsed = JSON.parse(JSON.stringify(canvasJson).replaceAll('"alphabetical"', '"alphabetic"'));
                }
            } catch (err) {
                console.warn('[SandboxTemplateRenderer] Failed to parse canvasJson:', err);
                resolve(null);
                return;
            }

            if (!parsed) {
                resolve(null);
                return;
            }

            const origW = Math.max(80, Number(nativeWidth) || Number(parsed.width) || 1000);
            const origH = Math.max(80, Number(nativeHeight) || Number(parsed.height) || 700);
            const maxDim = Math.max(origW, origH);
            const scale = Math.min(1.0, 1200 / maxDim);

            const hiddenCanvasEl = document.createElement('canvas');
            hiddenCanvasEl.width = origW;
            hiddenCanvasEl.height = origH;

            const staticCanvas = new fabric.StaticCanvas(hiddenCanvasEl, {
                width: origW,
                height: origH,
                backgroundColor: parsed.background || parsed.backgroundColor || '#ffffff',
                preserveObjectStacking: true,
                renderOnAddRemove: false
            });

            staticCanvas.loadFromJSON(parsed, () => {
                const toRemove = [];
                const imageSwapPromises = [];

                function walkObjects(objList) {
                    (objList || []).forEach((obj) => {
                        const id = String(obj.id || '');
                        if (id === 'safe-zone-guide' || id === 'bleed-zone-guide' || id.startsWith('box-dieline-') || obj.isBoxDieline) {
                            toRemove.push(obj);
                            return;
                        }

                        if (obj.type === 'group' && typeof obj.getObjects === 'function') {
                            walkObjects(obj.getObjects());
                        }

                        if (!rowData || typeof rowData !== 'object') return;

                        if (obj.type === 'i-text' || obj.type === 'text' || obj.type === 'textbox') {
                            let rawText = obj.variable_binding || obj.text || '';
                            let subText = String(rawText);
                            const matches = subText.match(/\{\{([a-zA-Z0-9_\- ]+)\}\}/g);
                            if (matches) {
                                matches.forEach((ph) => {
                                    const val = getRowValue(rowData, ph);
                                    if (val !== undefined && val !== null) {
                                        subText = subText.replaceAll(ph, String(val));
                                    }
                                });
                            } else if (obj.variable_binding) {
                                const val = getRowValue(rowData, obj.variable_binding);
                                if (val !== undefined && val !== null) {
                                    subText = String(val);
                                }
                            }
                            // Strip inline style tags like [b], [/b], [color=...] for clean canvas text
                            subText = subText.replace(/\[\/?[a-zA-Z0-9_=#\s]+\]/g, '');
                            obj.set('text', subText);
                            if (typeof obj.initDimensions === 'function') obj.initDimensions();
                            obj.dirty = true;
                        } else if (obj.type === 'image' && obj.variable_binding) {
                            const rawVal = getRowValue(rowData, obj.variable_binding);
                            const resolvedUrl = resolveAssetFilename(rawVal);
                            if (resolvedUrl && typeof obj.setSrc === 'function') {
                                const targetW = (obj.width || 0) * (obj.scaleX !== undefined ? obj.scaleX : 1);
                                const targetH = (obj.height || 0) * (obj.scaleY !== undefined ? obj.scaleY : 1);
                                imageSwapPromises.push(
                                    new Promise((imgResolve) => {
                                        obj.setSrc(
                                            resolvedUrl,
                                            () => {
                                                if (targetW > 0 && obj.width > 0) obj.set('scaleX', targetW / obj.width);
                                                if (targetH > 0 && obj.height > 0) obj.set('scaleY', targetH / obj.height);
                                                obj.setCoords();
                                                imgResolve();
                                            },
                                            { crossOrigin: 'anonymous' }
                                        );
                                    })
                                );
                            }
                        }
                    });
                }

                walkObjects(staticCanvas.getObjects());
                toRemove.forEach((obj) => staticCanvas.remove(obj));

                Promise.all(imageSwapPromises).then(() => {
                    staticCanvas.renderAll();

                    let dataUrl = null;
                    try {
                        dataUrl = staticCanvas.toDataURL({
                            format: 'jpeg',
                            quality: 0.86,
                            multiplier: scale
                        });
                    } catch (e) {
                        console.warn('[SandboxTemplateRenderer] toDataURL error:', e);
                    }

                    staticCanvas.dispose();
                    hiddenCanvasEl.remove();

                    if (dataUrl) {
                        renderedCache.set(cacheKey, dataUrl);
                        if (!rowData && window.sandboxBootstrap && window.sandboxBootstrap.csrfToken) {
                            cacheThumbnailOnServer(templateId, dataUrl);
                        }
                    }

                    inFlightPromises.delete(cacheKey);
                    resolve(dataUrl);
                });
            });
        });

        inFlightPromises.set(cacheKey, renderPromise);
        return renderPromise;
    }

    function cacheThumbnailOnServer(templateId, dataUrl) {
        if (!templateId || !dataUrl || !window.sandboxBootstrap) return;
        try {
            const formData = new FormData();
            formData.append('csrf_token', window.sandboxBootstrap.csrfToken);
            formData.append('template_id', String(templateId));
            formData.append('thumbnail', dataUrl);

            fetch('api.php?action=cache_template_thumbnail', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': window.sandboxBootstrap.csrfToken }
            })
                .then((r) => r.json())
                .then((res) => {
                    if (res && res.success && res.url && window.sandboxActiveStore) {
                        const state = window.sandboxActiveStore.getState();
                        Object.values(state.boardObjects || {}).forEach((obj) => {
                            if (obj && obj.type === 'BOARD' && obj.assetId === `tpl_board_${templateId}` && !obj.imageUrl) {
                                obj.imageUrl = res.url;
                            }
                        });
                    }
                })
                .catch(() => {});
        } catch (_) {}
    }

    function findBoardDef(board) {
        const assets = (window.sandboxBootstrap && window.sandboxBootstrap.projectAssets && window.sandboxBootstrap.projectAssets.importedAssets) || {};
        const bList = assets.boards || [];
        return bList.find(
            (b) =>
                (board.assetId && b.assetId === board.assetId) ||
                (board.templateId && b.templateId === board.templateId) ||
                (board.name && b.name === board.name && b.canvasJson)
        );
    }

    /**
     * Synchronously returns the best available URL for a board, or triggers async render and calls onReady(url).
     */
    function resolveBoardImage(board, onReady) {
        if (board.imageUrl && !board.imageUrl.startsWith('tpl_')) {
            return board.imageUrl;
        }
        const def = findBoardDef(board);
        const tplId = board.templateId || (def && def.templateId) || (board.assetId && board.assetId.startsWith('tpl_board_') ? Number(board.assetId.replace('tpl_board_', '')) : null);
        if (!tplId) return null;

        if (def && def.imageUrl && !def.imageUrl.startsWith('data:')) {
            return def.imageUrl;
        }

        const cacheKey = `tpl_${tplId}_base`;
        if (renderedCache.has(cacheKey)) {
            return renderedCache.get(cacheKey);
        }

        const canvasJson = board.canvasJson || (def && def.canvasJson);
        if (!canvasJson) return null;

        const nativeW = board.canvasWidth || (def && def.canvasWidth) || 1200;
        const nativeH = board.canvasHeight || (def && def.canvasHeight) || 800;

        renderTemplateAsync(tplId, canvasJson, null, nativeW, nativeH, cacheKey).then((dataUrl) => {
            if (dataUrl && typeof onReady === 'function') {
                onReady(dataUrl);
            }
        });

        return null;
    }

    /**
     * Synchronously returns the best available URL for a card, or triggers async render and calls onReady(url).
     */
    function resolveCardImage(card, onReady) {
        if (card.frontImageUrl && !card.frontImageUrl.startsWith('tpl_')) {
            return card.frontImageUrl;
        }

        const assets = (window.sandboxBootstrap && window.sandboxBootstrap.projectAssets && window.sandboxBootstrap.projectAssets.importedAssets) || {};
        const batches = assets.cardBatches || [];
        let matchedBatch = null;
        let matchedCardDef = null;

        for (let i = 0; i < batches.length; i++) {
            const b = batches[i];
            if (card.templateId && b.templateId === card.templateId) {
                matchedBatch = b;
            }
            const cDef = (b.cards || []).find((c) => c.cardAssetId === card.cardAssetId);
            if (cDef) {
                matchedBatch = b;
                matchedCardDef = cDef;
                break;
            }
        }

        if (matchedCardDef && matchedCardDef.frontImageUrl) {
            return matchedCardDef.frontImageUrl;
        }

        const tplId = card.templateId || (matchedBatch && matchedBatch.templateId);
        if (!tplId) return null;

        const cacheKey = `card_${card.cardAssetId || tplId}`;
        if (renderedCache.has(cacheKey)) {
            return renderedCache.get(cacheKey);
        }

        const canvasJson = card.canvasJson || (matchedBatch && matchedBatch.canvasJson);
        if (!canvasJson) return null;

        const rowData = card.rowData || (matchedCardDef && matchedCardDef.rowData) || card.metadata || null;
        const nativeW = (matchedBatch && matchedBatch.canvasWidth) || 744;
        const nativeH = (matchedBatch && matchedBatch.canvasHeight) || 1039;

        renderTemplateAsync(tplId, canvasJson, rowData, nativeW, nativeH, cacheKey).then((dataUrl) => {
            if (dataUrl && typeof onReady === 'function') {
                onReady(dataUrl);
            }
        });

        return null;
    }

    function preloadProjectTemplates(bootstrap, store) {
        window.sandboxActiveStore = store;
        if (!bootstrap || !bootstrap.projectAssets) return;
        const assets = bootstrap.projectAssets.importedAssets || {};
        const boards = assets.boards || [];

        boards.forEach((b) => {
            if (b.templateId && b.canvasJson && !b.imageUrl) {
                renderTemplateAsync(b.templateId, b.canvasJson, null, b.canvasWidth || 1200, b.canvasHeight || 800, `tpl_${b.templateId}_base`).then((url) => {
                    if (url) {
                        store.notify(true);
                    }
                });
            }
        });
    }

    window.SandboxTemplateRenderer = {
        renderTemplateAsync,
        resolveBoardImage,
        resolveCardImage,
        preloadProjectTemplates,
        renderedCache
    };
})();

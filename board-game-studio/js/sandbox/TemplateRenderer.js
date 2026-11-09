/**
 * Board Game Studio Playtest Sandbox Template Renderer.
 * Dynamically converts BGS Fabric.js design templates into high-fidelity image data URLs
 * for game boards, player mats, cards, and custom tokens.
 */
(function () {
    'use strict';

    const renderedCache = new Map();
    const inFlightPromises = new Map();

    /**
     * Renders a Fabric.js template JSON string into a crisp image Data URL.
     */
    function renderTemplateAsync(templateId, canvasJson, rowData = null, targetWidth = 1000, targetHeight = 700) {
        if (!canvasJson) {
            return Promise.resolve(null);
        }

        const cacheKey = `tpl_${templateId}_${rowData ? JSON.stringify(rowData) : 'base'}`;
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

            // Determine dimensions from template or fallback
            const origW = parsed.width || targetWidth || 1000;
            const origH = parsed.height || targetHeight || 700;
            const maxDim = Math.max(origW, origH);
            const scale = Math.min(1.0, 1400 / maxDim);
            const scaledW = Math.max(80, Math.round(origW * scale));
            const scaledH = Math.max(80, Math.round(origH * scale));

            const hiddenCanvasEl = document.createElement('canvas');
            hiddenCanvasEl.width = scaledW;
            hiddenCanvasEl.height = scaledH;

            const staticCanvas = new fabric.StaticCanvas(hiddenCanvasEl, {
                width: scaledW,
                height: scaledH,
                backgroundColor: parsed.background || parsed.backgroundColor || '#ffffff',
                renderOnAddRemove: false
            });

            staticCanvas.setZoom(scale);

            staticCanvas.loadFromJSON(parsed, () => {
                const objects = staticCanvas.getObjects();
                const toRemove = [];

                objects.forEach((obj) => {
                    const id = String(obj.id || '');
                    // Strip editor guide overlays
                    if (id === 'safe-zone-guide' || id === 'bleed-zone-guide' || id.startsWith('box-dieline-') || obj.isBoxDieline) {
                        toRemove.push(obj);
                        return;
                    }

                    // Apply row data bindings if card template is bound to a dataset
                    if (rowData && typeof rowData === 'object') {
                        if (obj.type === 'i-text' || obj.type === 'text' || obj.type === 'textbox') {
                            let text = obj.text || '';
                            Object.entries(rowData).forEach(([col, val]) => {
                                if (val !== null && val !== undefined) {
                                    const regex = new RegExp(`\\{\\{\\s*${col}\\s*\\}\\}`, 'gi');
                                    text = text.replace(regex, String(val));
                                }
                            });
                            obj.set('text', text);
                        }
                    }
                });

                toRemove.forEach((obj) => staticCanvas.remove(obj));

                // Render the complete canvas
                staticCanvas.renderAll();

                let dataUrl = null;
                try {
                    dataUrl = staticCanvas.toDataURL({
                        format: 'jpeg',
                        quality: 0.88,
                        multiplier: 1
                    });
                } catch (e) {
                    console.warn('[SandboxTemplateRenderer] toDataURL error (tainted canvas):', e);
                }

                // Clean up static canvas instance
                staticCanvas.dispose();
                hiddenCanvasEl.remove();

                if (dataUrl) {
                    renderedCache.set(cacheKey, dataUrl);
                    // Persist thumbnail to server cache in background
                    if (!rowData && window.sandboxBootstrap && window.sandboxBootstrap.csrfToken) {
                        cacheThumbnailOnServer(templateId, dataUrl);
                    }
                }

                inFlightPromises.delete(cacheKey);
                resolve(dataUrl);
            });
        });

        inFlightPromises.set(cacheKey, renderPromise);
        return renderPromise;
    }

    /**
     * Non-blocking background POST to cache pre-rendered template thumbnail on disk.
     */
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
            }).catch(() => {});
        } catch (_) {}
    }

    /**
     * Ensures a spawned Board has its complete artwork rendered.
     */
    function getOrRenderBoard(board, store) {
        if (board.imageUrl && !board.imageUrl.startsWith('tpl_')) {
            return;
        }

        const tplId = board.templateId || (board.assetId && board.assetId.startsWith('tpl_board_') ? Number(board.assetId.replace('tpl_board_', '')) : null);
        if (!tplId) return;

        let canvasJson = board.canvasJson;
        if (!canvasJson && window.sandboxBootstrap && window.sandboxBootstrap.projectAssets) {
            const bList = window.sandboxBootstrap.projectAssets.importedAssets.boards || [];
            const match = bList.find((b) => b.templateId === tplId || b.assetId === `tpl_board_${tplId}`);
            if (match) {
                canvasJson = match.canvasJson;
                if (!board.canvasWidth && match.canvasWidth) board.canvasWidth = match.canvasWidth;
                if (!board.canvasHeight && match.canvasHeight) board.canvasHeight = match.canvasHeight;
            }
        }

        if (!canvasJson) return;

        const w = board.canvasWidth || board.width || 1200;
        const h = board.canvasHeight || board.height || 800;

        renderTemplateAsync(tplId, canvasJson, null, w, h).then((dataUrl) => {
            if (dataUrl && board.imageUrl !== dataUrl) {
                board.imageUrl = dataUrl;
                store.notify();
            }
        });
    }

    /**
     * Ensures a spawned Card has its complete artwork rendered.
     */
    function getOrRenderCard(card, store) {
        if (card.frontImageUrl && !card.frontImageUrl.startsWith('tpl_')) {
            return;
        }

        const tplId = card.templateId;
        if (!tplId) return;

        let canvasJson = card.canvasJson;
        if (!canvasJson && window.sandboxBootstrap && window.sandboxBootstrap.projectAssets) {
            const batches = window.sandboxBootstrap.projectAssets.importedAssets.cardBatches || [];
            const match = batches.find((b) => b.templateId === tplId || b.batchId === `cb_${tplId}`);
            if (match) canvasJson = match.canvasJson;
        }

        if (!canvasJson) return;

        renderTemplateAsync(tplId, canvasJson, card.rowData || null, card.width || 140, card.height || 200).then((dataUrl) => {
            if (dataUrl && card.frontImageUrl !== dataUrl) {
                card.frontImageUrl = dataUrl;
                store.notify();
            }
        });
    }

    /**
     * Pre-renders all project design templates in background on sandbox startup.
     */
    function preloadProjectTemplates(bootstrap, store) {
        if (!bootstrap || !bootstrap.projectAssets) return;
        const assets = bootstrap.projectAssets.importedAssets || {};
        const boards = assets.boards || [];
        const decks = assets.cardBatches || [];

        boards.forEach((b) => {
            if (b.templateId && b.canvasJson && !b.imageUrl) {
                renderTemplateAsync(b.templateId, b.canvasJson, null, b.canvasWidth || 1200, b.canvasHeight || 800).then((url) => {
                    if (url) {
                        b.imageUrl = url;
                        // Also update any board already placed on table
                        const state = store.getState();
                        Object.values(state.boardObjects || {}).forEach((obj) => {
                            if (obj && obj.type === 'BOARD' && obj.templateId === b.templateId && !obj.imageUrl) {
                                obj.imageUrl = url;
                            }
                        });
                        store.notify();
                    }
                });
            }
        });

        decks.forEach((d) => {
            if (d.templateId && d.canvasJson) {
                renderTemplateAsync(d.templateId, d.canvasJson, null, 140, 200).then((url) => {
                    if (url) {
                        d.previewImageUrl = url;
                        store.notify(true);
                    }
                });
            }
        });
    }

    window.SandboxTemplateRenderer = {
        renderTemplateAsync,
        getOrRenderBoard,
        getOrRenderCard,
        preloadProjectTemplates,
        renderedCache
    };
})();

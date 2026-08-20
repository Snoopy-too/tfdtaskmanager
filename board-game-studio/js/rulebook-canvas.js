/**
 * Rulebook Headless Canvas & Diagram Item Picker Module
 * Uses FabricJS offscreen canvas to render component thumbnails and handles diagram placement.
 */
(function() {
    'use strict';

    const renderedTemplateCache = {};

    function renderTemplateToImage(templateId, rowIndex, callback) {
        if (typeof rowIndex === 'function') {
            callback = rowIndex;
            rowIndex = null;
        }

        const cacheKey = `${templateId}_${rowIndex !== null && rowIndex !== undefined ? rowIndex : 'default'}`;
        if (renderedTemplateCache[cacheKey]) {
            callback(renderedTemplateCache[cacheKey]);
            return;
        }

        fetch(`api.php?action=load_canvas&template_id=${templateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.dataset_id && rowIndex !== null && rowIndex !== undefined) {
                fetch(`api.php?action=get_dataset&dataset_id=${data.dataset_id}`)
                .then(r => r.json())
                .then(dataset => {
                    const row = dataset && dataset.rowData ? dataset.rowData[rowIndex] : null;
                    const assetMap = window.rulebookGetAssetMap ? window.rulebookGetAssetMap() : {};
                    const substitutedData = window.rulebookParser
                        ? window.rulebookParser.substituteCanvasJson(data.canvas_json, row, assetMap)
                        : data.canvas_json;
                    renderCanvasData(substitutedData, data.width, data.height);
                })
                .catch(err => {
                    console.error('Failed to substitute dataset values:', err);
                    renderCanvasData(data.canvas_json, data.width, data.height);
                });
            } else {
                renderCanvasData(data.canvas_json, data.width, data.height);
            }

            function renderCanvasData(canvasData, width, height) {
                const canvasEl = document.createElement('canvas');
                canvasEl.width = width || 300;
                canvasEl.height = height || 400;

                const fCanvas = new fabric.Canvas(canvasEl, { enableRetinaScaling: false });
                fCanvas.loadFromJSON(canvasData, () => {
                    const fontPromises = [];
                    function collectFonts(objects) {
                        if (!objects || !Array.isArray(objects)) return;
                        objects.forEach(obj => {
                            if (obj.type === 'group' && typeof obj.getObjects === 'function') {
                                collectFonts(obj.getObjects());
                            }
                            if ((obj.type === 'i-text' || obj.type === 'text') && obj.fontFamily && document.fonts) {
                                fontPromises.push(
                                    document.fonts.load(`1em "${obj.fontFamily}"`).catch(() => {})
                                );
                            }
                        });
                    }
                    collectFonts(fCanvas.getObjects());

                    const fontCheck = document.fonts ? document.fonts.ready.catch(() => {}) : Promise.resolve();

                    Promise.all([fontCheck, ...fontPromises]).then(() => {
                        if (typeof fabric !== 'undefined') {
                            fabric.charWidthsCache = {};
                            if (fabric.util) fabric.util.charWidthsCache = {};
                        }

                        function refreshObjects(objects) {
                            if (!objects || !Array.isArray(objects)) return;
                            objects.forEach(obj => {
                                if (obj.type === 'group' && typeof obj.getObjects === 'function') {
                                    refreshObjects(obj.getObjects());
                                    if (typeof obj.addWithUpdate === 'function') obj.addWithUpdate();
                                    else if (typeof obj._calcBounds === 'function') obj._calcBounds();
                                    obj.setCoords();
                                } else if (obj.type === 'i-text' || obj.type === 'text') {
                                    obj.dirty = true;
                                    if (typeof obj.initDimensions === 'function') obj.initDimensions();
                                    obj.setCoords();
                                }
                            });
                        }

                        refreshObjects(fCanvas.getObjects());
                        fCanvas.renderAll();

                        const dataUrl = fCanvas.toDataURL({ format: 'png' });
                        renderedTemplateCache[cacheKey] = dataUrl;
                        fCanvas.dispose();
                        callback(dataUrl);
                    });
                });
            }
        })
        .catch(err => {
            console.error('Failed to parse dynamic template preview:', err);
            callback('');
        });
    }

    let activeBlockIndexForPicker = null;

    function initDiagramItemPicker() {
        const selectTemplate = document.getElementById('diagram-select-template');
        const selectRow = document.getElementById('diagram-select-row');
        const rowSelectContainer = document.getElementById('diagram-row-select-container');

        if (selectTemplate && selectRow && rowSelectContainer) {
            selectTemplate.addEventListener('change', () => {
                const templateId = selectTemplate.value;
                if (!templateId) return;

                selectRow.innerHTML = '<option value="">Loading cards...</option>';
                rowSelectContainer.classList.remove('hidden');

                fetch(`api.php?action=load_canvas&template_id=${templateId}`)
                .then(r => r.json())
                .then(details => {
                    if (details.dataset_id) {
                        fetch(`api.php?action=get_dataset&dataset_id=${details.dataset_id}`)
                        .then(r => r.json())
                        .then(dataset => {
                            selectRow.innerHTML = '';
                            if (dataset && dataset.rowData && dataset.rowData.length > 0) {
                                dataset.rowData.forEach((row, idx) => {
                                    let displayName = '';
                                    const possibleKeys = ['name', 'title', 'id', 'card', 'character', 'label'];
                                    for (const key of possibleKeys) {
                                        if (row[key] !== undefined && row[key] !== null) {
                                            displayName = row[key].toString();
                                            break;
                                        }
                                    }
                                    if (!displayName) {
                                        const values = Object.values(row);
                                        displayName = values[0] ? values[0].toString() : `Card Row #${idx + 1}`;
                                    }
                                    const opt = document.createElement('option');
                                    opt.value = idx;
                                    opt.textContent = `${idx + 1}: ${displayName}`;
                                    selectRow.appendChild(opt);
                                });
                                rowSelectContainer.classList.remove('hidden');
                            } else {
                                selectRow.innerHTML = '<option value="">Default Template Design</option>';
                                rowSelectContainer.classList.add('hidden');
                            }
                        })
                        .catch(() => {
                            selectRow.innerHTML = '<option value="">Default Template Design</option>';
                            rowSelectContainer.classList.add('hidden');
                        });
                    } else {
                        selectRow.innerHTML = '<option value="">Default Template Design</option>';
                        rowSelectContainer.classList.add('hidden');
                    }
                })
                .catch(() => {
                    selectRow.innerHTML = '<option value="">Default Template Design</option>';
                    rowSelectContainer.classList.add('hidden');
                });
            });
        }

        const btnAddDiagram = document.getElementById('btn-add-to-diagram');
        if (btnAddDiagram) {
            btnAddDiagram.addEventListener('click', () => {
                if (activeBlockIndexForPicker === null) return;
                
                const templateId = parseInt(document.getElementById('diagram-select-template').value);
                const scale = parseFloat(document.getElementById('diagram-item-scale').value);
                const rotation = parseInt(document.getElementById('diagram-item-rotation').value);

                const selRow = document.getElementById('diagram-select-row');
                const rowCont = document.getElementById('diagram-row-select-container');
                let rowIndex = null;
                if (rowCont && !rowCont.classList.contains('hidden') && selRow.value !== '') {
                    rowIndex = parseInt(selRow.value);
                }

                if (window.addDiagramElement) {
                    window.addDiagramElement(activeBlockIndexForPicker, {
                        template_id: templateId,
                        row_index: rowIndex,
                        x: 100,
                        y: 100,
                        scale: scale,
                        rotation: rotation
                    });
                }

                closeDiagramPicker();
            });
        }
    }

    function openDiagramPicker(blockIdx) {
        activeBlockIndexForPicker = blockIdx;
        const picker = document.getElementById('diagram-item-picker');
        if (picker) picker.classList.remove('hidden');
        const selectTemplate = document.getElementById('diagram-select-template');
        if (selectTemplate) selectTemplate.dispatchEvent(new Event('change'));
    }

    function closeDiagramPicker() {
        const picker = document.getElementById('diagram-item-picker');
        if (picker) picker.classList.add('hidden');
        activeBlockIndexForPicker = null;
    }

    function setupDragEvents() {
        let draggingElement = null;
        let dragStartX = 0;
        let dragStartY = 0;
        let elementStartX = 0;
        let elementStartY = 0;

        document.addEventListener('pointerdown', (e) => {
            if (e.target.closest('button')) return;
            const target = e.target.closest('[data-element-index]');
            if (!target || (window.rulebookIsPreviewMode && window.rulebookIsPreviewMode())) return;

            draggingElement = target;
            const blockIdx = parseInt(target.dataset.blockIndex);
            const elIdx = parseInt(target.dataset.elementIndex);

            const blocks = window.rulebookGetBlocks ? window.rulebookGetBlocks() : [];
            const el = blocks[blockIdx] && blocks[blockIdx].elements ? blocks[blockIdx].elements[elIdx] : null;
            if (!el) return;

            dragStartX = e.clientX;
            dragStartY = e.clientY;
            elementStartX = el.x;
            elementStartY = el.y;

            target.setPointerCapture(e.pointerId);
            e.preventDefault();
        });

        document.addEventListener('pointermove', (e) => {
            if (!draggingElement) return;

            const blockIdx = parseInt(draggingElement.dataset.blockIndex);
            const elIdx = parseInt(draggingElement.dataset.elementIndex);
            const blocks = window.rulebookGetBlocks ? window.rulebookGetBlocks() : [];
            const el = blocks[blockIdx] && blocks[blockIdx].elements ? blocks[blockIdx].elements[elIdx] : null;
            if (!el) return;

            const dx = e.clientX - dragStartX;
            const dy = e.clientY - dragStartY;

            el.x = Math.max(20, Math.min(780, Math.round(elementStartX + dx)));
            el.y = Math.max(20, Math.min(480, Math.round(elementStartY + dy)));

            draggingElement.style.left = `${el.x}px`;
            draggingElement.style.top = `${el.y}px`;
        });

        document.addEventListener('pointerup', (e) => {
            if (!draggingElement) return;
            draggingElement.releasePointerCapture(e.pointerId);
            draggingElement = null;
            if (window.saveRulebook) window.saveRulebook(true);
        });
    }

    window.rotateElement = function(blockIdx, elIdx) {
        const blocks = window.rulebookGetBlocks ? window.rulebookGetBlocks() : [];
        const el = blocks[blockIdx] && blocks[blockIdx].elements ? blocks[blockIdx].elements[elIdx] : null;
        if (el) {
            el.rotation = ((el.rotation || 0) + 45) % 360;
            if (window.rulebookRenderBlocks) window.rulebookRenderBlocks();
            if (window.saveRulebook) window.saveRulebook(true);
        }
    };

    window.scaleElementUp = function(blockIdx, elIdx) {
        const blocks = window.rulebookGetBlocks ? window.rulebookGetBlocks() : [];
        const el = blocks[blockIdx] && blocks[blockIdx].elements ? blocks[blockIdx].elements[elIdx] : null;
        if (el) {
            el.scale = Math.min((el.scale || 1.0) + 0.1, 5.0);
            if (window.rulebookRenderBlocks) window.rulebookRenderBlocks();
            if (window.saveRulebook) window.saveRulebook(true);
        }
    };

    window.scaleElementDown = function(blockIdx, elIdx) {
        const blocks = window.rulebookGetBlocks ? window.rulebookGetBlocks() : [];
        const el = blocks[blockIdx] && blocks[blockIdx].elements ? blocks[blockIdx].elements[elIdx] : null;
        if (el) {
            el.scale = Math.max((el.scale || 1.0) - 0.1, 0.4);
            if (window.rulebookRenderBlocks) window.rulebookRenderBlocks();
            if (window.saveRulebook) window.saveRulebook(true);
        }
    };

    window.deleteElement = function(blockIdx, elIdx) {
        const blocks = window.rulebookGetBlocks ? window.rulebookGetBlocks() : [];
        if (blocks[blockIdx] && blocks[blockIdx].elements) {
            blocks[blockIdx].elements.splice(elIdx, 1);
            if (window.rulebookRenderBlocks) window.rulebookRenderBlocks();
            if (window.saveRulebook) window.saveRulebook(true);
        }
    };

    window.openDiagramPicker = openDiagramPicker;
    window.closeDiagramPicker = closeDiagramPicker;

    window.rulebookCanvas = {
        renderTemplateToImage,
        initDiagramItemPicker,
        openDiagramPicker,
        closeDiagramPicker,
        setupDragEvents
    };
})();

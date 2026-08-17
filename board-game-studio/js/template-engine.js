/**
 * Template Engine Module
 * Manages dataset row rendering, variable bindings, and dynamic live previews.
 */
(function() {
    'use strict';

    let dataset = null;
    let currentRowIndex = 0;
    let activeRowIndices = [];
    
    // Original template strings stored to prevent loss on row changes
    const textTemplates = new Map();

    // Original image sources stored to restore when binding is removed
    const imageOriginalSrcs = new Map();

    // ponytail: parse user row filter expression (e.g. "1-42", "43-82", "1-10, 15, 20-30") into valid 0-based indices
    function parseRowFilter(filterStr, totalRows) {
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

    function initTemplateEngine() {
        setupFilterInputListener();

        if (!window.studioConfig.datasetId) {
            return;
        }

        // Fetch dataset
        fetch(`api.php?action=get_dataset&dataset_id=${window.studioConfig.datasetId}`)
        .then(response => response.json())
        .then(data => {
            if (data.rowData && data.rowData.length > 0) {
                dataset = data;
                activeRowIndices = parseRowFilter(window.studioConfig.rowFilter, dataset.rowData.length);
                currentRowIndex = 0;
                
                document.getElementById('row-total').textContent = activeRowIndices.length.toString() + (activeRowIndices.length < dataset.rowData.length ? ` (Filtered from ${dataset.rowData.length})` : '');
                
                setupNavControls();
                
                // Wait for canvas to load before initial binding application
                const checkCanvas = setInterval(() => {
                    if (window.editorCanvas && window.editorCanvas.getObjects().length > 0) {
                        clearInterval(checkCanvas);
                        applyBindings();
                    }
                }, 200);
            }
        })
        .catch(err => {
            console.error('Failed to load dataset for editor binding:', err);
        });
    }

    function setupFilterInputListener() {
        const filterInput = document.getElementById('template-row-filter');
        if (!filterInput || filterInput.dataset.bound) return;
        filterInput.dataset.bound = 'true';

        filterInput.addEventListener('change', (e) => {
            const val = e.target.value.trim();
            window.studioConfig.rowFilter = val;

            const formData = new FormData();
            formData.append('template_id', window.studioConfig.templateId);
            formData.append('row_filter', val);
            if (window.studioConfig.csrfToken) {
                formData.append('csrf_token', window.studioConfig.csrfToken);
            }

            fetch('api.php?action=update_template_row_filter', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success && dataset && dataset.rowData) {
                    activeRowIndices = parseRowFilter(window.studioConfig.rowFilter, dataset.rowData.length);
                    currentRowIndex = 0;
                    const totalEl = document.getElementById('row-total');
                    if (totalEl) {
                        totalEl.textContent = activeRowIndices.length.toString() + (activeRowIndices.length < dataset.rowData.length ? ` (Filtered from ${dataset.rowData.length})` : '');
                    }
                    applyBindings();
                }
            })
            .catch(err => console.error('Failed to update template row filter:', err));
        });
    }

    function setupNavControls() {
        const btnPrev = document.getElementById('btn-row-prev');
        const btnNext = document.getElementById('btn-row-next');
        
        if (!btnPrev || !btnNext) return;

        btnPrev.addEventListener('click', () => {
            if (currentRowIndex > 0) {
                currentRowIndex--;
                applyBindings();
            }
        });

        btnNext.addEventListener('click', () => {
            if (currentRowIndex < activeRowIndices.length - 1) {
                currentRowIndex++;
                applyBindings();
            }
        });
    }

    // ponytail: parse inline color/format tags (<red>, <gold>, <color:#hex>, <b>, <i>, <u>) into 1D character styles
    function parseStyledText(rawText) {
        if (rawText === null || rawText === undefined) return { cleanText: '', charStyles: [] };
        rawText = String(rawText).replace(/\r\n/g, '\n').replace(/\r/g, '\n');

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
            return { cleanText: rawText, charStyles: new Array(rawText.length).fill(null) };
        }

        let cleanText = '';
        const charStyles = [];
        const styleStack = [];
        const tagRegex = /<\/?([a-zA-Z0-9_\-#:=]+)>/g;

        function getEffectiveStyle() {
            if (styleStack.length === 0) return null;
            const eff = {};
            styleStack.forEach(item => Object.assign(eff, item.style));
            return eff;
        }

        let lastIndex = 0;
        let match;
        tagRegex.lastIndex = 0;

        while ((match = tagRegex.exec(rawText)) !== null) {
            const textBefore = rawText.substring(lastIndex, match.index);
            const currentStyle = getEffectiveStyle();

            for (let i = 0; i < textBefore.length; i++) {
                cleanText += textBefore[i];
                charStyles.push(currentStyle ? { ...currentStyle } : null);
            }

            const rawTag = match[1];
            const isClosing = match[0].startsWith('</');
            const lowerTag = rawTag.toLowerCase();
            const tagKey = lowerTag.split(/[:=]/)[0];

            if (isClosing) {
                // Find matching tag from top of stack and remove it
                for (let s = styleStack.length - 1; s >= 0; s--) {
                    if (styleStack[s].tag === tagKey || styleStack[s].rawTag === lowerTag) {
                        styleStack.splice(s, 1);
                        break;
                    }
                }
            } else {
                const newStyle = {};

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
                    styleStack.push({ tag: tagKey, rawTag: lowerTag, style: newStyle });
                }
            }

            lastIndex = tagRegex.lastIndex;
        }

        const remainingText = rawText.substring(lastIndex);
        const remainingStyle = getEffectiveStyle();

        for (let i = 0; i < remainingText.length; i++) {
            cleanText += remainingText[i];
            charStyles.push(remainingStyle ? { ...remainingStyle } : null);
        }

        return { cleanText, charStyles };
    }

    /**
     * Map 1D character styles onto a Fabric text/textbox object across all soft-wrapped lines.
     */
    function applyStyledTextToObject(obj, rawText) {
        const parsed = parseStyledText(rawText !== undefined && rawText !== null ? rawText : '');
        obj.set('text', parsed.cleanText);
        obj.set('styles', {});

        // Explicitly re-wrap text lines in Fabric Textbox
        if (typeof obj._splitTextIntoLines === 'function') {
            const splitRes = obj._splitTextIntoLines(parsed.cleanText);
            if (typeof obj._wrapText === 'function' && obj.width) {
                obj._textLines = obj._wrapText(splitRes.lines, obj.width);
            } else {
                obj._textLines = splitRes.lines;
            }
        }

        if (typeof obj.initDimensions === 'function') {
            obj.initDimensions();
        }

        const hasAnyStyles = parsed.charStyles.some(s => s !== null);
        if (!hasAnyStyles) {
            obj.set('styles', {});
            if (obj._clearCache) obj._clearCache();
            obj.setCoords();
            obj.set('dirty', true);
            return;
        }

        const styles = {};

        // 1. Map styles by paragraph (unwrapped line) as required by Fabric's internal _styleMap
        const paragraphs = parsed.cleanText.split('\n');
        let globalOffset = 0;
        for (let pIdx = 0; pIdx < paragraphs.length; pIdx++) {
            const pStr = paragraphs[pIdx];
            const pStyles = {};
            for (let c = 0; c < pStr.length; c++) {
                const style = parsed.charStyles[globalOffset + c];
                if (style && Object.keys(style).length > 0) {
                    pStyles[c] = { ...style };
                }
            }
            if (Object.keys(pStyles).length > 0) {
                styles[pIdx] = pStyles;
            }
            globalOffset += pStr.length + 1;
        }

        // 2. Also map styles by wrapped line index in case _styleMap is bypassed
        const rawLines = obj._textLines || (obj.text ? obj.text.split('\n') : []);
        let searchOffset = 0;

        for (let lineIdx = 0; lineIdx < rawLines.length; lineIdx++) {
            const rawLine = rawLines[lineIdx];
            const lineStr = Array.isArray(rawLine) ? rawLine.join('') : String(rawLine);
            const lineStyles = styles[lineIdx] ? { ...styles[lineIdx] } : {};

            const matchIdx = parsed.cleanText.indexOf(lineStr, searchOffset);
            const startCharPos = (matchIdx !== -1) ? matchIdx : searchOffset;

            for (let c = 0; c < lineStr.length; c++) {
                const globalPos = startCharPos + c;
                const charStyle = parsed.charStyles[globalPos];
                if (charStyle && Object.keys(charStyle).length > 0) {
                    lineStyles[c] = { ...charStyle };
                }
            }

            if (Object.keys(lineStyles).length > 0) {
                styles[lineIdx] = lineStyles;
            }

            searchOffset = startCharPos + lineStr.length;
        }

        obj.set('styles', styles);
        if (obj._clearCache) obj._clearCache();
        obj.setCoords();
        obj.set('dirty', true);
    }

    // Apply variable substitution to canvas text/image layers
    function applyBindings() {
        const canvas = window.editorCanvas;
        if (!canvas) return;

        let row = null;
        let actualRowIndex = 0;

        if (dataset && dataset.rowData && dataset.rowData.length > 0) {
            if (!activeRowIndices || activeRowIndices.length === 0) {
                activeRowIndices = parseRowFilter(window.studioConfig.rowFilter, dataset.rowData.length);
            }

            if (currentRowIndex >= activeRowIndices.length) {
                currentRowIndex = Math.max(0, activeRowIndices.length - 1);
            }

            actualRowIndex = activeRowIndices[currentRowIndex] !== undefined ? activeRowIndices[currentRowIndex] : currentRowIndex;
            row = dataset.rowData[actualRowIndex];

            const rowIndicator = document.getElementById('row-indicator');
            if (rowIndicator) {
                rowIndicator.textContent = `Row ${actualRowIndex + 1} (${currentRowIndex + 1} of ${activeRowIndices.length})`;
            }
        }

        const objects = canvas.getObjects();
        let needsRender = false;
        const imageSwapPromises = [];

        objects.forEach(obj => {
            if (obj.type === 'i-text' || obj.type === 'text' || obj.type === 'textbox') {
                // Initialize original template store
                if (!textTemplates.has(obj)) {
                    textTemplates.set(obj, obj.text || '');
                }

                let templateText = obj.variable_binding || textTemplates.get(obj);
                let substitutedText = templateText;
                
                // Replace any double brackets syntax {{ColumnName}}
                if (row) {
                    const matches = templateText.match(/\{\{([a-zA-Z0-9_\-]+)\}\}/g);
                    
                    if (matches) {
                        matches.forEach(placeholder => {
                            const colName = placeholder.replace(/\{\{|\}\}/g, '');
                            const replacement = row[colName] !== undefined ? row[colName] : placeholder;
                            substitutedText = substitutedText.replaceAll(placeholder, replacement);
                        });
                    } else if (obj.variable_binding) {
                        // If direct binding dropdown is set but text doesn't match bracket regex, fallback to direct swap
                        const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '');
                        if (row[colName] !== undefined) {
                            substitutedText = row[colName];
                        }
                    }
                }

                applyStyledTextToObject(obj, substitutedText);
                needsRender = true;
            } else if (obj.type === 'image' && obj.variable_binding) {
                // Image binding: swap image src based on dataset column value
                if (!imageOriginalSrcs.has(obj)) {
                    imageOriginalSrcs.set(obj, obj.getSrc ? obj.getSrc() : (obj._element ? obj._element.src : ''));
                }

                if (row) {
                    const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '');
                    const filename = row[colName];

                    if (filename && window.assetPicker && typeof window.assetPicker.getAssetUrlByFilename === 'function') {
                        const assetUrl = window.assetPicker.getAssetUrlByFilename(filename);
                        if (assetUrl) {
                            const currentSrc = obj.getSrc ? obj.getSrc() : '';
                            // Only swap if URL actually changed to avoid unnecessary reloads
                            if (!currentSrc.endsWith(assetUrl) && currentSrc !== assetUrl) {
                                const swapPromise = new Promise((resolve) => {
                                    obj.setSrc(assetUrl, () => {
                                        obj.setCoords();
                                        resolve();
                                    }, { crossOrigin: 'anonymous' });
                                });
                                imageSwapPromises.push(swapPromise);
                            }
                        }
                    }
                }
            } else if (obj.variable_binding) {
                // Shape / Object visibility and dataset binding (including SVG layer substitution)
                if (row) {
                    const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '');
                    const val = row[colName] !== undefined ? String(row[colName]).trim() : '';

                    // Check if value is an image/SVG asset filename
                    if (val && window.assetPicker && typeof window.assetPicker.getAssetUrlByFilename === 'function') {
                        const assetUrl = window.assetPicker.getAssetUrlByFilename(val);
                        if (assetUrl) {
                            if (obj.setSrc && typeof obj.setSrc === 'function') {
                                const currentSrc = obj.getSrc ? obj.getSrc() : '';
                                if (!currentSrc.endsWith(assetUrl) && currentSrc !== assetUrl) {
                                    const swapPromise = new Promise((resolve) => {
                                        obj.setSrc(assetUrl, () => {
                                            obj.setCoords();
                                            resolve();
                                        }, { crossOrigin: 'anonymous' });
                                    });
                                    imageSwapPromises.push(swapPromise);
                                }
                            } else {
                                // Swap legacy group/path SVG with dynamic fabric.Image
                                const swapPromise = new Promise((resolve) => {
                                    fabric.Image.fromURL(assetUrl, (newImg) => {
                                        if (!newImg) return resolve();
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
                                        const idx = canvas.getObjects().indexOf(obj);
                                        canvas.remove(obj);
                                        if (idx >= 0) {
                                            canvas.insertAt(newImg, idx);
                                        } else {
                                            canvas.add(newImg);
                                        }
                                        newImg.setCoords();
                                        resolve();
                                    }, { crossOrigin: 'anonymous' });
                                });
                                imageSwapPromises.push(swapPromise);
                            }
                            return;
                        }
                    }

                    if (obj._originalOpacity === undefined) {
                        obj._originalOpacity = obj.opacity !== undefined ? obj.opacity : 1;
                    }

                    if (val === 'transparent.png' || val === '0' || val === 'false' || val === 'none' || val === '' || val === 'hidden') {
                        obj.set('opacity', 0);
                        obj.set('visible', false);
                    } else {
                        obj.set('opacity', obj._originalOpacity || 1);
                        obj.set('visible', true);
                    }
                    obj.setCoords();
                    needsRender = true;
                }
            }
        });

        if (imageSwapPromises.length > 0) {
            // Wait for all image swaps to complete, then render
            Promise.all(imageSwapPromises).then(() => {
                canvas.renderAll();
                if (window.propertyInspector && typeof window.propertyInspector.inspect === 'function' && canvas.getActiveObject()) {
                    window.propertyInspector.inspect(canvas.getActiveObject());
                }
            });
        } else if (needsRender) {
            canvas.renderAll();
            if (window.propertyInspector && typeof window.propertyInspector.inspect === 'function' && canvas.getActiveObject()) {
                window.propertyInspector.inspect(canvas.getActiveObject());
            }
        }
    }

    // Public method to reset template text when user edits it in inspector
    function updateTextTemplate(obj, rawText) {
        textTemplates.set(obj, rawText);
        applyBindings();
    }

    let datasetSaveDebounce = null;
    function updateDatasetCell(colName, value) {
        if (!dataset || !dataset.rowData) return;
        const actualRowIndex = activeRowIndices[currentRowIndex] !== undefined ? activeRowIndices[currentRowIndex] : currentRowIndex;
        if (!dataset.rowData[actualRowIndex]) return;

        dataset.rowData[actualRowIndex][colName] = value;
        applyBindings();

        // Debounced background API save to database
        if (window.studioConfig && window.studioConfig.datasetId) {
            clearTimeout(datasetSaveDebounce);
            datasetSaveDebounce = setTimeout(() => {
                const formData = new FormData();
                formData.append('dataset_id', window.studioConfig.datasetId);
                formData.append('row_index', actualRowIndex);
                formData.append('column_name', colName);
                formData.append('value', value);
                if (window.studioConfig.csrfToken) {
                    formData.append('csrf_token', window.studioConfig.csrfToken);
                }

                fetch('api.php?action=update_dataset_cell', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-Token': window.studioConfig.csrfToken
                    }
                }).then(r => r.json()).then(res => {
                    if (res.error) {
                        console.error('Failed to save dataset cell:', res.error);
                    }
                }).catch(e => console.error('Dataset save error:', e));
            }, 350);
        }
    }

    function switchDataset(newDatasetId) {
        const templateId = window.studioConfig ? window.studioConfig.templateId : null;
        const csrfToken = window.studioConfig?.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

        if (!templateId) return;

        const formData = new FormData();
        formData.append('template_id', templateId);
        formData.append('dataset_id', newDatasetId || '');
        if (csrfToken) formData.append('csrf_token', csrfToken);

        fetch('api.php?action=bind_template_dataset', {
            method: 'POST',
            body: formData,
            headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {}
        })
        .then(r => r.json())
        .then(res => {
            if (res.error) {
                alert(res.error);
                return;
            }

            window.studioConfig.datasetId = res.dataset_id ? parseInt(res.dataset_id) : null;

            const navControls = document.getElementById('dataset-nav-controls');
            const totalContainer = document.getElementById('dataset-total-container');
            const filterContainer = document.getElementById('dataset-filter-container');
            const statusDot = document.getElementById('dataset-status-dot');

            if (res.dataset) {
                dataset = res.dataset;
                currentRowIndex = 0;
                const totalRows = (dataset.rowData && Array.isArray(dataset.rowData)) ? dataset.rowData.length : 0;

                if (navControls) navControls.classList.remove('hidden');
                if (totalContainer) totalContainer.classList.remove('hidden');
                if (filterContainer) filterContainer.classList.remove('hidden');
                if (statusDot) {
                    statusDot.classList.remove('bg-slate-600');
                    statusDot.classList.add('bg-violet-400');
                }

                const rowTotal = document.getElementById('row-total');
                if (rowTotal) rowTotal.textContent = totalRows.toString();

                setupNavControls();
            } else {
                dataset = null;
                currentRowIndex = 0;

                if (navControls) navControls.classList.add('hidden');
                if (totalContainer) totalContainer.classList.add('hidden');
                if (filterContainer) filterContainer.classList.add('hidden');
                if (statusDot) {
                    statusDot.classList.remove('bg-violet-400');
                    statusDot.classList.add('bg-slate-600');
                }
            }

            if (window.propertyInspector && typeof window.propertyInspector.updateDatasetColumns === 'function') {
                window.propertyInspector.updateDatasetColumns(dataset ? dataset.columnMap : []);
            }

            applyBindings();
        })
        .catch(err => {
            console.error('Error switching dataset:', err);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initTemplateEngine();
    });

    window.templateEngine = {
        applyBindings: applyBindings,
        updateTextTemplate: updateTextTemplate,
        updateDatasetCell: updateDatasetCell,
        parseStyledText: parseStyledText,
        getTextTemplate: (obj) => textTemplates.get(obj),
        switchDataset: switchDataset,
        getCurrentRowIndex: () => activeRowIndices[currentRowIndex] !== undefined ? activeRowIndices[currentRowIndex] : currentRowIndex,
        getCurrentRowData: () => {
            if (!dataset || !dataset.rowData) return null;
            const actualRowIndex = activeRowIndices[currentRowIndex] !== undefined ? activeRowIndices[currentRowIndex] : currentRowIndex;
            return dataset.rowData[actualRowIndex] || null;
        },
        getDataset: () => dataset,
        setRowIndex: (idx) => {
            if (dataset && idx >= 0 && idx < dataset.rowData.length) {
                currentRowIndex = idx;
                applyBindings();
            }
        }
    };
})();

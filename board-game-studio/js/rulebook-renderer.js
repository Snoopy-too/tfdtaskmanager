/**
 * Board Game Studio Rulebook Renderer & Editor Engine
 * Fully client-side parsing, visual block composition, and headless FabricJS drawing.
 */
(function() {
    'use strict';

    let blocks = [];
    const renderedTemplateCache = {};
    let isPreviewMode = false;
    let draggingElement = null;
    let dragStartX = 0;
    let dragStartY = 0;
    let elementStartX = 0;
    let elementStartY = 0;

    // Map of loaded assets and glossary for rapid inline parsing
    const assetMap = {};
    const glossaryMap = {};

    // Helper to substitute dynamic dataset row values in canvas JSON before rendering
    function substituteCanvasJson(canvasJson, row) {
        if (!canvasJson || !row) return canvasJson;
        const data = typeof canvasJson === 'string' ? JSON.parse(canvasJson) : canvasJson;
        
        if (data.objects) {
            data.objects.forEach(obj => {
                // Text substitution
                if ((obj.type === 'text' || obj.type === 'i-text') && (obj.text || obj.variable_binding)) {
                    let templateText = obj.variable_binding || obj.text || '';
                    let substitutedText = templateText;
                    const matches = templateText.match(/\{\{([a-zA-Z0-9_\-]+)\}\}/g);
                    if (matches) {
                        matches.forEach(placeholder => {
                            const colName = placeholder.replace(/\{\{|\}\}/g, '');
                            const replacement = row[colName] !== undefined ? row[colName] : placeholder;
                            substitutedText = substitutedText.replaceAll(placeholder, replacement);
                        });
                    } else if (obj.variable_binding) {
                        const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '');
                        if (row[colName] !== undefined) {
                            substitutedText = row[colName];
                        }
                    }
                    obj.text = substitutedText;
                }
                
                // Image substitution
                if (obj.type === 'image' && obj.variable_binding) {
                    const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '');
                    const filename = row[colName];
                    if (filename) {
                        let cleanedFilename = filename.replace(/\[\[|\]\]/g, '').toLowerCase().trim();
                        if (assetMap[cleanedFilename]) {
                            obj.src = assetMap[cleanedFilename];
                        }
                    }
                }
            });
        }
        
        return data;
    }

    function init() {
        if (typeof fabric !== 'undefined' && fabric.Text) {
            fabric.Text.prototype._setTextStyles = function(ctx, charStyle, forMeasuring) {
                ctx.textBaseline = 'alphabetic';
                if (this.path) {
                    switch (this.pathAlign) {
                        case 'center':
                            ctx.textBaseline = 'middle';
                            break;
                        case 'ascender':
                            ctx.textBaseline = 'top';
                            break;
                        case 'descender':
                            ctx.textBaseline = 'bottom';
                            break;
                    }
                }
                ctx.font = this._getFontDeclaration(charStyle, forMeasuring);
            };
        }
        // Build maps
        window.rulebookConfig.assets.forEach(a => {
            if (a.tag) {
                assetMap[a.tag.toLowerCase()] = a.url;
            }
        });
        window.rulebookConfig.glossary.forEach(g => {
            glossaryMap[g.key.toLowerCase()] = g;
        });

        // Initialize blocks from config
        const raw = window.rulebookConfig.initialBlocks;
        let initialBlocksList = Array.isArray(raw) ? raw : [];
        let themeBlock = initialBlocksList.find(b => b.type === 'theme');
        if (!themeBlock) {
            themeBlock = {
                type: 'theme',
                fontFamily: 'Inter',
                accentColor: '#f59e0b',
                customCss: ''
            };
            initialBlocksList.unshift(themeBlock);
        }
        blocks = initialBlocksList;

        // Apply theme styles
        applyThemeSettings();

        // Hydrate sidebar form fields
        const fontSelect = document.getElementById('theme-font-select');
        const colorInput = document.getElementById('theme-color-input');
        const colorHex = document.getElementById('theme-color-hex');
        const cssTextarea = document.getElementById('theme-css-textarea');
        if (fontSelect) fontSelect.value = themeBlock.fontFamily || 'Inter';
        if (colorInput) colorInput.value = themeBlock.accentColor || '#f59e0b';
        if (colorHex) colorHex.textContent = themeBlock.accentColor || '#f59e0b';
        if (cssTextarea) cssTextarea.value = themeBlock.customCss || '';

        renderBlocks();
        setupDragEvents();

        if (window.rulebookConfig.isLocked) {
            isPreviewMode = true;
            const editBtn = document.getElementById('btn-edit-mode');
            if (editBtn) editBtn.style.display = 'none';

            const prevBtn = document.getElementById('btn-preview-mode');
            if (prevBtn) {
                prevBtn.className = 'px-3.5 py-1.5 rounded-lg text-xs font-bold bg-amber-500/10 text-amber-400 transition';
            }

            const sidebar = document.getElementById('editor-sidebar');
            if (sidebar) {
                sidebar.querySelectorAll('button, select, input, textarea').forEach(el => {
                    el.disabled = true;
                    el.style.opacity = '0.5';
                    el.style.pointerEvents = 'none';
                });
            }
        } else {
            setInterval(() => {
                const formData = new FormData();
                formData.append('rulebook_id', window.rulebookConfig.rulebookId);
                formData.append('csrf_token', window.rulebookConfig.csrfToken);

                fetch('api.php?action=heartbeat_lock_rulebook', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.locked) {
                        alert("This rulebook has been locked by another user or your session expired. Entering read-only mode.");
                        window.location.reload();
                    }
                })
                .catch(err => console.error('Lock heartbeat failed:', err));
            }, 20000);

            window.addEventListener('beforeunload', () => {
                const formData = new FormData();
                formData.append('rulebook_id', window.rulebookConfig.rulebookId);
                formData.append('csrf_token', window.rulebookConfig.csrfToken);
                navigator.sendBeacon('api.php?action=release_lock_rulebook', formData);
            });
        }

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
    }

    // Dynamic renderer orchestrator
    function renderBlocks() {
        const list = document.getElementById('blocks-list');
        const emptyState = document.getElementById('empty-blocks-state');
        list.innerHTML = '';

        const visibleBlocks = blocks.filter(b => b.type !== 'theme');
        if (visibleBlocks.length === 0) {
            emptyState.classList.remove('hidden');
            return;
        } else {
            emptyState.classList.add('hidden');
        }

        blocks.forEach((block, index) => {
            if (block.type === 'theme') return;

            const card = document.createElement('div');
            card.className = `block-card bg-slate-900 border ${isPreviewMode ? 'border-transparent p-0' : 'border-slate-800 p-6'} rounded-2xl relative transition-all duration-200`;
            card.dataset.index = index;

            // Editor Controls (hidden in preview)
            if (!isPreviewMode) {
                const toolbar = document.createElement('div');
                toolbar.className = 'block-actions absolute right-6 top-6 flex items-center space-x-2 opacity-0 transition duration-200';
                toolbar.innerHTML = `
                    <button onclick="moveBlock(${index}, -1)" class="p-1 rounded bg-slate-800 text-slate-400 hover:text-white border border-slate-700" title="Move Up">↑</button>
                    <button onclick="moveBlock(${index}, 1)" class="p-1 rounded bg-slate-800 text-slate-400 hover:text-white border border-slate-700" title="Move Down">↓</button>
                    <button onclick="deleteBlock(${index})" class="p-1 rounded bg-rose-500/10 text-rose-500 hover:bg-rose-500 hover:text-white border border-rose-500/20" title="Delete Block">✕</button>
                `;
                card.appendChild(toolbar);
            }

            // Block Content Renderers
            if (block.type === 'markdown') {
                renderMarkdownBlock(card, block, index);
            } else if (block.type === 'setup') {
                renderSetupBlock(card, block, index);
            } else if (block.type === 'component_list') {
                renderComponentListBlock(card, block, index);
            } else if (block.type === 'anatomy') {
                renderAnatomyBlock(card, block, index);
            }

            list.appendChild(card);
        });
    }

    // 1. Markdown Text Block
    function renderMarkdownBlock(card, block, index) {
        if (isPreviewMode) {
            const contentDiv = document.createElement('div');
            contentDiv.className = 'prose prose-invert max-w-none text-slate-300 leading-relaxed text-sm md:text-base';
            contentDiv.innerHTML = parseMarkdownText(block.text || '');
            card.appendChild(contentDiv);
        } else {
            const container = document.createElement('div');
            container.className = 'space-y-2';
            container.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">Markdown Section</span>
                </div>
                <textarea class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl p-3 focus:ring-amber-500 focus:border-amber-500 transition h-40 font-mono" placeholder="Write markdown rules here..." oninput="updateBlockText(${index}, this.value)">${block.text || ''}</textarea>
                <div class="p-3 bg-slate-950/40 rounded-xl border border-slate-800/60 text-xs text-slate-400 space-y-1">
                    <p class="font-semibold text-slate-300">Live Preview:</p>
                    <div class="prose prose-invert text-slate-300">${parseMarkdownText(block.text || '')}</div>
                </div>
            `;
            card.appendChild(container);
        }
    }

    // Markdown compiler with icon & glossary replacements
    function parseMarkdownText(text) {
        if (!text) return '<p class="text-slate-500 italic">No content</p>';
        
        let html = text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");

        // Parse Inline Icons: [gold_coin]
        html = html.replace(/\[([a-zA-Z0-9_\-]+)\]/g, (match, tag) => {
            const lowerTag = tag.toLowerCase();
            if (assetMap[lowerTag]) {
                return `<img src="${assetMap[lowerTag]}" class="inline-block h-[1.3em] align-middle px-0.5" alt="${tag}" title="[${tag}]">`;
            }
            return match;
        });

        // Parse Glossary terms: [[discard_pile]]
        html = html.replace(/\[\[([a-zA-Z0-9_\-]+)\]\]/g, (match, key) => {
            const lowerKey = key.toLowerCase();
            if (glossaryMap[lowerKey]) {
                const term = glossaryMap[lowerKey];
                return `<span class="border-b border-dashed border-amber-500/70 text-amber-400 cursor-help font-semibold px-0.5" title="${term.name}: ${term.description.replace(/"/g, '&quot;')}">${term.name}</span>`;
            }
            return `<span class="text-rose-400 underline decoration-dotted" title="Glossary key not found: ${key}">${key}</span>`;
        });

        // Trivial markdown syntax rendering (bold, italics, headers)
        html = html.replace(/^### (.*$)/gim, '<h4 class="text-sm font-bold text-slate-100 mt-3 mb-1">$1</h4>');
        html = html.replace(/^## (.*$)/gim, '<h3 class="text-base font-bold text-slate-100 mt-4 mb-2">$1</h3>');
        html = html.replace(/^# (.*$)/gim, '<h2 class="text-lg font-black text-white mt-6 mb-3">$1</h2>');
        html = html.replace(/\*\*(.*)\*\*/gim, '<strong>$1</strong>');
        html = html.replace(/\*(.*)\*/gim, '<em>$1</em>');
        html = html.replace(/\r?\n/g, '<br>');

        return html;
    }

    // 2. Setup Diagram Builder
    function renderSetupBlock(card, block, index) {
        const elements = block.elements || [];

        const container = document.createElement('div');
        container.className = 'space-y-4';
        
        const titleBar = document.createElement('div');
        titleBar.className = 'flex items-center justify-between';
        titleBar.innerHTML = `
            <span class="text-xs font-bold text-indigo-400 uppercase tracking-wider">Interactive Game Setup Diagram</span>
            ${!isPreviewMode ? `<button onclick="openDiagramPicker(${index})" class="text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white px-2.5 py-1 rounded-lg transition duration-200">+ Add Template Component</button>` : ''}
        `;
        container.appendChild(titleBar);

        // Virtual Table Area Wrapper for horizontal panning on mobile
        const scrollWrapper = document.createElement('div');
        scrollWrapper.className = 'w-full overflow-x-auto border border-slate-800 rounded-xl';

        const tableArea = document.createElement('div');
        tableArea.className = 'min-w-[800px] w-full h-[500px] bg-slate-950 relative overflow-hidden pattern-grid';
        tableArea.dataset.blockIndex = index;
        
        if (elements.length === 0) {
            tableArea.innerHTML = `<div class="absolute inset-0 flex items-center justify-center text-xs text-slate-500">No components placed on virtual table yet.</div>`;
        } else {
            elements.forEach((el, elIdx) => {
                const elementDiv = document.createElement('div');
                elementDiv.className = 'absolute cursor-move select-none flex flex-col items-center';
                elementDiv.style.left = `${el.x}px`;
                elementDiv.style.top = `${el.y}px`;
                elementDiv.style.transform = `translate(-50%, -50%) rotate(${el.rotation || 0}deg) scale(${el.scale || 1.0})`;
                elementDiv.style.transformOrigin = 'center center';
                elementDiv.dataset.blockIndex = index;
                elementDiv.dataset.elementIndex = elIdx;

                const template = window.rulebookConfig.templates.find(t => t.id === el.template_id);
                let containerWidth = 70;
                let containerHeight = 100;
                if (template && template.width && template.height) {
                    const ratio = template.width / template.height;
                    if (ratio >= 1) {
                        containerWidth = 150;
                        containerHeight = Math.round(150 / ratio);
                    } else {
                        containerHeight = 100;
                        containerWidth = Math.round(100 * ratio);
                    }
                }

                // Load and draw template thumbnail asynchronously
                const imgContainer = document.createElement('div');
                imgContainer.className = 'bg-transparent flex items-center justify-center';
                imgContainer.style.width = `${containerWidth}px`;
                imgContainer.style.height = `${containerHeight}px`;
                imgContainer.innerHTML = `<div class="text-[8px] text-slate-500 font-bold text-center uppercase tracking-widest">Loading</div>`;
                
                renderTemplateToImage(el.template_id, el.row_index, (src) => {
                    if (src) {
                        imgContainer.innerHTML = `<img src="${src}" class="max-w-full max-h-full rounded shadow-lg object-contain">`;
                    } else {
                        imgContainer.innerHTML = `<div class="text-[8px] text-rose-500 text-center font-bold">Failed</div>`;
                    }
                });

                elementDiv.appendChild(imgContainer);

                // Small control panel in edit mode
                if (!isPreviewMode) {
                    const elControls = document.createElement('div');
                    elControls.className = 'absolute bottom-1 right-1 bg-slate-950/90 border border-slate-800 text-[9px] px-1.5 py-0.5 rounded flex space-x-1.5 opacity-0 group-hover:opacity-100 transition shadow z-10';
                    elControls.innerHTML = `
                        <button onclick="scaleElementUp(${index}, ${elIdx})" class="text-emerald-400 font-bold px-0.5 hover:text-emerald-350" title="Scale Up">+</button>
                        <button onclick="scaleElementDown(${index}, ${elIdx})" class="text-amber-400 font-bold px-0.5 hover:text-amber-350" title="Scale Down">-</button>
                        <button onclick="rotateElement(${index}, ${elIdx})" class="text-indigo-400 hover:text-indigo-350" title="Rotate">↻</button>
                        <button onclick="deleteElement(${index}, ${elIdx})" class="text-rose-500 hover:text-rose-450" title="Delete">✕</button>
                    `;
                    elementDiv.appendChild(elControls);
                    elementDiv.classList.add('group');
                }

                tableArea.appendChild(elementDiv);
            });
        }

        scrollWrapper.appendChild(tableArea);
        container.appendChild(scrollWrapper);
        card.appendChild(container);
    }

    // 3. Component List Block
    function renderComponentListBlock(card, block, index) {
        const container = document.createElement('div');
        container.className = 'space-y-3';
        container.innerHTML = `
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Inventory List (Automatic Component Sync)</span>
            </div>
        `;

        const tableWrapper = document.createElement('div');
        tableWrapper.className = 'w-full overflow-x-auto border border-slate-800 rounded-xl';

        const table = document.createElement('table');
        table.className = 'w-full text-left text-xs';
        table.innerHTML = `
            <thead class="bg-slate-950 text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="px-4 py-2.5">Component Template</th>
                    <th class="px-4 py-2.5">Type Dimensions</th>
                    <th class="px-4 py-2.5 text-right">Quantity</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50 bg-slate-900/50">
            </tbody>
        `;
        const tbody = table.querySelector('tbody');

        if (window.rulebookConfig.templates.length === 0) {
            tbody.innerHTML = `<tr><td colspan="3" class="px-4 py-4 text-center text-slate-500 italic">No design templates in project.</td></tr>`;
        } else {
            // Count templates. If bound to datasets, we must pull dataset info
            window.rulebookConfig.templates.forEach(t => {
                // Read from client dataset info if available
                let qty = 1;
                
                // Let's resolve component type display label
                const mmWidth = Math.round(t.width / 11.81);
                const mmHeight = Math.round(t.height / 11.81);

                // Check if card is bound to dataset in backend
                fetch(`api.php?action=load_canvas&template_id=${t.id}`)
                .then(r => r.json())
                .then(details => {
                    if (details.dataset_id) {
                        fetch(`api.php?action=get_dataset&dataset_id=${details.dataset_id}`)
                        .then(r => r.json())
                        .then(dataset => {
                            if (dataset && dataset.rowData) {
                                qty = dataset.rowData.length;
                                const qtyCell = document.getElementById(`inv-qty-${t.id}`);
                                if (qtyCell) qtyCell.textContent = `${qty}x`;
                            }
                        });
                    }
                });

                const row = document.createElement('tr');
                row.className = 'text-slate-300';
                row.innerHTML = `
                    <td class="px-4 py-3 font-semibold text-slate-200">${t.name}</td>
                    <td class="px-4 py-3 text-slate-450">${mmWidth}x${mmHeight}mm</td>
                    <td class="px-4 py-3 text-right font-black text-emerald-400" id="inv-qty-${t.id}">1x</td>
                `;
                tbody.appendChild(row);
            });
        }

        tableWrapper.appendChild(table);
        container.appendChild(tableWrapper);
        card.appendChild(container);
    }

    // 4. Anatomy of a Card block
    function renderAnatomyBlock(card, block, index) {
        const container = document.createElement('div');
        container.className = 'space-y-4';
        
        const titleBar = document.createElement('div');
        titleBar.className = 'flex items-center justify-between';
        titleBar.innerHTML = `
            <span class="text-xs font-bold text-rose-400 uppercase tracking-wider">Anatomy of a Component</span>
        `;
        container.appendChild(titleBar);

        const columns = document.createElement('div');
        if (isPreviewMode) {
            columns.className = 'grid grid-cols-1 gap-6 items-start';
        } else {
            columns.className = 'grid grid-cols-1 md:grid-cols-2 gap-6 items-start';
        }

        // Card Container
        const visualColumn = document.createElement('div');
        visualColumn.className = 'space-y-3';
        
        if (!isPreviewMode) {
            const selector = document.createElement('select');
            selector.className = 'w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl p-2';
            selector.innerHTML = `<option value="">-- Choose Component Template --</option>`;
            window.rulebookConfig.templates.forEach(t => {
                selector.innerHTML += `<option value="${t.id}" ${block.template_id == t.id ? 'selected' : ''}>${t.name}</option>`;
            });
            selector.addEventListener('change', (e) => {
                blocks[index].template_id = e.target.value ? parseInt(e.target.value) : null;
                blocks[index].pins = [];
                renderBlocks();
            });
            visualColumn.appendChild(selector);
        }

        // Relative Pin target element
        const pinCanvas = document.createElement('div');
        pinCanvas.className = 'relative border border-slate-800 rounded-xl bg-slate-950 overflow-hidden mx-auto w-full max-w-[240px]';

        if (!block.template_id) {
            pinCanvas.innerHTML = `<div class="text-slate-500 text-xs italic text-center p-4">Select a component template above to annotate.</div>`;
        } else {
            pinCanvas.innerHTML = `<div class="text-[10px] text-slate-650 font-bold uppercase tracking-widest text-center py-12">Rendering</div>`;
            renderTemplateToImage(block.template_id, (src) => {
                if (src) {
                    pinCanvas.innerHTML = `<img src="${src}" class="w-full h-auto rounded shadow-xl pointer-events-none block">`;
                    
                    // Render pins
                    const pins = block.pins || [];
                    pins.forEach((pin, pinIdx) => {
                        const pinDiv = document.createElement('div');
                        pinDiv.className = 'anatomy-pin z-10';
                        pinDiv.style.left = `${pin.x}%`;
                        pinDiv.style.top = `${pin.y}%`;
                        pinDiv.textContent = pinIdx + 1;
                        pinCanvas.appendChild(pinDiv);
                    });

                    // Add Pin event listener in edit mode
                    if (!isPreviewMode) {
                        pinCanvas.addEventListener('click', (e) => {
                            const rect = pinCanvas.getBoundingClientRect();
                            const x = ((e.clientX - rect.left) / rect.width) * 100;
                            const y = ((e.clientY - rect.top) / rect.height) * 100;

                            if (!blocks[index].pins) blocks[index].pins = [];
                            blocks[index].pins.push({
                                x: Math.round(x),
                                y: Math.round(y),
                                label: `${blocks[index].pins.length + 1}`,
                                text: 'New label description...'
                            });
                            renderBlocks();
                        });
                    }
                } else {
                    pinCanvas.innerHTML = `<div class="text-xs text-rose-500">Failed to render card preview</div>`;
                }
            });
        }
        visualColumn.appendChild(pinCanvas);
        columns.appendChild(visualColumn);

        // Sidebar Annotations Description Column
        const notesColumn = document.createElement('div');
        notesColumn.className = 'space-y-3';
        notesColumn.innerHTML = `<h4 class="text-xs font-bold text-slate-350 uppercase tracking-wider">Labels & Definitions</h4>`;

        const pinsList = document.createElement('div');
        pinsList.className = 'space-y-2';

        const pins = block.pins || [];
        if (pins.length === 0) {
            if (isPreviewMode) {
                pinsList.innerHTML = `<p class="text-xs text-slate-500 italic">No labels defined for this component.</p>`;
            } else {
                pinsList.innerHTML = `<p class="text-xs text-slate-500 italic">${!block.template_id ? 'Select a template.' : 'Click anywhere on the component to place a numbered label pin.'}</p>`;
            }
        } else {
            pins.forEach((pin, pinIdx) => {
                const pinRow = document.createElement('div');
                pinRow.className = 'flex items-start space-x-3 bg-slate-950/60 p-3 border border-slate-850 rounded-xl';
                
                if (isPreviewMode) {
                    pinRow.innerHTML = `
                        <span class="w-5 h-5 flex-shrink-0 bg-amber-500 text-white rounded-full flex items-center justify-center font-black text-xs">${pinIdx + 1}</span>
                        <div class="text-xs text-slate-300 pt-0.5">${parseMarkdownText(pin.text)}</div>
                    `;
                } else {
                    pinRow.innerHTML = `
                        <span class="w-5 h-5 bg-amber-500 text-white rounded-full flex items-center justify-center font-bold text-xs mt-1.5 flex-shrink-0">${pinIdx + 1}</span>
                        <input type="text" value="${pin.text}" class="flex-grow bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-lg p-1.5 focus:ring-amber-500" oninput="updatePinText(${index}, ${pinIdx}, this.value)">
                        <button onclick="deletePin(${index}, ${pinIdx})" class="text-rose-500 hover:text-rose-400 text-xs px-1 mt-1.5">✕</button>
                    `;
                }
                pinsList.appendChild(pinRow);
            });
        }
        notesColumn.appendChild(pinsList);
        columns.appendChild(notesColumn);

        container.appendChild(columns);
        card.appendChild(container);
    }

    // Load templates cached in memory headlessly using FabricJS
    window.renderTemplateToImage = function(templateId, rowIndex, callback) {
        // Callback shifting for optional parameter compatibility
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
                    const substitutedData = substituteCanvasJson(data.canvas_json, row);
                    
                    const canvasEl = document.createElement('canvas');
                    canvasEl.width = data.width || 300;
                    canvasEl.height = data.height || 400;

                    const fCanvas = new fabric.Canvas(canvasEl, { enableRetinaScaling: false });
                    fCanvas.loadFromJSON(substitutedData, () => {
                        fCanvas.renderAll();
                        const dataUrl = fCanvas.toDataURL({ format: 'png' });
                        renderedTemplateCache[cacheKey] = dataUrl;
                        fCanvas.dispose();
                        callback(dataUrl);
                    });
                })
                .catch(err => {
                    console.error('Failed to substitute dataset values:', err);
                    renderDefault(data);
                });
            } else {
                renderDefault(data);
            }

            function renderDefault(details) {
                const canvasEl = document.createElement('canvas');
                canvasEl.width = details.width || 300;
                canvasEl.height = details.height || 400;

                const fCanvas = new fabric.Canvas(canvasEl, { enableRetinaScaling: false });
                fCanvas.loadFromJSON(details.canvas_json, () => {
                    fCanvas.renderAll();
                    const dataUrl = fCanvas.toDataURL({ format: 'png' });
                    renderedTemplateCache[cacheKey] = dataUrl;
                    fCanvas.dispose();
                    callback(dataUrl);
                });
            }
        })
        .catch(err => {
            console.error('Failed to parse dynamic template preview:', err);
            callback('');
        });
    };

    // Block Operations
    window.addBlock = function(type) {
        if (type === 'markdown') {
            blocks.push({ type: 'markdown', text: '## Rule Section Name\nType rule mechanics here...' });
        } else if (type === 'setup') {
            blocks.push({ type: 'setup', elements: [] });
        } else if (type === 'component_list') {
            blocks.push({ type: 'component_list' });
        } else if (type === 'anatomy') {
            blocks.push({ type: 'anatomy', template_id: null, pins: [] });
        }
        renderBlocks();
        saveRulebook(true); // Autosave quietly
    };

    window.deleteBlock = function(idx) {
        showConfirmDialog("Are you sure you want to delete this block?", () => {
            blocks.splice(idx, 1);
            renderBlocks();
            saveRulebook(true);
        });
    };

    window.moveBlock = function(idx, dir) {
        const target = idx + dir;
        if (target < 0 || target >= blocks.length) return;
        const temp = blocks[idx];
        blocks[idx] = blocks[target];
        blocks[target] = temp;
        renderBlocks();
        saveRulebook(true);
    };

    window.updateBlockText = function(idx, text) {
        blocks[idx].text = text;
        saveRulebook(true);
    };

    window.updatePinText = function(blockIdx, pinIdx, text) {
        blocks[blockIdx].pins[pinIdx].text = text;
        saveRulebook(true);
    };

    window.deletePin = function(blockIdx, pinIdx) {
        blocks[blockIdx].pins.splice(pinIdx, 1);
        renderBlocks();
        saveRulebook(true);
    };

    // Table elements dragging inside diagram
    let activeBlockIndexForPicker = null;

    window.openDiagramPicker = function(blockIdx) {
        activeBlockIndexForPicker = blockIdx;
        document.getElementById('diagram-item-picker').classList.remove('hidden');
        const selectTemplate = document.getElementById('diagram-select-template');
        if (selectTemplate) {
            selectTemplate.dispatchEvent(new Event('change'));
        }
    };

    window.closeDiagramPicker = function() {
        document.getElementById('diagram-item-picker').classList.add('hidden');
        activeBlockIndexForPicker = null;
    };

    document.getElementById('btn-add-to-diagram').addEventListener('click', () => {
        if (activeBlockIndexForPicker === null) return;
        
        const templateId = parseInt(document.getElementById('diagram-select-template').value);
        const scale = parseFloat(document.getElementById('diagram-item-scale').value);
        const rotation = parseInt(document.getElementById('diagram-item-rotation').value);

        const selectRow = document.getElementById('diagram-select-row');
        const rowSelectContainer = document.getElementById('diagram-row-select-container');
        let rowIndex = null;
        if (rowSelectContainer && !rowSelectContainer.classList.contains('hidden') && selectRow.value !== '') {
            rowIndex = parseInt(selectRow.value);
        }

        if (!blocks[activeBlockIndexForPicker].elements) {
            blocks[activeBlockIndexForPicker].elements = [];
        }

        blocks[activeBlockIndexForPicker].elements.push({
            template_id: templateId,
            row_index: rowIndex,
            x: 100, // Spawn centered coordinates
            y: 100,
            scale: scale,
            rotation: rotation
        });

        closeDiagramPicker();
        renderBlocks();
        saveRulebook(true);
    });

    window.rotateElement = function(blockIdx, elIdx) {
        const el = blocks[blockIdx].elements[elIdx];
        el.rotation = ((el.rotation || 0) + 45) % 360;
        renderBlocks();
        saveRulebook(true);
    };

    window.scaleElementUp = function(blockIdx, elIdx) {
        const el = blocks[blockIdx].elements[elIdx];
        el.scale = Math.min((el.scale || 1.0) + 0.1, 5.0);
        renderBlocks();
        saveRulebook(true);
    };

    window.scaleElementDown = function(blockIdx, elIdx) {
        const el = blocks[blockIdx].elements[elIdx];
        el.scale = Math.max((el.scale || 1.0) - 0.1, 0.4);
        renderBlocks();
        saveRulebook(true);
    };

    window.deleteElement = function(blockIdx, elIdx) {
        blocks[blockIdx].elements.splice(elIdx, 1);
        renderBlocks();
        saveRulebook(true);
    };

    // Client-side Pointer Event tracking for diagram positioning
    function setupDragEvents() {
        document.addEventListener('pointerdown', (e) => {
            if (e.target.closest('button')) return; // Allow button clicks without triggering drag
            const target = e.target.closest('[data-element-index]');
            if (!target || isPreviewMode) return;

            draggingElement = target;
            const blockIdx = parseInt(target.dataset.blockIndex);
            const elIdx = parseInt(target.dataset.elementIndex);

            const el = blocks[blockIdx].elements[elIdx];

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
            const el = blocks[blockIdx].elements[elIdx];

            const dx = e.clientX - dragStartX;
            const dy = e.clientY - dragStartY;

            // Update coordinates
            el.x = Math.round(elementStartX + dx);
            el.y = Math.round(elementStartY + dy);

            // Bounds constraint for visual table diagram container
            el.x = Math.max(20, Math.min(780, el.x));
            el.y = Math.max(20, Math.min(480, el.y));

            // Move element in real-time DOM
            draggingElement.style.left = `${el.x}px`;
            draggingElement.style.top = `${el.y}px`;
        });

        document.addEventListener('pointerup', (e) => {
            if (!draggingElement) return;
            draggingElement.releasePointerCapture(e.pointerId);
            draggingElement = null;
            saveRulebook(true);
        });
    }

    // Save rulebook document to DB
    window.saveRulebook = function(quiet = false) {
        if (window.rulebookConfig.isLocked) {
            if (!quiet) alert("This rulebook is locked in Read-Only Mode.");
            return;
        }
        const indicator = document.getElementById('status-indicator');
        if (indicator) {
            indicator.textContent = 'Saving changes...';
            indicator.className = 'text-xs text-amber-400 font-medium';
        }

        const formData = new FormData();
        formData.append('csrf_token', window.rulebookConfig.csrfToken);
        formData.append('project_id', window.rulebookConfig.projectId.toString());
        formData.append('rulebook_id', window.rulebookConfig.rulebookId.toString());
        formData.append('name', document.querySelector('#editor-sidebar h2').textContent);
        formData.append('content', JSON.stringify(blocks));

        fetch('api.php?action=save_rulebook', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (indicator) {
                    indicator.textContent = 'All changes saved';
                    indicator.className = 'text-xs text-slate-500 font-medium';
                }
            } else {
                throw new Error(data.error || 'Server error saving');
            }
        })
        .catch(err => {
            console.error('Error saving rulebook:', err);
            if (indicator) {
                indicator.textContent = 'Error saving changes';
                indicator.className = 'text-xs text-rose-500 font-bold';
            }
            if (!quiet) {
                alert('Failed to save rulebook: ' + err.message);
            }
        });
    };

    // Toggle workspace previews
    window.togglePreviewMode = function(preview) {
        isPreviewMode = preview;
        const wrapper = document.getElementById('rulebook-content-wrapper');
        const workspace = document.getElementById('editor-workspace');
        const viewport = document.getElementById('rulebook-viewport-container');

        if (viewport) {
            viewport.scrollTop = 0;
        }

        const btnEdit = document.getElementById('btn-edit-mode');
        const btnPrev = document.getElementById('btn-preview-mode');

        if (preview) {
            // Apply mobile styling emulation
            wrapper.classList.remove('max-w-3xl', 'p-10');
            wrapper.classList.add('max-w-sm', 'p-4', 'mx-auto');
            if (viewport) {
                viewport.classList.remove('p-8');
                viewport.classList.add('p-4', 'flex', 'justify-center');
            }

            btnPrev.className = 'px-3.5 py-1.5 rounded-lg text-xs font-bold bg-amber-500/10 text-amber-400 transition';
            btnEdit.className = 'px-3.5 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition';
        } else {
            // Revert to editor desktop page layout
            wrapper.classList.remove('max-w-sm', 'p-4', 'mx-auto');
            wrapper.classList.add('max-w-3xl', 'p-10');
            if (viewport) {
                viewport.classList.remove('p-4', 'flex', 'items-center', 'justify-center');
                viewport.classList.add('p-8');
            }

            btnEdit.className = 'px-3.5 py-1.5 rounded-lg text-xs font-bold bg-amber-500/10 text-amber-400 transition';
            btnPrev.className = 'px-3.5 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition';
        }

        renderBlocks();
    };

    window.showConfirmDialog = function(message, onConfirm) {
        const modal = document.getElementById('custom-confirm-modal');
        const msgEl = document.getElementById('custom-confirm-message');
        const btnOk = document.getElementById('btn-confirm-ok');
        const btnCancel = document.getElementById('btn-confirm-cancel');

        if (!modal || !msgEl || !btnOk || !btnCancel) {
            // Fallback if DOM elements aren't present
            if (confirm(message)) onConfirm();
            return;
        }

        msgEl.textContent = message;
        modal.classList.remove('hidden');

        const cleanUp = () => {
            modal.classList.add('hidden');
            btnOk.onclick = null;
            btnCancel.onclick = null;
        };

        btnOk.onclick = () => {
            cleanUp();
            onConfirm();
        };

        btnCancel.onclick = () => {
            cleanUp();
        };
    };

    // Theme Management functions
    function applyThemeSettings() {
        const theme = blocks.find(b => b.type === 'theme') || { fontFamily: 'Inter', accentColor: '#f59e0b', customCss: '' };
        
        // 1. Inject Google Font dynamically if needed
        const fontId = 'gfont-' + theme.fontFamily.replace(/\s+/g, '-');
        if (!document.getElementById(fontId)) {
            const link = document.createElement('link');
            link.id = fontId;
            link.rel = 'stylesheet';
            link.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(theme.fontFamily)}:wght@400;600;850&display=swap`;
            document.head.appendChild(link);
        }

        // 2. Inject CSS rules and overrides
        let styleTag = document.getElementById('rulebook-custom-styles');
        if (!styleTag) {
            styleTag = document.createElement('style');
            styleTag.id = 'rulebook-custom-styles';
            document.head.appendChild(styleTag);
        }

        styleTag.innerHTML = `
            #rulebook-content-wrapper, .prose {
                font-family: '${theme.fontFamily}', sans-serif !important;
            }
            :root {
                --theme-accent-color: ${theme.accentColor} !important;
            }
            /* Custom CSS Overrides */
            ${theme.customCss || ''}
        `;
    }

    window.switchEditorSidebarTab = function(tab) {
        const tabBlocks = document.getElementById('tab-content-blocks');
        const tabTheme = document.getElementById('tab-content-theme');
        const btnBlocks = document.getElementById('btn-sidebar-blocks');
        const btnTheme = document.getElementById('btn-sidebar-theme');

        if (tab === 'theme') {
            tabBlocks.classList.add('hidden');
            tabTheme.classList.remove('hidden');
            btnTheme.classList.remove('border-transparent', 'text-slate-400');
            btnTheme.classList.add('border-amber-500', 'text-white');
            btnBlocks.classList.remove('border-amber-500', 'text-white');
            btnBlocks.classList.add('border-transparent', 'text-slate-400');
        } else {
            tabBlocks.classList.remove('hidden');
            tabTheme.classList.add('hidden');
            btnBlocks.classList.remove('border-transparent', 'text-slate-400');
            btnBlocks.classList.add('border-amber-500', 'text-white');
            btnTheme.classList.remove('border-amber-500', 'text-white');
            btnTheme.classList.add('border-transparent', 'text-slate-400');
        }
    };

    window.updateThemeFont = function(font) {
        const theme = blocks.find(b => b.type === 'theme');
        if (theme) {
            theme.fontFamily = font;
            applyThemeSettings();
            saveRulebook(true);
        }
    };

    window.updateThemeColor = function(color) {
        const theme = blocks.find(b => b.type === 'theme');
        if (theme) {
            theme.accentColor = color;
            document.getElementById('theme-color-hex').textContent = color;
            applyThemeSettings();
            saveRulebook(true);
        }
    };

    window.updateThemeCss = function(css) {
        const theme = blocks.find(b => b.type === 'theme');
        if (theme) {
            theme.customCss = css;
            applyThemeSettings();
            saveRulebook(true);
        }
    };

    window.triggerPrint = function() {
        // Automatically switch to full width layout, force preview compiling for rendering output, then print
        togglePreviewMode(false);
        setTimeout(() => {
            window.print();
        }, 300);
    };

    document.addEventListener('DOMContentLoaded', () => {
        init();
    });
})();

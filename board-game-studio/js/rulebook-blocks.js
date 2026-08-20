/**
 * Rulebook Block Types Renderer Module
 * Generates DOM for Markdown, Page Break, Setup Diagram, Component List, and Card Anatomy blocks.
 */
(function() {
    'use strict';

    function parseMarkdown(text) {
        if (window.rulebookParser) {
            const assetMap = window.rulebookGetAssetMap ? window.rulebookGetAssetMap() : {};
            const glossaryMap = window.rulebookGetGlossaryMap ? window.rulebookGetGlossaryMap() : {};
            return window.rulebookParser.parseMarkdownText(text, assetMap, glossaryMap);
        }
        return text || '';
    }

    function renderMarkdownBlock(card, block, index, isPreviewMode) {
        if (isPreviewMode) {
            const contentDiv = document.createElement('div');
            contentDiv.className = 'prose prose-invert max-w-none text-slate-300 leading-relaxed text-sm md:text-base';
            contentDiv.innerHTML = parseMarkdown(block.text || '');
            card.appendChild(contentDiv);
        } else {
            const container = document.createElement('div');
            container.className = 'space-y-2';
            container.innerHTML = `
                <div class="flex items-center justify-between pr-24">
                    <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">Markdown Section</span>
                </div>
                <textarea class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl p-3 focus:ring-amber-500 focus:border-amber-500 transition h-40 font-mono" placeholder="Write markdown rules here..." oninput="updateBlockText(${index}, this.value)">${block.text || ''}</textarea>
                <div class="p-3 bg-slate-950/40 rounded-xl border border-slate-800/60 text-xs text-slate-400 space-y-1">
                    <p class="font-semibold text-slate-300">Live Preview:</p>
                    <div class="prose prose-invert text-slate-300">${parseMarkdown(block.text || '')}</div>
                </div>
            `;
            card.appendChild(container);
        }
    }

    function renderPageBreakBlock(card, block, index, isPreviewMode) {
        if (isPreviewMode) {
            card.className = 'page-break';
            card.innerHTML = '';
        } else {
            card.className = 'py-4 relative flex items-center justify-center';
            card.innerHTML = `
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t-2 border-dashed border-teal-500/40"></div>
                </div>
                <div class="relative flex justify-center text-xs uppercase bg-slate-900 px-4 text-teal-400 font-black tracking-widest border border-teal-500/20 rounded-full py-1">
                    ✂ Page Break (For Print Layout)
                </div>
            `;
        }
    }

    function renderSetupBlock(card, block, index, isPreviewMode) {
        const elements = block.elements || [];
        const pins = block.pins || [];

        const container = document.createElement('div');
        container.className = 'space-y-4';
        
        const titleBar = document.createElement('div');
        titleBar.className = `flex items-center justify-between ${!isPreviewMode ? 'pr-24' : ''}`;
        if (isPreviewMode) {
            titleBar.innerHTML = `<span class="text-xs font-bold text-indigo-400 uppercase tracking-wider">${block.title || 'Interactive Game Setup Diagram'}</span>`;
        } else {
            titleBar.innerHTML = `
                <div class="flex items-center space-x-4 w-full">
                    <input type="text" value="${block.title || 'Interactive Game Setup Diagram'}" 
                        class="bg-transparent border-b border-slate-800 text-xs font-bold text-indigo-400 uppercase tracking-wider focus:outline-none focus:border-indigo-500 flex-grow"
                        oninput="updateBlockTitle(${index}, this.value)" placeholder="Interactive Game Setup Diagram">
                    <div class="flex items-center space-x-1.5 flex-shrink-0">
                        <button onclick="openDiagramPicker(${index})" class="text-[10px] font-bold bg-indigo-600 hover:bg-indigo-500 text-white px-2 py-1 rounded-lg transition duration-200">+ Add Component</button>
                        <button onclick="addSetupPin(${index})" class="text-[10px] font-bold bg-rose-600 hover:bg-rose-500 text-white px-2 py-1 rounded-lg transition duration-200">+ Add Label Pin</button>
                        <span class="text-[10px] text-slate-550 font-semibold uppercase tracking-wider">Layout:</span>
                        <select onchange="updateBlockLayout(${index}, this.value)" class="bg-slate-950 border border-slate-800 text-slate-200 text-[10px] rounded-lg p-1 focus:ring-indigo-500">
                            <option value="stacked" ${block.layout === 'stacked' || !block.layout ? 'selected' : ''}>Stacked</option>
                            <option value="side-by-side" ${block.layout === 'side-by-side' ? 'selected' : ''}>Side-by-Side</option>
                        </select>
                    </div>
                </div>
            `;
        }
        container.appendChild(titleBar);

        const scrollWrapper = document.createElement('div');
        scrollWrapper.className = 'w-full max-w-[800px] mx-auto overflow-x-auto border border-slate-800 rounded-xl';

        const tableArea = document.createElement('div');
        tableArea.className = 'w-[800px] h-[500px] bg-slate-950 relative overflow-hidden pattern-grid';
        tableArea.dataset.blockIndex = index;
        
        if (elements.length === 0 && pins.length === 0) {
            tableArea.innerHTML = `<div class="absolute inset-0 flex items-center justify-center text-xs text-slate-500">No components placed on virtual table yet.</div>`;
        } else {
            tableArea.innerHTML = '';
            
            elements.forEach((el, elIdx) => {
                const elementDiv = document.createElement('div');
                elementDiv.className = 'absolute cursor-move select-none flex flex-col items-center';
                elementDiv.style.left = `${el.x}px`;
                elementDiv.style.top = `${el.y}px`;
                elementDiv.style.transform = `translate(-50%, -50%) rotate(${el.rotation || 0}deg) scale(${el.scale || 1.0})`;
                elementDiv.style.transformOrigin = 'center center';
                elementDiv.dataset.blockIndex = index;
                elementDiv.dataset.elementIndex = elIdx;

                const template = (window.rulebookConfig.templates || []).find(t => t.id === el.template_id);
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

                const imgContainer = document.createElement('div');
                imgContainer.className = 'bg-transparent flex items-center justify-center';
                imgContainer.style.width = `${containerWidth}px`;
                imgContainer.style.height = `${containerHeight}px`;
                imgContainer.innerHTML = `<div class="text-[8px] text-slate-500 font-bold text-center uppercase tracking-widest">Loading</div>`;
                
                if (window.renderTemplateToImage) {
                    window.renderTemplateToImage(el.template_id, el.row_index, (src) => {
                        if (src) {
                            imgContainer.innerHTML = `<img src="${src}" class="max-w-full max-h-full rounded shadow-lg object-contain">`;
                        } else {
                            imgContainer.innerHTML = `<div class="text-[8px] text-rose-500 text-center font-bold">Failed</div>`;
                        }
                    });
                }

                elementDiv.appendChild(imgContainer);

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
            
            pins.forEach((pin, pinIdx) => {
                const pinDiv = document.createElement('div');
                pinDiv.className = 'anatomy-pin z-10';
                pinDiv.style.left = `${pin.x}%`;
                pinDiv.style.top = `${pin.y}%`;
                pinDiv.textContent = pinIdx + 1;
                
                if (!isPreviewMode) {
                    pinDiv.style.touchAction = 'none';
                    pinDiv.style.cursor = 'grab';
                    pinDiv.addEventListener('click', (e) => e.stopPropagation());
                    pinDiv.addEventListener('pointerdown', (e) => {
                        e.stopPropagation();
                        pinDiv.style.cursor = 'grabbing';
                        pinDiv.setPointerCapture(e.pointerId);
                        
                        const onPointerMove = (moveEvent) => {
                            const rect = tableArea.getBoundingClientRect();
                            let newX = Math.max(0, Math.min(100, ((moveEvent.clientX - rect.left) / rect.width) * 100));
                            let newY = Math.max(0, Math.min(100, ((moveEvent.clientY - rect.top) / rect.height) * 100));
                            pin.x = Math.round(newX);
                            pin.y = Math.round(newY);
                            pinDiv.style.left = `${pin.x}%`;
                            pinDiv.style.top = `${pin.y}%`;
                        };
                        
                        const onPointerUp = (upEvent) => {
                            pinDiv.style.cursor = 'grab';
                            pinDiv.releasePointerCapture(upEvent.pointerId);
                            pinDiv.removeEventListener('pointermove', onPointerMove);
                            pinDiv.removeEventListener('pointerup', onPointerUp);
                            if (window.saveRulebook) window.saveRulebook(true);
                        };
                        
                        pinDiv.addEventListener('pointermove', onPointerMove);
                        pinDiv.addEventListener('pointerup', onPointerUp);
                    });
                }
                tableArea.appendChild(pinDiv);
            });
        }

        scrollWrapper.appendChild(tableArea);

        const columns = document.createElement('div');
        const layout = block.layout || 'stacked';
        columns.className = 'anatomy-columns ' + (layout === 'stacked' ? 'layout-stacked' : 'layout-side-by-side');

        const visualColumn = document.createElement('div');
        visualColumn.className = 'space-y-3';
        visualColumn.appendChild(scrollWrapper);
        columns.appendChild(visualColumn);

        const notesColumn = document.createElement('div');
        notesColumn.className = 'space-y-3';

        if (pins.length > 0 || !isPreviewMode) {
            notesColumn.innerHTML = `<h4 class="text-xs font-bold uppercase tracking-wider text-indigo-400">Labels & Explanations</h4>`;
            
            const pinsList = document.createElement('div');
            pinsList.className = 'space-y-2';

            if (pins.length === 0) {
                pinsList.innerHTML = `<p class="text-xs text-slate-500 italic">Click "+ Add Label Pin" above to place an explanation pin on the setup diagram.</p>`;
            } else {
                pins.forEach((pin, pinIdx) => {
                    const pinRow = document.createElement('div');
                    pinRow.className = 'flex items-start space-x-3 bg-slate-950/60 p-3 border border-slate-850 rounded-xl';
                    
                    if (isPreviewMode) {
                        pinRow.innerHTML = `
                            <span class="w-5 h-5 flex-shrink-0 bg-amber-500 text-white rounded-full flex items-center justify-center font-black text-xs">${pinIdx + 1}</span>
                            <div class="text-xs text-slate-300 pt-0.5">${parseMarkdown(pin.text)}</div>
                        `;
                    } else {
                        pinRow.innerHTML = `
                            <span class="w-5 h-5 bg-amber-500 text-white rounded-full flex items-center justify-center font-bold text-xs mt-1.5 flex-shrink-0">${pinIdx + 1}</span>
                            <input type="text" value="${pin.text}" class="flex-grow bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-lg p-1.5 focus:ring-indigo-500" oninput="updatePinText(${index}, ${pinIdx}, this.value)">
                            <button onclick="deletePin(${index}, ${pinIdx})" class="text-rose-500 hover:text-rose-450 text-xs px-1 mt-1.5">✕</button>
                        `;
                    }
                    pinsList.appendChild(pinRow);
                });
            }
            notesColumn.appendChild(pinsList);
            columns.appendChild(notesColumn);
        }

        container.appendChild(columns);
        card.appendChild(container);
    }

    function renderComponentListBlock(card, block, index, isPreviewMode) {
        const container = document.createElement('div');
        container.className = 'space-y-3';
        
        const titleBar = document.createElement('div');
        titleBar.className = `flex items-center justify-between ${!isPreviewMode ? 'pr-24' : ''}`;
        if (isPreviewMode) {
            titleBar.innerHTML = `<span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">${block.title || 'Inventory List (Automatic Component Sync)'}</span>`;
        } else {
            titleBar.innerHTML = `
                <input type="text" value="${block.title || 'Inventory List (Automatic Component Sync)'}" 
                    class="bg-transparent border-b border-slate-800 text-xs font-bold text-emerald-400 uppercase tracking-wider focus:outline-none focus:border-emerald-500 w-full"
                    oninput="updateBlockTitle(${index}, this.value)" placeholder="Inventory List (Automatic Component Sync)">
            `;
        }
        container.appendChild(titleBar);

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
            <tbody class="divide-y divide-slate-800/50 bg-slate-900/50"></tbody>
        `;
        const tbody = table.querySelector('tbody');

        const templates = window.rulebookConfig.templates || [];
        if (templates.length === 0) {
            tbody.innerHTML = `<tr><td colspan="3" class="px-4 py-4 text-center text-slate-500 italic">No design templates in project.</td></tr>`;
        } else {
            templates.forEach(t => {
                const qty = t.quantity || 1;
                const mmWidth = Math.round(t.width / 11.81);
                const mmHeight = Math.round(t.height / 11.81);

                const row = document.createElement('tr');
                row.className = 'text-slate-300';
                row.innerHTML = `
                    <td class="px-4 py-3 font-semibold text-slate-200">${t.name}</td>
                    <td class="px-4 py-3 text-slate-450">${mmWidth}x${mmHeight}mm</td>
                    <td class="px-4 py-3 text-right font-black text-emerald-400" id="inv-qty-${t.id}">${qty}x</td>
                `;
                tbody.appendChild(row);
            });
        }

        tableWrapper.appendChild(table);
        container.appendChild(tableWrapper);
        card.appendChild(container);
    }

    function renderAnatomyBlock(card, block, index, isPreviewMode) {
        const container = document.createElement('div');
        container.className = 'space-y-4';
        
        const titleBar = document.createElement('div');
        titleBar.className = `flex items-center justify-between ${!isPreviewMode ? 'pr-24' : ''}`;
        if (isPreviewMode) {
            titleBar.innerHTML = `<span class="text-xs font-bold text-rose-400 uppercase tracking-wider">${block.title || 'Anatomy of a Component'}</span>`;
        } else {
            titleBar.innerHTML = `
                <div class="flex items-center space-x-4 w-full">
                    <input type="text" value="${block.title || 'Anatomy of a Component'}" 
                        class="bg-transparent border-b border-slate-800 text-xs font-bold text-rose-400 uppercase tracking-wider focus:outline-none focus:border-rose-500 flex-grow"
                        oninput="updateBlockTitle(${index}, this.value)" placeholder="Anatomy of a Component">
                    <div class="flex items-center space-x-1.5 flex-shrink-0">
                        <span class="text-[10px] text-slate-550 font-semibold uppercase tracking-wider">Layout:</span>
                        <select onchange="updateBlockLayout(${index}, this.value)" class="bg-slate-950 border border-slate-800 text-slate-200 text-[10px] rounded-lg p-1 focus:ring-rose-500">
                            <option value="side-by-side" ${block.layout === 'side-by-side' || !block.layout ? 'selected' : ''}>Side-by-Side</option>
                            <option value="stacked" ${block.layout === 'stacked' ? 'selected' : ''}>Stacked</option>
                        </select>
                    </div>
                </div>
            `;
        }
        container.appendChild(titleBar);

        const columns = document.createElement('div');
        const layout = block.layout || 'side-by-side';
        columns.className = 'anatomy-columns ' + (layout === 'stacked' ? 'layout-stacked' : 'layout-side-by-side');

        const visualColumn = document.createElement('div');
        visualColumn.className = 'space-y-3';
        
        if (!isPreviewMode) {
            const selector = document.createElement('select');
            selector.className = 'w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl p-2';
            selector.innerHTML = `<option value="">-- Choose Component Template --</option>`;
            (window.rulebookConfig.templates || []).forEach(t => {
                selector.innerHTML += `<option value="${t.id}" ${block.template_id == t.id ? 'selected' : ''}>${t.name}</option>`;
            });
            selector.addEventListener('change', (e) => {
                if (window.setBlockTemplate) window.setBlockTemplate(index, e.target.value ? parseInt(e.target.value) : null);
            });
            visualColumn.appendChild(selector);
        }

        const pinCanvas = document.createElement('div');
        pinCanvas.className = 'relative border border-slate-800 rounded-xl bg-slate-950 overflow-hidden mx-auto w-full max-w-[240px]';

        if (!block.template_id) {
            pinCanvas.innerHTML = `<div class="text-slate-500 text-xs italic text-center p-4">Select a component template above to annotate.</div>`;
        } else {
            pinCanvas.innerHTML = `<div class="text-[10px] text-slate-650 font-bold uppercase tracking-widest text-center py-12">Rendering</div>`;
            if (window.renderTemplateToImage) {
                window.renderTemplateToImage(block.template_id, (src) => {
                    if (src) {
                        pinCanvas.innerHTML = `<img src="${src}" class="w-full h-auto rounded shadow-xl pointer-events-none block">`;
                        
                        const pins = block.pins || [];
                        pins.forEach((pin, pinIdx) => {
                            const pinDiv = document.createElement('div');
                            pinDiv.className = 'anatomy-pin z-10';
                            pinDiv.style.left = `${pin.x}%`;
                            pinDiv.style.top = `${pin.y}%`;
                            pinDiv.textContent = pinIdx + 1;
                            
                            if (!isPreviewMode) {
                                pinDiv.style.touchAction = 'none';
                                pinDiv.style.cursor = 'grab';
                                pinDiv.addEventListener('click', (e) => e.stopPropagation());
                                pinDiv.addEventListener('pointerdown', (e) => {
                                    e.stopPropagation();
                                    pinDiv.style.cursor = 'grabbing';
                                    pinDiv.setPointerCapture(e.pointerId);
                                    
                                    const onPointerMove = (moveEvent) => {
                                        const rect = pinCanvas.getBoundingClientRect();
                                        let newX = Math.max(0, Math.min(100, ((moveEvent.clientX - rect.left) / rect.width) * 100));
                                        let newY = Math.max(0, Math.min(100, ((moveEvent.clientY - rect.top) / rect.height) * 100));
                                        pin.x = Math.round(newX);
                                        pin.y = Math.round(newY);
                                        pinDiv.style.left = `${pin.x}%`;
                                        pinDiv.style.top = `${pin.y}%`;
                                    };
                                    
                                    const onPointerUp = (upEvent) => {
                                        pinDiv.style.cursor = 'grab';
                                        pinDiv.releasePointerCapture(upEvent.pointerId);
                                        pinDiv.removeEventListener('pointermove', onPointerMove);
                                        pinDiv.removeEventListener('pointerup', onPointerUp);
                                        if (window.saveRulebook) window.saveRulebook(true);
                                    };
                                    
                                    pinDiv.addEventListener('pointermove', onPointerMove);
                                    pinDiv.addEventListener('pointerup', onPointerUp);
                                });
                            }
                            
                            pinCanvas.appendChild(pinDiv);
                        });

                        if (!isPreviewMode) {
                            pinCanvas.addEventListener('click', (e) => {
                                if (e.target.closest('.anatomy-pin')) return;
                                
                                const rect = pinCanvas.getBoundingClientRect();
                                const x = ((e.clientX - rect.left) / rect.width) * 100;
                                const y = ((e.clientY - rect.top) / rect.height) * 100;

                                if (window.addAnatomyPin) {
                                    window.addAnatomyPin(index, Math.round(x), Math.round(y));
                                }
                            });
                        }
                    } else {
                        pinCanvas.innerHTML = `<div class="text-xs text-rose-500">Failed to render card preview</div>`;
                    }
                });
            }
        }
        visualColumn.appendChild(pinCanvas);
        columns.appendChild(visualColumn);

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
                        <div class="text-xs text-slate-300 pt-0.5">${parseMarkdown(pin.text)}</div>
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

    window.rulebookBlocks = {
        renderMarkdownBlock,
        renderPageBreakBlock,
        renderSetupBlock,
        renderComponentListBlock,
        renderAnatomyBlock
    };
})();

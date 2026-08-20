/**
 * Board Game Studio Rulebook Renderer & Editor Engine
 * Main orchestrator: coordinates block collection state, drag events, auto-saving, and rendering.
 */
(function() {
    'use strict';

    let blocks = [];
    let isPreviewMode = false;
    let draggingElement = null;
    let dragStartX = 0;
    let dragStartY = 0;
    let elementStartX = 0;
    let elementStartY = 0;

    const assetMap = {};
    const glossaryMap = {};

    window.rulebookGetAssetMap = () => assetMap;
    window.rulebookGetGlossaryMap = () => glossaryMap;
    window.rulebookGetBlocks = () => blocks;
    window.rulebookIsPreviewMode = () => isPreviewMode;
    window.rulebookRenderBlocks = renderBlocks;

    function init() {
        if (typeof fabric !== 'undefined' && fabric.Text) {
            fabric.Text.prototype._setTextStyles = function(ctx, charStyle, forMeasuring) {
                ctx.textBaseline = 'alphabetic';
                if (this.path) {
                    switch (this.pathAlign) {
                        case 'center': ctx.textBaseline = 'middle'; break;
                        case 'ascender': ctx.textBaseline = 'top'; break;
                        case 'descender': ctx.textBaseline = 'bottom'; break;
                    }
                }
                ctx.font = this._getFontDeclaration(charStyle, forMeasuring);
            };
        }

        (window.rulebookConfig.assets || []).forEach(a => {
            if (a.tag) {
                assetMap[a.tag.toLowerCase().replace(/[\s_\-]+/g, '')] = a.url;
                assetMap[a.tag.toLowerCase()] = a.url;
            }
            if (a.filename) {
                const nameLower = a.filename.toLowerCase();
                assetMap[nameLower] = a.url;
                assetMap[nameLower.replace(/[\s_\-]+/g, '')] = a.url;
                const dotIdx = nameLower.lastIndexOf('.');
                if (dotIdx > 0) {
                    const noExt = nameLower.substring(0, dotIdx);
                    assetMap[noExt] = a.url;
                    assetMap[noExt.replace(/[\s_\-]+/g, '')] = a.url;
                }
            }
        });

        (window.rulebookConfig.glossary || []).forEach(g => {
            glossaryMap[g.key.toLowerCase()] = g;
        });

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

        if (window.rulebookTheme) {
            window.rulebookTheme.applyThemeSettings(blocks);
            window.rulebookTheme.initPresetsDropdown();
        }

        syncSidebarThemeInputs(themeBlock);
        renderBlocks();
        if (window.rulebookCanvas) window.rulebookCanvas.setupDragEvents();

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

        if (window.rulebookCanvas) window.rulebookCanvas.initDiagramItemPicker();
    }

    function syncSidebarThemeInputs(themeBlock) {
        if (!themeBlock) return;
        const fontSelect = document.getElementById('theme-font-select');
        const colorInput = document.getElementById('theme-color-input');
        const colorHex = document.getElementById('theme-color-hex');
        const styleSelect = document.getElementById('theme-style-select');
        const sizeSelect = document.getElementById('theme-size-select');
        const densitySelect = document.getElementById('theme-density-select');
        const alignSelect = document.getElementById('theme-align-select');
        const cssTextarea = document.getElementById('theme-css-textarea');
        
        if (fontSelect) fontSelect.value = themeBlock.fontFamily || 'Inter';
        if (colorInput) colorInput.value = themeBlock.accentColor || '#f59e0b';
        if (colorHex) colorHex.textContent = themeBlock.accentColor || '#f59e0b';
        if (styleSelect) styleSelect.value = themeBlock.themeStyle || 'dark';
        if (sizeSelect) sizeSelect.value = themeBlock.textSize || 'medium';
        if (densitySelect) densitySelect.value = themeBlock.spacingDensity || 'normal';
        if (alignSelect) alignSelect.value = themeBlock.headerAlign || 'left';
        if (cssTextarea) cssTextarea.value = themeBlock.customCss || '';
    }

    function renderBlocks() {
        const list = document.getElementById('blocks-list');
        const emptyState = document.getElementById('empty-blocks-state');
        if (!list) return;
        list.innerHTML = '';

        const visibleBlocks = blocks.filter(b => b.type !== 'theme');
        if (visibleBlocks.length === 0) {
            if (emptyState) emptyState.classList.remove('hidden');
            return;
        } else {
            if (emptyState) emptyState.classList.add('hidden');
        }

        blocks.forEach((block, index) => {
            if (block.type === 'theme') return;

            const card = document.createElement('div');
            card.className = `block-card bg-slate-900 border ${isPreviewMode ? 'border-transparent p-0' : 'border-slate-800 p-6'} rounded-2xl relative transition-all duration-200`;
            card.dataset.index = index;

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

            if (window.rulebookBlocks) {
                if (block.type === 'markdown') {
                    window.rulebookBlocks.renderMarkdownBlock(card, block, index, isPreviewMode);
                } else if (block.type === 'setup') {
                    window.rulebookBlocks.renderSetupBlock(card, block, index, isPreviewMode);
                } else if (block.type === 'component_list') {
                    window.rulebookBlocks.renderComponentListBlock(card, block, index, isPreviewMode);
                } else if (block.type === 'anatomy') {
                    window.rulebookBlocks.renderAnatomyBlock(card, block, index, isPreviewMode);
                } else if (block.type === 'page_break') {
                    window.rulebookBlocks.renderPageBreakBlock(card, block, index, isPreviewMode);
                }
            }

            list.appendChild(card);
        });
    }

    window.addBlock = function(type) {
        if (type === 'markdown') {
            blocks.push({ type: 'markdown', text: '## Rule Section Name\nType rule mechanics here...' });
        } else if (type === 'setup') {
            blocks.push({ type: 'setup', elements: [] });
        } else if (type === 'component_list') {
            blocks.push({ type: 'component_list' });
        } else if (type === 'anatomy') {
            blocks.push({ type: 'anatomy', template_id: null, pins: [] });
        } else if (type === 'page_break') {
            blocks.push({ type: 'page_break' });
        }
        renderBlocks();
        saveRulebook(true);
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

    window.updateBlockTitle = function(idx, title) {
        blocks[idx].title = title;
        saveRulebook(true);
    };

    window.updateBlockLayout = function(idx, layout) {
        blocks[idx].layout = layout;
        renderBlocks();
        saveRulebook(true);
    };

    window.addSetupPin = function(blockIdx) {
        if (!blocks[blockIdx].pins) blocks[blockIdx].pins = [];
        blocks[blockIdx].pins.push({ x: 50, y: 50, text: 'New label explanation...' });
        renderBlocks();
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

    window.setBlockTemplate = function(index, templateId) {
        blocks[index].template_id = templateId;
        blocks[index].pins = [];
        renderBlocks();
        saveRulebook(true);
    };

    window.addAnatomyPin = function(index, x, y) {
        if (!blocks[index].pins) blocks[index].pins = [];
        blocks[index].pins.push({
            x: x,
            y: y,
            label: `${blocks[index].pins.length + 1}`,
            text: 'New label description...'
        });
        renderBlocks();
        saveRulebook(true);
    };

    window.addDiagramElement = function(blockIdx, elementObj) {
        if (!blocks[blockIdx].elements) blocks[blockIdx].elements = [];
        blocks[blockIdx].elements.push(elementObj);
        renderBlocks();
        saveRulebook(true);
    };




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
        const headerEl = document.querySelector('#editor-sidebar h2');
        formData.append('name', headerEl ? headerEl.textContent : 'Rulebook');
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
            if (!quiet) alert('Failed to save rulebook: ' + err.message);
        });
    };

    window.togglePreviewMode = function(preview) {
        isPreviewMode = preview;
        const wrapper = document.getElementById('rulebook-content-wrapper');
        const viewport = document.getElementById('rulebook-viewport-container');

        if (viewport) viewport.scrollTop = 0;

        const btnEdit = document.getElementById('btn-edit-mode');
        const btnPrev = document.getElementById('btn-preview-mode');

        if (preview) {
            if (wrapper) {
                wrapper.classList.remove('max-w-3xl', 'p-10');
                wrapper.classList.add('max-w-sm', 'p-4', 'mx-auto', 'preview-mode');
            }
            if (viewport) {
                viewport.classList.remove('p-8');
                viewport.classList.add('p-4', 'flex', 'justify-center', 'items-start');
            }
            if (btnPrev) btnPrev.className = 'px-3.5 py-1.5 rounded-lg text-xs font-bold bg-amber-500/10 text-amber-400 transition';
            if (btnEdit) btnEdit.className = 'px-3.5 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition';
        } else {
            if (wrapper) {
                wrapper.classList.remove('max-w-sm', 'p-4', 'mx-auto', 'preview-mode');
                wrapper.classList.add('max-w-3xl', 'p-10');
            }
            if (viewport) {
                viewport.classList.remove('p-4', 'flex', 'items-center', 'justify-center', 'items-start');
                viewport.classList.add('p-8');
            }
            if (btnEdit) btnEdit.className = 'px-3.5 py-1.5 rounded-lg text-xs font-bold bg-amber-500/10 text-amber-400 transition';
            if (btnPrev) btnPrev.className = 'px-3.5 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition';
        }

        renderBlocks();
    };

    window.showConfirmDialog = function(message, onConfirm) {
        const modal = document.getElementById('custom-confirm-modal');
        const msgEl = document.getElementById('custom-confirm-message');
        const btnOk = document.getElementById('btn-confirm-ok');
        const btnCancel = document.getElementById('btn-confirm-cancel');

        if (!modal || !msgEl || !btnOk || !btnCancel) {
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

        btnOk.onclick = () => { cleanUp(); onConfirm(); };
        btnCancel.onclick = () => { cleanUp(); };
    };

    window.getRulebookTheme = () => blocks.find(b => b.type === 'theme');
    window.setRulebookTheme = (newThemeProps) => {
        const theme = blocks.find(b => b.type === 'theme');
        if (theme) {
            Object.assign(theme, newThemeProps);
            syncSidebarThemeInputs(theme);
            if (window.rulebookTheme) window.rulebookTheme.applyThemeSettings(blocks);
            saveRulebook(true);
        }
    };

    window.updateThemeFont = (font) => window.setRulebookTheme({ fontFamily: font });
    window.updateThemeColor = (color) => {
        const colorHex = document.getElementById('theme-color-hex');
        if (colorHex) colorHex.textContent = color;
        window.setRulebookTheme({ accentColor: color });
    };
    window.updateThemeCss = (css) => window.setRulebookTheme({ customCss: css });
    window.updateThemeStyle = (style) => window.setRulebookTheme({ themeStyle: style });
    window.updateThemeSize = (size) => window.setRulebookTheme({ textSize: size });
    window.updateThemeDensity = (density) => window.setRulebookTheme({ spacingDensity: density });
    window.updateThemeAlign = (align) => window.setRulebookTheme({ headerAlign: align });

    window.triggerPrint = function() {
        const wasPreview = isPreviewMode;
        window.togglePreviewMode(true);
        setTimeout(() => {
            window.print();
            if (!wasPreview) window.togglePreviewMode(false);
        }, 300);
    };

    document.addEventListener('DOMContentLoaded', init);
})();

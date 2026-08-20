/**
 * Rulebook Markdown & Dataset Parser Module
 * Compiles markdown syntax, substitutes inline icons and glossary terms, and performs dynamic dataset row canvas replacements.
 */
(function() {
    'use strict';

    function substituteCanvasJson(canvasJson, row, assetMap) {
        if (!canvasJson || !row) return canvasJson;
        const data = typeof canvasJson === 'string' ? JSON.parse(canvasJson) : canvasJson;
        
        function processObjects(objectsList) {
            if (!objectsList || !Array.isArray(objectsList)) return;
            objectsList.forEach(obj => {
                if (obj.type === 'group' && Array.isArray(obj.objects)) {
                    processObjects(obj.objects);
                }
                
                if ((obj.type === 'text' || obj.type === 'i-text' || obj.type === 'textbox') && (obj.text || obj.variable_binding)) {
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
                
                if (obj.type === 'image' && obj.variable_binding) {
                    const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '');
                    const filename = row[colName];
                    if (filename && assetMap) {
                        let cleaned = filename.replace(/\[\[|\]\]/g, '').toLowerCase().trim();
                        let matchUrl = assetMap[cleaned] || assetMap[cleaned.replace(/[\s_\-]+/g, '')];
                        if (!matchUrl) {
                            const dotIdx = cleaned.lastIndexOf('.');
                            if (dotIdx > 0) {
                                const noExt = cleaned.substring(0, dotIdx);
                                matchUrl = assetMap[noExt] || assetMap[noExt.replace(/[\s_\-]+/g, '')];
                            }
                        }
                        if (matchUrl) {
                            obj.src = matchUrl;
                        }
                    }
                }

                if (obj.variable_binding && obj.type !== 'text' && obj.type !== 'i-text' && obj.type !== 'textbox' && obj.type !== 'image') {
                    const colName = obj.variable_binding.replace(/\{\{|\}\}/g, '');
                    const val = row[colName] !== undefined ? String(row[colName]).trim() : '';
                    if (val === 'transparent.png' || val === '0' || val === 'false' || val === 'none' || val === '' || val === 'hidden') {
                        obj.opacity = 0;
                        obj.visible = false;
                    } else {
                        obj.opacity = 1;
                        obj.visible = true;
                    }
                }
            });
        }

        if (data.objects) {
            processObjects(data.objects);
        }
        
        return data;
    }

    function parseMarkdownText(text, assetMap, glossaryMap) {
        if (!text) return '<p class="text-slate-500 italic">No content</p>';
        
        let html = text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");

        if (assetMap) {
            html = html.replace(/\[([a-zA-Z0-9_\-]+)\]/g, (match, tag) => {
                const lowerTag = tag.toLowerCase();
                if (assetMap[lowerTag]) {
                    return `<img src="${assetMap[lowerTag]}" class="inline-block h-[1.3em] align-middle px-0.5" alt="${tag}" title="[${tag}]">`;
                }
                return match;
            });
        }

        if (glossaryMap) {
            html = html.replace(/\[\[([a-zA-Z0-9_\-]+)\]\]/g, (match, key) => {
                const lowerKey = key.toLowerCase();
                if (glossaryMap[lowerKey]) {
                    const term = glossaryMap[lowerKey];
                    return `<span class="border-b border-dashed border-amber-500/70 text-amber-400 cursor-help font-semibold px-0.5" title="${term.name}: ${term.description.replace(/"/g, '&quot;')}">${term.name}</span>`;
                }
                return `<span class="text-rose-400 underline decoration-dotted" title="Glossary key not found: ${key}">${key}</span>`;
            });
        }

        html = html.replace(/^### (.*$)/gim, '<h4 class="text-sm font-bold text-slate-100 mt-3 mb-1">$1</h4>');
        html = html.replace(/^## (.*$)/gim, '<h3 class="text-base font-bold text-slate-100 mt-4 mb-2">$1</h3>');
        html = html.replace(/^# (.*$)/gim, '<h2 class="text-lg font-black text-white mt-6 mb-3">$1</h2>');
        html = html.replace(/\*\*(.*)\*\*/gim, '<strong>$1</strong>');
        html = html.replace(/\*(.*)\*/gim, '<em>$1</em>');
        html = html.replace(/\r?\n/g, '<br>');

        return html;
    }

    window.rulebookParser = {
        substituteCanvasJson,
        parseMarkdownText
    };
})();

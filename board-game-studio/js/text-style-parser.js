/**
 * Shared Text Styling Parser for Board Game Studio
 * Parses inline tags (<red>, <gold>, <color:#hex>, <b>, <i>, <u>) and applies 1D character styles to Fabric Textbox objects.
 */
(function() {
    'use strict';

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

    function parseStyledText(rawText) {
        if (rawText === null || rawText === undefined) return { cleanText: '', charStyles: [] };
        rawText = String(rawText).replace(/\r\n/g, '\n').replace(/\r/g, '\n');

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

    window.textStyleParser = {
        parseStyledText,
        applyStyledTextToObject,
        parseRowFilter,
        colorMap
    };
})();

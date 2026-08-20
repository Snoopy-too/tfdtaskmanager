/**
 * Rulebook Theme Module
 * Handles CSS dynamic injection, styling modes (dark, light, parchment), font loading, and presets exchange.
 */
(function() {
    'use strict';

    function applyThemeSettings(blocks) {
        const theme = (blocks || []).find(b => b.type === 'theme') || { fontFamily: 'Inter', accentColor: '#f59e0b', customCss: '' };
        
        const fontId = 'gfont-' + (theme.fontFamily || 'Inter').replace(/\s+/g, '-');
        if (!document.getElementById(fontId)) {
            let fontUrl;
            if (theme.fontFamily === 'Queensberry Vintage') {
                fontUrl = `https://fonts.googleapis.com/css2?family=IM+Fell+Double+Pica:ital@0;1&family=Special+Elite&display=swap`;
            } else {
                let weights = (theme.fontFamily === 'Share Tech Mono') ? '' : ':wght@400;600;800';
                fontUrl = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(theme.fontFamily || 'Inter')}${weights}&display=swap`;
            }
            const link = document.createElement('link');
            link.id = fontId;
            link.rel = 'stylesheet';
            link.href = fontUrl;
            document.head.appendChild(link);
        }

        let styleTag = document.getElementById('rulebook-custom-styles');
        if (!styleTag) {
            styleTag = document.createElement('style');
            styleTag.id = 'rulebook-custom-styles';
            document.head.appendChild(styleTag);
        }

        const activeStyle = theme.themeStyle || (theme.fontFamily === 'Queensberry Vintage' ? 'parchment' : 'dark');
        let baseCss = '';
        
        if (theme.fontFamily === 'Queensberry Vintage') {
            baseCss += `
                #rulebook-content-wrapper .prose, #rulebook-content-wrapper p, #rulebook-content-wrapper span, #rulebook-content-wrapper td, #rulebook-content-wrapper li {
                    font-family: 'Special Elite', monospace !important;
                }
                #rulebook-content-wrapper h1, #rulebook-content-wrapper h2, #rulebook-content-wrapper h3, #rulebook-content-wrapper h4 {
                    font-family: 'IM Fell Double Pica', Georgia, serif !important;
                    text-transform: uppercase; letter-spacing: 0.05em;
                }
                #rulebook-content-wrapper h2 {
                    border-bottom: 4px double currentColor !important; padding-bottom: 0.5rem; margin-top: 2rem;
                }
            `;
        } else {
            baseCss += `
                #rulebook-content-wrapper, #rulebook-content-wrapper .prose {
                    font-family: '${theme.fontFamily || 'Inter'}', sans-serif !important;
                }
            `;
        }

        if (activeStyle === 'parchment') {
            baseCss += `
                #rulebook-content-wrapper { background-color: #f2eee2 !important; color: #2c2421 !important; border: 1px solid #dcd7ca !important; box-sizing: border-box; }
                #rulebook-content-wrapper .prose, #rulebook-content-wrapper p, #rulebook-content-wrapper span, #rulebook-content-wrapper td, #rulebook-content-wrapper li { color: #37302d !important; }
                #rulebook-content-wrapper .prose-invert, #rulebook-content-wrapper .prose-invert *, #rulebook-content-wrapper .text-slate-300, #rulebook-content-wrapper .text-slate-400 { color: #37302d !important; }
                #rulebook-content-wrapper .prose-invert h1, #rulebook-content-wrapper .prose-invert h2, #rulebook-content-wrapper .prose-invert h3, #rulebook-content-wrapper .prose-invert h4, #rulebook-content-wrapper .prose-invert strong { color: #2c2421 !important; }
                #rulebook-content-wrapper h1, #rulebook-content-wrapper h2, #rulebook-content-wrapper h3, #rulebook-content-wrapper h4 { color: #2c2421 !important; }
                #rulebook-content-wrapper:not(.preview-mode) .block-card { background-color: #eae6db !important; border: 1px dashed #b9b09c !important; box-shadow: 0 4px 12px rgba(44, 36, 33, 0.06) !important; }
                #rulebook-content-wrapper.preview-mode .block-card { background: transparent !important; border: none !important; box-shadow: none !important; }
                #rulebook-content-wrapper:not(.preview-mode) textarea, #rulebook-content-wrapper:not(.preview-mode) select, #rulebook-content-wrapper:not(.preview-mode) input[type="text"] { background-color: #faf8f5 !important; color: #2c2421 !important; border: 1px solid #b9b09c !important; }
                #rulebook-content-wrapper:not(.preview-mode) .bg-slate-950\\/40, #rulebook-content-wrapper:not(.preview-mode) [class*="bg-slate-950"] { background-color: #faf8f5 !important; border-color: #d4cbb5 !important; color: #37302d !important; }
                #rulebook-content-wrapper table { border-collapse: collapse !important; border: 2px solid #2c2421 !important; }
                #rulebook-content-wrapper table thead, #rulebook-content-wrapper table thead th { background-color: #eae6db !important; border-bottom: 2px solid #2c2421 !important; color: #2c2421 !important; }
                #rulebook-content-wrapper table tbody tr, #rulebook-content-wrapper table tbody td { background-color: transparent !important; border-bottom: 1px solid #dcd7ca !important; color: #37302d !important; }
                #rulebook-content-wrapper .alert-box, #rulebook-content-wrapper .bg-rose-500\\/10, #rulebook-content-wrapper [class*="bg-red-"] { background-color: #f5eae8 !important; border: 1px solid #8f2d30 !important; color: #8f2d30 !important; }
                #rulebook-content-wrapper .anatomy-pin, #rulebook-content-wrapper [class*="bg-amber-500"] { background-color: #1b2d42 !important; border-color: #1b2d42 !important; color: #e6c895 !important; }
                #rulebook-content-wrapper .pattern-grid { background-color: #eae6db !important; background-image: radial-gradient(#c5bba4 1px, transparent 0) !important; border: 1px solid #b9b09c !important; }
                #rulebook-content-wrapper .flex.items-start.space-x-3.bg-slate-950\\/60, #rulebook-content-wrapper [class*="bg-slate-950/60"] { color: #2c2421 !important; }
            `;
        } else if (activeStyle === 'light') {
            baseCss += `
                #rulebook-content-wrapper { background-color: #ffffff !important; color: #1f2937 !important; border: 1px solid #e5e7eb !important; box-sizing: border-box; }
                #rulebook-content-wrapper .prose, #rulebook-content-wrapper p, #rulebook-content-wrapper span, #rulebook-content-wrapper td, #rulebook-content-wrapper li { color: #374151 !important; }
                #rulebook-content-wrapper .prose-invert, #rulebook-content-wrapper .prose-invert *, #rulebook-content-wrapper .text-slate-300, #rulebook-content-wrapper .text-slate-400 { color: #374151 !important; }
                #rulebook-content-wrapper .prose-invert h1, #rulebook-content-wrapper .prose-invert h2, #rulebook-content-wrapper .prose-invert h3, #rulebook-content-wrapper .prose-invert h4, #rulebook-content-wrapper .prose-invert strong { color: #111827 !important; }
                #rulebook-content-wrapper h1, #rulebook-content-wrapper h2, #rulebook-content-wrapper h3, #rulebook-content-wrapper h4 { color: #111827 !important; }
                #rulebook-content-wrapper:not(.preview-mode) .block-card { background-color: #f9fafb !important; border: 1px solid #e5e7eb !important; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03) !important; }
                #rulebook-content-wrapper.preview-mode .block-card { background: transparent !important; border: none !important; box-shadow: none !important; }
                #rulebook-content-wrapper:not(.preview-mode) textarea, #rulebook-content-wrapper:not(.preview-mode) select, #rulebook-content-wrapper:not(.preview-mode) input[type="text"] { background-color: #ffffff !important; color: #1f2937 !important; border: 1px solid #d1d5db !important; }
                #rulebook-content-wrapper:not(.preview-mode) .bg-slate-950\\/40, #rulebook-content-wrapper:not(.preview-mode) [class*="bg-slate-950"] { background-color: #f3f4f6 !important; border-color: #e5e7eb !important; color: #1f2937 !important; }
                #rulebook-content-wrapper table { border-collapse: collapse !important; border: 1px solid #e5e7eb !important; }
                #rulebook-content-wrapper table thead, #rulebook-content-wrapper table thead th { background-color: #f3f4f6 !important; border-bottom: 2px solid #e5e7eb !important; color: #111827 !important; }
                #rulebook-content-wrapper table tbody tr, #rulebook-content-wrapper table tbody td { background-color: transparent !important; border-bottom: 1px solid #f3f4f6 !important; color: #374151 !important; }
                #rulebook-content-wrapper .alert-box, #rulebook-content-wrapper .bg-rose-500\\/10, #rulebook-content-wrapper [class*="bg-red-"] { background-color: #fef2f2 !important; border: 1px solid #fee2e2 !important; color: #991b1b !important; }
                #rulebook-content-wrapper .anatomy-pin, #rulebook-content-wrapper [class*="bg-amber-500"] { background-color: #111827 !important; border-color: #111827 !important; color: #ffffff !important; }
                #rulebook-content-wrapper .pattern-grid { background-color: #f9fafb !important; background-image: radial-gradient(#e5e7eb 1px, transparent 0) !important; border: 1px solid #e5e7eb !important; }
                #rulebook-content-wrapper .flex.items-start.space-x-3.bg-slate-950\\/60, #rulebook-content-wrapper [class*="bg-slate-950/60"] { color: #1f2937 !important; }
            `;
        }

        const textSize = theme.textSize || 'medium';
        if (textSize === 'small') {
            baseCss += `#rulebook-content-wrapper { font-size: 13px !important; } #rulebook-content-wrapper h1 { font-size: 1.75rem !important; } #rulebook-content-wrapper h2 { font-size: 1.35rem !important; } #rulebook-content-wrapper h3 { font-size: 1.1rem !important; }`;
        } else if (textSize === 'large') {
            baseCss += `#rulebook-content-wrapper { font-size: 17px !important; } #rulebook-content-wrapper h1 { font-size: 2.5rem !important; } #rulebook-content-wrapper h2 { font-size: 1.85rem !important; } #rulebook-content-wrapper h3 { font-size: 1.4rem !important; }`;
        }

        const density = theme.spacingDensity || 'normal';
        if (density === 'compact') {
            baseCss += `#rulebook-content-wrapper .block-card { padding-top: 0.75rem !important; padding-bottom: 0.75rem !important; margin-bottom: 0.5rem !important; }`;
        } else if (density === 'spacious') {
            baseCss += `#rulebook-content-wrapper .block-card { padding-top: 2.5rem !important; padding-bottom: 2.5rem !important; margin-bottom: 2rem !important; }`;
        }

        if ((theme.headerAlign || 'left') === 'center') {
            baseCss += `#rulebook-content-wrapper h1, #rulebook-content-wrapper h2, #rulebook-content-wrapper h3, #rulebook-content-wrapper h4, #rulebook-content-wrapper .markdown-header { text-align: center !important; }`;
        }

        styleTag.innerHTML = `
            ${baseCss}
            :root { --theme-accent-color: ${theme.accentColor || '#f59e0b'} !important; }
            ${theme.customCss || ''}
        `;
    }

    function initPresetsDropdown() {
        const select = document.getElementById('theme-presets-select');
        if (!select) return;
        select.innerHTML = '<option value="">-- Select Saved Preset --</option>';
        
        let presets = {};
        try {
            presets = JSON.parse(localStorage.getItem('bg_theme_presets')) || {};
        } catch (e) {
            presets = {};
        }
        
        for (const name in presets) {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            select.appendChild(opt);
        }
    }

    window.switchEditorSidebarTab = function(tab) {
        const tabBlocks = document.getElementById('tab-content-blocks');
        const tabTheme = document.getElementById('tab-content-theme');
        const btnBlocks = document.getElementById('btn-sidebar-blocks');
        const btnTheme = document.getElementById('btn-sidebar-theme');

        if (tab === 'theme') {
            if (tabBlocks) tabBlocks.classList.add('hidden');
            if (tabTheme) tabTheme.classList.remove('hidden');
            if (btnTheme) btnTheme.classList.replace('border-transparent', 'border-amber-500');
            if (btnBlocks) btnBlocks.classList.replace('border-amber-500', 'border-transparent');
        } else {
            if (tabBlocks) tabBlocks.classList.remove('hidden');
            if (tabTheme) tabTheme.classList.add('hidden');
            if (btnBlocks) btnBlocks.classList.replace('border-transparent', 'border-amber-500');
            if (btnTheme) btnTheme.classList.replace('border-amber-500', 'border-transparent');
        }
    };

    window.saveThemePreset = async function() {
        let name = (typeof window.studioPrompt === 'function') 
            ? await window.studioPrompt("Enter a name for this theme preset:", "", "Save Theme Preset")
            : prompt("Enter a name for this theme preset:");
        if (!name || !name.trim()) return;
        const trimmedName = name.trim();
        
        const theme = window.getRulebookTheme ? window.getRulebookTheme() : null;
        if (!theme) return;
        
        let presets = {};
        try { presets = JSON.parse(localStorage.getItem('bg_theme_presets')) || {}; } catch (e) { presets = {}; }
        
        presets[trimmedName] = {
            fontFamily: theme.fontFamily || 'Inter',
            accentColor: theme.accentColor || '#f59e0b',
            themeStyle: theme.themeStyle || 'dark',
            textSize: theme.textSize || 'medium',
            spacingDensity: theme.spacingDensity || 'normal',
            headerAlign: theme.headerAlign || 'left',
            customCss: theme.customCss || ''
        };
        
        localStorage.setItem('bg_theme_presets', JSON.stringify(presets));
        initPresetsDropdown();
        const sel = document.getElementById('theme-presets-select');
        if (sel) sel.value = trimmedName;
        if (typeof window.studioAlert === 'function') {
            window.studioAlert(`Preset "${trimmedName}" saved successfully!`, "Preset Saved");
        } else {
            alert(`Preset "${trimmedName}" saved successfully!`);
        }
    };

    window.deleteThemePreset = async function() {
        const select = document.getElementById('theme-presets-select');
        if (!select || !select.value) return;
        const name = select.value;
        
        let confirmed = (typeof window.studioConfirm === 'function')
            ? await window.studioConfirm(`Are you sure you want to delete the preset "${name}"?`, "Delete", "Delete Preset")
            : confirm(`Are you sure you want to delete the preset "${name}"?`);

        if (confirmed) {
            let presets = {};
            try { presets = JSON.parse(localStorage.getItem('bg_theme_presets')) || {}; } catch (e) { presets = {}; }
            delete presets[name];
            localStorage.setItem('bg_theme_presets', JSON.stringify(presets));
            initPresetsDropdown();
        }
    };

    window.loadThemePreset = function(name) {
        if (!name) return;
        let presets = {};
        try { presets = JSON.parse(localStorage.getItem('bg_theme_presets')) || {}; } catch (e) { presets = {}; }
        const preset = presets[name];
        if (!preset) return;
        
        if (window.setRulebookTheme) {
            window.setRulebookTheme(preset);
        }
    };

    window.exportTheme = function() {
        const theme = window.getRulebookTheme ? window.getRulebookTheme() : null;
        if (!theme) return;
        
        const themeData = {
            name: (theme.fontFamily || 'Custom') + ' Theme',
            fontFamily: theme.fontFamily || 'Inter',
            accentColor: theme.accentColor || '#f59e0b',
            themeStyle: theme.themeStyle || 'dark',
            textSize: theme.textSize || 'medium',
            spacingDensity: theme.spacingDensity || 'normal',
            headerAlign: theme.headerAlign || 'left',
            customCss: theme.customCss || ''
        };
        
        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(themeData, null, 2));
        const a = document.createElement('a');
        a.setAttribute("href", dataStr);
        a.setAttribute("download", `${themeData.name.toLowerCase().replace(/\s+/g, '-')}.json`);
        document.body.appendChild(a);
        a.click();
        a.remove();
    };

    window.importTheme = function(file) {
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(event) {
            try {
                const imported = JSON.parse(event.target.result);
                if (!imported.fontFamily || !imported.accentColor) {
                    alert("Invalid theme file: fontFamily and accentColor are required.");
                    return;
                }
                if (window.setRulebookTheme) {
                    window.setRulebookTheme(imported);
                    document.getElementById('theme-import-input').value = '';
                    alert(`Theme "${imported.name || 'Imported'}" applied successfully!`);
                }
            } catch (e) {
                alert("Error reading theme JSON file: " + e.message);
            }
        };
        reader.readAsText(file);
    };

    window.rulebookTheme = {
        applyThemeSettings,
        initPresetsDropdown
    };
})();

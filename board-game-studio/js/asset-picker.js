/**
 * Asset Picker Module
 * Loads assets from API, populates the assets tab, handles adding image layers
 * and dynamically registering uploaded custom fonts.
 */
(function() {
    'use strict';

    let assetsLoaded = false;
    const registeredFonts = new Set();

    function loadAssets() {
        const grid = document.getElementById('asset-picker-grid');
        if (!grid) return;

        // Only load once unless requested
        if (assetsLoaded) return;
        
        grid.innerHTML = '<div class="col-span-2 text-center text-xs text-slate-500 py-6">Loading assets...</div>';

        fetch(`api.php?action=list_assets&project_id=${window.studioConfig.projectId}`)
        .then(response => response.json())
        .then(assets => {
            grid.innerHTML = '';
            assetsLoaded = true;

            if (!Array.isArray(assets)) {
                const errMsg = assets.error || 'Failed to load assets. Please log in again.';
                grid.innerHTML = `<div class="col-span-2 text-center text-xs text-red-500 py-6">${errMsg}</div>`;
                return;
            }

            if (assets.length === 0) {
                grid.innerHTML = '<div class="col-span-2 text-center text-xs text-slate-500 py-6">No assets uploaded.</div>';
                return;
            }

            assets.forEach(asset => {
                const isImage = asset.mime_type.startsWith('image/');
                const ext = asset.original_filename.split('.').pop().toLowerCase();
                const isFont = asset.mime_type.includes('font') || ['ttf', 'otf'].includes(ext);

                const card = document.createElement('div');
                card.className = "bg-slate-950 border border-slate-800 rounded-xl overflow-hidden p-2 hover:border-indigo-500/40 transition cursor-pointer flex flex-col items-center justify-between text-center space-y-2 group";
                
                // Set click action
                card.addEventListener('click', () => {
                    if (isImage) {
                        addImageLayer(asset);
                    } else if (isFont) {
                        registerAndSelectFont(asset);
                    }
                });

                // Thumbnail area
                const preview = document.createElement('div');
                preview.className = "h-20 w-full flex items-center justify-center bg-slate-900/60 rounded-lg overflow-hidden p-1 relative";

                if (isImage) {
                    const img = document.createElement('img');
                    img.src = asset.url;
                    img.className = "max-h-full max-w-full object-contain group-hover:scale-105 transition duration-200";
                    preview.appendChild(img);
                } else if (isFont) {
                    preview.innerHTML = `
                        <svg class="h-8 w-8 text-indigo-400/80 bg-indigo-500/10 p-1.5 rounded-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    `;
                    // Load and register font in background so it displays nicely
                    loadFontFace(asset);
                }

                const label = document.createElement('span');
                label.className = "text-[10px] text-slate-400 truncate w-full group-hover:text-slate-200 transition font-medium";
                label.textContent = asset.original_filename;
                label.title = asset.original_filename;

                card.appendChild(preview);
                card.appendChild(label);
                grid.appendChild(card);
            });
        })
        .catch(err => {
            grid.innerHTML = '<div class="col-span-2 text-center text-xs text-rose-500 py-6">Failed to load assets.</div>';
            console.error(err);
        });
    }

    // Dynamic Font Injection
    function loadFontFace(asset) {
        const fontName = `font_asset_${asset.id}`;
        if (registeredFonts.has(fontName)) return fontName;

        const newStyle = document.createElement('style');
        newStyle.id = `style_${fontName}`;
        newStyle.appendChild(document.createTextNode(`
            @font-face {
                font-family: '${fontName}';
                src: url('${asset.url}');
            }
        `));
        document.head.appendChild(newStyle);
        registeredFonts.add(fontName);

        // Add font family to selector in Property Inspector
        const fontSelect = document.getElementById('prop-font-family');
        if (fontSelect) {
            // Check if already in options
            let found = false;
            for (let i = 0; i < fontSelect.options.length; i++) {
                if (fontSelect.options[i].value === fontName) {
                    found = true;
                    break;
                }
            }
            if (!found) {
                const opt = document.createElement('option');
                opt.value = fontName;
                opt.textContent = asset.original_filename.split('.')[0]; // Use clean name
                fontSelect.appendChild(opt);
            }
        }

        return fontName;
    }

    // Add Image to Canvas
    function addImageLayer(asset) {
        const canvas = window.editorCanvas;
        if (!canvas) return;

        fabric.Image.fromURL(asset.url, (img) => {
            // Keep size reasonable inside card workspace
            const maxWidth = canvas.width * 0.7;
            const maxHeight = canvas.height * 0.7;
            let scale = 1.0;

            if (img.width > maxWidth || img.height > maxHeight) {
                scale = Math.min(maxWidth / img.width, maxHeight / img.height);
            }

            img.set({
                left: canvas.width / 2,
                top: canvas.height / 2,
                originX: 'center',
                originY: 'center',
                scaleX: scale,
                scaleY: scale,
                name: asset.original_filename,
                original_filename: asset.original_filename,
                stored_filename: asset.stored_filename
            });

            canvas.add(img);
            canvas.setActiveObject(img);
            canvas.renderAll();
            window.editorCore.triggerAutoSave();
        }, { crossOrigin: 'anonymous' });
    }

    // Select custom font for active text layer
    function registerAndSelectFont(asset) {
        const canvas = window.editorCanvas;
        if (!canvas) return;

        const fontName = loadFontFace(asset);
        const activeObj = canvas.getActiveObject();
        
        if (activeObj && (activeObj.type === 'i-text' || activeObj.type === 'text')) {
            // Set font on select and input dropdown
            document.getElementById('prop-font-family').value = fontName;
            activeObj.set('fontFamily', fontName);
            canvas.renderAll();
            window.editorCore.triggerAutoSave();
        } else {
            // Alert user to select or create a text layer
            window.studioAlert(`Font "${asset.original_filename}" registered successfully! Select a text layer and apply this font from the Properties panel.`, 'Font Registered');
        }
    }

    window.assetPicker = {
        loadAssets: loadAssets,
        loadFontFace: loadFontFace
    };
})();

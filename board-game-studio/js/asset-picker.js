/**
 * Asset Picker Module
 * Loads assets from API, populates the assets tab, handles adding image layers
 * and dynamically registering uploaded custom fonts.
 */
(function() {
    'use strict';

    let assetsLoaded = false;
    let cachedAssets = [];
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
            cachedAssets = Array.isArray(assets) ? assets : [];

            if (!Array.isArray(assets)) {
                const errMsg = assets.error || 'Failed to load assets. Please log in again.';
                grid.innerHTML = `<div class="col-span-2 text-center text-xs text-red-500 py-6">${errMsg}</div>`;
                return;
            }

            // Register custom uploaded fonts
            assets.filter(a => a.mime_type === 'font/ttf' || a.mime_type === 'font/otf' || a.original_filename.endsWith('.ttf') || a.original_filename.endsWith('.otf'))
                .forEach(fontAsset => loadFontFace(fontAsset));

            // Re-apply template engine image bindings now that assets are cached
            if (window.templateEngine && typeof window.templateEngine.applyBindings === 'function') {
                window.templateEngine.applyBindings();
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
                card.setAttribute('draggable', 'true');
                
                card.addEventListener('dragstart', (e) => {
                    e.dataTransfer.setData('application/json', JSON.stringify({
                        asset: asset,
                        isImage: isImage,
                        isFont: isFont
                    }));
                    e.dataTransfer.effectAllowed = 'copy';
                    card.classList.add('opacity-50');
                });

                card.addEventListener('dragend', () => {
                    card.classList.remove('opacity-50');
                });
                
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
            // Refresh canvas text layers to apply newly registered custom fonts!
            if (window.editorCore && typeof window.editorCore.refreshCanvasTextLayers === 'function') {
                window.editorCore.refreshCanvasTextLayers();
            }
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

    // Add Image or SVG to Canvas
    function addImageLayer(asset, left, top) {
        const canvas = window.editorCanvas;
        if (!canvas) return;

        const isSvg = asset.mime_type === 'image/svg+xml' || asset.original_filename.endsWith('.svg');
        const posX = (typeof left === 'number') ? left : (canvas.width / 2);
        const posY = (typeof top === 'number') ? top : (canvas.height / 2);

        if (isSvg) {
            fabric.loadSVGFromURL(asset.url, (objects, options) => {
                const svgObj = fabric.util.groupSVGElements(objects, options);
                
                // Keep size reasonable inside card workspace
                const maxWidth = canvas.width * 0.4;
                const maxHeight = canvas.height * 0.4;
                let scale = 1.0;

                if (svgObj.width > maxWidth || svgObj.height > maxHeight) {
                    scale = Math.min(maxWidth / svgObj.width, maxHeight / svgObj.height);
                }

                svgObj.set({
                    left: posX,
                    top: posY,
                    originX: 'center',
                    originY: 'center',
                    scaleX: scale,
                    scaleY: scale,
                    name: asset.original_filename,
                    original_filename: asset.original_filename,
                    stored_filename: asset.stored_filename
                });

                // Default our vector icons to dark charcoal (#1e293b) if they don't have a visible stroke
                if (svgObj.getObjects) {
                    svgObj.getObjects().forEach(child => {
                        if (!child.stroke || child.stroke === 'currentColor' || child.stroke === 'none') {
                            child.set('stroke', '#1e293b');
                        }
                    });
                }
                svgObj.set('stroke', '#1e293b');

                canvas.add(svgObj);
                canvas.setActiveObject(svgObj);
                canvas.renderAll();
                window.editorCore.triggerAutoSave();
            });
        } else {
            fabric.Image.fromURL(asset.url, (img) => {
                // Keep size reasonable inside card workspace
                const maxWidth = canvas.width * 0.7;
                const maxHeight = canvas.height * 0.7;
                let scale = 1.0;

                if (img.width > maxWidth || img.height > maxHeight) {
                    scale = Math.min(maxWidth / img.width, maxHeight / img.height);
                }

                img.set({
                    left: posX,
                    top: posY,
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

    // Setup Canvas Drag and Drop Dropzone
    document.addEventListener('DOMContentLoaded', () => {
        const wrapper = document.getElementById('canvas-container-wrapper');
        if (wrapper) {
            let dragCounter = 0;
            
            wrapper.addEventListener('dragenter', (e) => {
                e.preventDefault();
                dragCounter++;
                if (dragCounter === 1) {
                    wrapper.classList.add('drag-over');
                }
            });
            
            wrapper.addEventListener('dragleave', (e) => {
                e.preventDefault();
                dragCounter--;
                if (dragCounter === 0) {
                    wrapper.classList.remove('drag-over');
                }
            });
            
            wrapper.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'copy';
            });
            
            wrapper.addEventListener('drop', (e) => {
                e.preventDefault();
                dragCounter = 0;
                wrapper.classList.remove('drag-over');
                
                let data;
                try {
                    data = JSON.parse(e.dataTransfer.getData('application/json'));
                } catch (err) {
                    console.error("Failed to parse drag and drop JSON:", err);
                    return;
                }
                
                if (!data || !data.asset) return;
                
                const canvas = window.editorCanvas;
                if (!canvas) return;
                
                const rect = canvas.getElement().getBoundingClientRect();
                const zoom = (window.editorCore && typeof window.editorCore.getZoomLevel === 'function')
                    ? window.editorCore.getZoomLevel()
                    : 1.0;
                    
                const x = (e.clientX - rect.left) / zoom;
                const y = (e.clientY - rect.top) / zoom;
                
                if (data.isImage) {
                    addImageLayer(data.asset, x, y);
                } else if (data.isFont) {
                    // Detect if dropped over a text object
                    let targetTextObject = null;
                    const pointer = new fabric.Point(x, y);
                    const objects = canvas.getObjects();
                    
                    for (let i = objects.length - 1; i >= 0; i--) {
                        const obj = objects[i];
                        if (obj.id === 'safe-zone-guide' || obj.id === 'bleed-zone-guide') continue;
                        if (obj.containsPoint(pointer) && (obj.type === 'i-text' || obj.type === 'text')) {
                            targetTextObject = obj;
                            break;
                        }
                    }
                    
                    const fontName = loadFontFace(data.asset);
                    if (targetTextObject) {
                        const fontSelect = document.getElementById('prop-font-family');
                        if (fontSelect) {
                            fontSelect.value = fontName;
                        }
                        targetTextObject.set('fontFamily', fontName);
                        canvas.setActiveObject(targetTextObject);
                        canvas.renderAll();
                        window.editorCore.triggerAutoSave();
                    } else {
                        // Create a new text object at the dropped position
                        const defaultFontSize = Math.max(28, Math.round(canvas.height * 0.05));
                        const defaultWidth = Math.round(canvas.width * 0.8);
                        const text = new fabric.Textbox('Text Layer', {
                            left: x,
                            top: y,
                            originX: 'center',
                            originY: 'center',
                            width: defaultWidth,
                            fontFamily: fontName,
                            fontSize: defaultFontSize,
                            fill: '#1e293b',
                            name: 'Text Layer'
                        });
                        canvas.add(text);
                        canvas.setActiveObject(text);
                        canvas.renderAll();
                        window.editorCore.triggerAutoSave();
                    }
                }
            });
        }
    });

    /**
     * Resolve an original filename to its full asset URL.
     * Used by template-engine and export-handler for per-row image binding.
     * @param {string} filename - The original_filename of the asset.
     * @returns {string|null} The asset URL, or null if not found.
     */
    function getAssetUrlByFilename(filename) {
        if (!filename) return null;
        const match = cachedAssets.find(a => a.original_filename === filename);
        return match ? match.url : null;
    }

    window.assetPicker = {
        loadAssets: loadAssets,
        loadFontFace: loadFontFace,
        getAssetUrlByFilename: getAssetUrlByFilename,
        getCachedAssets: () => cachedAssets
    };

    // ponytail: auto-load project assets on page init so dataset image bindings resolve immediately on hard refresh
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadAssets);
    } else {
        loadAssets();
    }
})();

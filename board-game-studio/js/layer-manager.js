/**
 * Layer Manager Module
 * Manages layer creation, ordering, locking, visibility, and list updates.
 */
(function() {
    'use strict';

    function initLayers() {
        // Wire up creation buttons
        document.getElementById('btn-add-text').addEventListener('click', addTextLayer);
        document.getElementById('btn-add-rect').addEventListener('click', addRectLayer);
        document.getElementById('btn-add-circle').addEventListener('click', addCircleLayer);
        
        // Image: switch to Assets tab so the user can select an uploaded image to place
        document.getElementById('btn-add-image').addEventListener('click', () => {
            document.getElementById('tab-assets-btn').click();
        });
        
        // Setup simple tab switching for Left Sidebar
        const tabLayersBtn = document.getElementById('tab-layers-btn');
        const tabAssetsBtn = document.getElementById('tab-assets-btn');
        const tabLayersView = document.getElementById('tab-layers-view');
        const tabAssetsView = document.getElementById('tab-assets-view');

        tabLayersBtn.addEventListener('click', () => {
            tabLayersBtn.className = "flex-1 py-3 text-center text-xs font-bold uppercase tracking-wider text-indigo-400 border-b-2 border-indigo-400";
            tabAssetsBtn.className = "flex-1 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-400 hover:text-slate-200";
            tabLayersView.classList.remove('hidden');
            tabAssetsView.classList.add('hidden');
        });

        tabAssetsBtn.addEventListener('click', () => {
            tabAssetsBtn.className = "flex-1 py-3 text-center text-xs font-bold uppercase tracking-wider text-indigo-400 border-b-2 border-indigo-400";
            tabLayersBtn.className = "flex-1 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-400 hover:text-slate-200";
            tabAssetsView.classList.remove('hidden');
            tabLayersView.classList.add('hidden');
            
            // Trigger asset load
            if (window.assetPicker && typeof window.assetPicker.loadAssets === 'function') {
                window.assetPicker.loadAssets();
            }
        });
    }

    // Add Text Layer
    function addTextLayer() {
        const canvas = window.editorCanvas;
        const defaultFontSize = Math.max(28, Math.round(canvas.height * 0.05));
        const text = new fabric.IText('Double click to edit', {
            left: canvas.width / 2,
            top: canvas.height / 2,
            originX: 'center',
            originY: 'center',
            fontFamily: 'Plus Jakarta Sans',
            fontSize: defaultFontSize,
            fill: '#1e293b',
            name: 'Text Layer'
        });
        canvas.add(text);
        canvas.setActiveObject(text);
        canvas.renderAll();
        window.editorCore.triggerAutoSave();
    }

    // Add Rectangle Layer
    function addRectLayer() {
        const canvas = window.editorCanvas;
        const dim = Math.max(150, Math.round(canvas.height * 0.15));
        const rect = new fabric.Rect({
            left: canvas.width / 2,
            top: canvas.height / 2,
            originX: 'center',
            originY: 'center',
            fill: '#c7d2fe',
            width: dim,
            height: dim,
            name: 'Rectangle Layer'
        });
        canvas.add(rect);
        canvas.setActiveObject(rect);
        canvas.renderAll();
        window.editorCore.triggerAutoSave();
    }

    // Add Circle Layer
    function addCircleLayer() {
        const canvas = window.editorCanvas;
        const radius = Math.max(75, Math.round(canvas.height * 0.075));
        const circle = new fabric.Circle({
            left: canvas.width / 2,
            top: canvas.height / 2,
            originX: 'center',
            originY: 'center',
            fill: '#fbcfe8',
            radius: radius,
            name: 'Circle Layer'
        });
        canvas.add(circle);
        canvas.setActiveObject(circle);
        canvas.renderAll();
        window.editorCore.triggerAutoSave();
    }

    // Render Sidebar List
    function renderLayersList() {
        const canvas = window.editorCanvas;
        const container = document.getElementById('layers-list');
        if (!canvas || !container) return;

        container.innerHTML = '';
        const objects = canvas.getObjects();
        
        // Reverse iterate to display from front (top z-index) to back
        for (let i = objects.length - 1; i >= 0; i--) {
            const obj = objects[i];
            
            // Skip Guides
            if (obj.id === 'safe-zone-guide' || obj.id === 'bleed-zone-guide') {
                continue;
            }

            const activeObject = canvas.getActiveObject();
            const isActive = activeObject === obj;

            const item = document.createElement('div');
            item.className = `flex items-center justify-between p-2 rounded-lg text-xs font-semibold cursor-pointer border ${isActive ? 'bg-indigo-500/10 border-indigo-500/30 text-white' : 'bg-slate-900 border-slate-800 text-slate-300 hover:bg-slate-800/80 hover:text-white'} transition`;

            // Left part: Icon, Layer Name
            const leftBlock = document.createElement('div');
            leftBlock.className = 'flex items-center space-x-2 truncate flex-grow';
            leftBlock.addEventListener('click', () => {
                canvas.setActiveObject(obj);
                canvas.renderAll();
            });

            // Small type indicator badge
            const typeBadge = document.createElement('span');
            typeBadge.className = 'px-1 rounded bg-slate-950 text-[9px] uppercase tracking-wider text-slate-500';
            typeBadge.textContent = obj.type === 'i-text' ? 'TXT' : (obj.type === 'image' ? 'IMG' : 'SHP');

            const nameSpan = document.createElement('span');
            nameSpan.className = 'truncate pr-2';
            nameSpan.textContent = obj.name || `${obj.type} Layer`;

            leftBlock.appendChild(typeBadge);
            leftBlock.appendChild(nameSpan);

            // Right part: Action controls (Visibility, Locking, Move Up/Down, Delete)
            const rightBlock = document.createElement('div');
            rightBlock.className = 'flex items-center space-x-1.5 flex-shrink-0';

            // Visibility Toggle
            const btnVisible = document.createElement('button');
            btnVisible.className = `p-1 hover:text-white ${obj.visible ? 'text-indigo-400' : 'text-slate-600'}`;
            btnVisible.innerHTML = obj.visible ? 
                `<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>` : 
                `<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>`;
            btnVisible.addEventListener('click', (e) => {
                e.stopPropagation();
                obj.visible = !obj.visible;
                canvas.renderAll();
                renderLayersList();
                window.editorCore.triggerAutoSave();
            });

            // Lock Toggle
            const btnLock = document.createElement('button');
            const isLocked = obj.lockMovementX || false;
            btnLock.className = `p-1 hover:text-white ${isLocked ? 'text-indigo-400' : 'text-slate-600'}`;
            btnLock.innerHTML = isLocked ? 
                `<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 00-2 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>` : 
                `<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 00-2 2z"/></svg>`;
            btnLock.addEventListener('click', (e) => {
                e.stopPropagation();
                const newLockState = !isLocked;
                obj.lockMovementX = newLockState;
                obj.lockMovementY = newLockState;
                obj.lockRotation = newLockState;
                obj.lockScalingX = newLockState;
                obj.lockScalingY = newLockState;
                canvas.renderAll();
                renderLayersList();
                window.editorCore.triggerAutoSave();
            });

            // Reordering up
            const btnUp = document.createElement('button');
            btnUp.className = 'p-1 hover:text-white text-slate-600';
            btnUp.innerHTML = `<svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>`;
            btnUp.addEventListener('click', (e) => {
                e.stopPropagation();
                canvas.bringForward(obj);
                // Keep guides on top
                if (window.guideRenderer && typeof window.guideRenderer.renderGuides === 'function') {
                    window.guideRenderer.renderGuides();
                }
                canvas.renderAll();
                renderLayersList();
                window.editorCore.triggerAutoSave();
            });

            // Reordering down
            const btnDown = document.createElement('button');
            btnDown.className = 'p-1 hover:text-white text-slate-600';
            btnDown.innerHTML = `<svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>`;
            btnDown.addEventListener('click', (e) => {
                e.stopPropagation();
                canvas.sendBackwards(obj);
                // Make sure it doesn't go below index 0 if index 0 is not a guide
                canvas.renderAll();
                renderLayersList();
                window.editorCore.triggerAutoSave();
            });

            // Delete Layer
            const btnDelete = document.createElement('button');
            btnDelete.className = 'p-1 hover:text-rose-500 text-slate-600';
            btnDelete.innerHTML = `<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>`;
            btnDelete.addEventListener('click', (e) => {
                e.stopPropagation();
                if (confirm(`Remove layer: "${obj.name || obj.type}"?`)) {
                    canvas.remove(obj);
                    canvas.discardActiveObject();
                    canvas.renderAll();
                    renderLayersList();
                    window.editorCore.triggerAutoSave();
                }
            });

            rightBlock.appendChild(btnVisible);
            rightBlock.appendChild(btnLock);
            rightBlock.appendChild(btnUp);
            rightBlock.appendChild(btnDown);
            rightBlock.appendChild(btnDelete);

            item.appendChild(leftBlock);
            item.appendChild(rightBlock);
            container.appendChild(item);
        }

        // Empty state
        if (container.children.length === 0) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center h-48 text-center px-4 space-y-3">
                    <svg class="h-8 w-8 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <p class="text-xs font-medium text-slate-400">Your canvas is empty.</p>
                    <p class="text-[10px] text-slate-500">Click the buttons above to add Text, Shapes, or Images to build your card.</p>
                </div>
            `;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initLayers();
    });

    window.layerManager = {
        renderLayersList: renderLayersList
    };
})();

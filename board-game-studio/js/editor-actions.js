/**
 * Editor Actions & UI Controls
 * Handles template renaming, duplication, and fullscreen preview overlays.
 */

function promptRenameTemplate(templateId, currentName) {
    const handleName = (newName) => {
        if (newName && newName.trim() !== "" && newName.trim() !== currentName) {
            performRenameTemplate(templateId, newName.trim());
        }
    };

    if (typeof window.studioPrompt === 'function') {
        window.studioPrompt("Enter a new name for the design template:", currentName, "Rename Template").then(handleName);
    } else {
        const newName = prompt("Enter a new name for the design template:", currentName);
        handleName(newName);
    }
}

function performRenameTemplate(templateId, newName) {
    const formData = new FormData();
    formData.append('action', 'rename_template');
    formData.append('template_id', templateId);
    formData.append('name', newName);
    formData.append('csrf_token', window.studioConfig ? window.studioConfig.csrfToken : '');

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (window.studioConfig) window.studioConfig.templateName = data.name;
            const titleEl = document.getElementById('template-title-display');
            if (titleEl) titleEl.innerText = data.name;
            if (typeof window.studioAlert === 'function') {
                window.studioAlert("Template renamed successfully.", "Success");
            }
        } else {
            if (typeof window.studioAlert === 'function') {
                window.studioAlert(data.error || "Failed to rename template.", "Error");
            } else {
                alert(data.error || "Failed to rename template.");
            }
        }
    })
    .catch(err => console.error('[BoardGameStudio] Rename error:', err));
}

function makeCopy() {
    const originalName = window.studioConfig ? window.studioConfig.templateName : '';
    window.studioPrompt("Enter a name for the duplicated template:", originalName + " (Copy)", "Duplicate Template")
    .then(newName => {
        if (newName === null) return;
        const trimmed = newName.trim();
        if (trimmed === "") {
            window.studioAlert("Template name cannot be empty.", "Validation Error");
            return;
        }
        
        if (window.editorCore && typeof window.editorCore.setSaveStatus === 'function') {
            window.editorCore.setSaveStatus('Saving and duplicating...', 'pulse');
        }
        
        // Save canvas first if NOT in view mode
        let savePromise = Promise.resolve();
        if (!window.studioConfig.isViewMode && window.editorCore && typeof window.editorCore.saveCanvas === 'function') {
            const res = window.editorCore.saveCanvas();
            if (res instanceof Promise) {
                savePromise = res;
            }
        }
        
        savePromise
            .then(() => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'duplicate_template';
                form.appendChild(actionInput);
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = window.studioConfig.csrfToken;
                form.appendChild(csrfInput);
                
                const nameInput = document.createElement('input');
                nameInput.type = 'hidden';
                nameInput.name = 'new_name';
                nameInput.value = trimmed;
                form.appendChild(nameInput);
                
                document.body.appendChild(form);
                form.submit();
            })
            .catch(err => {
                window.studioAlert("Could not save the current state before duplicating: " + err.message, "Error");
                if (window.editorCore && typeof window.editorCore.setSaveStatus === 'function') {
                    window.editorCore.setSaveStatus('Duplication failed', 'error');
                }
            });
    });
}

function showFullscreenPreview() {
    if (!window.editorCanvas) return;
    
    const canvas = window.editorCanvas;
    
    // Hide guides temporarily before exporting to keep preview clean
    let safeGuide = null;
    let bleedGuide = null;
    canvas.getObjects().forEach(obj => {
        if (obj.id === 'safe-zone-guide') safeGuide = obj;
        if (obj.id === 'bleed-zone-guide') bleedGuide = obj;
    });
    
    const safeVisible = safeGuide ? safeGuide.visible : false;
    const bleedVisible = bleedGuide ? bleedGuide.visible : false;
    
    if (safeGuide) safeGuide.visible = false;
    if (bleedGuide) bleedGuide.visible = false;
    canvas.discardActiveObject().renderAll();
    
    // Generate high-resolution clean preview
    const dataUrl = canvas.toDataURL({
        format: 'png',
        quality: 1.0,
        multiplier: 2
    });
    
    // Restore guides
    if (safeGuide) safeGuide.visible = safeVisible;
    if (bleedGuide) bleedGuide.visible = bleedVisible;
    canvas.renderAll();
    
    const overlay = document.getElementById('preview-overlay');
    const img = document.getElementById('preview-image');
    img.src = dataUrl;
    
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    setTimeout(() => {
        overlay.style.opacity = '1';
    }, 50);
    
    document.addEventListener('keydown', onPreviewEscKey);
}

function closeFullscreenPreview() {
    const overlay = document.getElementById('preview-overlay');
    if (!overlay) return;
    overlay.style.opacity = '0';
    setTimeout(() => {
        overlay.classList.remove('flex');
        overlay.classList.add('hidden');
        const img = document.getElementById('preview-image');
        if (img) img.src = '';
    }, 300);
    
    document.removeEventListener('keydown', onPreviewEscKey);
}

function onPreviewEscKey(e) {
    if (e.key === 'Escape') {
        closeFullscreenPreview();
    }
}

function toggleSidebar(panelId) {
    const panel = document.getElementById(panelId);
    if (!panel) return;
    panel.classList.toggle('hidden');
    setTimeout(() => {
        const fitBtn = document.getElementById('btn-zoom-fit');
        if (fitBtn) fitBtn.click();
    }, 150);
}

// ponytail: lightweight modal controls for resizing canvas template
function openChangeSizeModal() {
    const modal = document.getElementById('modal-change-size');
    if (!modal) return;
    updateResizePreview();
    modal.classList.remove('hidden');
}

function closeChangeSizeModal() {
    const modal = document.getElementById('modal-change-size');
    if (!modal) return;
    modal.classList.add('hidden');
}

function handleResizePresetChange(selectEl) {
    const selected = selectEl.options[selectEl.selectedIndex];
    if (!selected || selected.value === 'custom') return;
    const w = parseFloat(selected.getAttribute('data-width')) || 0;
    const h = parseFloat(selected.getAttribute('data-height')) || 0;
    if (w > 0 && h > 0) {
        document.getElementById('resize-width-mm').value = w;
        document.getElementById('resize-height-mm').value = h;
        updateResizePreview();
    }
}

function updateResizePreview() {
    const wMm = parseFloat(document.getElementById('resize-width-mm')?.value) || 0;
    const hMm = parseFloat(document.getElementById('resize-height-mm')?.value) || 0;
    const wPx = Math.round((wMm / 25.4) * 300);
    const hPx = Math.round((hMm / 25.4) * 300);
    const preview = document.getElementById('resize-px-preview');
    if (preview) {
        preview.textContent = `${wPx} × ${hPx} px`;
    }
}

function applyCanvasResize() {
    const wMm = parseFloat(document.getElementById('resize-width-mm')?.value) || 0;
    const hMm = parseFloat(document.getElementById('resize-height-mm')?.value) || 0;

    if (wMm <= 0 || hMm <= 0) {
        alert('Please enter positive numbers for width and height.');
        return;
    }

    const wPx = Math.round((wMm / 25.4) * 300);
    const hPx = Math.round((hMm / 25.4) * 300);

    const btn = document.getElementById('btn-confirm-resize');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Updating...';
    }

    const formData = new FormData();
    formData.append('csrf_token', window.studioConfig.csrfToken);
    formData.append('template_id', window.studioConfig.templateId);
    formData.append('width_px', wPx);
    formData.append('height_px', hPx);
    formData.append('width_mm', wMm);
    formData.append('height_mm', hMm);

    fetch('api.php?action=resize_template', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': window.studioConfig.csrfToken
        }
    })
    .then(res => res.json())
    .then(data => {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Update Size';
        }

        if (data.error) {
            alert(data.error);
            return;
        }

        closeChangeSizeModal();

        // Update studio configuration
        window.studioConfig.canvasWidth = data.canvasWidth;
        window.studioConfig.canvasHeight = data.canvasHeight;
        window.studioConfig.orientation = data.orientation;

        // Resize Fabric canvas and wrapper
        const canvas = window.editorCanvas;
        if (canvas) {
            canvas.setWidth(data.canvasWidth);
            canvas.setHeight(data.canvasHeight);
        }

        const wrapper = document.getElementById('canvas-container-wrapper');
        if (wrapper) {
            wrapper.style.width = data.canvasWidth + 'px';
            wrapper.style.height = data.canvasHeight + 'px';
        }

        // Re-render guidelines
        if (window.guideRenderer && typeof window.guideRenderer.renderGuides === 'function') {
            window.guideRenderer.renderGuides();
        }

        // Update UI elements in header
        const badge = document.getElementById('template-orientation-badge');
        if (badge) {
            if (data.orientation === 'landscape') {
                badge.textContent = 'Landscape';
                badge.className = 'text-xs font-semibold px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20';
            } else {
                badge.textContent = 'Portrait';
                badge.className = 'text-xs font-semibold px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700';
            }
        }

        const btnText = document.getElementById('orient-btn-text');
        if (btnText) {
            btnText.textContent = data.orientation === 'landscape' ? 'Switch to Portrait' : 'Switch to Landscape';
        }

        const sizeDisplay = document.getElementById('template-size-display');
        if (sizeDisplay) {
            sizeDisplay.textContent = `${data.widthMm}x${data.heightMm} mm (${data.canvasWidth}x${data.canvasHeight} px)`;
        }

        // Re-fit canvas to view
        const viewport = document.getElementById('canvas-viewport');
        if (viewport && wrapper) {
            const containerWidth = viewport.clientWidth - 64;
            const containerHeight = viewport.clientHeight - 64;
            const widthScale = containerWidth / data.canvasWidth;
            const heightScale = containerHeight / data.canvasHeight;
            const zoomLevel = Math.min(widthScale, heightScale, 1.0);
            const zoomValElem = document.getElementById('zoom-value');
            if (zoomValElem) zoomValElem.value = Math.round(zoomLevel * 100) + '%';
            wrapper.style.transform = `scale(${zoomLevel})`;
        }

        if (canvas) canvas.renderAll();
        if (window.editorCore) {
            if (typeof window.editorCore.pushState === 'function') window.editorCore.pushState();
            if (typeof window.editorCore.triggerAutoSave === 'function') window.editorCore.triggerAutoSave();
            if (typeof window.editorCore.setSaveStatus === 'function') {
                window.editorCore.setSaveStatus('Template size updated', 'saved');
            }
        }
    })
    .catch(err => {
        console.error('Error updating template size:', err);
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Update Size';
        }
        alert('Failed to update template size. Please try again.');
    });
}

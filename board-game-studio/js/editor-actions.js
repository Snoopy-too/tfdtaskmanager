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

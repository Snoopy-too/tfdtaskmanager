// ponytail: rich file preview card with live thumbnail and clear action
const fileInput = document.getElementById('asset_file');
const previewContainer = document.getElementById('file_selected_preview');
const tagInput = document.getElementById('tag');

function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function clearSelectedFiles() {
    if (fileInput) fileInput.value = '';
    if (previewContainer) {
        previewContainer.innerHTML = '';
        previewContainer.classList.add('hidden');
    }
}

if (fileInput && previewContainer) {
    fileInput.addEventListener('change', (e) => {
        const files = e.target.files;
        if (!files || files.length === 0) {
            clearSelectedFiles();
            return;
        }

        previewContainer.innerHTML = '';
        previewContainer.classList.remove('hidden');

        if (files.length === 1) {
            const file = files[0];
            const isImg = file.type.startsWith('image/') || file.name.toLowerCase().endsWith('.svg');
            const thumbSrc = isImg ? URL.createObjectURL(file) : null;

            const card = document.createElement('div');
            card.className = "bg-slate-950 border border-indigo-500/40 rounded-xl p-3 flex items-center justify-between gap-3 shadow-lg shadow-indigo-500/5";

            card.innerHTML = `
                <div class="flex items-center space-x-3 overflow-hidden min-w-0">
                    <div class="w-10 h-10 rounded-lg bg-slate-900 border border-slate-800 flex items-center justify-center flex-shrink-0 overflow-hidden p-1">
                        ${thumbSrc 
                            ? `<img src="${thumbSrc}" class="w-full h-full object-contain" alt="Preview">` 
                            : `<svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`}
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-100 truncate" title="${file.name}">${file.name}</div>
                        <div class="flex items-center space-x-2 mt-0.5">
                            <span class="text-[10px] text-slate-400 font-mono">${formatBytes(file.size)}</span>
                            <span class="text-[10px] text-emerald-400 font-medium flex items-center gap-0.5">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                Ready to upload
                            </span>
                        </div>
                    </div>
                </div>
                <button type="button" id="btn-clear-file" title="Remove file" class="text-slate-400 hover:text-rose-400 transition p-1 rounded-lg hover:bg-slate-900 flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            `;

            previewContainer.appendChild(card);
            document.getElementById('btn-clear-file')?.addEventListener('click', clearSelectedFiles);

            if (tagInput && !tagInput.value.trim()) {
                const cleanTag = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                tagInput.placeholder = cleanTag;
            }
        } else {
            let totalBytes = 0;
            for (let i = 0; i < files.length; i++) totalBytes += files[i].size;

            const card = document.createElement('div');
            card.className = "bg-slate-950 border border-indigo-500/40 rounded-xl p-3 flex items-center justify-between gap-3 shadow-lg shadow-indigo-500/5";
            card.innerHTML = `
                <div class="flex items-center space-x-3 overflow-hidden min-w-0">
                    <div class="w-10 h-10 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center flex-shrink-0 text-indigo-400 font-bold text-xs">
                        ${files.length}x
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-100">${files.length} files selected</div>
                        <div class="flex items-center space-x-2 mt-0.5">
                            <span class="text-[10px] text-slate-400 font-mono">${formatBytes(totalBytes)} total</span>
                            <span class="text-[10px] text-emerald-400 font-medium flex items-center gap-0.5">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                Ready
                            </span>
                        </div>
                    </div>
                </div>
                <button type="button" id="btn-clear-file" title="Remove all files" class="text-slate-400 hover:text-rose-400 transition p-1 rounded-lg hover:bg-slate-900 flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            `;
            previewContainer.appendChild(card);
            document.getElementById('btn-clear-file')?.addEventListener('click', clearSelectedFiles);
        }
    });
}

function handleAssetUploadSubmit(form) {
    const input = document.getElementById('asset_file');
    if (input && input.files.length > 0) {
        const modal = document.getElementById('upload-processing-modal');
        const statusText = document.getElementById('upload-status-text');
        const submitBtn = document.getElementById('btn-upload-submit');

        if (input.files.length > 1) {
            statusText.textContent = `Uploading & registering ${input.files.length} files...`;
        } else if (input.files[0].name.endsWith('.zip')) {
            statusText.textContent = `Extracting & importing ZIP archive...`;
        } else {
            statusText.textContent = `Uploading ${input.files[0].name}...`;
        }

        if (modal) modal.classList.remove('hidden');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            submitBtn.textContent = 'Processing Upload...';
        }
    }
    return true;
}

// Batch Action Operations
function getSelectedAssetIds() {
    const checkboxes = document.querySelectorAll('.asset-item-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

function updateBatchActionBar() {
    const selected = getSelectedAssetIds();
    const count = selected.length;
    const countSpan = document.getElementById('selected-count');
    if (countSpan) countSpan.textContent = count;

    const allCheckboxes = document.querySelectorAll('.asset-item-checkbox');
    const selectAll = document.getElementById('select-all-checkbox');
    if (selectAll) {
        selectAll.checked = allCheckboxes.length > 0 && count === allCheckboxes.length;
        selectAll.indeterminate = count > 0 && count < allCheckboxes.length;
    }

    const btnMove = document.getElementById('btn-batch-move');
    const btnDelete = document.getElementById('btn-batch-delete');
    if (btnMove) btnMove.disabled = count === 0;
    if (btnDelete) btnDelete.disabled = count === 0;

    // Card ring highlight
    allCheckboxes.forEach(cb => {
        const card = document.getElementById(`asset-card-${cb.value}`);
        if (card) {
            if (cb.checked) {
                card.classList.add('ring-2', 'ring-indigo-500');
            } else {
                card.classList.remove('ring-2', 'ring-indigo-500');
            }
        }
    });
}

function toggleSelectAllAssets(master) {
    const checkboxes = document.querySelectorAll('.asset-item-checkbox');
    checkboxes.forEach(cb => { cb.checked = master.checked; });
    updateBatchActionBar();
}

function clearAssetSelection() {
    const checkboxes = document.querySelectorAll('.asset-item-checkbox');
    checkboxes.forEach(cb => { cb.checked = false; });
    updateBatchActionBar();
}

function handleBatchMoveSubmit(form) {
    const selected = getSelectedAssetIds();
    if (selected.length === 0) return false;
    const selectEl = document.getElementById('batch-target-project');
    const targetText = selectEl.options[selectEl.selectedIndex].text.trim();
    
    window.studioConfirm(`Move ${selected.length} selected asset(s) to "${targetText}"?`, 'Move', 'Move Assets').then(confirmed => {
        if (confirmed) {
            document.getElementById('batch-move-ids').value = selected.join(',');
            form.submit();
        }
    });
    return false;
}

function handleBatchDeleteSubmit(form) {
    const selected = getSelectedAssetIds();
    if (selected.length === 0) return false;

    window.studioConfirm(`Are you sure you want to permanently delete ${selected.length} selected asset(s)? This action cannot be undone.`, 'Delete', 'Delete Assets').then(confirmed => {
        if (confirmed) {
            document.getElementById('batch-delete-ids').value = selected.join(',');
            form.submit();
        }
    });
    return false;
}

/**
 * Dataset Grid Live Auto-Save & Lock Heartbeat
 */

document.addEventListener('DOMContentLoaded', () => {
    const cellInputs = document.querySelectorAll('.dataset-cell-input');
    const saveStatusEl = document.getElementById('dataset-save-status');
    const csrfToken = window.studioConfig ? window.studioConfig.csrfToken : (document.querySelector('meta[name="csrf-token"]')?.content || '');
    const debounceTimers = new Map();

    function setSaveStatus(status) {
        if (!saveStatusEl) return;
        if (status === 'saving') {
            saveStatusEl.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20';
            saveStatusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span> Saving...';
        } else if (status === 'error') {
            saveStatusEl.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20';
            saveStatusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> Save Failed';
        } else {
            saveStatusEl.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
            saveStatusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Auto-Saved';
        }
    }

    function saveCellInput(input) {
        if (!input || !input.dataset.dirty) return;
        delete input.dataset.dirty;

        if (debounceTimers.has(input)) {
            clearTimeout(debounceTimers.get(input));
            debounceTimers.delete(input);
        }

        const datasetId = input.getAttribute('data-dataset-id');
        const rowIndex = input.getAttribute('data-row-index');
        const columnName = input.getAttribute('data-column-name');
        const value = input.value;
        const parentTd = input.closest('td');

        setSaveStatus('saving');
        if (parentTd) {
            parentTd.className = 'p-1 border-r border-slate-800/40 last:border-r-0 transition-colors duration-200 bg-indigo-500/10';
        }

        const formData = new FormData();
        const tokenVal = input.dataset.csrfToken || csrfToken;
        formData.append('csrf_token', tokenVal);
        formData.append('dataset_id', datasetId);
        formData.append('row_index', rowIndex);
        formData.append('column_name', columnName);
        formData.append('value', value);

        fetch('api.php?action=update_dataset_cell', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': tokenVal
            }
        })
        .then(res => res.json())
        .then(data => {
            if (parentTd) {
                parentTd.className = 'p-1 border-r border-slate-800/40 last:border-r-0 transition-colors duration-200';
            }
            if (data.success) {
                if (parentTd) {
                    parentTd.classList.add('bg-emerald-500/10');
                    setTimeout(() => parentTd.classList.remove('bg-emerald-500/10'), 600);
                }
                setSaveStatus('saved');
            } else {
                if (parentTd) {
                    parentTd.classList.add('bg-rose-500/10');
                    setTimeout(() => parentTd.classList.remove('bg-rose-500/10'), 1500);
                }
                setSaveStatus('error');
                console.error("Cell save failed:", data.error);
            }
        })
        .catch(err => {
            if (parentTd) {
                parentTd.className = 'p-1 border-r border-slate-800/40 last:border-r-0 transition-colors duration-200 bg-rose-500/10';
                setTimeout(() => parentTd.className = 'p-1 border-r border-slate-800/40 last:border-r-0 transition-colors duration-200', 1500);
            }
            setSaveStatus('error');
            console.error("Cell save failed:", err);
        });
    }

    cellInputs.forEach(input => {
        // Real-time debounced auto-save as user types
        input.addEventListener('input', () => {
            input.dataset.dirty = 'true';
            setSaveStatus('saving');

            if (debounceTimers.has(input)) {
                clearTimeout(debounceTimers.get(input));
            }

            const timer = setTimeout(() => {
                saveCellInput(input);
            }, 450);
            debounceTimers.set(input, timer);
        });

        // Immediate save on blur or change
        input.addEventListener('blur', () => saveCellInput(input));
        input.addEventListener('change', () => saveCellInput(input));

        // Save immediately on Enter keypress
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveCellInput(input);
                input.blur();
            }
        });
    });

    // Flush any pending unsaved dirty inputs before page navigation or tab close
    window.addEventListener('beforeunload', () => {
        cellInputs.forEach(input => {
            if (input.dataset.dirty === 'true') {
                const formData = new FormData();
                const tokenVal = input.dataset.csrfToken || csrfToken;
                formData.append('csrf_token', tokenVal);
                formData.append('dataset_id', input.getAttribute('data-dataset-id'));
                formData.append('row_index', input.getAttribute('data-row-index'));
                formData.append('column_name', input.getAttribute('data-column-name'));
                formData.append('value', input.value);
                navigator.sendBeacon('api.php?action=update_dataset_cell', formData);
            }
        });
    });

    // Horizontal mouse wheel scrolling support for wide data grids
    const tableContainer = document.getElementById('dataset-table-container');
    if (tableContainer) {
        tableContainer.addEventListener('wheel', (e) => {
            if (e.shiftKey) {
                e.preventDefault();
                tableContainer.scrollLeft += (e.deltaY || e.deltaX);
            }
        }, { passive: false });
    }
});

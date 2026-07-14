document.addEventListener('DOMContentLoaded', () => {
    const tabImport = document.getElementById('tab-import');
    const tabBuild = document.getElementById('tab-build');
    const formImport = document.getElementById('form-import');
    const formBuild = document.getElementById('form-build');

    if (!tabImport || !tabBuild) return;

    // Custom Modals
    function createModalOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center';
        document.body.appendChild(overlay);
        return overlay;
    }

    function customPrompt(message, defaultValue = '', callback) {
        const overlay = createModalOverlay();
        overlay.innerHTML = `
            <div class="bg-slate-900 border border-slate-700 rounded-2xl p-6 w-full max-w-sm shadow-2xl transform transition-all">
                <h3 class="text-sm font-bold text-slate-200 mb-4">${message}</h3>
                <input type="text" id="custom-prompt-input" class="w-full bg-slate-950 border border-slate-700 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 p-2.5 mb-6" value="${defaultValue}">
                <div class="flex justify-end space-x-3">
                    <button id="custom-prompt-cancel" class="px-4 py-2 text-sm font-medium text-slate-400 hover:text-white transition">Cancel</button>
                    <button id="custom-prompt-ok" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-xl shadow-lg hover:shadow-indigo-500/20 transition">OK</button>
                </div>
            </div>
        `;
        const input = document.getElementById('custom-prompt-input');
        input.focus();
        if (defaultValue) input.select();

        const close = (value) => {
            overlay.remove();
            callback(value);
        };

        document.getElementById('custom-prompt-cancel').onclick = () => close(null);
        document.getElementById('custom-prompt-ok').onclick = () => close(input.value);
        input.onkeydown = (e) => {
            if (e.key === 'Enter') close(input.value);
            if (e.key === 'Escape') close(null);
        };
    }

    window.customConfirm = function(message, callback) {
        const overlay = createModalOverlay();
        overlay.innerHTML = `
            <div class="bg-slate-900 border border-slate-700 rounded-2xl p-6 w-full max-w-sm shadow-2xl transform transition-all text-center">
                <h3 class="text-base font-bold text-slate-200 mb-6">${message}</h3>
                <div class="flex justify-center space-x-4">
                    <button id="custom-confirm-cancel" class="px-5 py-2.5 text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition">Cancel</button>
                    <button id="custom-confirm-ok" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-500 text-white text-sm font-medium rounded-xl shadow-lg hover:shadow-rose-500/20 transition">Confirm</button>
                </div>
            </div>
        `;
        const close = (value) => {
            overlay.remove();
            callback(value);
        };
        document.getElementById('custom-confirm-cancel').onclick = () => close(false);
        document.getElementById('custom-confirm-ok').onclick = () => close(true);
    }

    function customAlert(message) {
        const overlay = createModalOverlay();
        overlay.innerHTML = `
            <div class="bg-slate-900 border border-slate-700 rounded-2xl p-6 w-full max-w-sm shadow-2xl transform transition-all text-center">
                <h3 class="text-base font-bold text-slate-200 mb-6">${message}</h3>
                <button id="custom-alert-ok" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-xl shadow-lg transition w-full">Got it</button>
            </div>
        `;
        document.getElementById('custom-alert-ok').onclick = () => overlay.remove();
    }

    // Tab logic
    tabImport.addEventListener('click', () => {
        tabImport.classList.add('text-indigo-400', 'border-indigo-500');
        tabImport.classList.remove('text-slate-500', 'border-transparent');
        tabBuild.classList.add('text-slate-500', 'border-transparent');
        tabBuild.classList.remove('text-indigo-400', 'border-indigo-500');
        
        formImport.classList.remove('hidden');
        formBuild.classList.add('hidden');
    });

    tabBuild.addEventListener('click', () => {
        tabBuild.classList.add('text-indigo-400', 'border-indigo-500');
        tabBuild.classList.remove('text-slate-500', 'border-transparent');
        tabImport.classList.add('text-slate-500', 'border-transparent');
        tabImport.classList.remove('text-indigo-400', 'border-indigo-500');
        
        formBuild.classList.remove('hidden');
        formImport.classList.add('hidden');
    });

    // Builder State
    let columnMap = ['Name', 'Value'];
    let rowData = [
        ['Example Item 1', '10'],
        ['Example Item 2', '20']
    ];

    const headerRow = document.getElementById('builder-header-row');
    const bodyEl = document.getElementById('builder-body');

    function renderGrid() {
        // Render Header
        headerRow.innerHTML = '';
        columnMap.forEach((col, idx) => {
            const th = document.createElement('th');
            th.className = 'px-3 py-2 font-semibold relative group';
            
            const nameSpan = document.createElement('span');
            nameSpan.textContent = col;
            nameSpan.className = 'cursor-pointer hover:text-white';
            nameSpan.title = "Click to rename column";
            nameSpan.onclick = () => {
                customPrompt('Enter new column name:', col, (newName) => {
                    if (newName && newName.trim()) {
                        columnMap[idx] = newName.trim();
                        renderGrid();
                    }
                });
            };

            const delBtn = document.createElement('button');
            delBtn.innerHTML = '&times;';
            delBtn.className = 'absolute right-1 top-1 text-rose-500 hover:text-rose-400 font-bold opacity-0 group-hover:opacity-100 transition';
            delBtn.title = "Remove column";
            delBtn.onclick = (e) => {
                e.stopPropagation();
                if (columnMap.length <= 1) return customAlert('Dataset must have at least one column.');
                
                window.customConfirm(`Remove column '${col}'?`, (confirmed) => {
                    if (confirmed) {
                        columnMap.splice(idx, 1);
                        rowData.forEach(row => row.splice(idx, 1));
                        renderGrid();
                    }
                });
            };

            th.appendChild(nameSpan);
            th.appendChild(delBtn);
            headerRow.appendChild(th);
        });

        const actionTh = document.createElement('th');
        actionTh.className = 'px-3 py-2 w-10 text-center';
        headerRow.appendChild(actionTh);

        // Render Body
        bodyEl.innerHTML = '';
        rowData.forEach((row, rowIndex) => {
            const tr = document.createElement('tr');
            
            row.forEach((cellVal, colIndex) => {
                const td = document.createElement('td');
                td.className = 'px-3 py-2 border-r border-slate-800/30 last:border-0';
                
                const input = document.createElement('input');
                input.type = 'text';
                input.value = cellVal;
                input.className = 'w-full bg-transparent border-none p-0 text-sm focus:ring-0 text-slate-300 focus:text-white';
                input.onchange = (e) => {
                    rowData[rowIndex][colIndex] = e.target.value;
                };
                
                td.appendChild(input);
                tr.appendChild(td);
            });

            const actionTd = document.createElement('td');
            actionTd.className = 'px-3 py-2 text-center';
            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'text-rose-500 hover:text-rose-400 font-bold';
            delBtn.innerHTML = '&times;';
            delBtn.title = "Remove row";
            delBtn.onclick = () => {
                rowData.splice(rowIndex, 1);
                renderGrid();
            };
            actionTd.appendChild(delBtn);
            tr.appendChild(actionTd);

            bodyEl.appendChild(tr);
        });
    }

    document.getElementById('btn-add-col').addEventListener('click', () => {
        customPrompt('Enter new column name (e.g. Health, Attack, Image):', '', (newCol) => {
            if (newCol && newCol.trim()) {
                columnMap.push(newCol.trim());
                rowData.forEach(row => row.push(''));
                renderGrid();
            }
        });
    });

    document.getElementById('btn-add-row').addEventListener('click', () => {
        const newRow = Array(columnMap.length).fill('');
        rowData.push(newRow);
        renderGrid();
    });

    // Initial render
    renderGrid();

    // Attach saving logic to window so the form onsubmit can call it
    window.saveManualDataset = function(event) {
        const nameInput = document.getElementById('build_name');
        if (!nameInput.value.trim()) {
            customAlert('Please enter a dataset name.');
            event.preventDefault();
            return false;
        }

        if (rowData.length === 0) {
            customAlert('Please add at least one row of data.');
            event.preventDefault();
            return false;
        }

        // Package data
        const payload = {
            columnMap: columnMap,
            rowData: rowData
        };

        document.getElementById('grid_json').value = JSON.stringify(payload);
        return true;
    };
});

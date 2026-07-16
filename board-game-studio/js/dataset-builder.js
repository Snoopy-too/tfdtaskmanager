document.addEventListener('DOMContentLoaded', () => {
    const tabImport = document.getElementById('tab-import');
    const tabBuild = document.getElementById('tab-build');
    const formImport = document.getElementById('form-import');
    const formBuild = document.getElementById('form-build');

    if (!tabImport || !tabBuild) return;

    // Custom Modals

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
                window.studioPrompt('Enter new column name:', col, 'Rename Column')
                .then((newName) => {
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
                if (columnMap.length <= 1) {
                    window.studioAlert('Dataset must have at least one column.', 'Action Denied');
                    return;
                }
                
                window.studioConfirm(`Remove column '${col}'?`, 'Remove', 'Confirm Action')
                .then((confirmed) => {
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
        window.studioPrompt('Enter new column name (e.g. Health, Attack, Image):', '', 'Add Column')
        .then((newCol) => {
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

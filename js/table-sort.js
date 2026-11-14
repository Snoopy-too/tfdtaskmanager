/**
 * TFD Task Manager - Universal Sortable Tables
 * Automatically activates on all table.data-table elements.
 */
(function() {
    'use strict';

    const ICONS_HTML = `
        <span class="sort-indicator" aria-hidden="true">
            <svg class="sort-icon-neutral" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg>
            <svg class="sort-icon-asc" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 15l-6-6-6 6"/></svg>
            <svg class="sort-icon-desc" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
        </span>
    `.trim();

    function getCellValue(tr, colIndex) {
        const td = tr.children[colIndex];
        if (!td) return '';
        if (td.hasAttribute('data-sort-value')) {
            return td.getAttribute('data-sort-value').trim();
        }
        return td.innerText.trim();
    }

    function parseValue(val) {
        if (val === null || val === undefined) return { type: 'empty', val: '' };
        const str = String(val).trim();
        if (str === '' || str === '—' || str === '-') return { type: 'empty', val: '' };

        // Check if numeric (handles raw numbers, IDs like #12, BUG #45, percentages like 50%, currencies)
        const cleanedNum = str.replace(/^(bug\s*)?#/i, '').replace(/[$,%]/g, '').replace(/\s+/g, '');
        if (cleanedNum !== '' && !isNaN(Number(cleanedNum))) {
            return { type: 'number', val: Number(cleanedNum) };
        }

        // Check if date string
        if (/^[A-Za-z]{3,9}\s+\d{1,2},\s*\d{4}/.test(str) || /^\d{4}-\d{2}-\d{2}/.test(str)) {
            const timestamp = Date.parse(str);
            if (!isNaN(timestamp)) {
                return { type: 'date', val: timestamp };
            }
        }

        return { type: 'string', val: str.toLowerCase() };
    }

    function sortTable(table, colIndex, headerTh) {
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        const allRows = Array.from(tbody.querySelectorAll('tr'));
        // Ignore tables showing empty state with colspan
        if (allRows.length === 0 || allRows.some(row => row.querySelector('td[colspan]'))) {
            return;
        }

        // Determine new sort direction
        const currentSort = headerTh.getAttribute('aria-sort');
        const newDirection = (currentSort === 'ascending') ? 'descending' : 'ascending';
        const isAsc = (newDirection === 'ascending');

        // Reset all other headers in this table
        const allHeaders = table.querySelectorAll('thead th');
        allHeaders.forEach(th => {
            if (th !== headerTh) {
                th.setAttribute('aria-sort', 'none');
            }
        });
        headerTh.setAttribute('aria-sort', newDirection);

        // Sort rows
        allRows.sort((rowA, rowB) => {
            const rawA = getCellValue(rowA, colIndex);
            const rawB = getCellValue(rowB, colIndex);

            const pA = parseValue(rawA);
            const pB = parseValue(rawB);

            // Always keep empty values at the bottom
            if (pA.type === 'empty' && pB.type === 'empty') return 0;
            if (pA.type === 'empty') return 1;
            if (pB.type === 'empty') return -1;

            let diff = 0;
            if (pA.type === 'number' && pB.type === 'number') {
                diff = pA.val - pB.val;
            } else if (pA.type === 'date' && pB.type === 'date') {
                diff = pA.val - pB.val;
            } else {
                diff = String(rawA).localeCompare(String(rawB), undefined, { numeric: true, sensitivity: 'base' });
            }

            return isAsc ? diff : -diff;
        });

        // Re-append sorted rows to tbody
        tbody.append(...allRows);
    }

    function initTable(table) {
        if (table.dataset.sortableInitialized) return;
        table.dataset.sortableInitialized = 'true';

        const thead = table.querySelector('thead');
        if (!thead) return;

        const headers = thead.querySelectorAll('tr:last-child th');
        headers.forEach((th, index) => {
            const text = th.textContent.trim().toLowerCase();
            const isNonSortable = th.dataset.sortable === 'false' ||
                                  th.classList.contains('no-sort') ||
                                  text === 'actions' ||
                                  text === '';

            if (isNonSortable) {
                th.setAttribute('aria-sort', 'none');
                return;
            }

            th.classList.add('sortable');
            th.setAttribute('role', 'button');
            th.setAttribute('tabindex', '0');
            th.setAttribute('aria-sort', 'none');
            th.setAttribute('title', `Click to sort by ${th.textContent.trim()}`);
            th.insertAdjacentHTML('beforeend', ICONS_HTML);

            th.addEventListener('click', function(e) {
                // Avoid sorting if clicking an input, link, or button inside header
                if (e.target.closest('a, button, input, select')) return;
                sortTable(table, index, th);
            });

            th.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    sortTable(table, index, th);
                }
            });
        });
    }

    window.initSortableTables = function() {
        document.querySelectorAll('table.data-table').forEach(initTable);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.initSortableTables);
    } else {
        window.initSortableTables();
    }
})();

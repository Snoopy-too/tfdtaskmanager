/**
 * Rulebooks and Glossary Index Page Actions
 */
(function() {
    'use strict';

    let activeConfirmForm = null;

    document.addEventListener('DOMContentLoaded', () => {
        const btnOk = document.getElementById('btn-confirm-ok');
        const btnCancel = document.getElementById('btn-confirm-cancel');
        const modal = document.getElementById('custom-confirm-modal');

        if (btnOk && btnCancel && modal) {
            btnOk.addEventListener('click', () => {
                modal.classList.add('hidden');
                if (activeConfirmForm) {
                    activeConfirmForm.submit();
                }
            });
            btnCancel.addEventListener('click', () => {
                modal.classList.add('hidden');
                activeConfirmForm = null;
            });
        }
    });

    window.switchTab = function(tabId, btn) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
        const target = document.getElementById(tabId);
        if (target) target.classList.remove('hidden');
        
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('border-amber-500', 'text-white');
            b.classList.add('border-transparent', 'text-slate-400');
        });
        if (btn) {
            btn.classList.remove('border-transparent', 'text-slate-400');
            btn.classList.add('border-amber-500', 'text-white');
        }
    };

    window.switchSidebarTab = function(tab) {
        const manualForm = document.getElementById('sidebar-manual-form');
        const csvForm = document.getElementById('sidebar-csv-form');
        const btnManual = document.getElementById('btn-sidebar-manual');
        const btnCsv = document.getElementById('btn-sidebar-csv');
        
        if (tab === 'csv') {
            if (manualForm) manualForm.classList.add('hidden');
            if (csvForm) csvForm.classList.remove('hidden');
            if (btnCsv) {
                btnCsv.classList.remove('border-transparent', 'text-slate-400');
                btnCsv.classList.add('border-amber-500', 'text-white');
            }
            if (btnManual) {
                btnManual.classList.remove('border-amber-500', 'text-white');
                btnManual.classList.add('border-transparent', 'text-slate-400');
            }
        } else {
            if (manualForm) manualForm.classList.remove('hidden');
            if (csvForm) csvForm.classList.add('hidden');
            if (btnManual) {
                btnManual.classList.remove('border-transparent', 'text-slate-400');
                btnManual.classList.add('border-amber-500', 'text-white');
            }
            if (btnCsv) {
                btnCsv.classList.remove('border-amber-500', 'text-white');
                btnCsv.classList.add('border-transparent', 'text-slate-400');
            }
        }
    };

    window.editGlossary = function(data) {
        window.switchSidebarTab('manual');
        const title = document.getElementById('glossary-form-title');
        if (title) title.textContent = 'Edit Glossary Term';
        const formId = document.getElementById('form_term_id');
        if (formId) formId.value = data.id;
        const keyInput = document.getElementById('form_term_key');
        if (keyInput) {
            keyInput.value = data.term_key;
            keyInput.readOnly = true;
            keyInput.classList.add('opacity-50');
        }
        const nameInput = document.getElementById('form_term_name');
        if (nameInput) nameInput.value = data.term_name;
        const descInput = document.getElementById('form_term_description');
        if (descInput) descInput.value = data.term_description;
        const cancelBtn = document.getElementById('form_cancel_btn');
        if (cancelBtn) cancelBtn.classList.remove('hidden');
    };

    window.resetGlossaryForm = function() {
        const title = document.getElementById('glossary-form-title');
        if (title) title.textContent = 'Add Glossary Term';
        const formId = document.getElementById('form_term_id');
        if (formId) formId.value = '';
        const keyInput = document.getElementById('form_term_key');
        if (keyInput) {
            keyInput.value = '';
            keyInput.readOnly = false;
            keyInput.classList.remove('opacity-50');
        }
        const nameInput = document.getElementById('form_term_name');
        if (nameInput) nameInput.value = '';
        const descInput = document.getElementById('form_term_description');
        if (descInput) descInput.value = '';
        const cancelBtn = document.getElementById('form_cancel_btn');
        if (cancelBtn) cancelBtn.classList.add('hidden');
    };

    window.showCustomConfirm = function(message, form) {
        activeConfirmForm = form;
        const modal = document.getElementById('custom-confirm-modal');
        const msgEl = document.getElementById('custom-confirm-message');
        if (modal && msgEl) {
            msgEl.textContent = message;
            modal.classList.remove('hidden');
            return false;
        }
        return confirm(message);
    };

    window.handleCsvImport = function(e) {
        e.preventDefault();
        const form = document.getElementById('csv-import-form');
        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(`Successfully imported/updated ${data.count} glossary terms!`);
                window.location.reload();
            } else {
                alert('Error importing CSV: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('CSV import error:', err);
            alert('An unexpected error occurred during CSV import.');
        });
        return false;
    };
})();

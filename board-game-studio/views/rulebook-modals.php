<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
?>
<!-- Custom Confirmation Modal -->
<div id="custom-confirm-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl w-full max-w-sm space-y-4 shadow-2xl">
        <div class="flex items-center space-x-3">
            <div class="p-2 rounded-lg bg-rose-500/10 text-rose-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h3 class="text-base font-bold text-slate-200">Confirm Action</h3>
        </div>
        <p id="custom-confirm-message" class="text-xs text-slate-405">Are you sure you want to proceed?</p>
        <div class="flex justify-end space-x-2 pt-2">
            <button id="btn-confirm-cancel" class="px-4 py-2 bg-slate-850 hover:bg-slate-800 text-slate-300 text-xs font-semibold rounded-xl transition duration-200">Cancel</button>
            <button id="btn-confirm-ok" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-xl transition duration-200">Confirm</button>
        </div>
    </div>
</div>

<!-- Modal configurations/helpers -->
<div id="diagram-item-picker" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl w-full max-w-md space-y-4 shadow-2xl">
        <h3 class="text-base font-bold text-slate-200">Add Component to Diagram</h3>
        <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1">Select Component</label>
            <select id="diagram-select-template" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl p-2.5">
                <?php foreach ($templates as $tmpl): ?>
                    <option value="<?php echo $tmpl->getId(); ?>"><?php echo SecurityHelper::escape($tmpl->getName()); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="diagram-row-select-container" class="hidden">
            <label class="block text-xs font-semibold text-slate-400 mb-1 font-semibold text-slate-400">Select Specific Card/Row</label>
            <select id="diagram-select-row" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl p-2.5">
                <!-- Dynamically populated -->
            </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Scale (0.1 to 2.0)</label>
                <input type="number" id="diagram-item-scale" min="0.1" max="2.0" step="0.1" value="1.0" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl p-2">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Rotation (Degrees)</label>
                <input type="number" id="diagram-item-rotation" min="0" max="360" step="15" value="0" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl p-2">
            </div>
        </div>
        <div class="flex justify-end space-x-2 pt-2">
            <button onclick="closeDiagramPicker()" class="px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white transition">Cancel</button>
            <button id="btn-add-to-diagram" class="px-4 py-2 text-xs font-bold bg-amber-600 hover:bg-amber-500 text-white rounded-xl transition">Add Item</button>
        </div>
    </div>
</div>

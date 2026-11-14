<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
?>
<!-- Create/Import Dataset Collapsible Form Panel -->
<div id="new-dataset-panel" class="form-panel-collapsible mb-6">
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800">
        <div>
            <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                <span>📊</span>
                <span>Create or Import Dataset</span>
            </h2>
            <p class="text-xs text-slate-400">Import CSV files or build custom spreadsheet tables for card variable substitutions.</p>
        </div>
        <button type="button" onclick="toggleCollapsible('new-dataset-panel')" class="text-slate-400 hover:text-slate-200 text-sm font-semibold p-1">
            &times; Close
        </button>
    </div>

    <!-- Mode Selector Tabs -->
    <div class="flex items-center space-x-4 mb-4 border-b border-slate-800 pb-2">
        <button type="button" id="tab-import" class="text-xs font-bold text-indigo-400 hover:text-indigo-300 transition pb-2 border-b-2 border-indigo-500">
            Import CSV
        </button>
        <button type="button" id="tab-build" class="text-xs font-bold text-slate-500 hover:text-slate-300 transition pb-2 border-b-2 border-transparent">
            Build Manually
        </button>
    </div>

    <!-- Mode 1: Import CSV Form -->
    <form id="form-import" action="" method="POST" enctype="multipart/form-data" class="space-y-4 block">
        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
        <input type="hidden" name="action" value="import_dataset">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Dataset Name *</label>
                <input type="text" id="name" name="name" required placeholder="e.g., Monster Deck V1, Equipment Table, Loot Table"
                    class="w-full bg-slate-950/70 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
            </div>

            <div>
                <label for="csv_file" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Upload CSV File</label>
                <input type="file" id="csv_file" name="csv_file" accept=".csv"
                    class="w-full bg-slate-950/70 border border-slate-800 rounded-lg p-1.5 text-xs text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500 cursor-pointer">
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="csv_text" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Or Paste Raw CSV Data</label>
                <span class="text-[10px] text-slate-500">Comma-separated values with header row</span>
            </div>
            <textarea id="csv_text" name="csv_text" rows="4" placeholder="Name,Attack,Health,Rarity&#10;Goblin,3,2,Common&#10;Dragon,12,25,Legendary"
                class="w-full bg-slate-950/70 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg p-3 text-slate-100 text-xs font-mono outline-none transition"></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800/80">
            <button type="button" onclick="toggleCollapsible('new-dataset-panel')" class="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 transition">
                Cancel
            </button>
            <button type="submit" class="px-5 py-2 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">
                Import Dataset
            </button>
        </div>
    </form>

    <!-- Mode 2: Build Manually Form -->
    <form id="form-build" action="" method="POST" class="space-y-4 hidden" onsubmit="return saveManualDataset(event);">
        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
        <input type="hidden" name="action" value="build_dataset">
        <input type="hidden" id="grid_json" name="grid_json" value="">

        <div>
            <label for="build_name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Dataset Name *</label>
            <input type="text" id="build_name" name="name" placeholder="e.g., Custom Event Deck"
                class="w-full bg-slate-950/70 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
        </div>

        <div class="border border-slate-800 rounded-xl overflow-hidden bg-slate-950/80">
            <div class="flex items-center justify-between p-2.5 border-b border-slate-800 bg-slate-900/60">
                <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">Data Grid Builder</span>
                <div class="flex items-center space-x-2">
                    <button type="button" id="btn-add-col" class="text-xs font-semibold px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg border border-slate-700 transition">+ Column</button>
                    <button type="button" id="btn-add-row" class="text-xs font-semibold px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg border border-slate-700 transition">+ Row</button>
                </div>
            </div>
            <div class="overflow-x-auto max-h-72">
                <table id="builder-grid" class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-900/40 text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-800">
                        <tr id="builder-header-row">
                            <!-- Columns injected by JS -->
                        </tr>
                    </thead>
                    <tbody id="builder-body" class="divide-y divide-slate-800/50">
                        <!-- Rows injected by JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800/80">
            <button type="button" onclick="toggleCollapsible('new-dataset-panel')" class="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 transition">
                Cancel
            </button>
            <button type="submit" class="px-5 py-2 rounded-lg text-xs font-semibold bg-violet-600 hover:bg-violet-500 text-white shadow-sm transition">
                Build &amp; Save Dataset
            </button>
        </div>
    </form>
</div>

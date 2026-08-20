<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
?>
<!-- Left: Upload/Paste CSV Form & Dataset List -->
<div class="space-y-6">
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl">
        <div class="flex items-center space-x-4 mb-4 border-b border-slate-800 pb-2">
            <button type="button" id="tab-import" class="text-sm font-bold text-indigo-400 hover:text-indigo-300 transition pb-2 border-b-2 border-indigo-500">Import CSV</button>
            <button type="button" id="tab-build" class="text-sm font-bold text-slate-500 hover:text-slate-300 transition pb-2 border-b-2 border-transparent">Build Manually</button>
        </div>
        
        <!-- Import CSV Form -->
        <form id="form-import" action="" method="POST" enctype="multipart/form-data" class="space-y-4 block">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="import_dataset">
            
            <div>
                <label for="name" class="block text-sm font-medium text-slate-300 mb-1">Dataset Name</label>
                <input type="text" id="name" name="name" placeholder="e.g. Monster Deck V1" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 p-2.5">
            </div>

            <div>
                <label for="csv_file" class="block text-sm font-medium text-slate-300 mb-1">Upload CSV File</label>
                <input type="file" id="csv_file" name="csv_file" accept=".csv" class="w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500 cursor-pointer">
            </div>

            <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-slate-800"></div>
                </div>
                <div class="relative flex justify-center text-xs uppercase font-extrabold tracking-wider">
                    <span class="bg-slate-900 px-3 text-slate-500">Or Paste Raw CSV</span>
                </div>
            </div>

            <div>
                <label for="csv_text" class="block text-sm font-medium text-slate-300 mb-1">Pasted Tabular Data</label>
                <textarea id="csv_text" name="csv_text" rows="5" placeholder="Name,Attack,Health&#10;Goblin,3,2&#10;Dragon,12,25" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-indigo-500 p-2.5 font-mono"></textarea>
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-xl shadow-lg hover:shadow-indigo-500/20 py-2.5 px-4 transition duration-200">
                Import Dataset
            </button>
        </form>

        <!-- Build Manually Form -->
        <form id="form-build" action="" method="POST" class="space-y-4 hidden" onsubmit="return saveManualDataset(event);">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="build_dataset">
            <input type="hidden" id="grid_json" name="grid_json" value="">

            <div>
                <label for="build_name" class="block text-sm font-medium text-slate-300 mb-1">Dataset Name</label>
                <input type="text" id="build_name" name="name" placeholder="e.g. Custom Event Deck" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 p-2.5">
            </div>

            <div class="border border-slate-800 rounded-xl overflow-hidden bg-slate-950">
                <div class="flex items-center justify-between p-2 border-b border-slate-800 bg-slate-900/50">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Data Grid</h4>
                    <div class="flex space-x-2">
                        <button type="button" id="btn-add-col" class="text-[10px] uppercase font-bold px-2 py-1 bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 rounded transition">+ Column</button>
                        <button type="button" id="btn-add-row" class="text-[10px] uppercase font-bold px-2 py-1 bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 rounded transition">+ Row</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table id="builder-grid" class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-900/30 text-xs uppercase text-slate-500 border-b border-slate-800">
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

            <button type="submit" class="w-full bg-violet-600 hover:bg-violet-500 text-white font-medium rounded-xl shadow-lg hover:shadow-violet-500/20 py-2.5 px-4 transition duration-200">
                Build & Save Dataset
            </button>
        </form>
    </div>
    
    <!-- List of Datasets -->
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl space-y-4">
        <h2 class="text-xl font-bold text-slate-200">Available Datasets</h2>
        
        <?php if (empty($datasets)): ?>
            <p class="text-xs text-slate-500 py-4 text-center">No datasets imported yet.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($datasets as $data): ?>
                    <div class="flex items-center justify-between p-3 rounded-xl border <?php echo $inspectDatasetId === $data->getId() ? 'bg-indigo-500/10 border-indigo-500/30 text-white' : 'bg-slate-950 border-slate-800/80 text-slate-300'; ?> transition">
                        <a href="?project_id=<?php echo $activeProjectId; ?>&inspect_id=<?php echo $data->getId(); ?>" class="flex-grow font-semibold text-xs hover:underline truncate pr-2">
                            <?php echo SecurityHelper::escape($data->getName()); ?>
                            <span class="block text-[10px] text-slate-500 mt-0.5"><?php echo count($data->getRowData()); ?> rows | <?php echo count($data->getColumnMap()); ?> cols</span>
                        </a>
                        
                        <form action="" method="POST" class="m-0" onsubmit="event.preventDefault(); window.studioConfirm('Are you sure you want to delete this dataset? This may break active variable bindings on templates.', 'Delete', 'Delete Dataset').then((confirmed) => { if (confirmed) this.submit(); });">
                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                            <input type="hidden" name="action" value="delete_dataset">
                            <input type="hidden" name="dataset_id" value="<?php echo $data->getId(); ?>">
                            
                            <button type="submit" class="text-xs text-rose-500 hover:text-rose-400 p-1.5 rounded hover:bg-rose-500/10 border border-transparent">
                                Remove
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

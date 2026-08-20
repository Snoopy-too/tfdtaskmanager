<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;

if ($inspectDataset && !$isDatasetLocked): ?>
<!-- Re-Import / Overwrite CSV Modal -->
<div id="reimport_csv_modal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
    <div class="relative bg-slate-900 border border-slate-800 max-w-lg w-full rounded-2xl p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-violet-400"></span>
                <span>Re-Import / Update CSV Data</span>
            </h3>
            <button type="button" onclick="document.getElementById('reimport_csv_modal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
        </div>
        
        <p class="text-xs text-slate-400">
            Upload your updated CSV file (edited in Excel). This will update all columns and rows in <strong><?php echo SecurityHelper::escape($inspectDataset->getName()); ?></strong> in-place without breaking bound templates!
        </p>
        
        <form action="datasets.php?project_id=<?php echo $activeProjectId; ?>&inspect_id=<?php echo $inspectDataset->getId(); ?>" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="overwrite_dataset">
            <input type="hidden" name="dataset_id" value="<?php echo $inspectDataset->getId(); ?>">

            <div>
                <label for="reimport_csv_file" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Upload Updated CSV File</label>
                <input type="file" id="reimport_csv_file" name="csv_file" accept=".csv"
                    class="w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-violet-600 file:text-white hover:file:bg-violet-500 cursor-pointer bg-slate-950 border border-slate-800 rounded-xl p-1.5">
            </div>

            <div>
                <label for="reimport_csv_text" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Or Paste CSV Content</label>
                <textarea id="reimport_csv_text" name="csv_text" rows="4" placeholder="Name,Attack,Health,Special_Ability..." class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs font-mono rounded-xl p-3 focus:ring-violet-500 transition outline-none resize-y"></textarea>
            </div>

            <div class="flex justify-end space-x-3 pt-2">
                <button type="button" onclick="document.getElementById('reimport_csv_modal').classList.add('hidden')"
                    class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-sm rounded-lg transition duration-200">
                    Cancel
                </button>
                <button type="submit"
                    class="px-5 py-2 bg-violet-600 hover:bg-violet-500 text-white font-medium text-sm rounded-lg shadow-lg transition duration-200">
                    Update Dataset
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

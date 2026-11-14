<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
?>
<!-- Main Datasets Area (Full Width) -->
<div class="space-y-4">
    <!-- Section Header matching Components page -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                <span>📊</span>
                <span>Datasets &amp; Variables</span>
            </h2>
            <p class="text-xs text-slate-400">Card variable mappings and spreadsheet tables for <?php echo SecurityHelper::escape($activeProject ? $activeProject->getName() : 'Active Project'); ?>.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <?php if (!empty($datasets)): ?>
                <form method="GET" class="m-0">
                    <input type="hidden" name="project_id" value="<?php echo $activeProjectId; ?>">
                    <select name="inspect_id" onchange="this.form.submit()" class="bg-slate-950 border border-slate-700 text-slate-200 text-xs font-semibold rounded-lg focus:ring-1 focus:ring-indigo-500 py-1.5 pl-2.5 pr-7 cursor-pointer">
                        <option value="">-- Choose Dataset --</option>
                        <?php foreach ($datasets as $ds): ?>
                            <option value="<?php echo $ds->getId(); ?>" <?php echo ($inspectDataset && $inspectDataset->getId() === $ds->getId()) ? 'selected' : ''; ?>>
                                <?php echo SecurityHelper::escape($ds->getName()); ?> (<?php echo count($ds->getRowData()); ?> rows)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>

            <button type="button" onclick="toggleCollapsible('new-dataset-panel')" class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-600/15 hover:bg-indigo-600 text-indigo-300 hover:text-white border border-indigo-500/30 hover:border-transparent text-xs font-semibold rounded-lg transition duration-150">
                <i class="bi bi-plus-lg"></i>
                <span>Add Dataset</span>
            </button>
        </div>
    </div>

    <?php if (!$inspectDataset): ?>
        <?php if (empty($datasets)): ?>
            <!-- Friendly Empty State -->
            <div class="p-12 text-center bg-slate-900/40 border border-dashed border-slate-800 rounded-2xl max-w-lg mx-auto my-8 space-y-4">
                <div class="w-14 h-14 rounded-2xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 flex items-center justify-center text-3xl mx-auto">
                    📊
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-slate-200">No Datasets Created Yet</h3>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
                        Import CSV spreadsheets or build manual data tables to dynamically generate cards for <?php echo SecurityHelper::escape($activeProject ? $activeProject->getName() : 'Active Project'); ?>.
                    </p>
                </div>
                <div class="pt-2">
                    <button type="button" onclick="toggleCollapsible('new-dataset-panel')" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                        <i class="bi bi-plus-lg"></i>
                        <span>Create First Dataset</span>
                    </button>
                </div>
            </div>
        <?php else: ?>
            <!-- Datasets Grid Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($datasets as $data): ?>
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl flex flex-col justify-between hover:border-slate-700 hover:shadow-md transition">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-bold text-slate-100 text-sm truncate"><?php echo SecurityHelper::escape($data->getName()); ?></h3>
                                <span class="text-[10px] font-mono bg-slate-950 px-2 py-0.5 rounded text-indigo-400 border border-slate-800">
                                    <?php echo count($data->getRowData()); ?> rows
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">
                                Columns: <span class="text-slate-300 font-mono text-[11px]"><?php echo count($data->getColumnMap()); ?> mapped variables</span>
                            </p>
                        </div>
                        <div class="flex items-center justify-between pt-2.5 border-t border-slate-800/80 gap-2">
                            <a href="?project_id=<?php echo $activeProjectId; ?>&inspect_id=<?php echo $data->getId(); ?>" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 bg-indigo-500/10 hover:bg-indigo-500/20 px-2.5 py-1 rounded-lg border border-indigo-500/20 transition flex items-center gap-1.5">
                                <i class="bi bi-table"></i>
                                <span>Inspect &amp; Edit Table</span>
                            </a>
                            <form action="" method="POST" class="m-0" onsubmit="event.preventDefault(); window.studioConfirm('Are you sure you want to delete this dataset? This may break active variable bindings on templates.', 'Delete', 'Delete Dataset').then((confirmed) => { if (confirmed) this.submit(); });">
                                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                <input type="hidden" name="action" value="delete_dataset">
                                <input type="hidden" name="dataset_id" value="<?php echo $data->getId(); ?>">
                                <button type="submit" class="text-xs text-rose-500 hover:text-rose-400 p-1 rounded hover:bg-rose-500/10" title="Delete Dataset">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl space-y-4">
            <?php if ($isDatasetLocked): ?>
                <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-3 rounded-xl text-sm flex items-center justify-between gap-4 mb-4">
                    <div class="flex items-center space-x-2">
                        <svg class="h-5 w-5 text-rose-500 animate-pulse flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <span><strong>Read-Only View:</strong> This dataset is currently locked for editing by <strong><?php echo SecurityHelper::escape($lockUser ? $lockUser->getName() : 'another user'); ?></strong>.</span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <div>
                    <div class="flex items-center space-x-3">
                        <h2 class="text-xl font-bold text-slate-200"><?php echo SecurityHelper::escape($inspectDataset->getName()); ?></h2>
                        <span id="dataset-save-status" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            Auto-Saved
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">Use binding format `{{ColumnName}}` on card layers to substitute values.</p>
                </div>
                <div class="flex items-center space-x-2 flex-wrap gap-y-2">
                    <a href="datasets.php?project_id=<?php echo $activeProjectId; ?>&inspect_id=<?php echo $inspectDataset->getId(); ?>&action=export_csv" class="text-xs uppercase font-bold px-3 py-1.5 bg-indigo-600/20 text-indigo-300 hover:text-white hover:bg-indigo-600/40 border border-indigo-500/30 rounded-xl transition inline-flex items-center space-x-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span>Export CSV</span>
                    </a>
                    
                    <?php if (!$isDatasetLocked): ?>
                        <button type="button" onclick="document.getElementById('reimport_csv_modal').classList.remove('hidden')" class="text-xs uppercase font-bold px-3 py-1.5 bg-violet-600/20 text-violet-300 hover:text-white hover:bg-violet-600/40 border border-violet-500/30 rounded-xl transition inline-flex items-center space-x-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            <span>Re-Import CSV</span>
                        </button>
                        
                        <form action="" method="POST" class="m-0" id="form-add-column-inspect" onsubmit="event.preventDefault(); window.studioPrompt('Enter new column name (e.g. Health, Attack, Image):', '', 'Add Column').then((newCol) => { if (newCol && newCol.trim()) { this.querySelector('[name=column_name]').value = newCol.trim(); this.submit(); } });">
                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                            <input type="hidden" name="action" value="add_dataset_column">
                            <input type="hidden" name="dataset_id" value="<?php echo $inspectDataset->getId(); ?>">
                            <input type="hidden" name="column_name" value="">
                            <button type="submit" class="text-xs uppercase font-bold px-3 py-1.5 bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 rounded-xl transition">+ Column</button>
                        </form>

                        <form id="form-rename-column" action="" method="POST" class="hidden m-0">
                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                            <input type="hidden" name="action" value="rename_dataset_column">
                            <input type="hidden" name="dataset_id" value="<?php echo $inspectDataset->getId(); ?>">
                            <input type="hidden" name="old_column_name" value="">
                            <input type="hidden" name="new_column_name" value="">
                        </form>
                        
                        <form action="" method="POST" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                            <input type="hidden" name="action" value="add_dataset_row">
                            <input type="hidden" name="dataset_id" value="<?php echo $inspectDataset->getId(); ?>">
                            <button type="submit" class="text-xs uppercase font-bold px-3 py-1.5 bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 rounded-xl transition">+ Row</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Column Map Variables Badges -->
            <div>
                <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-2">Available Bindings</h4>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($inspectDataset->getColumnMap() as $col): ?>
                        <span class="text-xs font-mono font-semibold px-2.5 py-1 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            {{<?php echo SecurityHelper::escape($col); ?>}}
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Bound Templates Section -->
            <div>
                <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-2">Bound Design Templates</h4>
                <div class="flex flex-wrap gap-2">
                    <?php 
                    $boundTemplates = array_filter($allTemplates, function($t) use ($inspectDataset) {
                        return $t->getDatasetId() === $inspectDataset->getId();
                    });
                    ?>
                    <?php if (empty($boundTemplates)): ?>
                        <span class="text-xs text-slate-500 italic">No templates currently bound to this dataset.</span>
                    <?php else: ?>
                        <?php foreach ($boundTemplates as $bTmpl): ?>
                            <a href="editor.php?id=<?php echo $bTmpl->getId(); ?>" class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-violet-500/10 text-violet-400 border border-violet-500/20 hover:bg-violet-500/20 transition flex items-center space-x-1">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                <span><?php echo SecurityHelper::escape($bTmpl->getName()); ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Row Data Table Preview -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Data Rows Preview</h4>
                    <span class="text-[10px] text-slate-500 font-mono">Shift + Scroll to pan horizontally</span>
                </div>
                <div id="dataset-table-container" class="overflow-auto max-h-[calc(100vh-280px)] border border-slate-800 rounded-xl relative shadow-inner">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 z-20 bg-slate-950 text-slate-300 border-b border-slate-800 shadow-sm">
                            <tr>
                                <th class="p-3 font-semibold w-12 text-center sticky left-0 top-0 z-30 bg-slate-950 border-r border-slate-800 shadow-[2px_0_5px_rgba(0,0,0,0.5)]">Row</th>
                                <?php foreach ($inspectDataset->getColumnMap() as $col): ?>
                                    <th class="p-3 font-semibold relative group pr-6 bg-slate-950">
                                        <?php if (!$isDatasetLocked): ?>
                                            <span class="cursor-pointer hover:text-white hover:underline decoration-dotted transition"
                                                  title="Click to rename column"
                                                  onclick="window.studioPrompt('Rename column:', '<?php echo SecurityHelper::escape($col); ?>', 'Rename Column').then(n => {
                                                      if (n && (n = n.trim()) && n !== '<?php echo SecurityHelper::escape($col); ?>') {
                                                          const f = document.getElementById('form-rename-column');
                                                          f.elements['old_column_name'].value = '<?php echo SecurityHelper::escape($col); ?>';
                                                          f.elements['new_column_name'].value = n;
                                                          f.submit();
                                                      }
                                                  });">
                                                <?php echo SecurityHelper::escape($col); ?>
                                            </span>
                                        <?php else: ?>
                                            <span><?php echo SecurityHelper::escape($col); ?></span>
                                        <?php endif; ?>
                                        <?php if (!$isDatasetLocked): ?>
                                            <form action="" method="POST" class="absolute right-1 top-2.5 m-0 inline" onsubmit="event.preventDefault(); window.studioConfirm('Remove column: <?php echo SecurityHelper::escape($col); ?>? This will delete all cell values for this column.', 'Remove', 'Remove Column').then((confirmed) => { if (confirmed) this.submit(); });">
                                                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                                <input type="hidden" name="action" value="delete_dataset_column">
                                                <input type="hidden" name="dataset_id" value="<?php echo $inspectDataset->getId(); ?>">
                                                <input type="hidden" name="column_name" value="<?php echo SecurityHelper::escape($col); ?>">
                                                <button type="submit" class="text-rose-500 hover:text-rose-450 font-bold opacity-0 group-hover:opacity-100 transition text-[13px] leading-none" title="Delete Column">&times;</button>
                                            </form>
                                        <?php endif; ?>
                                    </th>
                                <?php endforeach; ?>
                                <?php if (!$isDatasetLocked): ?>
                                    <th class="p-3 font-semibold w-16 text-center bg-slate-950">Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $allRows = $inspectDataset->getRowData();
                            $previewLimit = 200;
                            $rows = array_slice($allRows, 0, $previewLimit);
                            $totalRows = count($allRows);
                            if (empty($rows)): 
                            ?>
                                <tr>
                                    <td colspan="<?php echo count($inspectDataset->getColumnMap()) + ($isDatasetLocked ? 1 : 2); ?>" class="p-8 text-center text-slate-500">
                                        No rows of data found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $index => $row): ?>
                                    <tr class="border-b border-slate-800/60 hover:bg-slate-800/30 text-slate-300">
                                        <td class="p-3 text-center text-slate-400 bg-slate-950 sticky left-0 z-10 border-r border-slate-800 font-bold shadow-[2px_0_5px_rgba(0,0,0,0.5)]"><?php echo $index + 1; ?></td>
                                        <?php foreach ($inspectDataset->getColumnMap() as $col): ?>
                                            <td class="p-1 border-r border-slate-800/40 last:border-r-0 transition-colors duration-200">
                                                <input type="text" 
                                                       value="<?php echo SecurityHelper::escape($row[$col] ?? ''); ?>" 
                                                       data-dataset-id="<?php echo $inspectDataset->getId(); ?>"
                                                       data-row-index="<?php echo $index; ?>"
                                                       data-column-name="<?php echo SecurityHelper::escape($col); ?>"
                                                       data-csrf-token="<?php echo SecurityHelper::escape($csrfToken); ?>"
                                                       class="dataset-cell-input w-full bg-transparent border-0 focus:border-0 focus:ring-0 text-xs text-slate-300 focus:text-white px-2 py-2"
                                                       <?php echo $isDatasetLocked ? 'disabled' : ''; ?>
                                                >
                                            </td>
                                        <?php endforeach; ?>
                                        <?php if (!$isDatasetLocked): ?>
                                            <td class="p-3 text-center bg-slate-950/20 border-l border-slate-800/40">
                                                <form action="" method="POST" class="m-0" onsubmit="event.preventDefault(); window.studioConfirm('Delete Row <?php echo $index + 1; ?>?', 'Delete', 'Delete Row').then((confirmed) => { if (confirmed) this.submit(); });">
                                                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="delete_dataset_row">
                                                    <input type="hidden" name="dataset_id" value="<?php echo $inspectDataset->getId(); ?>">
                                                    <input type="hidden" name="row_index" value="<?php echo $index; ?>">
                                                    <button type="submit" class="text-rose-500 hover:text-rose-450 font-bold text-sm px-1" title="Delete Row">&times;</button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($totalRows > $previewLimit): ?>
                                    <tr>
                                        <td colspan="<?php echo count($inspectDataset->getColumnMap()) + ($isDatasetLocked ? 1 : 2); ?>" class="p-3 text-center text-xs text-amber-500/80 bg-amber-500/5 border-t border-amber-500/20">
                                            Showing first <?php echo $previewLimit; ?> of <?php echo $totalRows; ?> rows. All rows will be used during export.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;

$projects = $projects ?? [];
$typeFilter = $typeFilter ?? 'all';
$searchQuery = $searchQuery ?? '';
$activeProjectId = $activeProjectId ?? null;
?>
<!-- Main Assets Grid Area -->
<div class="lg:col-span-3 space-y-6">
    <!-- Search and Filter Bar -->
    <div class="bg-slate-900/40 border border-slate-800 p-4 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" class="flex flex-col md:flex-row md:items-center gap-3 m-0 w-full flex-wrap">
            <input type="hidden" name="project_id" value="<?php echo $activeProjectId; ?>">
            <input type="hidden" name="type" value="<?php echo SecurityHelper::escape($typeFilter); ?>">
            
            <div class="relative w-full md:max-w-xs">
                <input type="text" name="search" value="<?php echo SecurityHelper::escape($searchQuery); ?>" placeholder="Search filename or tag..." class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 pl-9 pr-4 py-2">
                <svg class="absolute left-3 top-2.5 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <div class="flex items-center space-x-1.5">
                <a href="?project_id=<?php echo $activeProjectId; ?>&type=all&search=<?php echo urlencode($searchQuery); ?>&sort=<?php echo urlencode($sort); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?php echo $typeFilter === 'all' ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-slate-950 text-slate-400 hover:text-slate-200 border border-slate-800/80'; ?> transition">
                    All Assets
                </a>
                <a href="?project_id=<?php echo $activeProjectId; ?>&type=image&search=<?php echo urlencode($searchQuery); ?>&sort=<?php echo urlencode($sort); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?php echo $typeFilter === 'image' ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-slate-950 text-slate-400 hover:text-slate-200 border border-slate-800/80'; ?> transition">
                    Images
                </a>
                <a href="?project_id=<?php echo $activeProjectId; ?>&type=font&search=<?php echo urlencode($searchQuery); ?>&sort=<?php echo urlencode($sort); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?php echo $typeFilter === 'font' ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-slate-950 text-slate-400 hover:text-slate-200 border border-slate-800/80'; ?> transition">
                    Fonts
                </a>
            </div>

            <!-- Sort Selector -->
            <div class="flex items-center space-x-1.5">
                <label for="sort-select" class="text-slate-400 text-xs font-medium whitespace-nowrap">Sort:</label>
                <select id="sort-select" name="sort" onchange="this.form.submit()" class="bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-1 focus:ring-indigo-500 py-1.5 pl-3 pr-8 font-medium cursor-pointer">
                    <option value="date_desc" class="bg-slate-950 text-slate-100" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="date_asc" class="bg-slate-950 text-slate-100" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="name_asc" class="bg-slate-950 text-slate-100" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A → Z)</option>
                    <option value="name_desc" class="bg-slate-950 text-slate-100" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z → A)</option>
                </select>
            </div>
            
            <?php if ($searchQuery !== '' || $typeFilter !== 'all' || $sort !== 'date_desc'): ?>
                <a href="?project_id=<?php echo $activeProjectId; ?>" class="text-xs text-slate-500 hover:text-slate-300 self-center md:ml-auto">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Batch Selection & Action Bar -->
    <?php if (!empty($assets)): ?>
        <div id="batch-action-bar" class="bg-slate-900/80 border border-slate-800 p-3 rounded-2xl flex flex-wrap items-center justify-between gap-3 transition">
            <div class="flex items-center space-x-3">
                <label class="flex items-center space-x-2 text-xs font-semibold text-slate-200 cursor-pointer select-none">
                    <input type="checkbox" id="select-all-checkbox" onchange="toggleSelectAllAssets(this)" class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-indigo-500 h-4 w-4 cursor-pointer">
                    <span>Select All (<span id="selected-count" class="text-indigo-400 font-bold">0</span> / <?php echo count($assets); ?>)</span>
                </label>
                <button type="button" onclick="clearAssetSelection()" class="text-[11px] text-slate-400 hover:text-slate-200 underline">Clear</button>
            </div>

            <div class="flex items-center space-x-2 flex-wrap">
                <!-- Batch Move / Assign -->
                <form id="batch-move-form" action="" method="POST" class="m-0 flex items-center space-x-1.5" onsubmit="return handleBatchMoveSubmit(this);">
                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                    <input type="hidden" name="action" value="batch_update_project">
                    <input type="hidden" name="selected_ids" id="batch-move-ids" value="">
                    
                    <select name="target_project_id" id="batch-target-project" class="bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-1 focus:ring-indigo-500 py-1.5 pl-2.5 pr-7 font-medium cursor-pointer">
                        <option value="" class="bg-slate-950 text-slate-100">🌐 Move to Global</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?php echo $p->getId(); ?>" class="bg-slate-950 text-slate-100" <?php echo ($activeProjectId === $p->getId()) ? 'disabled' : ''; ?>>
                                📁 Assign to <?php echo SecurityHelper::escape($p->getName()); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" id="btn-batch-move" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl shadow transition disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                        Move Selected
                    </button>
                </form>

                <!-- Batch Delete -->
                <form id="batch-delete-form" action="" method="POST" class="m-0" onsubmit="return handleBatchDeleteSubmit(this);">
                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                    <input type="hidden" name="action" value="batch_delete">
                    <input type="hidden" name="selected_ids" id="batch-delete-ids" value="">
                    
                    <button type="submit" id="btn-batch-delete" class="px-3 py-1.5 bg-rose-600/20 hover:bg-rose-600/40 text-rose-300 border border-rose-500/30 text-xs font-semibold rounded-xl transition disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                        Delete Selected
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Grid -->
    <?php if (empty($assets)): ?>
        <div class="p-16 text-center bg-slate-900/30 border border-dashed border-slate-800 rounded-3xl">
            <svg class="mx-auto h-12 w-12 text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <h3 class="text-lg font-bold text-slate-300">No Assets Found</h3>
            <p class="text-sm text-slate-500 mt-1 max-w-sm mx-auto">No assets match your current filters. Add standard board game images or design fonts in the left panel.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach ($assets as $asset): ?>
                <?php 
                $isImage = str_starts_with($asset->getMimeType(), 'image/');
                $ext = strtolower(pathinfo($asset->getStoredFilename(), PATHINFO_EXTENSION));
                $isFont = str_contains($asset->getMimeType(), 'font') || in_array($ext, ['ttf', 'otf']);
                $folderName = ($asset->getProjectId() === null) ? 'global' : $asset->getProjectId();
                $fileUrl = '../uploads/board-game-studio/' . $folderName . '/' . $asset->getStoredFilename();
                ?>
                <div class="bg-slate-900 border border-slate-800/80 rounded-2xl overflow-hidden flex flex-col justify-between hover:border-slate-700 hover:shadow-lg transition group relative" id="asset-card-<?php echo $asset->getId(); ?>">
                    <!-- Checkbox selector -->
                    <label class="absolute top-2.5 left-2.5 z-20 flex items-center justify-center cursor-pointer p-1 rounded-lg bg-slate-900/90 hover:bg-slate-900 border border-slate-700/80 transition shadow select-none" title="Select asset">
                        <input type="checkbox" name="asset_select" value="<?php echo $asset->getId(); ?>" class="asset-item-checkbox rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-indigo-500 h-4 w-4 cursor-pointer" onchange="updateBatchActionBar()">
                    </label>

                    <!-- Preview Box -->
                    <div class="bg-slate-950 h-44 flex items-center justify-center relative overflow-hidden p-4 border-b border-slate-800/60">
                        <?php if ($isImage): ?>
                            <img src="<?php echo $fileUrl; ?>" alt="<?php echo SecurityHelper::escape($asset->getOriginalFilename()); ?>" class="max-h-full max-w-full object-contain group-hover:scale-[1.03] transition duration-300">
                        <?php elseif ($isFont): ?>
                            <div class="text-center space-y-2">
                                <svg class="mx-auto h-12 w-12 text-indigo-400 bg-indigo-500/10 p-2.5 rounded-2xl" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                </svg>
                                <span class="text-xs uppercase font-extrabold tracking-wider text-slate-400 bg-slate-900 border border-slate-800 px-2 py-0.5 rounded">Font (<?php echo strtoupper($ext); ?>)</span>
                            </div>
                        <?php else: ?>
                            <div class="text-slate-500 text-center">
                                <svg class="mx-auto h-10 w-10 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                <span class="text-xs">Generic Asset</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Details Block -->
                    <div class="p-4 space-y-3">
                        <div>
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-bold text-slate-200 truncate pr-2 pl-1" title="<?php echo SecurityHelper::escape($asset->getOriginalFilename()); ?>">
                                    <?php echo SecurityHelper::escape($asset->getOriginalFilename()); ?>
                                </h4>
                                <?php if ($asset->getProjectId() === null): ?>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-indigo-400 bg-indigo-500/10 px-2 py-0.5 border border-indigo-500/20 rounded-md shrink-0">Global</span>
                                <?php else: ?>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 bg-slate-800 px-2 py-0.5 border border-slate-700/80 rounded-md shrink-0">Project</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-slate-500 mt-1">
                                <span><?php echo round($asset->getFileSizeBytes() / 1024, 1); ?> KB</span>
                                <span>Uploaded <?php echo date('Y-m-d', strtotime($asset->getCreatedAt())); ?></span>
                            </div>
                        </div>

                        <!-- Tag input form -->
                        <form action="" method="POST" class="m-0 flex items-center space-x-1.5">
                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                            <input type="hidden" name="action" value="update_tag">
                            <input type="hidden" name="asset_id" value="<?php echo $asset->getId(); ?>">
                            
                            <input type="text" name="tag" value="<?php echo SecurityHelper::escape($asset->getTag() ?? ''); ?>" placeholder="Add tag [icon]" class="bg-slate-950 border border-slate-800 text-slate-300 text-[11px] rounded-lg px-2 py-1 w-full focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                            <button type="submit" class="p-1 text-xs text-indigo-400 hover:text-indigo-300 bg-indigo-500/5 border border-indigo-500/10 hover:border-indigo-500/30 rounded-lg transition" title="Save tag">
                                Save
                            </button>
                        </form>

                        <!-- Project Assignment & Delete Actions -->
                        <div class="pt-2 border-t border-slate-800/60 flex items-center justify-between gap-2">
                            <form action="" method="POST" class="m-0 flex items-center gap-1.5 flex-grow">
                                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                <input type="hidden" name="action" value="update_asset_project">
                                <input type="hidden" name="asset_id" value="<?php echo $asset->getId(); ?>">
                                
                                <select name="target_project_id" onchange="this.form.submit()" class="bg-slate-950 border border-slate-800 text-slate-300 text-[11px] rounded-lg px-2 py-1 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer w-full" title="Change asset scope (Global or assign to a specific project)">
                                    <option value="" <?php echo $asset->getProjectId() === null ? 'selected' : ''; ?>>🌐 Global</option>
                                    <?php foreach ($projects as $p): ?>
                                        <option value="<?php echo $p->getId(); ?>" <?php echo $asset->getProjectId() === $p->getId() ? 'selected' : ''; ?>>
                                            📁 <?php echo SecurityHelper::escape($p->getName()); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>

                            <form action="" method="POST" class="m-0" onsubmit="return showCustomConfirm('Are you sure you want to delete this asset? This cannot be undone and may break canvas layers referencing this asset.', this, 'Delete', 'Delete Asset');">
                                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                <input type="hidden" name="action" value="delete_asset">
                                <input type="hidden" name="asset_id" value="<?php echo $asset->getId(); ?>">
                                
                                <button type="submit" class="text-xs text-rose-500 hover:text-rose-400 bg-rose-500/5 hover:bg-rose-500/10 border border-rose-500/10 hover:border-rose-500/20 px-2 py-1 rounded-lg transition" title="Delete File">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

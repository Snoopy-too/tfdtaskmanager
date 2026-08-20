<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
?>
<!-- Templates List Grid -->
<div class="lg:col-span-2 space-y-4">
    <div class="flex justify-between items-center">
        <h2 class="text-xl font-bold text-slate-200">Design Templates</h2>
        <span class="text-xs text-slate-400 bg-slate-800/80 px-2.5 py-1 rounded-full font-medium">
            <?php echo count($templates); ?> Templates
        </span>
    </div>

    <?php if (empty($templates)): ?>
        <div class="p-12 text-center bg-slate-900/30 border border-dashed border-slate-800 rounded-2xl">
            <svg class="mx-auto h-10 w-10 text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
            </svg>
            <h4 class="text-sm font-semibold text-slate-300">No Templates Created</h4>
            <p class="text-xs text-slate-500 mt-1 max-w-xs mx-auto">Create a Poker Card or Tarot Card template on the right to start designing.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($templates as $tmpl): ?>
                <?php 
                $compTypes = $compTypes ?? [];
                $currentUserId = $currentUserId ?? 0;
                $cType = null;
                foreach ($compTypes as $ct) {
                    if ($ct->getId() === $tmpl->getComponentTypeId()) {
                        $cType = $ct;
                        break;
                    }
                }
                $isLocked = isset($templateService) ? $templateService->isTemplateLockedByOther($tmpl, $currentUserId) : false;
                $lockUser = null;
                if ($isLocked && isset($container)) {
                    $userService = $container->get(\App\Application\Services\UserService::class);
                    $lockUser = $userService->getUserById($tmpl->getLockedByUserId());
                }
                ?>
                <div class="bg-slate-900 border border-slate-800/80 p-5 rounded-2xl flex flex-col justify-between hover:border-slate-700/80 hover:shadow-lg transition">
                    <div class="space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-bold text-slate-200 text-sm leading-snug line-clamp-2 flex-grow" title="<?php echo SecurityHelper::escape($tmpl->getName()); ?>">
                                <?php echo SecurityHelper::escape($tmpl->getName()); ?>
                            </h3>
                            <?php if ($isLocked): ?>
                                <div class="flex items-center space-x-1 text-[10px] text-rose-400 font-semibold bg-rose-500/10 px-2 py-0.5 rounded flex-shrink-0" title="Locked by <?php echo SecurityHelper::escape($lockUser ? $lockUser->getName() : 'Other User'); ?>">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    <span>Locked</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php $isLandscape = $tmpl->getCanvasWidthPx() > $tmpl->getCanvasHeightPx(); ?>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded <?php echo $isLandscape ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-slate-800 text-slate-300 border border-slate-700'; ?>">
                                <?php echo $isLandscape ? 'Landscape' : 'Portrait'; ?>
                            </span>
                            <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                <?php echo $cType ? SecurityHelper::escape($cType->getName()) : 'Component'; ?>
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">
                            Dimensions: <?php echo round(\App\Domain\Entities\BgTemplate::pxToMm($tmpl->getCanvasWidthPx(), 300), 1); ?>x<?php echo round(\App\Domain\Entities\BgTemplate::pxToMm($tmpl->getCanvasHeightPx(), 300), 1); ?>mm (<?php echo $tmpl->getCanvasWidthPx(); ?>x<?php echo $tmpl->getCanvasHeightPx(); ?>px)
                        </p>
                        <form action="" method="POST" class="mt-3 text-xs">
                             <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                             <input type="hidden" name="action" value="update_template_dataset">
                             <input type="hidden" name="template_id" value="<?php echo $tmpl->getId(); ?>">
                             <label class="block text-[11px] font-semibold text-slate-400 mb-1 flex items-center justify-between">
                                 <span>Dataset Binding</span>
                                 <?php if ($tmpl->getDatasetId()): ?>
                                     <span class="text-[10px] text-violet-400 font-medium">Bound</span>
                                 <?php endif; ?>
                             </label>
                             <select name="dataset_id" onchange="this.form.submit()" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl p-2 focus:ring-indigo-500 focus:border-indigo-500">
                                 <option value="">No Dataset Bound</option>
                                 <?php foreach ($datasets as $data): ?>
                                     <option value="<?php echo $data->getId(); ?>" <?php echo ($tmpl->getDatasetId() === $data->getId()) ? 'selected' : ''; ?>>
                                         <?php echo SecurityHelper::escape($data->getName()); ?> (<?php echo count($data->getRowData()); ?> rows)
                                     </option>
                                 <?php endforeach; ?>
                             </select>
                        </form>
                    </div>
                    <div class="flex items-center justify-between mt-6 pt-3 border-t border-slate-800/60 gap-2">
                        <?php if ($isLocked): ?>
                            <a href="editor.php?id=<?php echo $tmpl->getId(); ?>" class="text-xs font-semibold text-slate-300 hover:text-white flex items-center space-x-1.5 bg-slate-800/50 hover:bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-700 transition">
                                <svg class="h-3.5 w-3.5 text-slate-450" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span>View Design</span>
                            </a>
                        <?php else: ?>
                            <a href="editor.php?id=<?php echo $tmpl->getId(); ?>" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 flex items-center space-x-1.5 bg-indigo-500/5 hover:bg-indigo-500/10 px-3 py-1.5 rounded-lg border border-indigo-500/20 transition">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                <span>Open Editor</span>
                            </a>
                        <?php endif; ?>
                        
                        <div class="flex items-center space-x-2">
                            <?php if (!$isLocked): ?>
                                <form action="" method="POST" class="m-0" onsubmit="return renameTemplate(<?php echo $tmpl->getId(); ?>, '<?php echo SecurityHelper::escape(addslashes($tmpl->getName())); ?>', this);">
                                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="rename_template">
                                    <input type="hidden" name="template_id" value="<?php echo $tmpl->getId(); ?>">
                                    <input type="hidden" name="name" id="rename_name_<?php echo $tmpl->getId(); ?>" value="">
                                    <button type="submit" class="text-xs text-amber-400 hover:text-amber-300 transition p-1 rounded hover:bg-amber-500/10 border border-transparent hover:border-amber-500/10" title="Rename Template">
                                        Rename
                                    </button>
                                </form>
                            <?php endif; ?>

                            <form action="" method="POST" class="m-0" onsubmit="return duplicateTemplate(<?php echo $tmpl->getId(); ?>, '<?php echo SecurityHelper::escape(addslashes($tmpl->getName())); ?>', this);">
                                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                <input type="hidden" name="action" value="duplicate_template">
                                <input type="hidden" name="template_id" value="<?php echo $tmpl->getId(); ?>">
                                <input type="hidden" name="new_name" id="dup_name_<?php echo $tmpl->getId(); ?>" value="">
                                <button type="submit" class="text-xs text-indigo-400 hover:text-indigo-300 transition p-1 rounded hover:bg-indigo-500/10 border border-transparent hover:border-indigo-500/10" title="Duplicate Template">
                                    Copy
                                </button>
                            </form>

                            <?php if (!$isLocked): ?>
                                <form action="" method="POST" class="m-0" onsubmit="return showCustomConfirm('Are you sure you want to delete this template?', this);">
                                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="delete_template">
                                    <input type="hidden" name="template_id" value="<?php echo $tmpl->getId(); ?>">
                                    <button type="submit" class="text-xs text-rose-500 hover:text-rose-400 transition p-1 rounded hover:bg-rose-500/10 border border-transparent hover:border-rose-500/10">
                                        Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

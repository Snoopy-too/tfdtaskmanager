<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
?>
<!-- Components / Templates Workspace Grid -->
<div class="space-y-4">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                <span>🎴</span>
                <span>Design Components</span>
            </h2>
            <p class="text-xs text-slate-400">Cards, boards, boxes, and tokens for <?php echo SecurityHelper::escape($activeProject->getName()); ?>.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="toggleCollapsible('new-template-panel')" class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-600/15 hover:bg-indigo-600 text-indigo-300 hover:text-white border border-indigo-500/30 hover:border-transparent text-xs font-semibold rounded-lg transition duration-150">
                <i class="bi bi-plus-lg"></i>
                <span>Add Component</span>
            </button>
        </div>
    </div>

    <?php if (empty($templates)): ?>
        <!-- Friendly Empty State -->
        <div class="p-12 text-center bg-slate-900/40 border border-dashed border-slate-800 rounded-2xl max-w-lg mx-auto my-8 space-y-4">
            <div class="w-14 h-14 rounded-2xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 flex items-center justify-center text-3xl mx-auto">
                🎴
            </div>
            <div class="space-y-1">
                <h3 class="text-base font-bold text-slate-200">No Components Created Yet</h3>
                <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
                    Start designing playing cards, tarot cards, game boards, boxes, or tokens for <?php echo SecurityHelper::escape($activeProject->getName()); ?>.
                </p>
            </div>
            <div class="pt-2">
                <button type="button" onclick="toggleCollapsible('new-template-panel')" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                    <i class="bi bi-plus-lg"></i>
                    <span>Create First Component</span>
                </button>
            </div>
        </div>
    <?php else: ?>
        <!-- Full-Width Responsive Components Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
                $isLandscape = $tmpl->getCanvasWidthPx() > $tmpl->getCanvasHeightPx();
                ?>
                <div class="bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 rounded-xl p-4 flex flex-col justify-between transition-all duration-200 group shadow-sm hover:shadow-indigo-500/5">
                    <div>
                        <!-- Header: Title + Lock status -->
                        <div class="flex items-start justify-between gap-2 mb-1.5">
                            <a href="editor.php?id=<?php echo $tmpl->getId(); ?>" class="font-bold text-slate-100 group-hover:text-indigo-300 text-sm leading-snug line-clamp-2 transition flex-grow" title="<?php echo SecurityHelper::escape($tmpl->getName()); ?>">
                                <?php echo SecurityHelper::escape($tmpl->getName()); ?>
                            </a>
                            <?php if ($isLocked): ?>
                                <div class="flex items-center space-x-1 text-[10px] text-rose-400 font-semibold bg-rose-500/10 px-2 py-0.5 rounded flex-shrink-0" title="Locked by <?php echo SecurityHelper::escape($lockUser ? $lockUser->getName() : 'Other User'); ?>">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    <span>Locked</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Spec Pills: Type + Orientation + Dimensions -->
                        <div class="flex flex-wrap items-center gap-1.5 text-[10px] mb-2.5">
                            <span class="font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                <?php echo $cType ? SecurityHelper::escape($cType->getName()) : 'Component'; ?>
                            </span>
                            <span class="font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded <?php echo $isLandscape ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-slate-800 text-slate-400 border border-slate-700/80'; ?>">
                                <?php echo $isLandscape ? 'Landscape' : 'Portrait'; ?>
                            </span>
                            <span class="text-slate-400 font-mono text-[11px] ml-auto">
                                <?php echo round(\App\Domain\Entities\BgTemplate::pxToMm($tmpl->getCanvasWidthPx(), 300), 1); ?>&times;<?php echo round(\App\Domain\Entities\BgTemplate::pxToMm($tmpl->getCanvasHeightPx(), 300), 1); ?>mm
                            </span>
                        </div>

                        <!-- Dataset Binding Selector -->
                        <form action="" method="POST" class="text-xs flex items-center gap-2 m-0">
                             <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                             <input type="hidden" name="action" value="update_template_dataset">
                             <input type="hidden" name="template_id" value="<?php echo $tmpl->getId(); ?>">
                             <span class="text-[11px] text-slate-400 whitespace-nowrap font-medium flex items-center gap-1">
                                 <i class="bi bi-table text-slate-500"></i> Dataset:
                             </span>
                             <select name="dataset_id" onchange="this.form.submit()" class="w-full bg-slate-950/70 border border-slate-800 text-slate-200 text-xs rounded-lg py-1 px-2 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer">
                                 <option value="">None (Static Card)</option>
                                 <?php foreach ($datasets as $data): ?>
                                     <option value="<?php echo $data->getId(); ?>" <?php echo ($tmpl->getDatasetId() === $data->getId()) ? 'selected' : ''; ?>>
                                         <?php echo SecurityHelper::escape($data->getName()); ?> (<?php echo count($data->getRowData()); ?> rows)
                                     </option>
                                 <?php endforeach; ?>
                             </select>
                        </form>
                    </div>

                    <!-- Footer: Primary Open Editor CTA + Subtle Actions -->
                    <div class="flex items-center justify-between mt-3.5 pt-2.5 border-t border-slate-800/70 gap-2 text-xs">
                        <?php if ($isLocked): ?>
                            <a href="editor.php?id=<?php echo $tmpl->getId(); ?>" class="font-semibold text-slate-300 hover:text-white flex items-center space-x-1.5 bg-slate-800/50 hover:bg-slate-800 px-3 py-1 rounded-lg border border-slate-700 transition">
                                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span>View Design</span>
                            </a>
                        <?php else: ?>
                            <a href="editor.php?id=<?php echo $tmpl->getId(); ?>" class="font-semibold text-indigo-400 hover:text-indigo-300 flex items-center space-x-1.5 bg-indigo-500/10 hover:bg-indigo-500/20 px-3 py-1 rounded-lg border border-indigo-500/20 transition">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                <span>Open Editor</span>
                            </a>
                        <?php endif; ?>
                        
                        <div class="flex items-center space-x-1.5">
                            <?php if (!$isLocked): ?>
                                <form action="" method="POST" class="m-0" onsubmit="return renameTemplate(<?php echo $tmpl->getId(); ?>, '<?php echo SecurityHelper::escape(addslashes($tmpl->getName())); ?>', this);">
                                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="rename_template">
                                    <input type="hidden" name="template_id" value="<?php echo $tmpl->getId(); ?>">
                                    <input type="hidden" name="name" id="rename_name_<?php echo $tmpl->getId(); ?>" value="">
                                    <button type="submit" class="text-xs text-amber-400 hover:text-amber-300 transition py-0.5 px-1.5 rounded hover:bg-amber-500/10" title="Rename Component">
                                        Rename
                                    </button>
                                </form>
                            <?php endif; ?>

                            <form action="" method="POST" class="m-0" onsubmit="return duplicateTemplate(<?php echo $tmpl->getId(); ?>, '<?php echo SecurityHelper::escape(addslashes($tmpl->getName())); ?>', this);">
                                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                <input type="hidden" name="action" value="duplicate_template">
                                <input type="hidden" name="template_id" value="<?php echo $tmpl->getId(); ?>">
                                <input type="hidden" name="new_name" id="dup_name_<?php echo $tmpl->getId(); ?>" value="">
                                <button type="submit" class="text-xs text-indigo-400 hover:text-indigo-300 transition py-0.5 px-1.5 rounded hover:bg-indigo-500/10" title="Duplicate Component">
                                    Copy
                                </button>
                            </form>

                            <?php if (!$isLocked): ?>
                                <form action="" method="POST" class="m-0" onsubmit="return showCustomConfirm('Are you sure you want to delete this component template?', this, 'Delete', 'Delete Template');">
                                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="delete_template">
                                    <input type="hidden" name="template_id" value="<?php echo $tmpl->getId(); ?>">
                                    <button type="submit" class="text-xs text-rose-500 hover:text-rose-400 transition py-0.5 px-1.5 rounded hover:bg-rose-500/10" title="Delete Component">
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

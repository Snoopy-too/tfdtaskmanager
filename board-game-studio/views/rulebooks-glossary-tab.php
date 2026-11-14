<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;

/**
 * Glossary Tab Partial for rulebooks.php
 * @var array $glossary
 * @var string $csrfToken
 * @var int|null $activeProjectId
 */
?>
<!-- Glossary Tab -->
<div id="glossary-tab" class="tab-content hidden space-y-6">
    <!-- Section Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-800/80">
        <div class="flex items-center space-x-3">
            <div class="p-2 rounded-xl bg-amber-500/10 text-amber-400">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                    Centralized Glossary
                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 font-mono font-normal">
                        <?php echo count($glossary); ?>
                    </span>
                </h2>
                <p class="text-xs text-slate-400">Define terminology keys (e.g. <code>banish_zone</code>) insertable as <code>[[banish_zone]]</code> anywhere in rulebooks or components.</p>
            </div>
        </div>
        <div class="flex items-center space-x-2">
            <button type="button" onclick="switchSidebarTab('manual'); toggleCollapsible('new-glossary-panel');" class="inline-flex items-center justify-center space-x-2 px-3.5 py-2 bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-amber-600/20 transition-all duration-200">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Add Term</span>
            </button>
            <button type="button" onclick="switchSidebarTab('csv'); const p=document.getElementById('new-glossary-panel'); if (p && !p.classList.contains('is-open')) toggleCollapsible('new-glossary-panel');" class="inline-flex items-center justify-center space-x-2 px-3.5 py-2 bg-slate-850 hover:bg-slate-800 border border-slate-700/80 text-slate-200 text-xs font-semibold rounded-xl transition duration-200">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span>Import CSV</span>
            </button>
        </div>
    </div>

    <!-- Combined Create/Edit/Import Glossary Form Collapsible Panel -->
    <div id="new-glossary-panel" class="form-panel-collapsible mb-6">
        <div class="form-panel-collapsible-inner bg-slate-900 border border-amber-500/20 rounded-2xl p-6 shadow-xl space-y-5">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-amber-500/10 text-amber-400 rounded-xl">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-200">Glossary Term Management</h3>
                        <p class="text-xs text-slate-400">Add or update game terms or batch import them from CSV</p>
                    </div>
                </div>
                <button type="button" onclick="toggleCollapsible('new-glossary-panel')" class="text-slate-400 hover:text-slate-200 text-xs px-2 py-1 rounded-lg hover:bg-slate-800 transition">
                    &times; Close
                </button>
            </div>

            <!-- Panel Tabs -->
            <div class="flex border-b border-slate-800 text-xs font-semibold max-w-xs">
                <button type="button" id="btn-sidebar-manual" onclick="switchSidebarTab('manual')" class="flex-grow pb-2 border-b-2 border-amber-500 text-white transition">
                    Manual Add
                </button>
                <button type="button" id="btn-sidebar-csv" onclick="switchSidebarTab('csv')" class="flex-grow pb-2 border-b-2 border-transparent text-slate-400 hover:text-white transition">
                    CSV Import
                </button>
            </div>

            <!-- Manual Add Form -->
            <div id="sidebar-manual-form" class="space-y-4">
                <h4 id="glossary-form-title" class="text-xs font-bold uppercase tracking-wider text-slate-300">Add Glossary Term</h4>
                <form action="" method="POST" class="space-y-4 max-w-2xl">
                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                    <input type="hidden" name="action" value="save_glossary_term">
                    <input type="hidden" name="term_id" id="form_term_id" value="">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="form_term_key" class="block text-xs font-medium text-slate-400 mb-1">Key Shorthand (e.g. banish_zone)</label>
                            <input type="text" id="form_term_key" name="term_key" required placeholder="lowercase, no spaces" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-amber-500 focus:border-amber-500 p-2.5 font-mono">
                        </div>

                        <div>
                            <label for="form_term_name" class="block text-xs font-medium text-slate-400 mb-1">Term Name (Display Name)</label>
                            <input type="text" id="form_term_name" name="term_name" required placeholder="e.g. Banish Zone" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-amber-500 focus:border-amber-500 p-2.5">
                        </div>
                    </div>

                    <div>
                        <label for="form_term_description" class="block text-xs font-medium text-slate-400 mb-1">Description</label>
                        <textarea id="form_term_description" name="term_description" rows="3" required placeholder="Definition or gameplay mechanics..." class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-amber-500 focus:border-amber-500 p-2.5"></textarea>
                    </div>

                    <div class="flex items-center space-x-3 pt-1">
                        <button type="submit" class="bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold rounded-xl py-2 px-5 transition duration-200 shadow-lg shadow-amber-600/20">
                            Save Term
                        </button>
                        <button type="button" id="form_cancel_btn" onclick="resetGlossaryForm(); toggleCollapsible('new-glossary-panel');" class="bg-slate-850 hover:bg-slate-800 text-slate-300 text-xs font-semibold rounded-xl py-2 px-4 transition duration-200">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- CSV Import Form -->
            <div id="sidebar-csv-form" class="space-y-4 hidden max-w-2xl">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-300">Import CSV Glossary</h4>
                <form action="api.php?action=import_glossary_csv" method="POST" enctype="multipart/form-data" class="space-y-4" id="csv-import-form" onsubmit="return handleCsvImport(event);">
                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                    <input type="hidden" name="project_id" value="<?php echo (int)$activeProjectId; ?>">

                    <div>
                        <label for="csv_file" class="block text-xs font-medium text-slate-400 mb-1">Upload CSV File</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" class="w-full text-xs text-slate-450 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-amber-600 file:text-white hover:file:bg-amber-500 cursor-pointer">
                    </div>

                    <div class="relative flex py-1 items-center">
                        <div class="flex-grow border-t border-slate-800"></div>
                        <span class="flex-shrink mx-2.5 text-slate-500 text-[10px]">Or Paste Raw CSV</span>
                        <div class="flex-grow border-t border-slate-800"></div>
                    </div>

                    <div>
                        <label for="csv_text" class="block text-xs font-medium text-slate-400 mb-1">CSV Text (Comma/Semicolon)</label>
                        <textarea id="csv_text" name="csv_text" rows="4" placeholder="key,name,description&#10;banish,Banish Zone,Exile a card.&#10;exhaust,Exhaust,Tap a card." class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-amber-500 p-2.5 font-mono"></textarea>
                    </div>

                    <div class="flex items-center space-x-3 pt-1">
                        <button type="submit" class="bg-amber-600 hover:bg-amber-500 text-white font-bold py-2 px-5 rounded-xl transition duration-200 text-xs shadow-lg shadow-amber-600/20">
                            Start CSV Import
                        </button>
                        <button type="button" onclick="toggleCollapsible('new-glossary-panel')" class="bg-slate-850 hover:bg-slate-800 text-slate-300 text-xs font-semibold rounded-xl py-2 px-4 transition duration-200">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Glossary List -->
    <div>
        <?php if (empty($glossary)): ?>
            <div class="p-12 text-center bg-slate-900/30 border border-dashed border-slate-800 rounded-2xl flex flex-col items-center justify-center space-y-4">
                <svg class="mx-auto h-12 w-12 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <div>
                    <h4 class="text-sm font-semibold text-slate-300">No Glossary Terms Defined</h4>
                    <p class="text-xs text-slate-400 mt-1">Start by adding a term manually or import a glossary CSV file.</p>
                </div>
                <div class="flex items-center space-x-3 pt-2">
                    <button type="button" onclick="switchSidebarTab('manual'); toggleCollapsible('new-glossary-panel');" class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-500 text-xs font-bold rounded-xl text-white transition duration-200">
                        <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Add Term</span>
                    </button>
                    <button type="button" onclick="switchSidebarTab('csv'); const p=document.getElementById('new-glossary-panel'); if (p && !p.classList.contains('is-open')) toggleCollapsible('new-glossary-panel');" class="inline-flex items-center px-4 py-2 border border-slate-700 hover:border-amber-500/50 hover:bg-slate-800 text-xs font-semibold rounded-xl text-slate-350 hover:text-white transition duration-200">
                        <span>Import CSV</span>
                        <svg class="h-3.5 w-3.5 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach ($glossary as $term): ?>
                    <div class="bg-slate-900/80 border border-slate-800/80 p-5 rounded-2xl flex flex-col justify-between hover:border-slate-700 hover:shadow-md transition space-y-3">
                        <div class="space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-bold text-slate-200 text-sm"><?php echo SecurityHelper::escape($term->getTermName()); ?></span>
                                <code class="text-[11px] bg-slate-950 border border-slate-800 text-amber-400 px-2 py-0.5 rounded font-mono flex-shrink-0">[[<?php echo SecurityHelper::escape($term->getTermKey()); ?>]]</code>
                            </div>
                            <p class="text-xs text-slate-400 line-clamp-3 leading-relaxed"><?php echo SecurityHelper::escape($term->getTermDescription()); ?></p>
                        </div>
                        <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-800/70 text-xs">
                            <button onclick="editGlossary(<?php echo htmlspecialchars(json_encode([
                                'id' => $term->getId(),
                                'term_key' => $term->getTermKey(),
                                'term_name' => $term->getTermName(),
                                'term_description' => $term->getTermDescription()
                            ], JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" class="text-amber-400 hover:text-amber-300 font-semibold transition px-2 py-1 rounded hover:bg-amber-500/10">
                                Edit
                            </button>
                            <form action="" method="POST" class="m-0" onsubmit="return showCustomConfirm('Are you sure you want to delete this glossary term?', this);">
                                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                <input type="hidden" name="action" value="delete_glossary_term">
                                <input type="hidden" name="term_id" value="<?php echo $term->getId(); ?>">
                                <button type="submit" class="text-rose-500 hover:text-rose-400 transition px-2 py-1 rounded hover:bg-rose-500/10 font-semibold">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

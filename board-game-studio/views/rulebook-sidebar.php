<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;

$rulebook = $rulebook ?? null;
$project = $project ?? null;
?>
<!-- Sidebar: Templates & Block types -->
<div id="editor-sidebar" class="w-full md:w-80 bg-slate-900 border-r border-slate-800 flex flex-col justify-between flex-shrink-0">
    <div class="p-6 space-y-6 overflow-y-auto flex-grow h-1">
        <div class="space-y-1">
            <a href="rulebooks.php?project_id=<?php echo $rulebook ? $rulebook->getProjectId() : 0; ?>" class="text-xs font-semibold text-slate-400 hover:text-white transition duration-200 inline-flex items-center">
                <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                Rulebooks list
            </a>
            <h2 class="text-lg font-black text-white truncate"><?php echo SecurityHelper::escape($rulebook ? $rulebook->getName() : 'Rulebook'); ?></h2>
            <p class="text-xs text-slate-500">Project: <?php echo SecurityHelper::escape($project ? $project->getName() : 'Active Project'); ?></p>
        </div>

        <!-- Sidebar Tab Buttons -->
        <div class="flex border-b border-slate-800 text-xs font-semibold mb-4">
            <button id="btn-sidebar-blocks" onclick="switchEditorSidebarTab('blocks')" class="flex-grow pb-2 border-b-2 border-amber-500 text-white transition">
                Blocks
            </button>
            <button id="btn-sidebar-theme" onclick="switchEditorSidebarTab('theme')" class="flex-grow pb-2 border-b-2 border-transparent text-slate-400 hover:text-white transition">
                Theme & CSS
            </button>
        </div>

        <!-- Tab Content: Blocks -->
        <div id="tab-content-blocks" class="space-y-6">
            <!-- Block Adder Options -->
            <div class="space-y-3">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Add Rulebook Block</h3>
                <div class="grid grid-cols-1 gap-2">
                    <button onclick="addBlock('markdown')" class="w-full text-left bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 p-3 rounded-xl flex items-center space-x-3 transition duration-200">
                        <span class="p-2 rounded-lg bg-amber-500/10 text-amber-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Markdown Text</p>
                            <p class="text-[10px] text-slate-500">Write rules, map icons, and terms.</p>
                        </div>
                    </button>

                    <button onclick="addBlock('setup')" class="w-full text-left bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 p-3 rounded-xl flex items-center space-x-3 transition duration-200">
                        <span class="p-2 rounded-lg bg-indigo-500/10 text-indigo-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Game Setup Diagram</p>
                            <p class="text-[10px] text-slate-500">Drag & drop visual components.</p>
                        </div>
                    </button>

                    <button onclick="addBlock('component_list')" class="w-full text-left bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 p-3 rounded-xl flex items-center space-x-3 transition duration-200">
                        <span class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Component Inventory</p>
                            <p class="text-[10px] text-slate-500">Auto-generated templates list.</p>
                        </div>
                    </button>

                    <button onclick="addBlock('anatomy')" class="w-full text-left bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 p-3 rounded-xl flex items-center space-x-3 transition duration-200">
                        <span class="p-2 rounded-lg bg-rose-500/10 text-rose-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Anatomy of a Component</p>
                            <p class="text-[10px] text-slate-500">Label regions with coordinate pins.</p>
                        </div>
                    </button>

                    <button onclick="addBlock('page_break')" class="w-full text-left bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 p-3 rounded-xl flex items-center space-x-3 transition duration-200">
                        <span class="p-2 rounded-lg bg-teal-500/10 text-teal-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 5h16M4 12h16M4 19h16"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Page Break</p>
                            <p class="text-[10px] text-slate-500">Force content after this block to next page.</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Glossary and Assets list overview -->
            <div class="space-y-3">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Icon Tag Cheat Sheet</h3>
                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-1.5 max-h-48 overflow-y-auto">
                    <?php if (empty($assets)): ?>
                        <p class="text-[10px] text-slate-500">Upload assets in Studio to see tags.</p>
                    <?php else: ?>
                        <?php foreach ($assets as $asset): ?>
                            <?php if ($asset->getTag()): ?>
                                <div class="flex items-center justify-between text-[11px]">
                                    <code class="text-amber-400 font-mono">[<?php echo SecurityHelper::escape($asset->getTag()); ?>]</code>
                                    <span class="text-slate-500 truncate max-w-[120px]" title="<?php echo SecurityHelper::escape($asset->getOriginalFilename()); ?>"><?php echo SecurityHelper::escape($asset->getOriginalFilename()); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab Content: Theme & CSS -->
        <div id="tab-content-theme" class="space-y-6 hidden">
            <div class="space-y-4">
                <!-- Presets & Exchange Actions -->
                <div class="border-b border-slate-800 pb-4 mb-4 space-y-3">
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Theme Presets</label>
                    <div class="flex space-x-2">
                        <select id="theme-presets-select" onchange="loadThemePreset(this.value)" class="flex-grow bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl p-2.5 focus:ring-amber-500">
                            <option value="">-- Select Saved Preset --</option>
                        </select>
                        <button onclick="deleteThemePreset()" title="Delete Preset" class="px-3 bg-rose-950/40 hover:bg-rose-900/40 text-rose-400 border border-rose-900/50 rounded-xl text-xs transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <button onclick="saveThemePreset()" class="bg-slate-800 hover:bg-slate-750 border border-slate-700 text-slate-200 py-1.5 px-2.5 rounded-xl text-xs font-semibold transition text-center flex items-center justify-center space-x-1.5">
                            <svg class="h-3.5 w-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                            <span>Save Preset</span>
                        </button>
                        <button onclick="document.getElementById('theme-import-input').click()" class="bg-slate-800 hover:bg-slate-750 border border-slate-700 text-slate-200 py-1.5 px-2.5 rounded-xl text-xs font-semibold transition text-center flex items-center justify-center space-x-1.5">
                            <svg class="h-3.5 w-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            <span>Import File</span>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 pt-1">
                        <button onclick="exportTheme()" class="bg-amber-600/10 hover:bg-amber-600/20 border border-amber-500/20 hover:border-amber-500/30 text-amber-400 py-1.5 px-3 rounded-xl text-xs font-semibold transition text-center flex items-center justify-center space-x-1.5 w-full">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Export Theme JSON</span>
                        </button>
                    </div>
                    <input type="file" id="theme-import-input" accept=".json" onchange="importTheme(this.files[0])" class="hidden">
                </div>

                <div>
                     <label class="block text-xs font-semibold text-slate-400 mb-1">Rulebook Typography</label>
                     <select id="theme-font-select" onchange="updateThemeFont(this.value)" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl p-2.5 focus:ring-amber-500">
                         <option value="Inter">Classic Sans-Serif (Inter)</option>
                         <option value="Playfair Display">Elegant Classic Serif (Playfair Display)</option>
                         <option value="Outfit">Modern Clean Geometric (Outfit)</option>
                         <option value="Cinzel">Fantasy & Medieval Theme (Cinzel)</option>
                         <option value="Share Tech Mono">Sci-Fi & Cyberpunk (Share Tech Mono)</option>
                         <option value="Queensberry Vintage">Queensberry Vintage (Special Elite & Serif)</option>
                     </select>
                </div>

                <div>
                     <label class="block text-xs font-semibold text-slate-400 mb-1">Primary Accent Color</label>
                     <div class="flex items-center space-x-3">
                         <input type="color" id="theme-color-input" onchange="updateThemeColor(this.value)" class="w-10 h-10 bg-transparent border-0 cursor-pointer rounded">
                         <span id="theme-color-hex" class="text-xs font-mono text-slate-450">#f59e0b</span>
                     </div>
                </div>

                <div>
                     <label class="block text-xs font-semibold text-slate-400 mb-1">Page Style</label>
                     <select id="theme-style-select" onchange="updateThemeStyle(this.value)" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl p-2.5 focus:ring-amber-500">
                         <option value="dark">Dark Slate Workspace</option>
                         <option value="parchment">Warm Vintage Parchment</option>
                         <option value="light">Clean High-Contrast Light</option>
                     </select>
                </div>

                <div>
                     <label class="block text-xs font-semibold text-slate-400 mb-1">Global Font Size</label>
                     <select id="theme-size-select" onchange="updateThemeSize(this.value)" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl p-2.5 focus:ring-amber-500">
                         <option value="small">Small Text</option>
                         <option value="medium">Medium (Default)</option>
                         <option value="large">Large Text</option>
                     </select>
                </div>

                <div>
                     <label class="block text-xs font-semibold text-slate-400 mb-1">Layout Spacing Density</label>
                     <select id="theme-density-select" onchange="updateThemeDensity(this.value)" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl p-2.5 focus:ring-amber-500">
                         <option value="compact">Compact Spacing</option>
                         <option value="normal">Normal (Default)</option>
                         <option value="spacious">Spacious Spacing</option>
                     </select>
                </div>

                <div>
                     <label class="block text-xs font-semibold text-slate-400 mb-1">Header Text Alignment</label>
                     <select id="theme-align-select" onchange="updateThemeAlign(this.value)" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl p-2.5 focus:ring-amber-500">
                         <option value="left">Left Aligned</option>
                         <option value="center">Centered</option>
                     </select>
                </div>

                <div>
                     <label class="block text-xs font-semibold text-slate-400 mb-1">Custom CSS Styling overrides</label>
                     <p class="text-[10px] text-slate-500 mb-2">Write custom CSS rules to adjust padding, change borders, background colors, custom titles, page breaks, etc.</p>
                     <textarea id="theme-css-textarea" oninput="updateThemeCss(this.value)" rows="12" class="w-full font-mono text-[10px] bg-slate-950 border border-slate-800 text-slate-200 rounded-xl p-2.5 focus:ring-amber-500 focus:border-amber-500" placeholder="/* Custom CSS overrides */&#10;h2 { font-style: italic; }&#10;.block-card { border-radius: 12px; }"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="p-6 border-t border-slate-800 bg-slate-950 space-y-3">
        <button onclick="saveRulebook()" class="w-full bg-amber-600 hover:bg-amber-500 text-white text-sm font-bold py-2 rounded-xl transition duration-200 flex items-center justify-center space-x-2">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
            <span>Save Document</span>
        </button>
        <button onclick="triggerPrint()" class="w-full bg-slate-800 hover:bg-slate-700 text-slate-350 text-sm font-semibold py-2 rounded-xl border border-slate-700 transition duration-200 flex items-center justify-center space-x-2">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-3a2 2 0 00-2-2H9a2 2 0 00-2 2v3a2 2 0 002 2zm5-17V4a2 2 0 00-2-2H9a2 2 0 00-2 2v3"/></svg>
            <span>Print-Ready PDF</span>
        </button>
    </div>
</div>

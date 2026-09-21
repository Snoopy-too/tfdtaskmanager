<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
?>
<!-- Left Panel: Layers and Assets -->
<div id="left-layers-panel" class="w-[280px] shrink-0 min-w-0 bg-slate-900/50 border border-slate-800 rounded-2xl flex flex-col h-full overflow-hidden transition-all duration-200">
    <!-- Tabs -->
    <div class="flex border-b border-slate-800 items-center">
        <button id="tab-layers-btn" class="flex-1 py-2.5 text-center text-xs font-bold uppercase tracking-wider text-indigo-400 border-b-2 border-indigo-400">Layers</button>
        <button id="tab-assets-btn" class="flex-1 py-2.5 text-center text-xs font-bold uppercase tracking-wider text-slate-400 hover:text-slate-200">Assets</button>
    </div>

    <!-- Content Area -->
    <div class="flex-grow overflow-y-auto p-3 space-y-3">
        
        <!-- Layers Tab View -->
        <div id="tab-layers-view" class="space-y-3">
            <!-- Layer Addition Controls -->
            <div class="grid grid-cols-2 gap-2">
                <button id="btn-add-text" class="py-2 px-2.5 bg-slate-900 border border-slate-800 text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800 hover:border-slate-700 rounded-xl transition flex items-center justify-center space-x-1.5 shadow-sm" title="Add Text Layer">
                    <svg class="w-3.5 h-3.5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 6v12M14 6v12"/></svg>
                    <span>Text</span>
                </button>
                <button id="btn-add-image" class="py-2 px-2.5 bg-slate-900 border border-slate-800 text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800 hover:border-slate-700 rounded-xl transition flex items-center justify-center space-x-1.5 shadow-sm" title="Add Image Layer">
                    <svg class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Image</span>
                </button>
                <button id="btn-add-rect" class="py-2 px-2.5 bg-slate-900 border border-slate-800 text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800 hover:border-slate-700 rounded-xl transition flex items-center justify-center space-x-1.5 shadow-sm" title="Add Rectangle Layer">
                    <svg class="w-3.5 h-3.5 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2" stroke-width="2"/></svg>
                    <span>Rectangle</span>
                </button>
                <button id="btn-add-circle" class="py-2 px-2.5 bg-slate-900 border border-slate-800 text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800 hover:border-slate-700 rounded-xl transition flex items-center justify-center space-x-1.5 shadow-sm" title="Add Circle Layer">
                    <svg class="w-3.5 h-3.5 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8" stroke-width="2"/></svg>
                    <span>Circle</span>
                </button>
                <button id="btn-add-triangle" class="py-2 px-2.5 bg-slate-900 border border-slate-800 text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800 hover:border-slate-700 rounded-xl transition flex items-center justify-center space-x-1.5 shadow-sm" title="Add Triangle Layer">
                    <svg class="w-3.5 h-3.5 text-orange-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4l8 15H4L12 4z"/></svg>
                    <span>Triangle</span>
                </button>
                <button id="btn-add-line" class="py-2 px-2.5 bg-slate-900 border border-slate-800 text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800 hover:border-slate-700 rounded-xl transition flex items-center justify-center space-x-1.5 shadow-sm" title="Add Line Layer">
                    <svg class="w-3.5 h-3.5 text-sky-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19L19 5"/></svg>
                    <span>Line</span>
                </button>
                <button id="btn-import-template" class="col-span-2 py-2.5 bg-indigo-600/20 border border-indigo-500/40 text-xs font-semibold text-indigo-300 hover:bg-indigo-600/30 hover:text-white hover:border-indigo-400 rounded-xl transition flex items-center justify-center space-x-1.5">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <span>Import Template Component</span>
                </button>
            </div>

            <!-- Layers list container -->
            <div class="space-y-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Layers Stack (Top-down)</div>
                <div id="layers-list" class="space-y-1.5 min-h-[200px] border border-dashed border-slate-800/80 rounded-xl p-2 bg-slate-900/40">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>

        <!-- Assets Tab View (Hidden initially) -->
        <div id="tab-assets-view" class="space-y-3 hidden">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider flex justify-between items-center">
                <span>Project Assets</span>
                <a href="assets.php?project_id=<?php echo $template->getProjectId(); ?>" target="_blank" class="text-[10px] text-indigo-400 hover:underline">Upload Files</a>
            </div>
            <div id="asset-picker-grid" class="grid grid-cols-2 gap-3">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>
</div>

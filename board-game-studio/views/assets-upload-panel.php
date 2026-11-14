<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
?>
<!-- Upload Asset Collapsible Form Panel -->
<div id="upload-asset-panel" class="form-panel-collapsible mb-6">
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800">
        <div>
            <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                <span>🖼️</span>
                <span>Upload Assets &amp; Fonts</span>
            </h2>
            <p class="text-xs text-slate-400">Add card illustrations, background textures, token icons, custom typography, or bulk ZIP archives.</p>
        </div>
        <button type="button" onclick="toggleCollapsible('upload-asset-panel')" class="text-slate-400 hover:text-slate-200 text-sm font-semibold p-1">
            &times; Close
        </button>
    </div>

    <form action="" method="POST" enctype="multipart/form-data" class="space-y-4" id="asset-upload-form" onsubmit="return handleAssetUploadSubmit(this)">
        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
        <input type="hidden" name="action" value="upload_asset">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Left: Dropzone -->
            <div>
                <label for="asset_file" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Select File(s) or ZIP Archive *</label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-800 border-dashed rounded-xl hover:border-indigo-500/50 bg-slate-950/40 transition cursor-pointer relative group">
                    <input type="file" id="asset_file" name="asset_file[]" accept=".png,.jpg,.jpeg,.svg,.ttf,.otf,.zip" multiple required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    <div class="space-y-1.5 text-center pointer-events-none">
                        <svg class="mx-auto h-8 w-8 text-slate-500 group-hover:text-indigo-400 transition" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4-4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="text-xs text-slate-300">
                            <span class="font-semibold text-indigo-400 group-hover:text-indigo-300 transition">Click to upload files or ZIP</span> or drag and drop
                        </div>
                        <p class="text-[10px] text-slate-500">PNG, JPG, SVG, TTF, OTF or ZIP up to 10MB each</p>
                    </div>
                </div>
                <div id="file_selected_preview" class="mt-2.5 hidden"></div>
            </div>

            <!-- Right: Tagging, Options & Secondary Actions -->
            <div class="space-y-3.5 flex flex-col justify-between">
                <div class="space-y-3">
                    <div>
                        <label for="tag" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Asset Tag (Optional)</label>
                        <input type="text" id="tag" name="tag" placeholder="e.g. icon_health or font_title"
                            class="w-full bg-slate-950/70 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                        <p class="text-[10px] text-slate-500 mt-1">Tags let you insert dynamic icons via text boxes in components (e.g. [icon_health]).</p>
                    </div>

                    <?php if ($activeProjectId !== null): ?>
                        <label class="flex items-center gap-2 p-2 bg-slate-950/40 border border-slate-800 rounded-lg hover:border-slate-700 transition cursor-pointer select-none">
                            <input type="checkbox" id="is_global" name="is_global" value="1" class="rounded bg-slate-900 border-slate-700 text-indigo-600 focus:ring-indigo-500 h-4 w-4 cursor-pointer">
                            <span class="text-xs text-slate-300 font-medium">Make Global (available across all game projects)</span>
                        </label>
                    <?php endif; ?>
                </div>

                <div class="p-3 bg-slate-950/40 border border-slate-800/80 rounded-xl flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold text-slate-200">System Icon Repository</div>
                        <p class="text-[10px] text-slate-500">Sync built-in SVG attributes &amp; symbols into library.</p>
                    </div>
                    <button type="submit" form="sync-builtin-form" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 text-xs font-semibold rounded-lg transition whitespace-nowrap">
                        Sync Icons
                    </button>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800/80">
            <button type="button" onclick="toggleCollapsible('upload-asset-panel')" class="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 transition">
                Cancel
            </button>
            <button type="submit" id="btn-upload-submit" class="px-5 py-2 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">
                Upload Asset
            </button>
        </div>
    </form>

    <!-- Hidden form for icon sync trigger -->
    <form id="sync-builtin-form" action="" method="POST" class="hidden">
        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
        <input type="hidden" name="action" value="sync_builtin_icons">
    </form>
</div>

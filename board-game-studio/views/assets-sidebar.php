<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
?>
<!-- Sidebar Upload Section -->
<div class="space-y-6">
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl">
        <h2 class="text-xl font-bold text-slate-200 mb-4">Upload Asset</h2>
        
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4" id="asset-upload-form" onsubmit="return handleAssetUploadSubmit(this)">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="upload_asset">
            
            <div>
                <label for="asset_file" class="block text-sm font-medium text-slate-300 mb-1">Select File(s) or ZIP Archive</label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-800 border-dashed rounded-xl hover:border-indigo-500/50 transition cursor-pointer relative group">
                    <input type="file" id="asset_file" name="asset_file[]" accept=".png,.jpg,.jpeg,.svg,.ttf,.otf,.zip" multiple required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    <div class="space-y-1 text-center pointer-events-none">
                        <svg class="mx-auto h-10 w-10 text-slate-500 group-hover:text-indigo-400 transition" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4-4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="text-xs text-slate-300">
                            <span class="font-medium text-indigo-400 group-hover:text-indigo-300 transition">Click to upload files or ZIP</span> or drag and drop
                        </div>
                        <p class="text-[10px] text-slate-500">PNG, JPG, SVG, TTF, OTF or ZIP up to 10MB each</p>
                    </div>
                </div>
                <div id="file_selected_preview" class="mt-3 hidden"></div>
            </div>

            <div>
                <label for="tag" class="block text-sm font-medium text-slate-300 mb-1">Asset Tag (Optional)</label>
                <input type="text" id="tag" name="tag" placeholder="e.g. icon_health or font_title" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                <p class="text-[10px] text-slate-500 mt-1">Tags let you insert dynamic icons via text boxes (e.g. [icon_health]).</p>
            </div>

            <?php if ($activeProjectId !== null): ?>
                <div class="flex items-center space-x-2 py-1">
                    <input type="checkbox" id="is_global" name="is_global" value="1" class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-indigo-500 h-4 w-4 cursor-pointer">
                    <label for="is_global" class="text-sm font-medium text-slate-300 cursor-pointer select-none">Make Global (available to all projects)</label>
                </div>
            <?php endif; ?>

            <button type="submit" id="btn-upload-submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-xl shadow-lg hover:shadow-indigo-500/20 py-2.5 px-4 transition duration-200">
                Upload Asset
            </button>
        </form>
    </div>

    <!-- Built-in System Icons Card -->
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-200">System Icon Library</h3>
            <span class="text-[10px] font-semibold uppercase text-indigo-400 bg-indigo-500/10 px-2 py-0.5 rounded border border-indigo-500/20">Repository</span>
        </div>
        <p class="text-xs text-slate-400 leading-relaxed">
            Synchronize built-in SVG icons (boxing attributes, knockout icons, resources, and symbols) from the repository into your Global Asset Library.
        </p>
        <form action="" method="POST" class="m-0">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="sync_builtin_icons">
            <button type="submit" class="w-full bg-slate-950 hover:bg-slate-800 text-slate-200 hover:text-white border border-slate-700/80 font-medium text-xs rounded-xl py-2.5 px-4 transition flex items-center justify-center space-x-2 cursor-pointer shadow">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Sync / Import Built-in Icons</span>
            </button>
        </form>
    </div>
</div>

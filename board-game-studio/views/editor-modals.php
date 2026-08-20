<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;

$widthMm = $widthMm ?? ($compType ? $compType->getWidthMm() : round(($template ? $template->getCanvasWidthPx() : 750) / 11.811, 1));
$heightMm = $heightMm ?? ($compType ? $compType->getHeightMm() : round(($template ? $template->getCanvasHeightPx() : 1050) / 11.811, 1));
$compTypes = $compTypes ?? [];
?>
<!-- Full Screen Preview Overlay -->
<div id="preview-overlay" class="fixed inset-0 bg-slate-950/95 z-[9999] hidden flex-col items-center justify-center p-6 transition-all duration-300 opacity-0">
    <button onclick="closeFullscreenPreview()" class="absolute top-6 right-6 p-2 bg-slate-900/80 hover:bg-slate-850 border border-slate-800 text-slate-400 hover:text-white rounded-xl shadow-lg transition duration-200" title="Exit Preview (Esc)">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <div class="relative max-w-full max-h-full flex items-center justify-center">
        <img id="preview-image" src="" alt="Canvas Preview" class="max-w-[90vw] max-h-[85vh] rounded-xl shadow-2xl border border-slate-800 object-contain">
    </div>
    <p class="text-xs text-slate-500 mt-4 tracking-wider uppercase font-semibold">Press Esc to Exit Preview</p>
</div>

<!-- Import Template Component Modal -->
<div id="modal-import-template" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-base font-bold text-slate-100 flex items-center space-x-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <span>Import Design Template</span>
            </h3>
            <button id="btn-close-import-modal" class="text-slate-400 hover:text-white text-lg font-bold p-1">
                &times;
            </button>
        </div>

        <p class="text-xs text-slate-400">
            Select a saved template (e.g. Reference Chart, Legend, or Stat Block) to insert into your active canvas as a component layer.
        </p>

        <div class="space-y-3">
            <div>
                <label for="import-template-select" class="block text-xs font-semibold text-slate-300 mb-1">Available Templates</label>
                <select id="import-template-select" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl p-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Loading templates...</option>
                </select>
            </div>

            <!-- Dataset Row Selector (visible when selected template has a bound dataset) -->
            <div id="import-dataset-row-container" class="space-y-1 hidden pt-1">
                <label for="import-dataset-row-select" class="block text-xs font-semibold text-slate-300 mb-1 flex items-center justify-between">
                    <span>Dataset Card / Row (<span id="import-dataset-name" class="text-indigo-400 font-bold"></span>)</span>
                    <span id="import-dataset-row-count" class="text-[10px] text-slate-400 font-normal"></span>
                </label>
                <select id="import-dataset-row-select" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl p-2.5 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer">
                    <option value="raw">Template Default (Unsubstituted {{Placeholders}})</option>
                </select>
                <p class="text-[11px] text-slate-500 pt-0.5">Select a specific row (e.g. 16th fighter) to populate data onto this component.</p>
            </div>

            <div class="flex items-center space-x-2 pt-1">
                <input type="checkbox" id="import-as-group" checked class="rounded border-slate-800 bg-slate-950 text-indigo-600 focus:ring-indigo-500">
                <label for="import-as-group" class="text-xs text-slate-300">
                    Group elements together into a single component layer (recommended)
                </label>
            </div>
        </div>

        <div class="flex justify-end space-x-3 pt-3 border-t border-slate-800">
            <button id="btn-cancel-import" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-xl transition">
                Cancel
            </button>
            <button id="btn-confirm-import" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white rounded-xl shadow transition">
                Import onto Canvas
            </button>
        </div>
    </div>
</div>

<!-- Change Canvas Size Modal -->
<div id="modal-change-size" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-base font-bold text-slate-100 flex items-center space-x-2">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                </svg>
                <span>Change Template Size</span>
            </h3>
            <button onclick="closeChangeSizeModal()" class="text-slate-400 hover:text-white text-lg font-bold p-1">
                &times;
            </button>
        </div>

        <div class="space-y-3">
            <div>
                <label for="resize-preset-select" class="block text-xs font-semibold text-slate-300 mb-1">Preset Size / Component Type</label>
                <select id="resize-preset-select" onchange="handleResizePresetChange(this)" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl p-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="custom">Custom Size</option>
                    <?php foreach ($compTypes as $ct): ?>
                        <option value="<?php echo $ct->getId(); ?>" data-width="<?php echo $ct->getWidthMm(); ?>" data-height="<?php echo $ct->getHeightMm(); ?>" <?php echo ($ct->getId() === $template->getComponentTypeId()) ? 'selected' : ''; ?>>
                            <?php echo SecurityHelper::escape($ct->getName()); ?> (<?php echo $ct->getWidthMm(); ?>x<?php echo $ct->getHeightMm(); ?> mm)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="resize-width-mm" class="block text-xs font-medium text-slate-300 mb-1">Width (mm)</label>
                    <input type="number" id="resize-width-mm" min="10" max="2000" step="0.5" value="<?php echo $widthMm; ?>" oninput="updateResizePreview()" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl p-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label for="resize-height-mm" class="block text-xs font-medium text-slate-300 mb-1">Height (mm)</label>
                    <input type="number" id="resize-height-mm" min="10" max="2000" step="0.5" value="<?php echo $heightMm; ?>" oninput="updateResizePreview()" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl p-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            <div class="flex items-center justify-between bg-slate-950/60 border border-slate-800/80 px-3 py-2 rounded-xl text-xs">
                <span class="text-slate-400">Resulting Pixels (300 DPI):</span>
                <span id="resize-px-preview" class="font-mono font-semibold text-amber-400"><?php echo $template->getCanvasWidthPx(); ?> × <?php echo $template->getCanvasHeightPx(); ?> px</span>
            </div>
        </div>

        <div class="flex justify-end space-x-3 pt-3 border-t border-slate-800">
            <button onclick="closeChangeSizeModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-xl transition">
                Cancel
            </button>
            <button id="btn-confirm-resize" onclick="applyCanvasResize()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white rounded-xl shadow transition">
                Update Size
            </button>
        </div>
    </div>
</div>

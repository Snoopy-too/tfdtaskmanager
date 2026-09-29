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

<!-- 3D Board Game Box Die-Line Configurator Modal -->
<div id="modal-box-dieline" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-base font-bold text-slate-100 flex items-center space-x-2">
                <span class="text-lg">📦</span>
                <span>Board Game Box Die-Line Generator</span>
            </h3>
            <button type="button" onclick="if(window.boxDieline) window.boxDieline.closeBoxDielineModal();" class="text-slate-400 hover:text-white text-lg font-bold p-1">
                &times;
            </button>
        </div>

        <p class="text-xs text-slate-400">
            Specify the finished 3D dimensions of your board game box. The studio calculates the exact unfolded flat sheet size and renders solid <strong>Cut Lines</strong> and dashed <strong>Fold / Score Lines</strong> onto your canvas.
        </p>

        <div class="space-y-3.5">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label for="modal-box-type" class="block text-xs font-semibold text-slate-300">Box Type (by Sturdiness)</label>
                    <span id="modal-box-sturdiness-badge" class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-500/15 text-amber-300 border border-amber-500/30">⭐⭐⭐⭐⭐ Max Sturdiness (5/5)</span>
                </div>
                <select id="modal-box-type" onchange="if(window.boxDieline) window.boxDieline.updateModalBoxPreview();" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl p-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="double_wall_tray">⭐⭐⭐⭐⭐ Heavy-Duty Double-Wall Tray (Image Style)</option>
                    <option value="roll_end_tray">⭐⭐⭐⭐ Standard Roll-End Tuck Tray (Self-Locking)</option>
                    <option value="tuck_top_box">⭐⭐⭐ One-Piece Hinged Tuck-Top Box (All-in-One)</option>
                    <option value="simple_tray">⭐⭐ Classic Single-Wall Tray (Corner Glue Tabs)</option>
                </select>
                <p id="modal-box-type-desc" class="text-[11px] text-slate-400 mt-1 leading-snug">
                    Double-folded side &amp; end walls with dual corner dust flaps and shoulder locking lips (no glue required). Matches the uploaded reference box.
                </p>
            </div>

            <div id="modal-box-part-group">
                <label for="modal-box-part" class="block text-xs font-semibold text-slate-300 mb-1">Box Piece</label>
                <select id="modal-box-part" onchange="if(window.boxDieline) window.boxDieline.updateModalBoxPreview();" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl p-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="base">Bottom Box / Single Tray (Exact W × L × H)</option>
                    <option value="lid">Telescoping Top Lid (+Clearance to fit over Base)</option>
                </select>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label for="modal-box-width" class="block text-xs font-medium text-slate-300 mb-1">Width W (mm)</label>
                    <input type="number" id="modal-box-width" min="15" max="1000" step="0.5" value="120" oninput="if(window.boxDieline) window.boxDieline.updateModalBoxPreview();" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl p-2.5 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="modal-box-length" class="block text-xs font-medium text-slate-300 mb-1">Length L (mm)</label>
                    <input type="number" id="modal-box-length" min="15" max="1000" step="0.5" value="160" oninput="if(window.boxDieline) window.boxDieline.updateModalBoxPreview();" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl p-2.5 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="modal-box-height" class="block text-xs font-medium text-slate-300 mb-1">Height H (mm)</label>
                    <input type="number" id="modal-box-height" min="8" max="500" step="0.5" value="40" oninput="if(window.boxDieline) window.boxDieline.updateModalBoxPreview();" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl p-2.5 focus:ring-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div id="modal-box-clearance-group">
                    <label for="modal-box-clearance" class="block text-xs font-medium text-slate-300 mb-1">Lid Clearance per Side (mm)</label>
                    <input type="number" id="modal-box-clearance" min="0" max="15" step="0.5" value="1.5" oninput="if(window.boxDieline) window.boxDieline.updateModalBoxPreview();" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl p-2.5 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="modal-box-fill" class="block text-xs font-medium text-slate-300 mb-1">Canvas Base Fill</label>
                    <select id="modal-box-fill" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl p-2.5 focus:ring-indigo-500">
                        <option value="stencil">White Cut-Out Stencil</option>
                        <option value="kraft">Kraft Cardboard Preview</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center space-x-2">
                <input type="checkbox" id="modal-box-labels" checked class="rounded border-slate-800 bg-slate-950 text-indigo-600 focus:ring-indigo-500">
                <label for="modal-box-labels" class="text-xs text-slate-300 cursor-pointer select-none">
                    Display panel names &amp; millimeter dimensions on canvas
                </label>
            </div>

            <div class="bg-slate-950/80 border border-slate-800 px-3 py-2.5 rounded-xl text-xs space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Unfolded Flat Sheet (300 DPI):</span>
                    <span id="modal-box-flat-preview" class="font-mono font-bold text-amber-400">297.2 × 312 mm (3510 × 3685 px)</span>
                </div>
                <div id="modal-box-fitment-note" class="text-[11px] text-slate-400 pt-1 border-t border-slate-800/70">
                    <!-- Populated dynamically by JS -->
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-slate-800">
            <button type="button" id="btn-create-companion-box" onclick="if(window.boxDieline) window.boxDieline.createCompanionPieceTemplate();" class="px-3 py-2 bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/30 text-xs font-semibold text-amber-300 rounded-xl transition">
                ➕ Create Fitting Top Lid Template
            </button>
            <div class="flex items-center space-x-2 ml-auto">
                <button type="button" onclick="if(window.boxDieline) window.boxDieline.closeBoxDielineModal();" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-xl transition">
                    Cancel
                </button>
                <button type="button" id="btn-confirm-box-dieline" onclick="if(window.boxDieline) window.boxDieline.applyBoxDielineFromModal();" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white rounded-xl shadow transition">
                    Apply to Current Template
                </button>
            </div>
        </div>
    </div>
</div>


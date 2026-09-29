<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
?>
<!-- Create Template Sidebar Form -->
<div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl h-fit">
    <h2 class="text-xl font-bold text-slate-200 mb-4">New Design Template</h2>
    <form action="" method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
        <input type="hidden" name="action" value="create_template">

        <div>
            <label for="name" class="block text-sm font-medium text-slate-300 mb-1">Template Name</label>
            <input type="text" id="name" name="name" required placeholder="e.g. Card Front Layout" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
        </div>

        <div>
            <label for="component_type_id" class="block text-sm font-medium text-slate-300 mb-1">Component Type</label>
            <select id="component_type_id" name="component_type_id" required onchange="handleComponentTypeChange(this)" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                <?php foreach ($compTypes as $type): ?>
                    <?php
                    $isCustomType = ($type->getName() === 'Custom');
                    $isBoxType = str_contains($type->getName(), 'Board Game Box');
                    $dimSuffix = (!$isCustomType && !$isBoxType) ? "({$type->getWidthMm()}x{$type->getHeightMm()}mm)" : ($isBoxType ? "(3D W×L×H → Flat)" : "");
                    ?>
                    <option value="<?php echo $type->getId(); ?>" data-is-custom="<?php echo $isCustomType ? '1' : '0'; ?>" data-is-box="<?php echo $isBoxType ? '1' : '0'; ?>" data-width="<?php echo $type->getWidthMm(); ?>" data-height="<?php echo $type->getHeightMm(); ?>" data-name="<?php echo SecurityHelper::escape($type->getName()); ?>">
                        <?php echo SecurityHelper::escape($type->getName()); ?> <?php echo SecurityHelper::escape($dimSuffix); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 3D Board Game Box Configuration Panel -->
        <div id="box_configuration_panel" class="space-y-3.5 p-3.5 bg-slate-950/90 border border-indigo-500/30 rounded-xl hidden">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-400">📦 3D Box & Sturdiness Specs</span>
                <span id="box_sturdiness_badge" class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-500/15 text-amber-300 border border-amber-500/30">⭐⭐⭐⭐⭐ Max Sturdiness</span>
            </div>

            <div>
                <label for="box_type" class="block text-xs font-semibold text-slate-300 mb-1">Box Type (by Sturdiness)</label>
                <select id="box_type" name="box_type" onchange="updateDimensionsPreview()" class="w-full bg-slate-900 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2">
                    <option value="double_wall_tray" selected>⭐⭐⭐⭐⭐ Heavy-Duty Double-Wall Tray (Image Style)</option>
                    <option value="roll_end_tray">⭐⭐⭐⭐ Standard Roll-End Tuck Tray (Self-Locking)</option>
                    <option value="tuck_top_box">⭐⭐⭐ One-Piece Hinged Tuck-Top Box (All-in-One)</option>
                    <option value="simple_tray">⭐⭐ Classic Single-Wall Tray (Corner Glue Tabs)</option>
                </select>
                <p id="box_type_desc" class="text-[11px] text-slate-400 mt-1 leading-snug">
                    Double-folded side &amp; end walls with dual corner dust flaps and shoulder lips (no glue required). Matches the uploaded reference box.
                </p>
            </div>

            <div id="box_part_container">
                <label for="box_part" class="block text-xs font-semibold text-slate-300 mb-1">Box Piece to Generate</label>
                <select id="box_part" name="box_part" onchange="updateDimensionsPreview()" class="w-full bg-slate-900 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2">
                    <option value="base" selected>Bottom Box / Single Tray (Exact W × L × H)</option>
                    <option value="lid">Telescoping Top Lid (+Clearance to slide over Base)</option>
                    <option value="pair">Create Both: Bottom Base + Matching Top Lid (2 Templates)</option>
                </select>
            </div>

            <div class="grid grid-cols-3 gap-2.5">
                <div>
                    <label for="box_width_mm" class="block text-[11px] font-medium text-slate-300 mb-1">Width W (mm)</label>
                    <input type="number" id="box_width_mm" name="box_width_mm" min="15" max="1000" step="0.5" value="120" oninput="updateDimensionsPreview()" class="w-full bg-slate-900 border border-slate-800 text-slate-100 text-xs rounded-lg p-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="box_length_mm" class="block text-[11px] font-medium text-slate-300 mb-1">Length L (mm)</label>
                    <input type="number" id="box_length_mm" name="box_length_mm" min="15" max="1000" step="0.5" value="160" oninput="updateDimensionsPreview()" class="w-full bg-slate-900 border border-slate-800 text-slate-100 text-xs rounded-lg p-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="box_height_mm" class="block text-[11px] font-medium text-slate-300 mb-1">Height H (mm)</label>
                    <input type="number" id="box_height_mm" name="box_height_mm" min="8" max="500" step="0.5" value="40" oninput="updateDimensionsPreview()" class="w-full bg-slate-900 border border-slate-800 text-slate-100 text-xs rounded-lg p-2 focus:ring-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2.5">
                <div id="box_clearance_wrap">
                    <label for="box_clearance_mm" class="block text-[11px] font-medium text-slate-300 mb-1" title="Cardboard thickness allowance per side for Telescoping Lid">Lid Clearance (mm)</label>
                    <input type="number" id="box_clearance_mm" name="box_clearance_mm" min="0" max="15" step="0.5" value="1.5" oninput="updateDimensionsPreview()" class="w-full bg-slate-900 border border-slate-800 text-slate-100 text-xs rounded-lg p-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="box_fill_style" class="block text-[11px] font-medium text-slate-300 mb-1">Canvas Base Fill</label>
                    <select id="box_fill_style" name="box_fill_style" class="w-full bg-slate-900 border border-slate-800 text-slate-100 text-xs rounded-lg p-2 focus:ring-indigo-500">
                        <option value="stencil" selected>White Cut-Out Stencil</option>
                        <option value="kraft">Kraft Cardboard Preview</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center space-x-2 pt-0.5">
                <input type="checkbox" id="box_show_labels" name="box_show_labels" value="1" checked class="rounded border-slate-700 bg-slate-900 text-indigo-600 focus:ring-indigo-500">
                <label for="box_show_labels" class="text-xs text-slate-300 cursor-pointer select-none">Show panel labels &amp; dimensions on canvas</label>
            </div>

            <div id="box_sheet_estimate" class="text-[11px] text-emerald-300 bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1.5 rounded-lg">
                Unfolded flat template ready for A4 / A5 single or multi-sheet printing.
            </div>
        </div>

        <!-- Orientation Selector -->
        <div id="orientation_selector_group">
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Canvas Orientation</label>
            <div class="grid grid-cols-2 gap-2">
                <label id="orient-label-portrait" class="relative flex items-center justify-center p-2.5 rounded-xl border border-indigo-500/40 bg-indigo-500/10 cursor-pointer transition select-none">
                    <input type="radio" name="orientation" value="portrait" checked class="sr-only" onchange="updateOrientation('portrait')">
                    <div class="flex items-center space-x-2 text-xs font-semibold text-indigo-300">
                        <svg class="w-4 h-4 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="6" y="3" width="12" height="18" rx="2" ry="2"/>
                        </svg>
                        <span>Portrait</span>
                    </div>
                </label>
                <label id="orient-label-landscape" class="relative flex items-center justify-center p-2.5 rounded-xl border border-slate-800 bg-slate-950/80 cursor-pointer hover:border-slate-700 transition select-none">
                    <input type="radio" name="orientation" value="landscape" class="sr-only" onchange="updateOrientation('landscape')">
                    <div class="flex items-center space-x-2 text-xs font-semibold text-slate-400">
                        <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="6" width="18" height="12" rx="2" ry="2"/>
                        </svg>
                        <span>Landscape</span>
                    </div>
                </label>
            </div>
        </div>

        <div id="dimensions_preview_badge" class="mt-2 text-[11px] text-indigo-300/90 bg-indigo-500/10 border border-indigo-500/20 px-2.5 py-1.5 rounded-lg flex items-center justify-between">
            <span class="text-slate-400" id="dimensions_preview_label">Resulting Size:</span>
            <span id="dimensions_preview_text" class="font-bold text-indigo-300">63 × 88 mm (744 × 1039 px)</span>
        </div>

        <div id="custom_dimensions" class="grid grid-cols-2 gap-4 hidden">
            <div>
                <label for="custom_width_mm" class="block text-sm font-medium text-slate-300 mb-1">Custom Width (mm)</label>
                <input type="number" id="custom_width_mm" name="custom_width_mm" min="10" max="1000" step="1" value="63" oninput="updateDimensionsPreview()" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
            </div>
            <div>
                <label for="custom_height_mm" class="block text-sm font-medium text-slate-300 mb-1">Custom Height (mm)</label>
                <input type="number" id="custom_height_mm" name="custom_height_mm" min="10" max="1000" step="1" value="88" oninput="updateDimensionsPreview()" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
            </div>
        </div>

        <script>
            let currentOrientation = 'portrait';

            const BOX_TYPE_META = {
                double_wall_tray: {
                    badge: '⭐⭐⭐⭐⭐ Max Sturdiness',
                    desc: 'Double-folded side & end walls with dual corner dust flaps and shoulder lips (no glue required). Matches the uploaded reference box.'
                },
                roll_end_tray: {
                    badge: '⭐⭐⭐⭐ High Sturdiness',
                    desc: 'Self-locking double rollover side walls with single end walls and corner dust flaps. No glue required, compact height.'
                },
                tuck_top_box: {
                    badge: '⭐⭐⭐ Medium Sturdiness',
                    desc: 'All-in-one single-sheet folding box with attached hinged lid, side glue tab, dust flaps, and top/bottom tuck flaps.'
                },
                simple_tray: {
                    badge: '⭐⭐ Standard Sturdiness',
                    desc: 'Compact single-wall setup box tray with 4 corner glue/tape tabs. Uses the smallest flat sheet footprint.'
                }
            };

            function mmToPx(mm) {
                return Math.round((mm / 25.4) * 300);
            }

            function calculateFlatBoxSize(boxType, part, baseW, baseL, H, clearance) {
                const isLid = (part === 'lid' && boxType !== 'tuck_top_box');
                const W = isLid ? +(baseW + clearance * 2).toFixed(1) : baseW;
                const L = isLid ? +(baseL + clearance * 2).toFixed(1) : baseL;
                const pad = 6.0;
                let flatW = 0, flatH = 0;

                if (boxType === 'double_wall_tray') {
                    const flapH = +(H * 0.75).toFixed(1);
                    const shoulder = +(Math.min(4.0, Math.max(2.0, H * 0.08))).toFixed(1);
                    flatW = +(W + 4 * H + 2 * shoulder + 2 * pad).toFixed(1);
                    flatH = +(L + 2 * H + 2 * flapH + 2 * pad).toFixed(1);
                } else if (boxType === 'roll_end_tray') {
                    const shoulder = +(Math.min(3.5, Math.max(1.5, H * 0.07))).toFixed(1);
                    flatW = +(W + 4 * H + 2 * shoulder + 2 * pad).toFixed(1);
                    flatH = +(L + 2 * H + 2 * pad).toFixed(1);
                } else if (boxType === 'tuck_top_box') {
                    const glueTab = +(Math.min(22.0, Math.max(12.0, H * 0.45))).toFixed(1);
                    const tuckFlap = +(Math.min(28.0, Math.max(12.0, H * 0.60))).toFixed(1);
                    flatW = +(2 * W + 2 * H + glueTab + 2 * pad).toFixed(1);
                    flatH = +(L + 2 * H + 2 * tuckFlap + 2 * pad).toFixed(1);
                } else {
                    flatW = +(W + 2 * H + 2 * pad).toFixed(1);
                    flatH = +(L + 2 * H + 2 * pad).toFixed(1);
                }
                return { flatW, flatH, effW: W, effL: L };
            }

            function estimateSheetsNeeded(flatW, flatH, pageW, pageH) {
                const margin = 10;
                const availW1 = pageW - margin * 2, availH1 = pageH - margin * 2;
                const availW2 = pageH - margin * 2, availH2 = pageW - margin * 2;
                if ((flatW <= pageW && flatH <= pageH) || (flatW <= pageH && flatH <= pageW)) return 1;
                const cols1 = Math.ceil(flatW / availW1) * Math.ceil(flatH / availH1);
                const cols2 = Math.ceil(flatW / availW2) * Math.ceil(flatH / availH2);
                const minSheets = Math.min(cols1, cols2);
                if (minSheets <= 2) return 2;
                if (minSheets <= 4) return 4;
                return minSheets;
            }

            function updateOrientation(orient) {
                currentOrientation = orient;
                const portLabel = document.getElementById('orient-label-portrait');
                const landLabel = document.getElementById('orient-label-landscape');

                if (orient === 'portrait') {
                    portLabel.className = 'relative flex items-center justify-center p-2.5 rounded-xl border border-indigo-500/40 bg-indigo-500/10 cursor-pointer transition select-none';
                    portLabel.querySelector('div').className = 'flex items-center space-x-2 text-xs font-semibold text-indigo-300';
                    portLabel.querySelector('svg').className = 'w-4 h-4 text-indigo-400';

                    landLabel.className = 'relative flex items-center justify-center p-2.5 rounded-xl border border-slate-800 bg-slate-950/80 cursor-pointer hover:border-slate-700 transition select-none';
                    landLabel.querySelector('div').className = 'flex items-center space-x-2 text-xs font-semibold text-slate-400';
                    landLabel.querySelector('svg').className = 'w-4 h-4 text-slate-400';
                } else {
                    landLabel.className = 'relative flex items-center justify-center p-2.5 rounded-xl border border-amber-500/40 bg-amber-500/10 cursor-pointer transition select-none';
                    landLabel.querySelector('div').className = 'flex items-center space-x-2 text-xs font-semibold text-amber-300';
                    landLabel.querySelector('svg').className = 'w-4 h-4 text-amber-400';

                    portLabel.className = 'relative flex items-center justify-center p-2.5 rounded-xl border border-slate-800 bg-slate-950/80 cursor-pointer hover:border-slate-700 transition select-none';
                    portLabel.querySelector('div').className = 'flex items-center space-x-2 text-xs font-semibold text-slate-400';
                    portLabel.querySelector('svg').className = 'w-4 h-4 text-slate-400';
                }
                updateDimensionsPreview();
            }

            function handleComponentTypeChange(select) {
                const selectedOption = select.options[select.selectedIndex];
                const isCustom = selectedOption.getAttribute('data-is-custom') === '1';
                const isBox = selectedOption.getAttribute('data-is-box') === '1';

                document.getElementById('custom_dimensions').style.display = isCustom ? 'grid' : 'none';
                const boxPanel = document.getElementById('box_configuration_panel');
                const orientGroup = document.getElementById('orientation_selector_group');
                if (boxPanel) {
                    if (isBox) boxPanel.classList.remove('hidden');
                    else boxPanel.classList.add('hidden');
                }
                if (orientGroup) {
                    orientGroup.style.display = isBox ? 'none' : 'block';
                }
                updateDimensionsPreview();
            }

            function updateDimensionsPreview() {
                const select = document.getElementById('component_type_id');
                if (!select) return;
                const selectedOption = select.options[select.selectedIndex];
                const isCustom = selectedOption.getAttribute('data-is-custom') === '1';
                const isBox = selectedOption.getAttribute('data-is-box') === '1';
                const previewLabel = document.getElementById('dimensions_preview_label');
                const previewElem = document.getElementById('dimensions_preview_text');

                if (isBox) {
                    const boxType = document.getElementById('box_type')?.value || 'double_wall_tray';
                    const boxPart = document.getElementById('box_part')?.value || 'base';
                    const bw = parseFloat(document.getElementById('box_width_mm')?.value) || 120;
                    const bl = parseFloat(document.getElementById('box_length_mm')?.value) || 160;
                    const bh = parseFloat(document.getElementById('box_height_mm')?.value) || 40;
                    const bc = parseFloat(document.getElementById('box_clearance_mm')?.value) || 1.5;

                    const meta = BOX_TYPE_META[boxType] || BOX_TYPE_META.double_wall_tray;
                    const badgeEl = document.getElementById('box_sturdiness_badge');
                    const descEl = document.getElementById('box_type_desc');
                    if (badgeEl) badgeEl.textContent = meta.badge;
                    if (descEl) descEl.textContent = meta.desc;

                    const partWrap = document.getElementById('box_part_container');
                    const clearWrap = document.getElementById('box_clearance_wrap');
                    if (partWrap) partWrap.style.display = (boxType === 'tuck_top_box') ? 'none' : 'block';
                    if (clearWrap) clearWrap.style.display = (boxType === 'tuck_top_box' || boxPart === 'base') ? 'none' : 'block';

                    const calc = calculateFlatBoxSize(boxType, boxPart, bw, bl, bh, bc);
                    const wPx = mmToPx(calc.flatW);
                    const hPx = mmToPx(calc.flatH);

                    if (previewLabel) previewLabel.textContent = 'Unfolded Flat Size:';
                    if (previewElem) {
                        previewElem.textContent = `${calc.flatW} × ${calc.flatH} mm (${wPx} × ${hPx} px)`;
                    }

                    const estEl = document.getElementById('box_sheet_estimate');
                    if (estEl) {
                        const a4Sheets = estimateSheetsNeeded(calc.flatW, calc.flatH, 210, 297);
                        const a5Sheets = estimateSheetsNeeded(calc.flatW, calc.flatH, 148, 210);
                        estEl.innerHTML = `<strong>Finished Box:</strong> ${calc.effW}×${calc.effL}×${bh}mm &bull; <strong>Print:</strong> ${a4Sheets}× A4 sheet${a4Sheets > 1 ? 's' : ''} or ${a5Sheets}× A5 sheet${a5Sheets > 1 ? 's' : ''}`;
                    }
                    return;
                }

                if (previewLabel) previewLabel.textContent = 'Resulting Size:';
                let w, h;
                if (isCustom) {
                    w = parseFloat(document.getElementById('custom_width_mm').value) || 63;
                    h = parseFloat(document.getElementById('custom_height_mm').value) || 88;
                } else {
                    w = parseFloat(selectedOption.getAttribute('data-width')) || 63;
                    h = parseFloat(selectedOption.getAttribute('data-height')) || 88;
                }

                if (currentOrientation === 'landscape' && w < h) {
                    const tmp = w; w = h; h = tmp;
                } else if (currentOrientation === 'portrait' && w > h) {
                    const tmp = w; w = h; h = tmp;
                }

                const wPx = mmToPx(w);
                const hPx = mmToPx(h);

                if (previewElem) {
                    previewElem.textContent = `${w} × ${h} mm (${wPx} × ${hPx} px)`;
                }
            }

            // Trigger on load
            document.addEventListener('DOMContentLoaded', () => {
                const compSelect = document.getElementById('component_type_id');
                if (compSelect) handleComponentTypeChange(compSelect);
            });

            function renameTemplate(templateId, originalName, form) {
                if (form.dataset.renaming === "true") {
                    return true;
                }

                const handleName = (newName) => {
                    if (newName === null) return false;
                    const trimmed = newName.trim();
                    if (trimmed === "") {
                        if (typeof window.studioAlert === 'function') {
                            window.studioAlert("Template name cannot be empty.", "Validation Error");
                        } else {
                            alert("Template name cannot be empty.");
                        }
                        return false;
                    }
                    if (trimmed === originalName) return false;
                    document.getElementById("rename_name_" + templateId).value = trimmed;
                    form.dataset.renaming = "true";
                    form.submit();
                    return true;
                };

                if (typeof window.studioPrompt === 'function') {
                    window.studioPrompt("Enter a new name for the template:", originalName, "Rename Template").then(handleName);
                    return false;
                }

                const newName = prompt("Enter a new name for the template:", originalName);
                return handleName(newName);
            }

            function duplicateTemplate(templateId, originalName, form) {
                if (form.dataset.duplicating === "true") {
                    return true;
                }

                if (typeof window.studioPrompt === 'function') {
                    window.studioPrompt("Enter a name for the duplicated template:", originalName + " (Copy)", "Duplicate Template").then(newName => {
                        if (newName === null) {
                            return;
                        }
                        const trimmed = newName.trim();
                        if (trimmed === "") {
                            if (typeof window.studioAlert === 'function') {
                                window.studioAlert("Template name cannot be empty.", "Validation Error");
                            } else {
                                alert("Template name cannot be empty.");
                            }
                            return;
                        }
                        document.getElementById("dup_name_" + templateId).value = trimmed;
                        form.dataset.duplicating = "true";
                        form.submit();
                    });
                    return false;
                }

                const newName = prompt("Enter a name for the duplicated template:", originalName + " (Copy)");
                if (newName === null) {
                    return false;
                }
                const trimmed = newName.trim();
                if (trimmed === "") {
                    alert("Template name cannot be empty.");
                    return false;
                }
                document.getElementById("dup_name_" + templateId).value = trimmed;
                return true;
            }
        </script>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="bleed_mm" class="block text-sm font-medium text-slate-300 mb-1">Bleed Edge (mm)</label>
                <input type="number" id="bleed_mm" name="bleed_mm" min="0" max="20" step="0.1" value="3.0" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
            </div>
            <div>
                <label for="safe_margin_mm" class="block text-sm font-medium text-slate-300 mb-1">Safe Margin (mm)</label>
                <input type="number" id="safe_margin_mm" name="safe_margin_mm" min="0" max="30" step="0.1" value="5.0" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
            </div>
        </div>

        <div>
            <label for="dataset_id" class="block text-sm font-medium text-slate-300 mb-1">Dataset Binding (Optional)</label>
            <select id="dataset_id" name="dataset_id" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                <option value="">No Dataset Bound</option>
                <?php foreach ($datasets as $data): ?>
                    <option value="<?php echo $data->getId(); ?>">
                        <?php echo SecurityHelper::escape($data->getName()); ?> (<?php echo count($data->getRowData()); ?> rows)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-xl shadow-lg hover:shadow-indigo-500/20 py-2.5 px-4 transition duration-200">
            Create & Design
        </button>
    </form>
</div>

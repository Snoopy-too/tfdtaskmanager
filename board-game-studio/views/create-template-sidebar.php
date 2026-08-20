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
                    <option value="<?php echo $type->getId(); ?>" data-is-custom="<?php echo $type->getName() === 'Custom' ? '1' : '0'; ?>" data-width="<?php echo $type->getWidthMm(); ?>" data-height="<?php echo $type->getHeightMm(); ?>" data-name="<?php echo SecurityHelper::escape($type->getName()); ?>">
                        <?php echo SecurityHelper::escape($type->getName()); ?> <?php echo $type->getName() !== 'Custom' ? "({$type->getWidthMm()}x{$type->getHeightMm()}mm)" : ""; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Orientation Selector -->
        <div>
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
            <div id="dimensions_preview_badge" class="mt-2 text-[11px] text-indigo-300/90 bg-indigo-500/10 border border-indigo-500/20 px-2.5 py-1.5 rounded-lg flex items-center justify-between">
                <span class="text-slate-400">Resulting Size:</span>
                <span id="dimensions_preview_text" class="font-bold text-indigo-300">63 × 88 mm (744 × 1039 px)</span>
            </div>
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

            function mmToPx(mm) {
                return Math.round((mm / 25.4) * 300);
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
                document.getElementById('custom_dimensions').style.display = isCustom ? 'grid' : 'none';
                updateDimensionsPreview();
            }

            function updateDimensionsPreview() {
                const select = document.getElementById('component_type_id');
                if (!select) return;
                const selectedOption = select.options[select.selectedIndex];
                const isCustom = selectedOption.getAttribute('data-is-custom') === '1';
                
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

                const previewElem = document.getElementById('dimensions_preview_text');
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

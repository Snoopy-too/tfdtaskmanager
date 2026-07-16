<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/../src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\ProjectService;
use App\Application\Services\BgTemplateService;
use App\Application\Services\BgDatasetService;

SecurityHelper::requireLogin();

$projectService = $container->get(ProjectService::class);
$templateService = $container->get(BgTemplateService::class);
$datasetService = $container->get(BgDatasetService::class);

$csrfToken = SecurityHelper::generateCsrfToken();

// Projects dropdown
$projects = $projectService->getAllProjects();
$activeProjectId = (isset($_GET['project_id']) && $_GET['project_id'] !== '') ? (int)$_GET['project_id'] : null;

// Default to last project from session if not specified, otherwise default to first project
if ($activeProjectId === null && !isset($_GET['project_id'])) {
    if (isset($_SESSION['last_project_id'])) {
        $activeProjectId = (int)$_SESSION['last_project_id'];
    } elseif (!empty($projects)) {
        $activeProjectId = $projects[0]->getId();
    }
}

if ($activeProjectId) {
    $_SESSION['last_project_id'] = $activeProjectId;
}

$activeProject = null;
if ($activeProjectId) {
    $activeProject = $projectService->getProjectById($activeProjectId);
}

if (!$activeProject) {
    header("Location: index.php");
    exit;
}

// Get active template
$templates = $templateService->getTemplatesByProject($activeProjectId);
$activeTemplateId = isset($_GET['template_id']) ? (int)$_GET['template_id'] : null;
if (!$activeTemplateId && !empty($templates)) {
    $activeTemplateId = $templates[0]->getId();
}

$activeTemplate = null;
if ($activeTemplateId) {
    $activeTemplate = $templateService->getTemplateById($activeTemplateId);
}

// Fetch dataset if bound
$dataset = null;
$compType = null;
if ($activeTemplate) {
    if ($activeTemplate->getDatasetId()) {
        $dataset = $datasetService->getDatasetById($activeTemplate->getDatasetId());
    }
    
    $compTypes = $templateService->getComponentTypes();
    foreach ($compTypes as $ct) {
        if ($ct->getId() === $activeTemplate->getComponentTypeId()) {
            $compType = $ct;
            break;
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<!-- FabricJS and export libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<!-- Google Fonts for Board Game Creators -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Almendra:ital,wght@0,400;0,700;1,400&family=Bangers&family=Cinzel:wght@400;700&family=Comic+Neue:wght@400;700&family=Creepster&family=EB+Garamond:ital,wght@0,400;0,700;1,400&family=Fredoka:wght@400;700&family=Inter:wght@400;700&family=Jolly+Lodger&family=Lora:ital,wght@0,400;0,700;1,400&family=Luckiest+Guy&family=MedievalSharp&family=Metal+Mania&family=Montserrat:wght@400;700&family=Orbitron:wght@400;700&family=Outfit:wght@400;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Rajdhani:wght@500;700&family=Rye&family=Share+Tech+Mono&family=Courier+Prime&family=Special+Elite&display=swap" rel="stylesheet">

<div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-sm text-slate-400">
                <a href="index.php?project_id=<?php echo $activeProjectId; ?>" class="hover:text-white transition">Studio</a>
                <span>/</span>
                <span class="text-slate-200">Export Studio</span>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white mt-1">Print & Play Export Studio</h1>
            <p class="text-slate-400 mt-1">Generate print sheets or build digital files for tabletop simulation platforms.</p>
        </div>

        <div class="flex items-center space-x-2 bg-slate-900 border border-slate-800 p-2 rounded-xl">
            <label for="project_select" class="text-xs font-semibold text-slate-400 uppercase tracking-wider pl-2">Project:</label>
            <form method="GET" class="m-0">
                <select id="project_select" name="project_id" onchange="this.form.submit()" class="bg-slate-950 border-0 text-slate-100 text-sm rounded-lg focus:ring-2 focus:ring-indigo-500 py-1.5 pl-3 pr-8 font-medium cursor-pointer">
                    <option value="" <?php echo $activeProjectId === null ? 'selected' : ''; ?>>None</option>
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?php echo $proj->getId(); ?>" <?php echo $proj->getId() === $activeProjectId ? 'selected' : ''; ?>>
                            <?php echo SecurityHelper::escape($proj->getName()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <?php if (empty($templates)): ?>
        <div class="p-12 text-center bg-slate-900/30 border border-dashed border-slate-800 rounded-3xl max-w-lg mx-auto">
            <svg class="mx-auto h-12 w-12 text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <h3 class="text-lg font-bold text-slate-300">No Templates Available</h3>
            <p class="text-sm text-slate-500 mt-1 mb-6">Create and design a template in the Studio before using the Export Studio.</p>
            <a href="index.php?project_id=<?php echo $activeProjectId; ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg transition">
                Go to Studio
            </a>
        </div>
    <?php else: ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left: Settings Form -->
            <div class="space-y-6 lg:col-span-1">
                <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl space-y-4">
                    <h2 class="text-xl font-bold text-slate-200">Export Settings</h2>
                    
                    <div>
                        <label for="template_select" class="block text-sm font-medium text-slate-300 mb-1">Select Template</label>
                        <select id="template_select" onchange="window.location.href = `?project_id=<?php echo $activeProjectId; ?>&template_id=${this.value}`" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 p-2.5 cursor-pointer">
                            <?php foreach ($templates as $tmpl): ?>
                                <option value="<?php echo $tmpl->getId(); ?>" <?php echo $tmpl->getId() === $activeTemplateId ? 'selected' : ''; ?>>
                                    <?php echo SecurityHelper::escape($tmpl->getName()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($activeTemplate): ?>
                        <!-- Choose Format -->
                        <div>
                            <label for="export_format" class="block text-sm font-medium text-slate-300 mb-1">Export Format</label>
                            <select id="export_format" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 p-2.5">
                                <option value="pdf">Print-and-Play PDF (Tiled Components)</option>
                                <option value="tts">Digital Playtest (Tabletop Simulator Sheet + JSON)</option>
                            </select>
                        </div>

                        <!-- PDF specific configurations -->
                        <div id="pdf-settings" class="space-y-4">
                            <div>
                                <label for="pdf_page_size" class="block text-sm font-medium text-slate-300 mb-1">Page Size</label>
                                <select id="pdf_page_size" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 p-2.5">
                                    <option value="a4">A4 (210 x 297 mm)</option>
                                    <option value="letter">US Letter (8.5 x 11 in)</option>
                                </select>
                            </div>

                            <div>
                                <label for="pdf_orientation" class="block text-sm font-medium text-slate-300 mb-1">Orientation</label>
                                <select id="pdf_orientation" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 p-2.5">
                                    <option value="portrait">Portrait</option>
                                    <option value="landscape">Landscape</option>
                                </select>
                            </div>

                            <div class="flex items-center space-x-2">
                                <input type="checkbox" id="pdf_crop_marks" checked class="rounded border-slate-800 text-indigo-600 bg-slate-950 focus:ring-indigo-500">
                                <label for="pdf_crop_marks" class="text-sm font-medium text-slate-300 cursor-pointer select-none">Draw Crop Marks</label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <input type="checkbox" id="pdf_draw_bleed" class="rounded border-slate-800 text-indigo-600 bg-slate-950 focus:ring-indigo-500">
                                <label for="pdf_draw_bleed" class="text-sm font-medium text-slate-300 cursor-pointer select-none">Include Physical Bleed Margins</label>
                            </div>

                            <div id="pdf-tiling-container" class="space-y-1 hidden">
                                <label for="pdf_tiling" class="block text-sm font-medium text-slate-300 mb-1">Large Component Layout</label>
                                <select id="pdf_tiling" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 p-2.5">
                                    <option value="fit">Scale to Fit (1 Page)</option>
                                    <option value="split_2">Split into 2 Parts (2 Pages)</option>
                                    <option value="split_3">Split into 3 Parts (3 Pages)</option>
                                    <option value="split_4">Split into 4 Parts (2x2 Grid - 4 Pages)</option>
                                </select>
                                <p class="text-[10px] text-slate-500">Choose how components too large for the page margins should be exported.</p>
                            </div>
                        </div>

                        <!-- Tabletop Simulator specific configurations -->
                        <div id="tts-settings" class="space-y-4 hidden">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="tts_grid_cols" class="block text-sm font-medium text-slate-300 mb-1">Grid Columns</label>
                                    <input type="number" id="tts_grid_cols" min="1" max="10" value="10" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 p-2.5">
                                </div>
                                <div>
                                    <label for="tts_grid_rows" class="block text-sm font-medium text-slate-300 mb-1">Grid Rows</label>
                                    <input type="number" id="tts_grid_rows" min="1" max="10" value="7" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 p-2.5">
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-500">Tabletop Simulator custom imports use standard grids, traditionally maxing at 10 columns by 7 rows (70 items total) per texture sheet.</p>
                        </div>

                        <!-- Progress indicator -->
                        <div id="export-progress-container" class="hidden space-y-2">
                            <div class="flex justify-between text-xs text-slate-400 font-semibold">
                                <span id="progress-action">Generating export...</span>
                                <span id="progress-percent">0%</span>
                            </div>
                            <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden border border-slate-800">
                                <div id="progress-bar" class="bg-indigo-500 h-full w-0 transition-all duration-100"></div>
                            </div>
                        </div>

                        <button id="btn-run-export" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-xl shadow-lg hover:shadow-indigo-500/20 py-2.5 px-4 transition duration-200">
                            Generate Export
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Template Metadata Details & Offscreen View -->
            <div class="space-y-6 lg:col-span-2">
                <?php if ($activeTemplate): ?>
                    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl space-y-4">
                        <h2 class="text-xl font-bold text-slate-200">Template Specifications</h2>
                        
                        <div class="grid grid-cols-2 gap-4 text-xs">
                            <div class="bg-slate-950 border border-slate-800/80 p-3 rounded-xl">
                                <span class="text-slate-500 block font-semibold uppercase tracking-wider text-[10px]">Component Dimension</span>
                                <span class="font-bold text-slate-200 text-sm mt-1 block">
                                    <?php echo $compType ? $compType->getWidthMm() : 0; ?> x <?php echo $compType ? $compType->getHeightMm() : 0; ?> mm
                                </span>
                            </div>
                            <div class="bg-slate-950 border border-slate-800/80 p-3 rounded-xl">
                                <span class="text-slate-500 block font-semibold uppercase tracking-wider text-[10px]">Canvas Size (300 DPI)</span>
                                <span class="font-bold text-slate-200 text-sm mt-1 block">
                                    <?php echo $activeTemplate->getCanvasWidthPx(); ?> x <?php echo $activeTemplate->getCanvasHeightPx(); ?> px
                                </span>
                            </div>
                            <div class="bg-slate-950 border border-slate-800/80 p-3 rounded-xl">
                                <span class="text-slate-500 block font-semibold uppercase tracking-wider text-[10px]">Bleed Outer Edge</span>
                                <span class="font-bold text-slate-200 text-sm mt-1 block">
                                    <?php echo $activeTemplate->getBleedMm(); ?> mm
                                </span>
                            </div>
                            <div class="bg-slate-950 border border-slate-800/80 p-3 rounded-xl">
                                <span class="text-slate-500 block font-semibold uppercase tracking-wider text-[10px]">Dataset Binding</span>
                                <span class="font-bold text-slate-200 text-sm mt-1 block truncate">
                                    <?php echo $dataset ? SecurityHelper::escape($dataset->getName()) : 'None (Single Component Export)'; ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($dataset): ?>
                            <div>
                                <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-2">Dataset Rows (<?php echo count($dataset->getRowData()); ?> items to render)</h4>
                                <div class="max-h-[220px] overflow-y-auto border border-slate-800 rounded-xl">
                                    <table class="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr class="bg-slate-950 text-slate-400 border-b border-slate-800 font-bold">
                                                <th class="p-2 w-12 text-center">Row</th>
                                                <th class="p-2">Name / Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dataset->getRowData() as $idx => $row): ?>
                                                <tr class="border-b border-slate-800/40 hover:bg-slate-800/20 text-slate-300">
                                                    <td class="p-2 text-center text-slate-500 font-bold bg-slate-950/20"><?php echo $idx + 1; ?></td>
                                                    <td class="p-2 truncate max-w-[300px]">
                                                        <?php 
                                                        // Show first couple of keys
                                                        $summary = [];
                                                        foreach ($row as $k => $v) {
                                                            $summary[] = "$k: $v";
                                                            if (count($summary) >= 3) break;
                                                        }
                                                        echo SecurityHelper::escape(implode(', ', $summary));
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hidden Canvas Wrapper for Offscreen Rendering -->
        <div style="position: absolute; left: -9999px; top: -9999px; overflow: hidden; width: 1px; height: 1px;">
            <canvas id="offscreen-canvas"></canvas>
        </div>
    <?php endif; ?>
</div>

<?php if ($activeTemplate): ?>
    <script>
        window.studioConfig = {
            templateId: <?php echo $activeTemplate->getId(); ?>,
            projectId: <?php echo $activeTemplate->getProjectId(); ?>,
            csrfToken: "<?php echo SecurityHelper::escape($csrfToken); ?>",
            datasetId: <?php echo $activeTemplate->getDatasetId() ? $activeTemplate->getDatasetId() : 'null'; ?>,
            canvasWidth: <?php echo $activeTemplate->getCanvasWidthPx(); ?>,
            canvasHeight: <?php echo $activeTemplate->getCanvasHeightPx(); ?>,
            bleedMm: <?php echo $activeTemplate->getBleedMm(); ?>,
            safeMarginMm: <?php echo $activeTemplate->getSafeMarginMm(); ?>,
            widthMm: <?php echo $compType ? $compType->getWidthMm() : 0; ?>,
            heightMm: <?php echo $compType ? $compType->getHeightMm() : 0; ?>,
            templateName: "<?php echo SecurityHelper::escape($activeTemplate->getName()); ?>"
        };
    </script>
    <script src="js/export-handler.js"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

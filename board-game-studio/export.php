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

$currentUserId = (int)($_SESSION['user_id'] ?? 0);

// Projects dropdown - only board games
$projects = $projectService->getAllProjects($currentUserId);
$boardGameProjects = array_values(array_filter($projects, fn($p) => $p->isBoardGame()));

$activeProjectId = (isset($_GET['project_id']) && $_GET['project_id'] !== '') ? (int)$_GET['project_id'] : null;

if (!$activeProjectId && isset($_SESSION['last_project_id'])) {
    $candidateId = (int)$_SESSION['last_project_id'];
    $candProject = $projectService->getProjectById($candidateId, $currentUserId);
    if ($candProject && $candProject->isBoardGame()) {
        $activeProjectId = $candidateId;
    }
}

if (!$activeProjectId) {
    header("Location: index.php");
    exit;
}

$activeProject = $projectService->getProjectById($activeProjectId, $currentUserId);
if (!$activeProject || !$activeProject->isBoardGame()) {
    unset($_SESSION['last_project_id']);
    header("Location: index.php");
    exit;
}

$_SESSION['last_project_id'] = $activeProjectId;

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

// Handle Export Dataset Binding Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_export_dataset') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (SecurityHelper::verifyCsrfToken($submittedToken)) {
        $tmplId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
        $dsId = (isset($_POST['dataset_id']) && $_POST['dataset_id'] !== '' && $_POST['dataset_id'] !== 'null' && $_POST['dataset_id'] !== '0') ? (int)$_POST['dataset_id'] : null;
        $tmpl = $templateService->getTemplateById($tmplId);
        if ($tmpl) {
            $templateService->updateTemplate(
                $tmplId,
                $tmpl->getName(),
                $tmpl->getBleedMm(),
                $tmpl->getSafeMarginMm(),
                $dsId
            );
            header("Location: export.php?project_id=" . $tmpl->getProjectId() . "&template_id=" . $tmplId);
            exit;
        }
    }
}

// Fetch all project datasets for switcher
$projectDatasets = $datasetService->getDatasetsByProject($activeProjectId);

// Fetch dataset if bound
$dataset = null;
$compType = null;
$filteredRowData = [];

function parseExportRowFilter(?string $filterStr, int $totalRows): array {
    if ($totalRows <= 0) return [];
    if (!$filterStr || trim($filterStr) === '') {
        return range(0, $totalRows - 1);
    }
    $indices = [];
    $parts = explode(',', $filterStr);
    foreach ($parts as $part) {
        $trimmed = trim($part);
        if (str_contains($trimmed, '-')) {
            $range = explode('-', $trimmed);
            if (count($range) === 2 && is_numeric($range[0]) && is_numeric($range[1])) {
                $start = (int)$range[0];
                $end = (int)$range[1];
                $min = min($start, $end);
                $max = max($start, $end);
                for ($r = $min; $r <= $max; $r++) {
                    if ($r >= 1 && $r <= $totalRows) {
                        $indices[$r - 1] = true;
                    }
                }
            }
        } elseif (is_numeric($trimmed)) {
            $single = (int)$trimmed;
            if ($single >= 1 && $single <= $totalRows) {
                $indices[$single - 1] = true;
            }
        }
    }
    $keys = array_keys($indices);
    sort($keys);
    return !empty($keys) ? $keys : range(0, $totalRows - 1);
}

if ($activeTemplate) {
    if ($activeTemplate->getDatasetId()) {
        $dataset = $datasetService->getDatasetById($activeTemplate->getDatasetId());
        if ($dataset) {
            $allRows = $dataset->getRowData();
            $filterIndices = parseExportRowFilter($activeTemplate->getRowFilter(), count($allRows));
            foreach ($filterIndices as $idx) {
                if (isset($allRows[$idx])) {
                    $filteredRowData[$idx] = $allRows[$idx];
                }
            }
        }
    }
    
    $compTypes = $templateService->getComponentTypes();
    foreach ($compTypes as $ct) {
        if ($ct->getId() === $activeTemplate->getComponentTypeId()) {
            $compType = $ct;
            break;
        }
    }
}

$tmplWidth = $activeTemplate ? round(\App\Domain\Entities\BgTemplate::pxToMm($activeTemplate->getCanvasWidthPx()), 1) : ($compType ? $compType->getWidthMm() : 0.0);
$tmplHeight = $activeTemplate ? round(\App\Domain\Entities\BgTemplate::pxToMm($activeTemplate->getCanvasHeightPx()), 1) : ($compType ? $compType->getHeightMm() : 0.0);
$isF10A4 = ($compType && str_contains($compType->getName(), 'F10A4-1')) || (abs($tmplWidth - 91.0) < 1.5 && abs($tmplHeight - 55.0) < 1.5) || (abs($tmplWidth - 55.0) < 1.5 && abs($tmplHeight - 91.0) < 1.5);
$is51215 = ($compType && (str_contains($compType->getName(), '51215') || str_contains($compType->getName(), 'F8A4-5'))) || (abs($tmplWidth - 97.0) < 1.5 && abs($tmplHeight - 69.0) < 1.5) || (abs($tmplWidth - 69.0) < 1.5 && abs($tmplHeight - 97.0) < 1.5);
$isBoxTemplate = ($compType && str_contains($compType->getName(), 'Board Game Box')) || ($activeTemplate && str_contains((string)$activeTemplate->getCanvasJson(), 'isBoxDieline'));
$boxConfig = null;
if ($isBoxTemplate && $activeTemplate) {
    $canvasData = json_decode((string)$activeTemplate->getCanvasJson(), true);
    if (!empty($canvasData['boxConfig'])) {
        $boxConfig = $canvasData['boxConfig'];
    } elseif (!empty($canvasData['objects'])) {
        foreach ($canvasData['objects'] as $obj) {
            if (!empty($obj['boxConfig'])) {
                $boxConfig = $obj['boxConfig'];
                break;
            }
        }
    }
}
$isA3 = (abs($tmplWidth - 420.0) < 15.0 && abs($tmplHeight - 297.0) < 15.0) || (abs($tmplWidth - 297.0) < 15.0 && abs($tmplHeight - 420.0) < 15.0);
$isLargeTemplate = $isA3 || ($tmplWidth > 215.0 || $tmplHeight > 215.0);
$autoOrientation = ($tmplWidth > $tmplHeight) ? 'landscape' : 'portrait';

require_once __DIR__ . '/../templates/header.php';

if ($activeProject) {
    renderPageHeader($activeProject->getName(), 'Export Sheets', '', 'index.php');
} else {
    renderPageHeader('Export Sheets', 'Game Studio', '', 'index.php');
}
?>

<!-- FabricJS and export libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<!-- Google Fonts for Board Game Creators -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Almendra:ital,wght@0,400;0,700;1,400&family=Bangers&family=Cinzel:wght@400;700&family=Comic+Neue:wght@400;700&family=Creepster&family=EB+Garamond:ital,wght@0,400;0,700;1,400&family=Fredoka:wght@400;700&family=Inter:wght@400;700&family=Jolly+Lodger&family=Lora:ital,wght@0,400;0,700;1,400&family=Luckiest+Guy&family=MedievalSharp&family=Metal+Mania&family=Montserrat:wght@400;700&family=Orbitron:wght@400;700&family=Outfit:wght@400;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Rajdhani:wght@500;700&family=Rye&family=Share+Tech+Mono&family=Courier+Prime&family=Special+Elite&display=swap" rel="stylesheet">

<div class="page-container space-y-6">
    <?php
    $activeTab = 'export';
    include __DIR__ . '/views/studio-nav.php';
    ?>

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
        <!-- Section Header -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-2 border-b border-slate-800/80">
            <div>
                <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                    <span>🖨️</span>
                    <span>Export Sheets &amp; Printouts</span>
                </h2>
                <p class="text-xs text-slate-400">Produce high-resolution print-ready PDFs with crop marks, bleed, and tiling, or Tabletop Simulator decks.</p>
            </div>

            <div class="flex items-center gap-2.5 flex-nowrap shrink-0">
                <div class="flex items-center space-x-2">
                    <label for="template_select" class="text-xs text-slate-400 font-medium whitespace-nowrap">Template:</label>
                    <select id="template_select" onchange="window.location.href = `?project_id=<?php echo $activeProjectId; ?>&template_id=${this.value}`" class="bg-slate-950 border border-slate-700 text-slate-200 text-xs font-semibold rounded-lg focus:ring-1 focus:ring-indigo-500 py-1.5 pl-2.5 pr-7 cursor-pointer max-w-[200px] truncate">
                        <?php foreach ($templates as $tmpl): ?>
                            <option value="<?php echo $tmpl->getId(); ?>" <?php echo $tmpl->getId() === $activeTemplateId ? 'selected' : ''; ?>>
                                <?php echo SecurityHelper::escape($tmpl->getName()); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($activeTemplate): ?>
                    <button type="button" onclick="toggleCollapsible('export-settings-panel')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-850 hover:bg-slate-800 text-slate-200 border border-slate-700 hover:border-slate-600 text-xs font-semibold rounded-lg transition duration-150 whitespace-nowrap">
                        <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>Export Settings</span>
                    </button>
                    <button type="button" onclick="document.getElementById('btn-run-export').click();" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600 text-indigo-300 hover:text-white border border-indigo-500/40 hover:border-transparent text-xs font-semibold rounded-lg shadow-sm transition duration-150 whitespace-nowrap">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Generate Export</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($activeTemplate): ?>
            <!-- Collapsible Export Settings Panel -->
            <div id="export-settings-panel" class="form-panel-collapsible mb-6">
                <div class="form-panel-collapsible-inner bg-slate-900 border border-indigo-500/20 rounded-2xl p-6 shadow-xl space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                        <div class="flex items-center space-x-3">
                            <div class="p-2 bg-indigo-500/10 text-indigo-400 rounded-xl">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-200">Print &amp; Export Configuration</h3>
                                <p class="text-xs text-slate-400">Configure paper target, tiling scale, crop registration, and output format</p>
                            </div>
                        </div>
                        <button type="button" onclick="toggleCollapsible('export-settings-panel')" class="text-slate-400 hover:text-slate-200 text-xs px-2 py-1 rounded-lg hover:bg-slate-800 transition">
                            &times; Close
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Format Selection -->
                        <div class="space-y-4">
                            <div>
                                <label for="export_format" class="block text-xs font-semibold text-slate-300 mb-1">Export Format</label>
                                <select id="export_format" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-indigo-500 p-2.5">
                                    <option value="pdf">Print-and-Play PDF (Tiled Components)</option>
                                    <option value="tts">Digital Playtest (Tabletop Simulator Sheet + JSON)</option>
                                </select>
                            </div>

                            <div class="space-y-2 pt-2">
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="pdf_crop_marks" checked class="rounded border-slate-800 text-indigo-600 bg-slate-950 focus:ring-indigo-500">
                                    <label for="pdf_crop_marks" class="text-xs font-medium text-slate-300 cursor-pointer select-none">Draw Crop Marks &amp; Registration Crosshairs</label>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="pdf_draw_bleed" class="rounded border-slate-800 text-indigo-600 bg-slate-950 focus:ring-indigo-500">
                                    <label for="pdf_draw_bleed" class="text-xs font-medium text-slate-300 cursor-pointer select-none">Include Physical Bleed Margins</label>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="pdf_measurements" class="rounded border-slate-800 text-indigo-600 bg-slate-950 focus:ring-indigo-500">
                                    <label for="pdf_measurements" class="text-xs font-medium text-slate-300 cursor-pointer select-none">Show Measurements &amp; Dimension Overlay</label>
                                </div>
                            </div>
                        </div>

                        <!-- PDF specific configurations -->
                        <div id="pdf-settings" class="contents">
                            <div class="space-y-4">
                                <div>
                                    <label for="pdf_page_size" class="block text-xs font-semibold text-slate-300 mb-1">Page Size &amp; Layout</label>
                                    <select id="pdf_page_size" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-indigo-500 p-2.5">
                                        <optgroup label="Standard Paper Sizes">
                                            <option value="a4" <?php echo (!$isF10A4 && !$is51215) ? 'selected' : ''; ?>>A4 (210 x 297 mm)</option>
                                            <option value="a5">A5 (148 x 210 mm)</option>
                                            <option value="a3">A3 (297 x 420 mm)</option>
                                            <option value="letter">US Letter (8.5 x 11 in / 215.9 x 279.4 mm)</option>
                                        </optgroup>
                                        <optgroup label="Pre-cut / Label Sheets">
                                            <option value="f10a4_1" <?php echo $isF10A4 ? 'selected' : ''; ?>>A-one F10A4-1 (A4 10-Card / 91x55 mm)</option>
                                            <option value="a_one_51215" <?php echo $is51215 ? 'selected' : ''; ?>>A-one 51215 / F8A4-5 (A4 8-Card / 97x69 mm)</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <div>
                                    <label for="pdf_print_mode" class="block text-xs font-semibold text-slate-300 mb-1">Print Output Mode</label>
                                    <select id="pdf_print_mode" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-indigo-500 p-2.5">
                                        <option value="full_design" <?php echo !$isBoxTemplate ? 'selected' : ''; ?>>Full Artwork Design + Cut &amp; Fold Lines</option>
                                        <option value="cutout_stencil" <?php echo $isBoxTemplate ? 'selected' : ''; ?>>Cut-Out Lines Only (Cardboard Template Stencil)</option>
                                    </select>
                                    <p class="text-[11px] text-slate-400 mt-1">
                                        Use <strong>Cut-Out Lines Only</strong> to print a clean white stencil with solid cut lines and dashed fold lines.
                                    </p>
                                </div>

                                <div id="pdf-orientation-container">
                                    <div class="flex items-center justify-between mb-1">
                                        <label for="pdf_orientation" class="block text-xs font-semibold text-slate-300">Orientation</label>
                                        <span id="pdf-orientation-note" class="text-[11px] text-indigo-400 font-normal"></span>
                                    </div>
                                    <select id="pdf_orientation" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-indigo-500 p-2.5">
                                        <option value="portrait" <?php echo $autoOrientation === 'portrait' ? 'selected' : ''; ?>>Portrait</option>
                                        <option value="landscape" <?php echo $autoOrientation === 'landscape' ? 'selected' : ''; ?>>Landscape</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div id="pdf-tiling-container" class="space-y-2">
                                    <label for="pdf_tiling" class="block text-xs font-semibold text-slate-300">Multi-Sheet Assembly &amp; Print Scale</label>
                                    <select id="pdf_tiling" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-indigo-500 p-2.5">
                                        <option value="split_2_margin" selected>2 Sheets (A3 → 2× A4) — Maximize to Page Margins (Recommended + Seam Guide)</option>
                                        <option value="split_2">2 Sheets (A3 → 2× A4) — Full Bleed / Borderless (Fill Entire 210×297mm Sheets)</option>
                                        <option value="split_4">4 Sheets (2×2 Grid) — Maximize across 4 Sheets</option>
                                        <option value="actual_1page">1 Sheet — 100% Actual Size (Full Bleed Single Sheet)</option>
                                        <option value="fit">1 Sheet — Scale to Fit Page (⚠️ Condenses Entire Design onto 1 Page)</option>
                                        <option value="split_3">3 Sheets — Maximize across 3 Sheets</option>
                                        <option value="split_6">6 Sheets (2×3 / 3×2 Grid)</option>
                                        <option value="split_9">9 Sheets (3×3 Grid)</option>
                                    </select>
                                    <div id="pdf-tiling-warning" class="p-3 rounded-xl text-xs space-y-1">
                                        <!-- Populated dynamically by JS -->
                                    </div>
                                </div>

                                <div id="a3-info-badge" class="<?php echo ($isA3) ? '' : 'hidden '; ?>p-3 bg-indigo-500/10 border border-indigo-500/25 rounded-xl text-xs text-indigo-300 space-y-1">
                                    <div class="font-bold flex items-center gap-1.5 text-indigo-400">
                                        <span>📐 A3 Canvas (<?php echo $tmplWidth; ?>×<?php echo $tmplHeight; ?> mm) → 2× A4 Sheets</span>
                                    </div>
                                    <p class="text-[11px] text-slate-300 leading-relaxed">
                                        To print this A3 canvas on standard A4 paper, select <strong>A4</strong> and <strong>"2 Sheets (A3 → 2× A4)"</strong>. The studio tiles your design at <strong>100% scale</strong>.
                                    </p>
                                </div>

                                <div id="f10a4-info-badge" class="<?php echo $isF10A4 ? '' : 'hidden '; ?>p-3 bg-indigo-500/10 border border-indigo-500/20 rounded-xl text-xs text-indigo-300 space-y-1">
                                    <div class="font-bold flex items-center gap-1.5 text-indigo-400">
                                        <span>🎴 A-one F10A4-1 (10-Card Sheet Layout)</span>
                                    </div>
                                    <p class="text-[11px] text-slate-300">Places 10 cards (2x5 grid, 91x55mm) with exact 14mm side margins. Set printer scale to <strong>100% / Actual Size</strong>.</p>
                                </div>

                                <div id="f8a4-info-badge" class="<?php echo $is51215 ? '' : 'hidden '; ?>p-3 bg-indigo-500/10 border border-indigo-500/20 rounded-xl text-xs text-indigo-300 space-y-1">
                                    <div class="font-bold flex items-center gap-1.5 text-indigo-400">
                                        <span>🎴 A-one 51215 / F8A4-5 (8-Card Sheet Layout)</span>
                                    </div>
                                    <p class="text-[11px] text-slate-300">Places 8 cards (2x4 grid, 97x69mm) with exact 8mm side margins. Set printer scale to <strong>100% / Actual Size</strong>.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tabletop Simulator specific configurations -->
                        <div id="tts-settings" class="space-y-4 hidden md:col-span-2">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="tts_grid_cols" class="block text-xs font-semibold text-slate-300 mb-1">Grid Columns</label>
                                    <input type="number" id="tts_grid_cols" min="1" max="10" value="10" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-indigo-500 p-2.5">
                                </div>
                                <div>
                                    <label for="tts_grid_rows" class="block text-xs font-semibold text-slate-300 mb-1">Grid Rows</label>
                                    <input type="number" id="tts_grid_rows" min="1" max="10" value="7" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-indigo-500 p-2.5">
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-400">Tabletop Simulator custom imports use standard grids, traditionally maxing at 10 columns by 7 rows (70 items total) per texture sheet.</p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-3 pt-4 border-t border-slate-800">
                        <button id="btn-run-export" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl shadow-lg shadow-indigo-600/20 py-2.5 px-6 transition duration-200 text-xs flex items-center space-x-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Generate Export</span>
                        </button>
                        <button type="button" onclick="toggleCollapsible('export-settings-panel')" class="bg-slate-850 hover:bg-slate-800 text-slate-300 text-xs font-semibold rounded-xl py-2.5 px-4 transition duration-200">
                            Close Settings
                        </button>
                    </div>
                </div>
            </div>

            <!-- Progress indicator -->
            <div id="export-progress-container" class="hidden bg-slate-900 border border-indigo-500/30 p-4 rounded-2xl space-y-2">
                <div class="flex justify-between text-xs text-slate-300 font-semibold">
                    <span id="progress-action" class="flex items-center gap-2">
                        <svg class="animate-spin h-3.5 w-3.5 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Generating export...
                    </span>
                    <span id="progress-percent" class="font-mono text-indigo-400">0%</span>
                </div>
                <div class="w-full bg-slate-950 h-2.5 rounded-full overflow-hidden border border-slate-800">
                    <div id="progress-bar" class="bg-indigo-500 h-full w-0 transition-all duration-100"></div>
                </div>
            </div>

            <!-- Template Specifications Card (Full-Width) -->
            <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl space-y-6">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-slate-200 flex items-center gap-2">
                        <span>Template Specifications</span>
                        <span class="text-[11px] font-normal text-slate-400">&bull; <?php echo SecurityHelper::escape($activeTemplate->getName()); ?></span>
                    </h3>
                    <a href="editor.php?id=<?php echo $activeTemplate->getId(); ?>" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold transition flex items-center space-x-1.5 bg-indigo-500/10 hover:bg-indigo-500/20 px-2.5 py-1 rounded-lg border border-indigo-500/20">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        <span>Edit Component</span>
                    </a>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                    <div class="bg-slate-950 border border-slate-800/80 p-3.5 rounded-xl">
                        <span class="text-slate-500 block font-semibold uppercase tracking-wider text-[10px]">Component Dimension</span>
                        <span class="font-bold text-slate-200 text-sm mt-1 block">
                            <?php echo $tmplWidth; ?> &times; <?php echo $tmplHeight; ?> mm
                        </span>
                    </div>
                    <div class="bg-slate-950 border border-slate-800/80 p-3.5 rounded-xl">
                        <span class="text-slate-500 block font-semibold uppercase tracking-wider text-[10px]">Canvas Size (300 DPI)</span>
                        <span class="font-bold text-slate-200 text-sm mt-1 block font-mono">
                            <?php echo $activeTemplate->getCanvasWidthPx(); ?> &times; <?php echo $activeTemplate->getCanvasHeightPx(); ?> px
                        </span>
                    </div>
                    <div class="bg-slate-950 border border-slate-800/80 p-3.5 rounded-xl">
                        <span class="text-slate-500 block font-semibold uppercase tracking-wider text-[10px]">Bleed Outer Edge</span>
                        <span class="font-bold text-slate-200 text-sm mt-1 block">
                            <?php echo $activeTemplate->getBleedMm(); ?> mm
                        </span>
                    </div>
                    <div class="bg-slate-950 border border-slate-800/80 p-3.5 rounded-xl">
                        <label for="export-dataset-select" class="text-slate-500 block font-semibold uppercase tracking-wider text-[10px] mb-1">Dataset Binding</label>
                        <form action="" method="POST" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                            <input type="hidden" name="action" value="update_export_dataset">
                            <input type="hidden" name="template_id" value="<?php echo $activeTemplate->getId(); ?>">
                            <select id="export-dataset-select" name="dataset_id" onchange="this.form.submit()" class="w-full bg-slate-900 border border-slate-800 text-slate-200 text-xs rounded-lg p-1 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">No Dataset Bound</option>
                                <?php foreach ($projectDatasets as $ds): ?>
                                    <option value="<?php echo $ds->getId(); ?>" <?php echo ($dataset && $dataset->getId() === $ds->getId()) ? 'selected' : ''; ?>>
                                        <?php echo SecurityHelper::escape($ds->getName()); ?> (<?php echo count($ds->getRowData()); ?> rows)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>

                <?php if ($dataset): ?>
                    <div class="pt-2 border-t border-slate-800/80">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Dataset Rows (<?php echo count($filteredRowData); ?> items to render<?php echo (!empty($activeTemplate->getRowFilter())) ? ' &bull; Filter: ' . SecurityHelper::escape($activeTemplate->getRowFilter()) : ''; ?>)
                            </h4>
                            <span class="text-[11px] text-slate-500">Bound to <?php echo SecurityHelper::escape($dataset->getName()); ?></span>
                        </div>
                        <div class="max-h-[260px] overflow-y-auto border border-slate-800 rounded-xl">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-950 text-slate-400 border-b border-slate-800 font-bold">
                                        <th class="p-2.5 w-16 text-center">Row</th>
                                        <th class="p-2.5">Item Details &amp; Fields</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filteredRowData as $idx => $row): ?>
                                        <tr class="border-b border-slate-800/40 hover:bg-slate-800/30 text-slate-300">
                                            <td class="p-2.5 text-center text-slate-500 font-mono font-bold bg-slate-950/20"><?php echo $idx + 1; ?></td>
                                            <td class="p-2.5 truncate max-w-[500px]">
                                                <?php 
                                                $summary = [];
                                                foreach ($row as $k => $v) {
                                                    $summary[] = "<span class=\"text-slate-400 font-medium\">" . SecurityHelper::escape((string)$k) . ":</span> " . SecurityHelper::escape((string)$v);
                                                    if (count($summary) >= 4) break;
                                                }
                                                echo implode(' <span class="text-slate-650">&bull;</span> ', $summary);
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
            widthMm: <?php echo $activeTemplate ? round(\App\Domain\Entities\BgTemplate::pxToMm($activeTemplate->getCanvasWidthPx()), 1) : ($compType ? $compType->getWidthMm() : 0); ?>,
            heightMm: <?php echo $activeTemplate ? round(\App\Domain\Entities\BgTemplate::pxToMm($activeTemplate->getCanvasHeightPx()), 1) : ($compType ? $compType->getHeightMm() : 0); ?>,
            templateName: "<?php echo SecurityHelper::escape($activeTemplate->getName()); ?>",
            rowFilter: <?php echo json_encode($activeTemplate->getRowFilter() ?? ''); ?>,
            isBoxTemplate: <?php echo $isBoxTemplate ? 'true' : 'false'; ?>,
            boxConfig: <?php echo json_encode($boxConfig); ?>
        };
    </script>
    <script src="js/asset-picker.js?v=<?php echo filemtime(__DIR__ . '/js/asset-picker.js'); ?>"></script>
    <script src="js/text-style-parser.js?v=<?php echo filemtime(__DIR__ . '/js/text-style-parser.js'); ?>"></script>
<<<<<<< HEAD
    <script src="js/inspector-crop.js?v=<?php echo filemtime(__DIR__ . '/js/inspector-crop.js'); ?>"></script>
=======
    <script src="js/box-measurements.js?v=<?php echo filemtime(__DIR__ . '/js/box-measurements.js'); ?>"></script>
>>>>>>> UX+
    <script src="js/export-pdf.js?v=<?php echo filemtime(__DIR__ . '/js/export-pdf.js'); ?>"></script>
    <script src="js/export-tts.js?v=<?php echo filemtime(__DIR__ . '/js/export-tts.js'); ?>"></script>
    <script src="js/export-handler.js?v=<?php echo filemtime(__DIR__ . '/js/export-handler.js'); ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

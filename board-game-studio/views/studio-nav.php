<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;

/**
 * Studio Project Navigation Tab Bar
 * Variables expected:
 * - $activeProject (App\Domain\Entities\Project)
 * - $boardGameProjects (array of Project entities)
 * - $activeTab (string: 'components', 'assets', 'datasets', 'rulebooks', 'export')
 */

if (!isset($activeProject) || !$activeProject) {
    return;
}

$projectId = $activeProject->getId();
$activeTab = $activeTab ?? 'components';
$boardGameProjects = $boardGameProjects ?? [];

// Fetch live counts for current project
$tabCounts = [
    'components' => 0,
    'assets'     => 0,
    'datasets'   => 0,
    'rulebooks'  => 0,
];

if (isset($container)) {
    try {
        $db = $container->get(PDO::class);
        $tabCounts['components'] = (int)$db->query("SELECT COUNT(*) FROM `bg_templates` WHERE project_id = " . (int)$projectId)->fetchColumn();
        $tabCounts['assets']     = (int)$db->query("SELECT COUNT(*) FROM `bg_assets` WHERE project_id = " . (int)$projectId)->fetchColumn();
        $tabCounts['datasets']   = (int)$db->query("SELECT COUNT(*) FROM `bg_datasets` WHERE project_id = " . (int)$projectId)->fetchColumn();
        $tabCounts['rulebooks']  = (int)$db->query("SELECT COUNT(*) FROM `bg_rulebooks` WHERE project_id = " . (int)$projectId)->fetchColumn();
    } catch (\Exception $e) {
        // Fallback to 0 if count query fails
    }
}

$tabs = [
    'components' => [
        'label' => 'Components',
        'icon'  => 'bi-grid',
        'emoji' => '🎴',
        'count' => $tabCounts['components'],
        'url'   => 'index.php?project_id=' . $projectId,
    ],
    'assets' => [
        'label' => 'Assets',
        'icon'  => 'bi-images',
        'emoji' => '🖼️',
        'count' => $tabCounts['assets'],
        'url'   => 'assets.php?project_id=' . $projectId,
    ],
    'datasets' => [
        'label' => 'Datasets',
        'icon'  => 'bi-table',
        'emoji' => '📊',
        'count' => $tabCounts['datasets'],
        'url'   => 'datasets.php?project_id=' . $projectId,
    ],
    'rulebooks' => [
        'label' => 'Rulebooks',
        'icon'  => 'bi-book',
        'emoji' => '📖',
        'count' => $tabCounts['rulebooks'],
        'url'   => 'rulebooks.php?project_id=' . $projectId,
    ],
    'export' => [
        'label' => 'Export Sheets',
        'icon'  => 'bi-printer',
        'emoji' => '🖨️',
        'count' => null,
        'url'   => 'export.php?project_id=' . $projectId,
    ],
];
?>

<div class="studio-project-nav mb-6 border-b border-slate-800/80 pb-2">
    <!-- Studio Module Tabs (Theme Aligned) -->
    <div class="filter-tabs">
        <?php foreach ($tabs as $key => $tab): ?>
            <?php $isActive = ($activeTab === $key); ?>
            <a href="<?php echo $tab['url']; ?>"
               class="filter-tab <?php echo $isActive ? 'is-active' : ''; ?>">
                <i class="bi <?php echo $tab['icon']; ?> text-sm"></i>
                <span><?php echo $tab['label']; ?></span>
                <?php if ($tab['count'] !== null): ?>
                    <span class="text-[11px] px-1.5 py-0.2 rounded-full font-semibold <?php echo $isActive ? 'bg-indigo-500/20 text-indigo-300' : 'bg-slate-800/80 text-slate-400'; ?>">
                        <?php echo $tab['count']; ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

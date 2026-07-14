<?php
declare(strict_types=1);

namespace App\Application\Services;

class BgExportService
{
    // The export engine runs mostly client-side using Fabric.js rendering, jsPDF, and JSZip.
    // This service is here for future server-side expansions or rendering logs if needed.
    
    public function getExportMetadata(int $templateId): array
    {
        return [
            'dpi' => 300,
            'bleed_marks_mm' => 3.0,
            'export_format' => 'pdf'
        ];
    }
}

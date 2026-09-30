<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\BgTemplate;
use App\Application\Exceptions\ValidationException;

/**
 * Domain/Application Service for calculating unfolded flat board-game box die-lines
 * from finished 3D box dimensions (Width, Length, Height) and box sturdiness types.
 */
class BgBoxDielineService
{
    public const TYPE_DOUBLE_WALL_TRAY = 'double_wall_tray';
    public const TYPE_ROLL_END_TRAY    = 'roll_end_tray';
    public const TYPE_TUCK_TOP_BOX     = 'tuck_top_box';
    public const TYPE_SIMPLE_TRAY      = 'simple_tray';

    public const PART_BASE = 'base';
    public const PART_LID  = 'lid';
    public const PART_PAIR = 'pair';

    public const LID_HEIGHT_FULL  = 'full_coverage';
    public const LID_HEIGHT_THUMB = 'thumb_reveal';
    public const LID_HEIGHT_EXACT = 'exact';

    public const DIM_MODE_CAVITY   = 'usable_cavity';
    public const DIM_MODE_CONTENTS = 'contents_fit';
    public const DIM_MODE_RAW      = 'raw_panel';

    /**
     * Calculates the inward wall intrusion (in mm) that folded inner walls & flaps
     * consume from the center panel floor of a box, and derives both the guaranteed
     * usable interior cavity and the required outer score-to-score Base panel size.
     *
     * @return array{
     *   usableW: float,
     *   usableL: float,
     *   usableH: float,
     *   baseScoreW: float,
     *   baseScoreL: float,
     *   baseScoreH: float
     * }
     */
    public static function calculateBaseScoreDimensions(
        string $boxType,
        float $inputW,
        float $inputL,
        float $inputH,
        float $stockThicknessMm,
        string $dimensionMode
    ): array {
        // How much total interior width/length/height is eaten by inward-folding walls & flaps:
        if ($boxType === self::TYPE_DOUBLE_WALL_TRAY) {
            $wallLossW = 3.5 * $stockThicknessMm; // Left & Right dust flaps + inner rollover walls
            $wallLossL = 2.5 * $stockThicknessMm; // Top & Bottom inner tuck flaps
            $wallLossH = 1.0 * $stockThicknessMm; // Floor + fold bead
        } elseif ($boxType === self::TYPE_ROLL_END_TRAY) {
            $wallLossW = 3.5 * $stockThicknessMm; // Left & Right rollover walls + dust flaps
            $wallLossL = 1.0 * $stockThicknessMm; // Single end wall crease bead
            $wallLossH = 1.0 * $stockThicknessMm;
        } elseif ($boxType === self::TYPE_TUCK_TOP_BOX) {
            $wallLossW = 2.0 * $stockThicknessMm;
            $wallLossL = 2.0 * $stockThicknessMm;
            $wallLossH = 1.5 * $stockThicknessMm;
        } else {
            // TYPE_SIMPLE_TRAY (single wall with corner glue tabs)
            $wallLossW = 1.5 * $stockThicknessMm;
            $wallLossL = 1.5 * $stockThicknessMm;
            $wallLossH = 0.5 * $stockThicknessMm;
        }

        if ($dimensionMode === self::DIM_MODE_CONTENTS) {
            // User entered the size of their cards/boards/components:
            // Add +3.0mm (+1.5mm/side) finger/insertion play and +1.5mm vertical headroom,
            // then push score lines out by the inward wall thickness.
            $usableW = round($inputW + 3.0, 1);
            $usableL = round($inputL + 3.0, 1);
            $usableH = round($inputH + 1.5, 1);
            $baseScoreW = round($usableW + $wallLossW, 1);
            $baseScoreL = round($usableL + $wallLossL, 1);
            $baseScoreH = round($usableH + $wallLossH, 1);
        } elseif ($dimensionMode === self::DIM_MODE_RAW) {
            // User entered raw score-to-score panel dimensions directly:
            $baseScoreW = round($inputW, 1);
            $baseScoreL = round($inputL, 1);
            $baseScoreH = round($inputH, 1);
            $usableW = round(max(5.0, $baseScoreW - $wallLossW), 1);
            $usableL = round(max(5.0, $baseScoreL - $wallLossL), 1);
            $usableH = round(max(5.0, $baseScoreH - $wallLossH), 1);
        } else {
            // Default DIM_MODE_CAVITY: User entered the desired Usable Interior Cavity (W x L x H):
            // Push the Base score lines outward by the inward wall loss so the inside stays truly W x L x H.
            $usableW = round($inputW, 1);
            $usableL = round($inputL, 1);
            $usableH = round($inputH, 1);
            $baseScoreW = round($usableW + $wallLossW, 1);
            $baseScoreL = round($usableL + $wallLossL, 1);
            $baseScoreH = round($usableH + $wallLossH, 1);
        }

        return [
            'usableW'    => $usableW,
            'usableL'    => $usableL,
            'usableH'    => $usableH,
            'baseScoreW' => $baseScoreW,
            'baseScoreL' => $baseScoreL,
            'baseScoreH' => $baseScoreH,
        ];
    }

    /**
     * Calculates the recommended horizontal Lid clearance per side (in mm)
     * based on the box wall fold structure and cardboard stock caliper (t).
     */
    public static function calculateRecommendedClearance(string $boxType, float $stockThicknessMm): float
    {
        if ($boxType === self::TYPE_DOUBLE_WALL_TRAY) {
            return round(($stockThicknessMm * 2.5) + 0.2, 1);
        }
        if ($boxType === self::TYPE_ROLL_END_TRAY) {
            return round(($stockThicknessMm * 2.2) + 0.2, 1);
        }
        return round(($stockThicknessMm * 1.5) + 0.2, 1);
    }

    /**
     * Calculates the effective vertical wall height (H) for a telescoping Top Lid
     * so its side walls compensate for floor + rollover ceiling thickness (+3t)
     * and reach the bottom of the base without falling short.
     */
    public static function calculateLidEffectiveHeight(float $baseH, float $stockThicknessMm, string $lidHeightMode): float
    {
        if ($lidHeightMode === self::LID_HEIGHT_EXACT) {
            return round($baseH, 1);
        }
        if ($lidHeightMode === self::LID_HEIGHT_THUMB) {
            return max(8.0, round($baseH + (3.0 * $stockThicknessMm) - 3.0, 1));
        }
        return round($baseH + (3.0 * $stockThicknessMm), 1);
    }

    /**
     * Returns metadata for all supported box types ordered by sturdiness.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getBoxTypes(): array
    {
        return [
            self::TYPE_DOUBLE_WALL_TRAY => [
                'id'          => self::TYPE_DOUBLE_WALL_TRAY,
                'name'        => 'Heavy-Duty Double-Wall Tray (Image Style)',
                'sturdiness'  => 5,
                'badge'       => 'Maximum Sturdiness (5/5)',
                'description' => 'Self-locking double-folded side & end walls with dual corner dust flaps and shoulder lips. Matches the classic heavy-duty cardboard box template.',
            ],
            self::TYPE_ROLL_END_TRAY => [
                'id'          => self::TYPE_ROLL_END_TRAY,
                'name'        => 'Standard Roll-End Tuck Tray',
                'sturdiness'  => 4,
                'badge'       => 'High Sturdiness (4/5)',
                'description' => 'Self-locking double rollover side walls with single end walls and corner dust flaps. No glue required, uses less cardboard height.',
            ],
            self::TYPE_TUCK_TOP_BOX => [
                'id'          => self::TYPE_TUCK_TOP_BOX,
                'name'        => 'One-Piece Hinged Tuck-Top Box',
                'sturdiness'  => 3,
                'badge'       => 'Medium Sturdiness (3/5)',
                'description' => 'All-in-one single-sheet folding box with attached hinged lid, side glue tab, dust flaps, and tuck flap.',
            ],
            self::TYPE_SIMPLE_TRAY => [
                'id'          => self::TYPE_SIMPLE_TRAY,
                'name'        => 'Classic Single-Wall Tray (Corner Glue Tabs)',
                'sturdiness'  => 2,
                'badge'       => 'Standard Sturdiness (2/5)',
                'description' => 'Compact single-wall setup box tray with 4 corner glue/tape tabs. Uses the smallest flat sheet footprint.',
            ],
        ];
    }

    /**
     * Validates and normalizes user-supplied box configuration parameters.
     *
     * @param array<string, mixed> $params
     * @return array{
     *   boxType: string,
     *   boxPart: string,
     *   dimensionMode: string,
     *   widthMm: float,
     *   lengthMm: float,
     *   heightMm: float,
     *   stockThicknessMm: float,
     *   lidHeightMode: string,
     *   clearanceMm: float,
     *   showLabels: bool,
     *   fillStyle: string
     * }
     */
    public function validateConfig(array $params): array
    {
        $boxType = isset($params['box_type']) ? trim((string)$params['box_type']) : self::TYPE_DOUBLE_WALL_TRAY;
        $validTypes = array_keys(self::getBoxTypes());
        if (!in_array($boxType, $validTypes, true)) {
            $boxType = self::TYPE_DOUBLE_WALL_TRAY;
        }

        $boxPart = isset($params['box_part']) ? trim((string)$params['box_part']) : self::PART_BASE;
        if (!in_array($boxPart, [self::PART_BASE, self::PART_LID, self::PART_PAIR], true)) {
            $boxPart = self::PART_BASE;
        }

        $dimensionMode = isset($params['box_dim_mode']) ? trim((string)$params['box_dim_mode']) : self::DIM_MODE_CAVITY;
        if (!in_array($dimensionMode, [self::DIM_MODE_CAVITY, self::DIM_MODE_CONTENTS, self::DIM_MODE_RAW], true)) {
            $dimensionMode = self::DIM_MODE_CAVITY;
        }

        $widthMm = isset($params['box_width_mm']) ? (float)$params['box_width_mm'] : 120.0;
        $lengthMm = isset($params['box_length_mm']) ? (float)$params['box_length_mm'] : 160.0;
        $heightMm = isset($params['box_height_mm']) ? (float)$params['box_height_mm'] : 40.0;
        $stockThicknessMm = isset($params['box_stock_mm']) && $params['box_stock_mm'] !== ''
            ? (float)$params['box_stock_mm']
            : 0.6;

        if ($stockThicknessMm < 0.1 || $stockThicknessMm > 10.0) {
            throw new ValidationException('Cardboard / Stock Thickness must be between 0.1 mm and 10.0 mm.');
        }

        $lidHeightMode = isset($params['box_lid_height_mode']) ? trim((string)$params['box_lid_height_mode']) : self::LID_HEIGHT_FULL;
        if (!in_array($lidHeightMode, [self::LID_HEIGHT_FULL, self::LID_HEIGHT_THUMB, self::LID_HEIGHT_EXACT], true)) {
            $lidHeightMode = self::LID_HEIGHT_FULL;
        }

        $clearanceMm = isset($params['box_clearance_mm']) && $params['box_clearance_mm'] !== ''
            ? (float)$params['box_clearance_mm']
            : self::calculateRecommendedClearance($boxType, $stockThicknessMm);

        if ($widthMm < 15.0 || $widthMm > 1000.0) {
            throw new ValidationException('Finished Box Width must be between 15 mm and 1000 mm.');
        }
        if ($lengthMm < 15.0 || $lengthMm > 1000.0) {
            throw new ValidationException('Finished Box Length must be between 15 mm and 1000 mm.');
        }
        if ($heightMm < 8.0 || $heightMm > 500.0) {
            throw new ValidationException('Finished Box Height/Depth must be between 8 mm and 500 mm.');
        }
        if ($clearanceMm < 0.0 || $clearanceMm > 25.0) {
            throw new ValidationException('Cardboard Lid Clearance must be between 0 mm and 25 mm.');
        }

        $showLabels = !isset($params['box_show_labels']) || (bool)$params['box_show_labels'];
        $fillStyle = isset($params['box_fill_style']) && in_array($params['box_fill_style'], ['stencil', 'kraft'], true)
            ? (string)$params['box_fill_style']
            : 'stencil';

        return [
            'boxType'          => $boxType,
            'boxPart'          => $boxPart,
            'dimensionMode'    => $dimensionMode,
            'widthMm'          => round($widthMm, 1),
            'lengthMm'         => round($lengthMm, 1),
            'heightMm'         => round($heightMm, 1),
            'stockThicknessMm' => round($stockThicknessMm, 2),
            'lidHeightMode'    => $lidHeightMode,
            'clearanceMm'      => round($clearanceMm, 1),
            'showLabels'       => $showLabels,
            'fillStyle'        => $fillStyle,
        ];
    }

    /**
     * Calculates the unfolded flat sheet dimensions and panel geometry (in mm and 300 DPI px)
     * for a specific piece ('base' or 'lid').
     *
     * @param array<string, mixed> $config
     * @param string $piece 'base' or 'lid'
     * @return array<string, mixed>
     */
    public function calculateGeometry(array $config, string $piece = self::PART_BASE): array
    {
        $boxType = (string)($config['boxType'] ?? self::TYPE_DOUBLE_WALL_TRAY);
        $dimensionMode = (string)($config['dimensionMode'] ?? self::DIM_MODE_CAVITY);
        $inputW = (float)($config['widthMm'] ?? 120.0);
        $inputL = (float)($config['lengthMm'] ?? 160.0);
        $inputH = (float)($config['heightMm'] ?? 40.0);
        $stockMm = (float)($config['stockThicknessMm'] ?? 0.6);
        $lidHeightMode = (string)($config['lidHeightMode'] ?? self::LID_HEIGHT_FULL);
        $clearance = (float)($config['clearanceMm'] ?? self::calculateRecommendedClearance($boxType, $stockMm));

        $scoreDims = self::calculateBaseScoreDimensions($boxType, $inputW, $inputL, $inputH, $stockMm, $dimensionMode);
        $baseScoreW = $scoreDims['baseScoreW'];
        $baseScoreL = $scoreDims['baseScoreL'];
        $baseScoreH = $scoreDims['baseScoreH'];

        // If generating a telescoping Lid for a tray, add 2 * clearance to the compensated Base score-to-score W and L,
        // and compensate vertical Lid height (+3t) so the Lid sides reach the bottom of the Base.
        $isLid = ($piece === self::PART_LID && $boxType !== self::TYPE_TUCK_TOP_BOX);
        $W = $isLid ? round($baseScoreW + ($clearance * 2.0), 1) : $baseScoreW;
        $L = $isLid ? round($baseScoreL + ($clearance * 2.0), 1) : $baseScoreL;
        $H = $isLid ? self::calculateLidEffectiveHeight($baseScoreH, $stockMm, $lidHeightMode) : $baseScoreH;

        $padMm = 6.0; // Outer safety padding around cut line on canvas

        if ($boxType === self::TYPE_DOUBLE_WALL_TRAY) {
            // Floor interlocking lip (shoulder) on all 4 rollover walls + full inner wall height H so flaps reach the bottom floor and interlock
            $shoulder = round(max(5.0, min(18.0, ($H * 0.22) + ($stockMm * 1.5))), 1);
            $flapH = round($H + $shoulder, 1);
            $flatWidthMm = round($W + (4.0 * $H) + (2.0 * $shoulder) + (2.0 * $padMm), 1);
            $flatHeightMm = round($L + (2.0 * $H) + (2.0 * $flapH) + (2.0 * $padMm), 1);
        } elseif ($boxType === self::TYPE_ROLL_END_TRAY) {
            $shoulder = round(max(5.0, min(16.0, ($H * 0.20) + ($stockMm * 1.5))), 1);
            $flatWidthMm = round($W + (4.0 * $H) + (2.0 * $shoulder) + (2.0 * $padMm), 1);
            $flatHeightMm = round($L + (2.0 * $H) + (2.0 * $padMm), 1);
            $flapH = 0.0;
        } elseif ($boxType === self::TYPE_TUCK_TOP_BOX) {
            $glueTab = round(min(22.0, max(12.0, $H * 0.45)), 1);
            $tuckFlap = round(min(28.0, max(12.0, $H * 0.60)), 1);
            $flatWidthMm = round((2.0 * $W) + (2.0 * $H) + $glueTab + (2.0 * $padMm), 1);
            $flatHeightMm = round($L + (2.0 * $H) + (2.0 * $tuckFlap) + (2.0 * $padMm), 1);
            $flapH = $tuckFlap;
            $shoulder = $glueTab;
        } else {
            // TYPE_SIMPLE_TRAY
            $flatWidthMm = round($W + (2.0 * $H) + (2.0 * $padMm), 1);
            $flatHeightMm = round($L + (2.0 * $H) + (2.0 * $padMm), 1);
            $flapH = 0.0;
            $shoulder = 0.0;
        }

        $widthPx = BgTemplate::mmToPx($flatWidthMm, 300);
        $heightPx = BgTemplate::mmToPx($flatHeightMm, 300);

        return [
            'boxType'               => $boxType,
            'piece'                 => $isLid ? self::PART_LID : self::PART_BASE,
            'dimensionMode'         => $dimensionMode,
            'finishedW'             => $inputW,
            'finishedL'             => $inputL,
            'finishedH'             => $inputH,
            'usableW'               => $scoreDims['usableW'],
            'usableL'               => $scoreDims['usableL'],
            'usableH'               => $scoreDims['usableH'],
            'baseScoreW'            => $baseScoreW,
            'baseScoreL'            => $baseScoreL,
            'baseScoreH'            => $baseScoreH,
            'effectiveW'            => $W,
            'effectiveL'            => $L,
            'effectiveH'            => $H,
            'stockThicknessMm'      => $stockMm,
            'lidHeightMode'         => $lidHeightMode,
            'clearanceMm'           => $clearance,
            'padMm'                 => $padMm,
            'flapHMm'               => $flapH,
            'shoulderMm'            => $shoulder,
            'lipCornerAngleDeg'     => 20.0,
            'flatWidthMm'           => $flatWidthMm,
            'flatHeightMm'          => $flatHeightMm,
            'canvasWidthPx'         => $widthPx,
            'canvasHeightPx'        => $heightPx,
            'showLabels'            => (bool)($config['showLabels'] ?? true),
            'fillStyle'             => (string)($config['fillStyle'] ?? 'stencil'),
            'companionTemplateId'   => isset($config['companionTemplateId']) ? (int)$config['companionTemplateId'] : null,
            'companionTemplateName' => isset($config['companionTemplateName']) ? (string)$config['companionTemplateName'] : null,
        ];
    }

    /**
     * Generates the initial Fabric.js 5.3.1 JSON structure and layer metadata array
     * for the unfolded box template.
     *
     * @param array<string, mixed> $geom Output from calculateGeometry()
     * @return array{canvasJson: string, layersData: array<int, array<string, mixed>>}
     */
    public function buildFabricCanvasPayload(array $geom): array
    {
        $px = static fn(float $mm): float => round(($mm / 25.4) * 300.0, 2);

        $boxType = (string)$geom['boxType'];
        $W = (float)$geom['effectiveW'];
        $L = (float)$geom['effectiveL'];
        $H = (float)$geom['effectiveH'];
        $stockMm = (float)($geom['stockThicknessMm'] ?? 0.6);
        $pad = (float)$geom['padMm'];
        $flapH = (float)$geom['flapHMm'];
        $shoulder = (float)$geom['shoulderMm'];
        $lipCornerAngleDeg = (float)($geom['lipCornerAngleDeg'] ?? 20.0);
        $showLabels = (bool)$geom['showLabels'];
        $fillStyle = (string)$geom['fillStyle'];

        $cutPathStr = '';
        $foldPathStr = '';
        $labels = [];

        $slotMm = max(1.0, min(8.0, round($stockMm * 1.25, 2)));
        $halfSlot = $slotMm / 2.0;

        if ($boxType === self::TYPE_DOUBLE_WALL_TRAY) {
            $xLOut = $px($pad);
            $xL2   = $px($pad + $shoulder);
            $xL1   = $px($pad + $shoulder + $H);
            $xC0   = $px($pad + $shoulder + (2.0 * $H));
            $xC1   = $px($pad + $shoulder + (2.0 * $H) + $W);
            $xR1   = $px($pad + $shoulder + (3.0 * $H) + $W);
            $xR2   = $px($pad + $shoulder + (4.0 * $H) + $W);
            $xROut = $px($pad + (2.0 * $shoulder) + (4.0 * $H) + $W);

            $yT2   = $px($pad);
            $yTLip = $px($pad + $shoulder);
            $yT1   = $px($pad + $shoulder + $H);
            $yC0   = $px($pad + $shoulder + (2.0 * $H));
            $yC1   = $px($pad + $shoulder + (2.0 * $H) + $L);
            $yB1   = $px($pad + $shoulder + (3.0 * $H) + $L);
            $yBLip = $px($pad + $shoulder + (4.0 * $H) + $L);
            $yB2   = $px($pad + (2.0 * $shoulder) + (4.0 * $H) + $L);

            $sPx = $px($shoulder);
            $lipBevPx = round($sPx * tan(deg2rad($lipCornerAngleDeg)), 2);
            $slPx = $px($slotMm);
            $hslPx = $px($halfSlot);

            // Outer continuous Cut Line path with full-depth H inner rollover walls + scored interlocking bottom lips (shoulder) on all 4 sides
            $cutParts = [
                "M " . ($xC0 + $lipBevPx) . " {$yT2}",
                "L " . ($xC1 - $lipBevPx) . " {$yT2}",
                "L {$xC1} {$yTLip}",
                "L {$xC1} {$yC0}",
                "L " . ($xC1 + $slPx) . " {$yC0}",
                "L " . ($xC1 + $slPx) . " {$yT1}",
                "L " . ($xR1 - $hslPx) . " {$yT1}",
                "L " . ($xR1 - $hslPx) . " {$yC0}",
                "L " . ($xR1 + $hslPx) . " {$yC0}",
                "L " . ($xR1 + $hslPx) . " {$yT1}",
                "L {$xR2} {$yT1}",
                "L {$xR2} {$yC0}",
                "L {$xROut} " . ($yC0 + $lipBevPx),
                "L {$xROut} " . ($yC1 - $lipBevPx),
                "L {$xR2} {$yC1}",
                "L {$xR2} {$yB1}",
                "L " . ($xR1 + $hslPx) . " {$yB1}",
                "L " . ($xR1 + $hslPx) . " {$yC1}",
                "L " . ($xR1 - $hslPx) . " {$yC1}",
                "L " . ($xR1 - $hslPx) . " {$yB1}",
                "L " . ($xC1 + $slPx) . " {$yB1}",
                "L " . ($xC1 + $slPx) . " {$yC1}",
                "L {$xC1} {$yC1}",
                "L {$xC1} {$yBLip}",
                "L " . ($xC1 - $lipBevPx) . " {$yB2}",
                "L " . ($xC0 + $lipBevPx) . " {$yB2}",
                "L {$xC0} {$yBLip}",
                "L {$xC0} {$yC1}",
                "L " . ($xC0 - $slPx) . " {$yC1}",
                "L " . ($xC0 - $slPx) . " {$yB1}",
                "L " . ($xL1 + $hslPx) . " {$yB1}",
                "L " . ($xL1 + $hslPx) . " {$yC1}",
                "L " . ($xL1 - $hslPx) . " {$yC1}",
                "L " . ($xL1 - $hslPx) . " {$yB1}",
                "L {$xL2} {$yB1}",
                "L {$xL2} {$yC1}",
                "L {$xLOut} " . ($yC1 - $lipBevPx),
                "L {$xLOut} " . ($yC0 + $lipBevPx),
                "L {$xL2} {$yC0}",
                "L {$xL2} {$yT1}",
                "L " . ($xL1 - $hslPx) . " {$yT1}",
                "L " . ($xL1 - $hslPx) . " {$yC0}",
                "L " . ($xL1 + $hslPx) . " {$yC0}",
                "L " . ($xL1 + $hslPx) . " {$yT1}",
                "L " . ($xC0 - $slPx) . " {$yT1}",
                "L " . ($xC0 - $slPx) . " {$yC0}",
                "L {$xC0} {$yC0}",
                "L {$xC0} {$yTLip}",
                "Z"
            ];
            $cutPathStr = implode(' ', $cutParts);

            // Interior Fold / Score lines (including top/bottom interlocking floor lip score lines at yTLip and yBLip)
            $foldParts = [
                // Horizontal folds
                "M {$xC0} {$yTLip} L {$xC1} {$yTLip}",
                "M {$xC0} {$yT1} L {$xC1} {$yT1}",
                "M {$xL2} {$yC0} L {$xR2} {$yC0}",
                "M {$xL2} {$yC1} L {$xR2} {$yC1}",
                "M {$xC0} {$yB1} L {$xC1} {$yB1}",
                "M {$xC0} {$yBLip} L {$xC1} {$yBLip}",
                // Vertical folds
                "M {$xL2} {$yC0} L {$xL2} {$yC1}",
                "M {$xL1} {$yC0} L {$xL1} {$yC1}",
                "M {$xC0} {$yC0} L {$xC0} {$yC1}",
                "M {$xC1} {$yC0} L {$xC1} {$yC1}",
                "M {$xR1} {$yC0} L {$xR1} {$yC1}",
                "M {$xR2} {$yC0} L {$xR2} {$yC1}",
            ];
            $foldPathStr = implode(' ', $foldParts);

            $partTitle = ($geom['piece'] === self::PART_LID) ? 'TOP LID PANEL' : 'CENTER BASE PANEL';
            $labels = [
                ['text' => "{$partTitle}\n{$W} × {$L} mm\n(Height: {$H} mm)", 'x' => ($xC0 + $xC1) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => 0, 'size' => 28],
                ['text' => "TOP OUTER WALL ({$W}×{$H}mm)", 'x' => ($xC0 + $xC1) / 2.0, 'y' => ($yT1 + $yC0) / 2.0, 'angle' => 0, 'size' => 20],
                ['text' => "TOP INNER WALL ({$W}×{$H}mm)", 'x' => ($xC0 + $xC1) / 2.0, 'y' => ($yTLip + $yT1) / 2.0, 'angle' => 0, 'size' => 18],
                ['text' => "BOTTOM OUTER WALL ({$W}×{$H}mm)", 'x' => ($xC0 + $xC1) / 2.0, 'y' => ($yC1 + $yB1) / 2.0, 'angle' => 0, 'size' => 20],
                ['text' => "BOTTOM INNER WALL ({$W}×{$H}mm)", 'x' => ($xC0 + $xC1) / 2.0, 'y' => ($yB1 + $yBLip) / 2.0, 'angle' => 0, 'size' => 18],
                ['text' => "LEFT INNER WALL ({$L}×{$H}mm)", 'x' => ($xL1 + $xC0) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => -90, 'size' => 20],
                ['text' => "LEFT OUTER WALL ({$L}×{$H}mm)", 'x' => ($xL2 + $xL1) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => -90, 'size' => 18],
                ['text' => "RIGHT INNER WALL ({$L}×{$H}mm)", 'x' => ($xC1 + $xR1) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => 90, 'size' => 20],
                ['text' => "RIGHT OUTER WALL ({$L}×{$H}mm)", 'x' => ($xR1 + $xR2) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => 90, 'size' => 18],
            ];
        } elseif ($boxType === self::TYPE_ROLL_END_TRAY) {
            $xLOut = $px($pad);
            $xL2   = $px($pad + $shoulder);
            $xL1   = $px($pad + $shoulder + $H);
            $xC0   = $px($pad + $shoulder + (2.0 * $H));
            $xC1   = $px($pad + $shoulder + (2.0 * $H) + $W);
            $xR1   = $px($pad + $shoulder + (3.0 * $H) + $W);
            $xR2   = $px($pad + $shoulder + (4.0 * $H) + $W);
            $xROut = $px($pad + (2.0 * $shoulder) + (4.0 * $H) + $W);

            $yT1 = $px($pad);
            $yC0 = $px($pad + $H);
            $yC1 = $px($pad + $H + $L);
            $yB1 = $px($pad + (2.0 * $H) + $L);

            $sPx = $px($shoulder);
            $lipBevPx = round($sPx * tan(deg2rad($lipCornerAngleDeg)), 2);
            $slPx = $px($slotMm);
            $chamferPx = $px(min(8.0, max(2.5, $H * 0.2)));

            $cutParts = [
                "M {$xC0} {$yT1}",
                "L {$xC1} {$yT1}",
                "L {$xC1} {$yC0}",
                "L " . ($xC1 + $slPx) . " {$yC0}",
                "L " . ($xC1 + $slPx) . " " . ($yT1 + $chamferPx),
                "L " . ($xC1 + $slPx + $chamferPx) . " {$yT1}",
                "L {$xR1} {$yT1}",
                "L {$xR1} {$yC0}",
                "L {$xR2} {$yC0}",
                "L {$xROut} " . ($yC0 + $lipBevPx),
                "L {$xROut} " . ($yC1 - $lipBevPx),
                "L {$xR2} {$yC1}",
                "L {$xR1} {$yC1}",
                "L {$xR1} {$yB1}",
                "L " . ($xC1 + $slPx + $chamferPx) . " {$yB1}",
                "L " . ($xC1 + $slPx) . " " . ($yB1 - $chamferPx),
                "L " . ($xC1 + $slPx) . " {$yC1}",
                "L {$xC1} {$yC1}",
                "L {$xC1} {$yB1}",
                "L {$xC0} {$yB1}",
                "L {$xC0} {$yC1}",
                "L " . ($xC0 - $slPx) . " {$yC1}",
                "L " . ($xC0 - $slPx) . " " . ($yB1 - $chamferPx),
                "L " . ($xC0 - $slPx - $chamferPx) . " {$yB1}",
                "L {$xL1} {$yB1}",
                "L {$xL1} {$yC1}",
                "L {$xL2} {$yC1}",
                "L {$xLOut} " . ($yC1 - $lipBevPx),
                "L {$xLOut} " . ($yC0 + $lipBevPx),
                "L {$xL2} {$yC0}",
                "L {$xL1} {$yC0}",
                "L {$xL1} {$yT1}",
                "L " . ($xC0 - $slPx - $chamferPx) . " {$yT1}",
                "L " . ($xC0 - $slPx) . " " . ($yT1 + $chamferPx),
                "L " . ($xC0 - $slPx) . " {$yC0}",
                "L {$xC0} {$yC0}",
                "Z"
            ];
            $cutPathStr = implode(' ', $cutParts);

            $foldParts = [
                "M {$xL1} {$yC0} L {$xR1} {$yC0}",
                "M {$xL1} {$yC1} L {$xR1} {$yC1}",
                "M {$xL2} {$yC0} L {$xL2} {$yC1}",
                "M {$xL1} {$yC0} L {$xL1} {$yC1}",
                "M {$xC0} {$yC0} L {$xC0} {$yC1}",
                "M {$xC1} {$yC0} L {$xC1} {$yC1}",
                "M {$xR1} {$yC0} L {$xR1} {$yC1}",
                "M {$xR2} {$yC0} L {$xR2} {$yC1}",
            ];
            $foldPathStr = implode(' ', $foldParts);

            $partTitle = ($geom['piece'] === self::PART_LID) ? 'TOP LID PANEL' : 'CENTER BASE PANEL';
            $labels = [
                ['text' => "{$partTitle}\n{$W} × {$L} mm\n(Height: {$H} mm)", 'x' => ($xC0 + $xC1) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => 0, 'size' => 28],
                ['text' => "TOP WALL ({$W}×{$H}mm)", 'x' => ($xC0 + $xC1) / 2.0, 'y' => ($yT1 + $yC0) / 2.0, 'angle' => 0, 'size' => 20],
                ['text' => "BOTTOM WALL ({$W}×{$H}mm)", 'x' => ($xC0 + $xC1) / 2.0, 'y' => ($yC1 + $yB1) / 2.0, 'angle' => 0, 'size' => 20],
                ['text' => "LEFT SIDE WALL", 'x' => ($xL1 + $xC0) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => -90, 'size' => 20],
                ['text' => "LEFT ROLLOVER FLAP", 'x' => ($xL2 + $xL1) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => -90, 'size' => 18],
                ['text' => "RIGHT SIDE WALL", 'x' => ($xC1 + $xR1) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => 90, 'size' => 20],
                ['text' => "RIGHT ROLLOVER FLAP", 'x' => ($xR1 + $xR2) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => 90, 'size' => 18],
            ];
        } elseif ($boxType === self::TYPE_TUCK_TOP_BOX) {
            // One-Piece Hinged Tuck-Top Box:
            // Columns left-to-right: Glue Tab (shoulder) | Back Panel (W) | Left Spine (H) | Front/Lid Panel (W) | Right Spine (H)
            $glueW = $shoulder;
            $tuckH = $flapH;
            $dustH = round($H * 0.75, 1);

            $xG  = $px($pad);
            $xB0 = $px($pad + $glueW);
            $xB1 = $px($pad + $glueW + $W);
            $xS1 = $px($pad + $glueW + $W + $H);
            $xF1 = $px($pad + $glueW + (2.0 * $W) + $H);
            $xR1 = $px($pad + $glueW + (2.0 * $W) + (2.0 * $H));

            $yT2 = $px($pad);
            $yT1 = $px($pad + $tuckH);
            $yTD = $px($pad + $tuckH + $H - $dustH);
            $yC0 = $px($pad + $tuckH + $H);
            $yC1 = $px($pad + $tuckH + $H + $L);
            $yBD = $px($pad + $tuckH + $H + $L + $dustH);
            $yB1 = $px($pad + $tuckH + (2.0 * $H) + $L);
            $yB2 = $px($pad + (2.0 * $tuckH) + (2.0 * $H) + $L);

            $cPx = $px(min(10.0, max(3.5, $tuckH * 0.35)));
            $gBev = $px(min(12.0, max(4.0, $glueW * 0.6)));

            $cutParts = [
                // Start at top-left of Back Panel's Top End Wall
                "M {$xB0} {$yC0}",
                "L {$xB0} {$yT1}",
                "L {$xB1} {$yT1}",
                "L {$xB1} {$yC0}",
                // Left Spine Top Dust Flap
                "L " . ($xB1 + $cPx * 0.5) . " {$yTD}",
                "L " . ($xS1 - $cPx * 0.5) . " {$yTD}",
                "L {$xS1} {$yC0}",
                // Front/Lid Top Wall + Curved/Chamfered Tuck Flap
                "L {$xS1} " . ($yT2 + $cPx),
                "L " . ($xS1 + $cPx) . " {$yT2}",
                "L " . ($xF1 - $cPx) . " {$yT2}",
                "L {$xF1} " . ($yT2 + $cPx),
                "L {$xF1} {$yC0}",
                // Right Spine Top Dust Flap
                "L " . ($xF1 + $cPx * 0.5) . " {$yTD}",
                "L " . ($xR1 - $cPx * 0.5) . " {$yTD}",
                "L {$xR1} {$yC0}",
                // Right Outer Edge
                "L {$xR1} {$yC1}",
                // Right Spine Bottom Dust Flap
                "L " . ($xR1 - $cPx * 0.5) . " {$yBD}",
                "L " . ($xF1 + $cPx * 0.5) . " {$yBD}",
                "L {$xF1} {$yC1}",
                // Front/Lid Bottom Wall + Tuck Flap
                "L {$xF1} " . ($yB2 - $cPx),
                "L " . ($xF1 - $cPx) . " {$yB2}",
                "L " . ($xS1 + $cPx) . " {$yB2}",
                "L {$xS1} " . ($yB2 - $cPx),
                "L {$xS1} {$yC1}",
                // Left Spine Bottom Dust Flap
                "L " . ($xS1 - $cPx * 0.5) . " {$yBD}",
                "L " . ($xB1 + $cPx * 0.5) . " {$yBD}",
                "L {$xB1} {$yC1}",
                // Back Panel Bottom End Wall
                "L {$xB1} {$yB1}",
                "L {$xB0} {$yB1}",
                "L {$xB0} {$yC1}",
                // Left Glue Tab
                "L {$xG} " . ($yC1 - $gBev),
                "L {$xG} " . ($yC0 + $gBev),
                "Z"
            ];
            $cutPathStr = implode(' ', $cutParts);

            $foldParts = [
                "M {$xB0} {$yC0} L {$xR1} {$yC0}",
                "M {$xB0} {$yC1} L {$xR1} {$yC1}",
                "M {$xS1} {$yT1} L {$xF1} {$yT1}",
                "M {$xS1} {$yB1} L {$xF1} {$yB1}",
                "M {$xB0} {$yC0} L {$xB0} {$yC1}",
                "M {$xB1} {$yC0} L {$xB1} {$yC1}",
                "M {$xS1} {$yC0} L {$xS1} {$yC1}",
                "M {$xF1} {$yC0} L {$xF1} {$yC1}",
            ];
            $foldPathStr = implode(' ', $foldParts);

            $labels = [
                ['text' => "BACK / BOTTOM PANEL\n{$W} × {$L} mm", 'x' => ($xB0 + $xB1) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => 0, 'size' => 24],
                ['text' => "FRONT / LID PANEL\n{$W} × {$L} mm", 'x' => ($xS1 + $xF1) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => 0, 'size' => 24],
                ['text' => "SPINE ({$H}mm)", 'x' => ($xB1 + $xS1) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => -90, 'size' => 18],
                ['text' => "SIDE ({$H}mm)", 'x' => ($xF1 + $xR1) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => 90, 'size' => 18],
                ['text' => "GLUE TAB", 'x' => ($xG + $xB0) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => -90, 'size' => 14],
                ['text' => "TOP FLAP", 'x' => ($xS1 + $xF1) / 2.0, 'y' => ($yT1 + $yC0) / 2.0, 'angle' => 0, 'size' => 18],
                ['text' => "BOTTOM FLAP", 'x' => ($xS1 + $xF1) / 2.0, 'y' => ($yC1 + $yB1) / 2.0, 'angle' => 0, 'size' => 18],
            ];
        } else {
            // TYPE_SIMPLE_TRAY: Classic Single-Wall Tray with 4 corner glue/tape tabs
            $xL1 = $px($pad);
            $xC0 = $px($pad + $H);
            $xC1 = $px($pad + $H + $W);
            $xR1 = $px($pad + (2.0 * $H) + $W);

            $yT1 = $px($pad);
            $yC0 = $px($pad + $H);
            $yC1 = $px($pad + $H + $L);
            $yB1 = $px($pad + (2.0 * $H) + $L);

            $tabH = $px(min($H * 0.85, max(10.0, $H * 0.6)));
            $bev = $px(min(6.0, max(2.0, $H * 0.15)));
            $slPx = $px($slotMm);

            $cutParts = [
                "M {$xC0} {$yT1}",
                "L {$xC1} {$yT1}",
                "L {$xC1} {$yC0}",
                "L " . ($xC1 + $slPx) . " {$yC0}",
                "L " . ($xC1 + $slPx + $bev) . " " . ($yC0 - $tabH),
                "L " . ($xR1 - $bev) . " " . ($yC0 - $tabH),
                "L {$xR1} {$yC0}",
                "L {$xR1} {$yC1}",
                "L " . ($xR1 - $bev) . " " . ($yC1 + $tabH),
                "L " . ($xC1 + $slPx + $bev) . " " . ($yC1 + $tabH),
                "L " . ($xC1 + $slPx) . " {$yC1}",
                "L {$xC1} {$yC1}",
                "L {$xC1} {$yB1}",
                "L {$xC0} {$yB1}",
                "L {$xC0} {$yC1}",
                "L " . ($xC0 - $slPx) . " {$yC1}",
                "L " . ($xC0 - $slPx - $bev) . " " . ($yC1 + $tabH),
                "L " . ($xL1 + $bev) . " " . ($yC1 + $tabH),
                "L {$xL1} {$yC1}",
                "L {$xL1} {$yC0}",
                "L " . ($xL1 + $bev) . " " . ($yC0 - $tabH),
                "L " . ($xC0 - $slPx - $bev) . " " . ($yC0 - $tabH),
                "L " . ($xC0 - $slPx) . " {$yC0}",
                "L {$xC0} {$yC0}",
                "Z"
            ];
            $cutPathStr = implode(' ', $cutParts);

            $foldParts = [
                "M {$xL1} {$yC0} L {$xR1} {$yC0}",
                "M {$xL1} {$yC1} L {$xR1} {$yC1}",
                "M {$xC0} {$yC0} L {$xC0} {$yC1}",
                "M {$xC1} {$yC0} L {$xC1} {$yC1}",
            ];
            $foldPathStr = implode(' ', $foldParts);

            $partTitle = ($geom['piece'] === self::PART_LID) ? 'TOP LID PANEL' : 'CENTER BASE PANEL';
            $labels = [
                ['text' => "{$partTitle}\n{$W} × {$L} mm\n(Height: {$H} mm)", 'x' => ($xC0 + $xC1) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => 0, 'size' => 28],
                ['text' => "TOP WALL ({$W}×{$H}mm)", 'x' => ($xC0 + $xC1) / 2.0, 'y' => ($yT1 + $yC0) / 2.0, 'angle' => 0, 'size' => 20],
                ['text' => "BOTTOM WALL ({$W}×{$H}mm)", 'x' => ($xC0 + $xC1) / 2.0, 'y' => ($yC1 + $yB1) / 2.0, 'angle' => 0, 'size' => 20],
                ['text' => "LEFT WALL ({$L}×{$H}mm)", 'x' => ($xL1 + $xC0) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => -90, 'size' => 20],
                ['text' => "RIGHT WALL ({$L}×{$H}mm)", 'x' => ($xC1 + $xR1) / 2.0, 'y' => ($yC0 + $yC1) / 2.0, 'angle' => 90, 'size' => 20],
            ];
        }

        $objects = [];
        $layersData = [];

        $fillColor = ($fillStyle === 'kraft') ? '#d6b07c' : '#ffffff';

        // 1. Base Fill / Outer Cut Path (at bottom or top depending on role; we create a base fill path + a top cut-line path so user artwork sits cleanly between the fill and the cut/fold lines!)
        $objects[] = [
            'type'              => 'path',
            'version'           => '5.3.0',
            'id'                => 'box-dieline-fill',
            'name'              => 'Box Cardboard Fill',
            'layerType'         => 'shape',
            'isBoxDieline'      => true,
            'dielineRole'       => 'fill',
            'excludeFromStencil'=> true,
            'boxConfig'         => $geom,
            'path'              => $cutPathStr,
            'fill'              => $fillColor,
            'stroke'            => 'transparent',
            'strokeWidth'       => 0,
            'selectable'        => false,
            'evented'           => false,
            'lockMovementX'     => true,
            'lockMovementY'     => true,
        ];

        // 2. Solid Cut Line Overlay (stays above artwork)
        $objects[] = [
            'type'          => 'path',
            'version'       => '5.3.0',
            'id'            => 'box-dieline-cut',
            'name'          => 'Box Cut Line (Solid — Cut Here)',
            'layerType'     => 'shape',
            'isBoxDieline'  => true,
            'dielineRole'   => 'cut',
            'boxConfig'     => $geom,
            'path'          => $cutPathStr,
            'fill'          => 'transparent',
            'stroke'        => '#0f172a',
            'strokeWidth'   => 4,
            'selectable'    => false,
            'evented'       => false,
            'lockMovementX' => true,
            'lockMovementY' => true,
        ];

        // 3. Dashed Fold / Score Line Overlay (stays above artwork)
        $objects[] = [
            'type'            => 'path',
            'version'         => '5.3.0',
            'id'              => 'box-dieline-fold',
            'name'            => 'Box Fold / Score Lines (Dashed — Fold Here)',
            'layerType'       => 'shape',
            'isBoxDieline'    => true,
            'dielineRole'     => 'fold',
            'boxConfig'       => $geom,
            'path'            => $foldPathStr,
            'fill'            => 'transparent',
            'stroke'          => '#475569',
            'strokeWidth'     => 3,
            'strokeDashArray' => [18, 12],
            'selectable'      => false,
            'evented'         => false,
            'lockMovementX'   => true,
            'lockMovementY'   => true,
        ];

        // 4. Panel Labels (optional, toggleable)
        foreach ($labels as $idx => $lbl) {
            $objects[] = [
                'type'          => 'text',
                'version'       => '5.3.0',
                'id'            => 'box-dieline-label-' . $idx,
                'name'          => 'Box Panel Guide Label',
                'layerType'     => 'text',
                'isBoxDieline'  => true,
                'dielineRole'   => 'label',
                'text'          => $lbl['text'],
                'left'          => $lbl['x'],
                'top'           => $lbl['y'],
                'originX'       => 'center',
                'originY'       => 'center',
                'angle'         => $lbl['angle'],
                'fontFamily'    => 'Inter',
                'fontSize'      => $lbl['size'],
                'fontWeight'    => 'bold',
                'textAlign'     => 'center',
                'fill'          => '#64748b',
                'opacity'       => 0.75,
                'styles'        => new \stdClass(),
                'visible'       => $showLabels,
                'selectable'    => false,
                'evented'       => false,
                'lockMovementX' => true,
                'lockMovementY' => true,
            ];
        }

        foreach ($objects as $zIdx => $obj) {
            $layersData[] = [
                'name'             => $obj['name'],
                'layer_type'       => $obj['layerType'],
                'z_index'          => $zIdx,
                'x_pos'            => (float)($obj['left'] ?? 0),
                'y_pos'            => (float)($obj['top'] ?? 0),
                'width'            => (float)$geom['canvasWidthPx'],
                'height'           => (float)$geom['canvasHeightPx'],
                'rotation'         => (float)($obj['angle'] ?? 0),
                'opacity'          => (float)($obj['opacity'] ?? 1.0),
                'properties'       => ['isBoxDieline' => true, 'dielineRole' => $obj['dielineRole']],
                'variable_binding' => null,
                'is_visible'       => (bool)($obj['visible'] ?? true),
                'is_locked'        => true,
            ];
        }

        $canvasPayload = [
            'version'    => '5.3.0',
            'background' => '#ffffff',
            'boxConfig'  => $geom,
            'objects'    => $objects,
        ];

        return [
            'canvasJson' => (string)json_encode($canvasPayload, JSON_UNESCAPED_UNICODE),
            'layersData' => $layersData,
        ];
    }
}

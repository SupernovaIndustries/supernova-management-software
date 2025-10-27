<?php

namespace App\Services;

use App\Models\Component;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArUcoService
{
    private const ARUCO_SIZE = 200; // pixels
    private const MARKER_BITS = 4; // 4x4 ArUco marker
    
    /**
     * Generate ArUco code for a component
     */
    public function generateArUcoCode(Component $component): string
    {
        // Generate unique ArUco code based on component ID
        $code = 'ARUCO-' . str_pad($component->id, 6, '0', STR_PAD_LEFT);
        
        // Save to component
        $component->update([
            'aruco_code' => $code,
            'aruco_generated_at' => now(),
        ]);
        
        // Generate ArUco marker image
        $this->generateArUcoImage($component);
        
        return $code;
    }
    
    /**
     * Generate ArUco marker image
     */
    private function generateArUcoImage(Component $component): void
    {
        // Create a simple ArUco-like pattern
        // In production, you'd use a proper ArUco library
        $size = self::ARUCO_SIZE;
        $cellSize = $size / (self::MARKER_BITS + 2); // +2 for border
        
        // Create image
        $image = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        // Fill white background
        imagefilledrectangle($image, 0, 0, $size, $size, $white);
        
        // Draw black border
        imagefilledrectangle($image, 0, 0, $size, $cellSize, $black);
        imagefilledrectangle($image, 0, $size - $cellSize, $size, $size, $black);
        imagefilledrectangle($image, 0, 0, $cellSize, $size, $black);
        imagefilledrectangle($image, $size - $cellSize, 0, $size, $size, $black);
        
        // Generate pattern based on component ID
        $pattern = $this->generatePattern($component->id);
        
        // Draw pattern
        for ($row = 0; $row < self::MARKER_BITS; $row++) {
            for ($col = 0; $col < self::MARKER_BITS; $col++) {
                if ($pattern[$row][$col]) {
                    $x1 = ($col + 1) * $cellSize;
                    $y1 = ($row + 1) * $cellSize;
                    $x2 = $x1 + $cellSize;
                    $y2 = $y1 + $cellSize;
                    imagefilledrectangle($image, $x1, $y1, $x2, $y2, $black);
                }
            }
        }
        
        // Add text below marker
        $textImage = imagecreatetruecolor($size, $size + 60);
        imagefill($textImage, 0, 0, $white);
        imagecopy($textImage, $image, 0, 0, 0, 0, $size, $size);
        
        // Add component info text
        $fontSize = 3;
        $text1 = $component->aruco_code;
        $text2 = substr($component->part_number, 0, 20);
        $text3 = substr($component->manufacturer, 0, 20);
        
        $textX = ($size - strlen($text1) * imagefontwidth($fontSize)) / 2;
        imagestring($textImage, $fontSize, $textX, $size + 5, $text1, $black);
        
        $textX = ($size - strlen($text2) * imagefontwidth($fontSize)) / 2;
        imagestring($textImage, $fontSize, $textX, $size + 20, $text2, $black);
        
        $textX = ($size - strlen($text3) * imagefontwidth($fontSize)) / 2;
        imagestring($textImage, $fontSize, $textX, $size + 35, $text3, $black);
        
        // Save image
        $filename = 'aruco/' . $component->aruco_code . '.png';
        $path = Storage::disk('public')->path($filename);
        
        // Ensure directory exists
        Storage::disk('public')->makeDirectory('aruco');
        
        imagepng($textImage, $path);
        imagedestroy($image);
        imagedestroy($textImage);
        
        // Update component with image path
        $component->update(['aruco_image_path' => $filename]);
    }
    
    /**
     * Generate pattern based on ID
     */
    private function generatePattern(int $id): array
    {
        // Simple pattern generation based on ID
        // In production, use proper ArUco dictionary
        $pattern = [];
        $binary = str_pad(decbin($id % 65536), 16, '0', STR_PAD_LEFT);
        
        for ($i = 0; $i < self::MARKER_BITS; $i++) {
            $pattern[$i] = [];
            for ($j = 0; $j < self::MARKER_BITS; $j++) {
                $index = $i * self::MARKER_BITS + $j;
                $pattern[$i][$j] = $binary[$index] === '1';
            }
        }
        
        return $pattern;
    }
    
    /**
     * Find component by ArUco code
     */
    public function findByArUcoCode(string $code): ?Component
    {
        return Component::where('aruco_code', $code)->first();
    }
    
    /**
     * Generate ArUco codes for all components without one
     */
    public function generateMissingArUcoCodes(): int
    {
        $components = Component::whereNull('aruco_code')->get();
        $count = 0;
        
        foreach ($components as $component) {
            $this->generateArUcoCode($component);
            $count++;
        }
        
        return $count;
    }
    
    /**
     * Get printable ArUco sheet for multiple components
     */
    public function generatePrintSheet(array $componentIds): string
    {
        $components = Component::whereIn('id', $componentIds)
            ->whereNotNull('aruco_code')
            ->get();
        
        if ($components->isEmpty()) {
            throw new \Exception('No components with ArUco codes found');
        }
        
        // Generate HTML for printing
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>ArUco Codes - Supernova Components</title>
    <style>
        @page { size: A4; margin: 10mm; }
        body { 
            font-family: Arial, sans-serif; 
            margin: 0;
            padding: 0;
        }
        .page { 
            page-break-after: always;
            padding: 10mm;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10mm;
        }
        .aruco-card {
            border: 1px solid #ddd;
            padding: 5mm;
            text-align: center;
            break-inside: avoid;
        }
        .aruco-card img {
            width: 100%;
            max-width: 50mm;
            height: auto;
        }
        .info {
            margin-top: 3mm;
            font-size: 9pt;
            line-height: 1.3;
        }
        .info strong {
            display: block;
            font-size: 10pt;
            margin-bottom: 2mm;
        }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="page">
        <h2 style="text-align: center; margin-bottom: 20px;">Supernova Component ArUco Codes</h2>
        <div class="grid">';
        
        foreach ($components as $index => $component) {
            if ($index > 0 && $index % 9 === 0) {
                $html .= '</div></div><div class="page"><div class="grid">';
            }
            
            $html .= '
            <div class="aruco-card">
                <img src="' . Storage::disk('public')->url($component->aruco_image_path) . '" alt="' . $component->aruco_code . '">
                <div class="info">
                    <strong>' . $component->aruco_code . '</strong>
                    ' . e($component->part_number) . '<br>
                    ' . e($component->manufacturer) . '<br>
                    ' . e($component->description) . '
                </div>
            </div>';
        }
        
        $html .= '
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
}
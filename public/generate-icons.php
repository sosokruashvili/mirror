<?php
/**
 * Simple PWA Icon Generator
 * Run this script once to generate placeholder icons
 * php generate-icons.php
 */

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$iconDir = __DIR__ . '/icons';

if (!is_dir($iconDir)) {
    mkdir($iconDir, 0755, true);
}

foreach ($sizes as $size) {
    // Create image
    $image = imagecreatetruecolor($size, $size);
    
    // Colors
    $bgColor = imagecolorallocate($image, 32, 107, 196); // #206bc4
    $textColor = imagecolorallocate($image, 255, 255, 255); // white
    
    // Fill background
    imagefilledrectangle($image, 0, 0, $size, $size, $bgColor);
    
    // Add text "M"
    $fontSize = $size * 0.3;
    $font = 5; // Built-in font (you can use imageloadfont() for custom fonts)
    
    // Calculate text position (centered)
    $text = 'M';
    $textWidth = imagefontwidth($font) * strlen($text);
    $textHeight = imagefontheight($font);
    $x = ($size - $textWidth) / 2;
    $y = ($size - $textHeight) / 2;
    
    // For better text rendering, use imagestring with larger font
    if ($size >= 128) {
        imagestring($image, $font, $x, $y, $text, $textColor);
    } else {
        imagestring($image, $font, $x, $y, $text, $textColor);
    }
    
    // Save image
    $filename = $iconDir . "/icon-{$size}x{$size}.png";
    imagepng($image, $filename);
    imagedestroy($image);
    
    echo "Generated: {$filename}\n";
}

echo "All icons generated successfully!\n";


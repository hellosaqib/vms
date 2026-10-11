<?php
// Create this file and run once to generate icons
require_once 'includes/config.php';

// Base icon (create a 512x512 PNG logo first)
$baseIcon = 'assets/icons/logo-512x512.png';


if (!file_exists($baseIcon)) {
    die("Create a 512x512 logo first at: $baseIcon");
}

$sizes = [16, 32, 72, 96, 128, 144, 152, 167, 180, 192, 384, 512];
$source = imagecreatefrompng($baseIcon);

foreach ($sizes as $size) {
    $destination = imagecreatetruecolor($size, $size);
    
    // Preserve transparency
    imagealphablending($destination, false);
    imagesavealpha($destination, true);
    $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
    imagefilledrectangle($destination, 0, 0, $size, $size, $transparent);
    
    // Resize
    imagecopyresampled($destination, $source, 0, 0, 0, 0, $size, $size, 512, 512);
    
    // Save
    imagepng($destination, "assets/icons/icon-{$size}x{$size}.png");
    imagedestroy($destination);
    
    echo "Created icon-{$size}x{$size}.png<br>";
}

imagedestroy($source);
echo "All icons generated successfully!";
?>
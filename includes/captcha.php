<?php
/**
 * Sudarshan Yuvak Mandal - Enterprise Dynamic CAPTCHA Generator
 * Generates visual noise captcha image and stores code securely in session.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

// Generate random 5-character alphanumeric captcha (excluding ambiguous characters 0, O, 1, I, l)
$charset = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
$captcha_code = '';
$length = 5;
for ($i = 0; $i < $length; $i++) {
    $captcha_code .= $charset[random_int(0, strlen($charset) - 1)];
}

// Save uppercase answer in session
$_SESSION['captcha_code'] = strtoupper($captcha_code);

// Prevent caching
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Check if GD extension is available
if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
    header("Content-Type: image/png");

    $width = 160;
    $height = 48;
    $image = imagecreatetruecolor($width, $height);

    // Light Theme palette
    $bg_color = imagecolorallocate($image, 250, 248, 246); // Warm off-white
    $text_color = imagecolorallocate($image, 218, 77, 18);  // Saffron dark orange
    $noise_color1 = imagecolorallocate($image, 255, 183, 0); // Golden marigold
    $noise_color2 = imagecolorallocate($image, 220, 225, 230); // Soft grey line

    imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);

    // Draw random noise background lines
    for ($i = 0; $i < 6; $i++) {
        imageline(
            $image,
            random_int(0, $width), random_int(0, $height),
            random_int(0, $width), random_int(0, $height),
            ($i % 2 === 0) ? $noise_color1 : $noise_color2
        );
    }

    // Draw random dots
    for ($i = 0; $i < 100; $i++) {
        imagesetpixel($image, random_int(0, $width), random_int(0, $height), $noise_color1);
    }

    // Render text characters
    $font_size = 5; // Built-in GD font size (1 to 5)
    $char_width = imagefontwidth($font_size);
    $char_height = imagefontheight($font_size);
    $total_text_width = $char_width * $length;
    $start_x = (int)(($width - $total_text_width) / 2);

    for ($i = 0; $i < $length; $i++) {
        $x = $start_x + ($i * ($char_width + 8));
        $y = random_int(12, 18);
        imagechar($image, $font_size, $x, $y, $captcha_code[$i], $text_color);
    }

    imagepng($image);
    imagedestroy($image);
    exit;
} else {
    // Enterprise Fallback: High Quality SVG CAPTCHA (When GD PHP module is absent)
    header("Content-Type: image/svg+xml; charset=utf-8");
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    $svg = '<?xml version="1.0" encoding="UTF-8"?>';
    $svg .= '<svg width="160" height="48" viewBox="0 0 160 48" xmlns="http://www.w3.org/2000/svg">';
    $svg .= '<rect width="160" height="48" fill="#FAF8F6" rx="8"/>';
    $svg .= '<path d="M 10 15 Q 40 5 80 25 T 150 10" stroke="#FFB700" stroke-width="2" fill="none" opacity="0.6"/>';
    $svg .= '<path d="M 10 35 Q 60 45 100 20 T 150 35" stroke="#E2E8F0" stroke-width="2" fill="none" opacity="0.8"/>';
    
    // Add letters with slight rotation and distortion
    for ($i = 0; $i < $length; $i++) {
        $x = 20 + ($i * 26);
        $y = 32 + random_int(-3, 3);
        $rot = random_int(-12, 12);
        $char = $captcha_code[$i];
        $svg .= "<text x='{$x}' y='{$y}' fill='#DA4D12' font-family='Arial, sans-serif' font-weight='900' font-size='24' transform='rotate({$rot}, {$x}, {$y})'>{$char}</text>";
    }
    
    $svg .= '</svg>';
    echo $svg;
    exit;
}

<?php
/**
 * Sudarshan Yuvak Mandal - Header Template
 * Location: Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat
 */
require_once __DIR__ . '/../config/db.php';
$csrf_token = Security::getCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="Sudarshan Yuvak Mandal, Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat - Official Ganesh Utsav Member Portal">
    <meta name="theme-color" content="#FF9933">
    <title>Sudarshan Yuvak Mandal | Ganesh Utsav Portal, Surat</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Application Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
<body>
    <!-- Background Animated Festive Canvas -->
    <canvas id="festiveCanvas" class="festive-canvas"></canvas>

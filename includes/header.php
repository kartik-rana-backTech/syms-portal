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
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Sudarshan Yuvak Mandal | Ganesh Utsav Portal, Surat</title>

    <!-- Global Application Config & CSRF Bootstrap -->
    <script>
        window.APP_CONFIG = window.APP_CONFIG || {};
        window.APP_CONFIG.csrfToken = "<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>";
    </script>
    
    <!-- Passive Event Listeners Booster for Responsive Scrolling -->
    <script>
        (function () {
            'use strict';
            if (typeof EventTarget === 'undefined') return;
            var passiveEvents = ['touchstart', 'touchmove', 'touchend', 'touchcancel', 'wheel', 'mousewheel'];
            var origAdd = EventTarget.prototype.addEventListener;
            EventTarget.prototype.addEventListener = function (type, listener, options) {
                var modOptions = options;
                if (passiveEvents.indexOf(type) !== -1) {
                    if (typeof options === 'boolean') {
                        modOptions = { capture: options, passive: true };
                    } else if (typeof options === 'object' && options !== null) {
                        if (options.passive === undefined) {
                            modOptions = Object.assign({}, options, { passive: true });
                        }
                    } else if (options === undefined || options === null) {
                        modOptions = { passive: true };
                    }
                }
                return origAdd.call(this, type, listener, modOptions);
            };
        })();
    </script>

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

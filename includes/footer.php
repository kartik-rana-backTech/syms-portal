<?php
/**
 * Sudarshan Yuvak Mandal - Footer Template
 */
?>
    <footer class="app-footer">
        <div class="footer-container">
            <div class="mandal-info">
                <h4><i class="fa-solid fa-om"></i> Sudarshan Yuvak Mandal</h4>
                <p><i class="fa-solid fa-location-dot"></i> Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat, Gujarat</p>
                <p class="copyright">&copy; <?php echo date('Y'); ?> Sudarshan Yuvak Mandal. All Rights Reserved. Designed with devotion & enterprise precision.</p>
            </div>
        </div>
    </footer>

    <!-- Pass CSRF Token to JS -->
    <script>
        window.APP_CONFIG = {
            csrfToken: "<?php echo $csrf_token; ?>"
        };
    </script>
    <script src="assets/js/canvas_particles.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/canvas_particles.js'); ?>"></script>
    <script src="assets/js/main.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/main.js'); ?>"></script>
</body>
</html>

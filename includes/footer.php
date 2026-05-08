
    <footer>
        <?php
        $__footerSite = 'KCALS';
        if (function_exists('getSettings')) {
            $__fs = getSettings(['general_site_name']);
            if (!empty($__fs['general_site_name'])) $__footerSite = $__fs['general_site_name'];
        }
        ?>
        <p>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars($__footerSite) ?></strong> &mdash; <?= __('footer_text') ?> &bull; Created with love by <a href="https://activeweb.gr" target="_blank" rel="noopener noreferrer">activeweb.gr</a> &bull; <span class="footer-version"><?= htmlspecialchars(trim(@file_get_contents(__DIR__ . '/../VERSION') ?: '')) ?></span></p>
    </footer>

    <script>
        // Initialise Lucide icons
        if (typeof lucide !== 'undefined') lucide.createIcons();
    </script>
</body>
</html>

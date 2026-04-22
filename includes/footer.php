
    <footer>
        <p>&copy; <?= date('Y') ?> <strong>KCALS</strong> &mdash; <?= __('footer_text') ?> &bull; Created with love by <a href="https://activeweb.gr" target="_blank" rel="noopener noreferrer">activeweb.gr</a> &bull; <span class="footer-version"><?= htmlspecialchars(trim(@file_get_contents(__DIR__ . '/../VERSION') ?: '')) ?></span></p>
    </footer>

    <script>
        // Initialise Lucide icons
        if (typeof lucide !== 'undefined') lucide.createIcons();
    </script>
</body>
</html>

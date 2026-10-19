</main>

<?php if (empty($hideFooter)): ?>
    <footer class="site-footer">
        <div class="container site-footer__inner">
            <span>&copy; <?= date('Y') ?> <?= e(app_name()) ?></span>
            <nav class="site-footer__links">
                <a href="listings.php">Browse</a>
                <a href="requests.php">Requests</a>
                <a href="create_item.php">Sell an item</a>
            </nav>
        </div>
    </footer>
<?php endif; ?>

<dialog class="dialog" id="confirm-dialog">
    <form method="dialog" class="dialog__body">
        <h2 class="dialog__title" data-confirm-title>Are you sure?</h2>
        <p class="dialog__text" data-confirm-text></p>
        <div class="dialog__actions">
            <button class="btn btn--ghost" value="cancel">Cancel</button>
            <button class="btn btn--danger" value="confirm" data-confirm-button>Confirm</button>
        </div>
    </form>
</dialog>

<script src="assets/js/app.js"></script>
<?php foreach ($pageScripts ?? [] as $script): ?>
    <script src="<?= e(str_starts_with($script, 'https://') ? $script : 'assets/js/' . $script) ?>"></script>
<?php endforeach; ?>
</body>
</html>

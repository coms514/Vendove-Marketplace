<?php if ($pagination['pages'] > 1): ?>
    <nav class="pagination" aria-label="Pagination">
        <?php if ($pagination['page'] > 1): ?>
            <a class="btn btn--ghost btn--sm" href="?<?= e(current_query(['page' => $pagination['page'] - 1])) ?>">Previous</a>
        <?php endif; ?>

        <span class="pagination__status">Page <?= $pagination['page'] ?> of <?= $pagination['pages'] ?></span>

        <?php if ($pagination['page'] < $pagination['pages']): ?>
            <a class="btn btn--ghost btn--sm" href="?<?= e(current_query(['page' => $pagination['page'] + 1])) ?>">Next</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

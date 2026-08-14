<?php if ($moduleKey === 'tests' && $action === 'edit' && $editRow): ?>
        <?= render_test_builder((int)$editRow['id'], $admin) ?>
    <?php endif; ?>

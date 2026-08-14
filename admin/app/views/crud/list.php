<?php if ($moduleKey === 'leads'): ?>
    <?= render_lead_media_modal() ?>
<?php endif; ?>
<?php $showList = $action === 'list' && !$leadChatOnly; ?>
<?php if ($showList): ?>
    <section class="panel">
        <?= $listHtml ?>
    </section>
<?php endif; ?>
<?php require $crudPublicDir . '/../app/views/layouts/footer.php'; ?>

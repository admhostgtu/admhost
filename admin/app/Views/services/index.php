<?php ob_start(); ?>

<h1>Services clients</h1>

<div class="table-wrap">
<table class="admin-table">
    <thead>
        <tr><th>ID</th><th>Client</th><th>Service</th><th>Type</th><th>Statut</th><th>Sous-domaine</th><th>URL</th></tr>
    </thead>
    <tbody>
        <?php foreach ($services as $s): ?>
            <tr>
                <td><?= e((string) ($s['id'] ?? '')) ?></td>
                <td><?= e($s['user_name'] ?? '') ?><br><small><?= e($s['user_email'] ?? '') ?></small></td>
                <td><?= e($s['name'] ?? '') ?></td>
                <td><?= e($s['type'] ?? '') ?></td>
                <td><span class="badge"><?= e($s['status'] ?? '') ?></span></td>
                <td><?= e($s['subdomain'] ?? '—') ?></td>
                <td><?= e($s['web_url'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';

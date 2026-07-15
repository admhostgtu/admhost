<?php ob_start(); ?>

<div class="page-header">
    <h1>Plans & tarifs</h1>
    <a href="<?= e(admin_path('plans/create')) ?>" class="btn btn-primary">+ Nouveau plan</a>
</div>

<div class="table-wrap">
<table class="admin-table">
    <thead>
        <tr><th>Slug</th><th>Nom</th><th>Type</th><th>Mensuel</th><th>Annuel</th><th>Stripe (M/A)</th><th></th></tr>
    </thead>
    <tbody>
        <?php foreach ($plans as $plan): ?>
            <tr>
                <td><?= e($plan['slug'] ?? '') ?></td>
                <td><?= e($plan['name'] ?? '') ?></td>
                <td><?= e($plan['type'] ?? '') ?></td>
                <td><?= e((string) ($plan['price_monthly'] ?? 0)) ?> €</td>
                <td><?= e((string) ($plan['price_annual'] ?? 0)) ?> €</td>
                <td><small><?= e($plan['stripe_price_id'] ? 'M ✓' : 'M —') ?> / <?= e($plan['stripe_price_id_annual'] ?? '') ? 'A ✓' : 'A —' ?></small></td>
                <td><a href="<?= e(admin_path('plans/' . $plan['id'] . '/edit')) ?>">Modifier</a></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';

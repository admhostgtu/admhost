<?php ob_start(); ?>

<h1>Abonnements</h1>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Utilisateur</th>
            <th>Plan</th>
            <th>Montant</th>
            <th>Statut</th>
            <th>Période fin</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($subscriptions)): ?>
            <tr><td colspan="6">Aucun abonnement.</td></tr>
        <?php else: ?>
            <?php foreach ($subscriptions as $sub): ?>
                <tr>
                    <td><?= e((string) $sub['id']) ?></td>
                    <td><?= e($sub['user_name'] ?? '') ?> <small>(<?= e($sub['user_email'] ?? '') ?>)</small></td>
                    <td><?= e($sub['plan_name'] ?? $sub['plan_slug'] ?? '') ?></td>
                    <td><?= e((string) ($sub['amount'] ?? 0)) ?> <?= e($sub['currency'] ?? 'EUR') ?></td>
                    <td><span class="badge badge-<?= e($sub['status'] ?? '') ?>"><?= e($sub['status'] ?? '') ?></span></td>
                    <td><?= e($sub['current_period_end'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';

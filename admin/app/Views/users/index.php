<?php ob_start(); ?>

<div class="page-header">
    <h1>Utilisateurs</h1>
    <a href="<?= e(admin_path('users/create')) ?>" class="btn btn-primary">+ Créer</a>
</div>

<div class="table-wrap">
<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Email</th>
            <th>Rôle</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($users)): ?>
            <tr><td colspan="6">Aucun utilisateur.</td></tr>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= e((string) $user['id']) ?></td>
                    <td><?= e($user['name'] ?? '') ?></td>
                    <td><?= e($user['email'] ?? '') ?></td>
                    <td><span class="badge"><?= e($user['role'] ?? 'user') ?></span></td>
                    <td><span class="badge badge-<?= e($user['status'] ?? 'active') ?>"><?= e($user['status'] ?? '') ?></span></td>
                    <td class="actions">
                        <a href="<?= e(admin_path('users/' . (string) $user['id'] . '/assign')) ?>">+ Service</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>

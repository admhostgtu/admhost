<?php
/**
 * Vue admin : détail d'un utilisateur.
 */
ob_start();
?>

<?php if ($user): ?>
    <div class="user-detail">
        <h3><?= e($user['name'] ?? '') ?></h3>
        <p><strong>Email :</strong> <?= e($user['email'] ?? '') ?></p>
        <p><strong>ID :</strong> <?= e((string) $user['id']) ?></p>
        <p><strong>Créé le :</strong> <?= e($user['created_at'] ?? '') ?></p>
        <a href="/admin/users" class="btn btn-outline">← Retour</a>
    </div>
<?php else: ?>
    <p>Utilisateur introuvable.</p>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';

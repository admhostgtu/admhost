<?php ob_start(); ?>

<h1>Créer un utilisateur</h1>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST" action="<?= e(admin_path('users/create')) ?>" class="settings-form">
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="name">Nom</label>
        <input type="text" id="name" name="name" required>
    </div>
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>
    </div>
    <div class="form-group">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required minlength="8">
    </div>
    <div class="form-group">
        <label for="role">Rôle</label>
        <select id="role" name="role">
            <option value="user">Utilisateur</option>
            <option value="admin">Admin</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Créer</button>
    <a href="<?= e(admin_path('users')) ?>" class="btn btn-outline">Annuler</a>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';

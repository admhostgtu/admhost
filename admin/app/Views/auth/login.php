<?php
/**
 * Vue admin : formulaire de connexion administrateur.
 */
ob_start();
?>

<div class="admin-login">
    <form method="POST" action="/admin/login" class="login-card">
        <h1>Administration</h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="form-group">
            <label for="email">Email admin</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Connexion</button>
    </form>
</div>

<?php
$content = ob_get_clean();
$admin = null; // Pas de sidebar sur la page login
include __DIR__ . '/../layouts/admin.php';

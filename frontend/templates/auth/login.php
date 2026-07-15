<?php
/**
 * Template : formulaire de connexion.
 */
ob_start();
?>

<section class="auth-form">
    <h1>Connexion</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/login">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
    </form>

    <p class="auth-link">Pas encore de compte ? <a href="/register">S'inscrire</a></p>
</section>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';

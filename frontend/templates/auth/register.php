<?php
/**
 * Template : formulaire d'inscription.
 */
ob_start();
?>

<section class="auth-form">
    <h1>Inscription</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/register">
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
        <button type="submit" class="btn btn-primary btn-block">Créer mon compte</button>
    </form>

    <p class="auth-link">Déjà un compte ? <a href="/login">Se connecter</a></p>
</section>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';

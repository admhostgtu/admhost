<div class="dashboard-header"><h1>Paramètres</h1></div>

<?php if (!empty($success)): ?>
    <div class="alert-success">Modifications enregistrées.</div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="settings-grid">
    <section class="auth-form" style="margin:0">
        <h2>Profil</h2>
        <form method="POST" action="/settings/profile">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="name">Nom</label>
                <input type="text" id="name" name="name" value="<?= e($user['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" value="<?= e($user['email'] ?? '') ?>" disabled>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
    </section>

    <section class="auth-form" style="margin:0">
        <h2>Mot de passe</h2>
        <form method="POST" action="/settings/password">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="current_password">Mot de passe actuel</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">Nouveau mot de passe</label>
                <input type="password" id="new_password" name="new_password" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
        </form>
    </section>
</div>

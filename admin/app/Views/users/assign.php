<?php ob_start(); ?>

<h1>Attribuer un service</h1>

<?php if ($user): ?>
    <p>Utilisateur : <strong><?= e($user['name']) ?></strong> (<?= e($user['email']) ?>)</p>

    <form method="POST" action="<?= e(admin_path('users/' . $userId . '/assign')) ?>" class="settings-form">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="name">Nom du service</label>
            <input type="text" id="name" name="name" value="Hébergement" required>
        </div>
        <div class="form-group">
            <label for="type">Type</label>
            <select id="type" name="type">
                <option value="hosting">Hébergement</option>
                <option value="email">Email</option>
                <option value="vps">VPS</option>
            </select>
        </div>
        <div class="form-group">
            <label for="plan_id">Plan ID (optionnel)</label>
            <input type="number" id="plan_id" name="plan_id" placeholder="1 = Starter, 2 = Pro, 3 = Business">
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="provision" value="1" checked>
                Provisionner automatiquement (Linux + SSH + SMTP)
            </label>
        </div>
        <button type="submit" class="btn btn-primary">Attribuer</button>
        <a href="<?= e(admin_path('users')) ?>" class="btn btn-outline">Annuler</a>
    </form>
<?php else: ?>
    <p>Utilisateur introuvable.</p>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';

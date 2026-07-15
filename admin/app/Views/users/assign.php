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
            <label for="type">Type de service</label>
            <select id="type" name="type">
                <option value="hosting">Hébergement (Linux + SSH + SMTP)</option>
                <option value="email">Email / SMTP uniquement</option>
                <option value="vps">VPS / SSH uniquement</option>
                <option value="docker">Docker (conteneur isolé + sous-domaine)</option>
            </select>
        </div>
        <div class="form-group">
            <label for="plan_id">Plan associé</label>
            <select id="plan_id" name="plan_id">
                <option value="">— Aucun —</option>
                <?php foreach ($plans ?? [] as $plan): ?>
                    <option value="<?= e((string) $plan['id']) ?>"><?= e($plan['name'] ?? '') ?> (<?= e($plan['type'] ?? '') ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="provision" value="1" checked>
                Provisionner automatiquement
            </label>
        </div>
        <button type="submit" class="btn btn-primary">Attribuer & provisionner</button>
        <a href="<?= e(admin_path('users')) ?>" class="btn btn-outline">Annuler</a>
    </form>
<?php else: ?>
    <p>Utilisateur introuvable.</p>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';

<?php ob_start(); ?>
<?php if (!$plan): ?><p>Plan introuvable.</p><?php else: ?>
<h1>Modifier : <?= e($plan['name'] ?? '') ?></h1>
<form method="POST" class="settings-form">
    <?= csrf_field() ?>
    <div class="form-group"><label>Nom</label><input name="name" value="<?= e($plan['name'] ?? '') ?>" required></div>
    <div class="form-group"><label>Description</label><textarea name="description" rows="2"><?= e($plan['description'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Type</label>
        <select name="type">
            <?php foreach (['hosting','email','vps','docker'] as $t): ?>
                <option value="<?= $t ?>" <?= ($plan['type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><label>Prix mensuel</label><input name="price_monthly" type="number" step="0.01" value="<?= e((string) ($plan['price_monthly'] ?? 0)) ?>"></div>
    <div class="form-group"><label>Prix annuel</label><input name="price_annual" type="number" step="0.01" value="<?= e((string) ($plan['price_annual'] ?? 0)) ?>"></div>
    <div class="form-group"><label>Stripe mensuel</label><input name="stripe_price_id" value="<?= e($plan['stripe_price_id'] ?? '') ?>"></div>
    <div class="form-group"><label>Stripe annuel</label><input name="stripe_price_id_annual" value="<?= e($plan['stripe_price_id_annual'] ?? '') ?>"></div>
    <div class="form-group"><label>Fonctionnalités</label><textarea name="features" rows="4"><?php
        $f = $plan['features'] ?? '[]';
        if (is_string($f)) { $f = json_decode($f, true) ?: []; }
        echo e(implode("\n", $f));
    ?></textarea></div>
    <button type="submit" class="btn btn-primary">Enregistrer</button>
</form>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';

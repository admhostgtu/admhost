<?php ob_start(); ?>

<h1>Nouveau plan</h1>
<?php if (!empty($error)): ?><div class="alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="POST" class="settings-form">
    <?= csrf_field() ?>
    <div class="form-group"><label>Slug</label><input name="slug" required placeholder="pro"></div>
    <div class="form-group"><label>Nom</label><input name="name" required></div>
    <div class="form-group"><label>Description</label><textarea name="description" rows="2"></textarea></div>
    <div class="form-group"><label>Type</label>
        <select name="type">
            <option value="hosting">Hébergement</option>
            <option value="email">Email</option>
            <option value="vps">VPS / SSH</option>
            <option value="docker">Docker</option>
        </select>
    </div>
    <div class="form-group"><label>Prix mensuel (€)</label><input name="price_monthly" type="number" step="0.01" required></div>
    <div class="form-group"><label>Prix annuel (€)</label><input name="price_annual" type="number" step="0.01"></div>
    <div class="form-group"><label>Stripe Price ID (mensuel)</label><input name="stripe_price_id" placeholder="price_..."></div>
    <div class="form-group"><label>Stripe Price ID (annuel)</label><input name="stripe_price_id_annual" placeholder="price_..."></div>
    <div class="form-group"><label>Fonctionnalités (une par ligne)</label><textarea name="features" rows="4"></textarea></div>
    <button type="submit" class="btn btn-primary">Créer</button>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';

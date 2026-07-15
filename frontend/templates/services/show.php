<?php
$meta = [];
if (!empty($service['metadata'])) {
    $meta = is_string($service['metadata']) ? (json_decode($service['metadata'], true) ?: []) : $service['metadata'];
}
?>
<div class="dashboard-header">
    <h1><?= e($service['name'] ?? 'Service') ?></h1>
    <a href="/dashboard" class="btn btn-outline">Retour</a>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-success">Configuration enregistrée.</div>
<?php endif; ?>

<div class="service-card">
    <p><strong>Type :</strong> <?= e($service['type'] ?? '') ?> · <strong>Statut :</strong> <?= e($service['status'] ?? '') ?></p>
    <?php if (!empty($service['web_url'])): ?>
        <p><strong>URL :</strong> <a href="<?= e($service['web_url']) ?>" target="_blank"><?= e($service['web_url']) ?></a></p>
    <?php endif; ?>
    <?php if (!empty($service['subdomain'])): ?>
        <p><strong>Sous-domaine :</strong> <?= e($service['subdomain']) ?>.clients.admhost.fr</p>
    <?php endif; ?>
</div>

<section class="dashboard-section">
    <h2>Personnalisation</h2>
    <form method="POST" action="/services/<?= e((string) $service['id']) ?>/config" class="auth-form" style="max-width:520px;margin:0">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="site_title">Titre du site / service</label>
            <input type="text" id="site_title" name="site_title" value="<?= e($meta['site_title'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="php_version">Version PHP (hébergement)</label>
            <select id="php_version" name="php_version">
                <?php foreach (['8.4', '8.3', '8.2'] as $v): ?>
                    <option value="<?= $v ?>" <?= ($meta['php_version'] ?? '8.4') === $v ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="3"><?= e($meta['notes'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Enregistrer la configuration</button>
    </form>
</section>

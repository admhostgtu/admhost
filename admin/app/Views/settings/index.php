<?php
/**
 * Vue admin : page des paramètres de l'application.
 */
ob_start();
?>

<form method="POST" action="/admin/settings" class="settings-form">
    <div class="form-group">
        <label for="app_name">Nom de l'application</label>
        <input type="text" id="app_name" name="app_name" value="<?= e($settings['app_name'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label for="app_env">Environnement</label>
        <input type="text" id="app_env" name="app_env" value="<?= e($settings['app_env'] ?? '') ?>" readonly>
    </div>
    <div class="form-group">
        <label for="api_url">URL de l'API</label>
        <input type="text" id="api_url" name="api_url" value="<?= e($settings['api_url'] ?? '') ?>" readonly>
    </div>
    <button type="submit" class="btn btn-primary">Sauvegarder</button>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';

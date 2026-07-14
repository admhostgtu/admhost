<?php ob_start(); ?>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Utilisateurs</h3>
        <p class="stat-value"><?= e((string) ($userCount ?? 0)) ?></p>
    </div>
    <div class="stat-card">
        <h3>Abonnements actifs</h3>
        <p class="stat-value stat-ok"><?= e((string) ($activeSubCount ?? 0)) ?></p>
    </div>
    <div class="stat-card">
        <h3>Total abonnements</h3>
        <p class="stat-value"><?= e((string) ($subCount ?? 0)) ?></p>
    </div>
    <div class="stat-card">
        <h3>Services</h3>
        <p class="stat-value"><?= e((string) ($serviceCount ?? 0)) ?></p>
    </div>
    <div class="stat-card">
        <h3>API</h3>
        <p class="stat-value stat-ok"><?= e($health['status'] ?? 'N/A') ?></p>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';

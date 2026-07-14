<?php
/**
 * Template : page d'accueil du SaaS.
 * Utilise le layout principal via ob_start/ob_get_clean.
 */
ob_start();
?>

<section class="hero">
    <h1>Bienvenue sur <?= e($appName) ?></h1>
    <p class="hero-subtitle">La solution d'hébergement simple et performante pour votre SaaS.</p>
    <div class="hero-actions">
        <a href="/register" class="btn btn-primary btn-lg">Commencer gratuitement</a>
        <a href="/pricing" class="btn btn-outline btn-lg">Voir les tarifs</a>
    </div>
</section>

<section class="features">
    <div class="feature-card">
        <h3>⚡ Rapide</h3>
        <p>Infrastructure optimisée pour des performances maximales.</p>
    </div>
    <div class="feature-card">
        <h3>🔒 Sécurisé</h3>
        <p>SSL, sauvegardes automatiques et monitoring 24/7.</p>
    </div>
    <div class="feature-card">
        <h3>📈 Scalable</h3>
        <p>Évoluez sans limite selon la croissance de votre activité.</p>
    </div>
</section>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';

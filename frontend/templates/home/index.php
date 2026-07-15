<?php
/**
 * Template : page d'accueil du SaaS.
 */
ob_start();
?>

<section class="hero">
    <span class="hero-badge">Hébergement managé</span>
    <h1>Infrastructure fiable pour vos projets web</h1>
    <p class="hero-subtitle">AdmHost centralise l'hébergement, l'accès SSH et la messagerie dans un espace client unique. Déploiement rapide, support réactif.</p>
    <div class="hero-actions">
        <a href="<?= e(console_url('/register')) ?>" class="btn btn-primary btn-lg">Créer un compte</a>
        <a href="<?= e(vitrine_url('/pricing')) ?>" class="btn btn-outline btn-lg">Voir les offres</a>
    </div>
</section>

<section class="features">
    <div class="feature-card">
        <div class="feature-icon">01</div>
        <h3>Performance</h3>
        <p>Serveurs optimisés, PHP 8.4 et bases de données locales pour des temps de réponse constants.</p>
    </div>
    <div class="feature-card">
        <div class="feature-icon">02</div>
        <h3>Sécurité</h3>
        <p>SSL automatique, sessions sécurisées, chiffrement des identifiants et surveillance des accès.</p>
    </div>
    <div class="feature-card">
        <div class="feature-icon">03</div>
        <h3>Évolutivité</h3>
        <p>Des offres adaptées à chaque étape, de la première mise en ligne à la montée en charge.</p>
    </div>
</section>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';

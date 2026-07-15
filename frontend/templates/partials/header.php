<?php
/**
 * Partial : en-tête navigation (vitrine ou console selon APP_SITE).
 */
$site = app_site();
$appName = env('APP_NAME', 'AdmHost');
?>
<header class="site-header">
    <nav class="nav" aria-label="Navigation principale">
        <a href="<?= e($site === 'vitrine' ? '/' : vitrine_url('/')) ?>" class="nav-brand">
            <?= e($appName) ?><span>.</span>
        </a>
        <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="main-nav" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <ul class="nav-links" id="main-nav">
            <li><a href="<?= e(vitrine_url('/')) ?>">Accueil</a></li>
            <li><a href="<?= e(vitrine_url('/pricing')) ?>">Tarifs</a></li>
            <?php if ($site === 'console' && !empty($_SESSION['user'])): ?>
                <li><a href="/dashboard">Mon espace</a></li>
                <li><a href="/logout">Déconnexion</a></li>
            <?php else: ?>
                <li><a href="<?= e(console_url('/login')) ?>">Connexion</a></li>
                <li><a href="<?= e(console_url('/register')) ?>" class="btn btn-primary">Inscription</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

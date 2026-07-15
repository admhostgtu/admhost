<?php
/**
 * Partial : en-tête navigation (vitrine ou console selon APP_SITE).
 */
$site = app_site();
?>
<header class="site-header">
    <nav class="nav">
        <a href="<?= e($site === 'vitrine' ? '/' : vitrine_url('/')) ?>" class="nav-brand"><?= e(env('APP_NAME', 'AdmHost')) ?></a>
        <ul class="nav-links">
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

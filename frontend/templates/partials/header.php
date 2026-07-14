<?php
/**
 * Partial : en-tête de navigation du site public.
 */
?>
<header class="site-header">
    <nav class="nav">
        <a href="/" class="nav-brand"><?= e(env('APP_NAME', 'AdmHost')) ?></a>
        <ul class="nav-links">
            <li><a href="/">Accueil</a></li>
            <li><a href="/pricing">Tarifs</a></li>
            <?php if (!empty($_SESSION['user'])): ?>
                <li><a href="/dashboard">Mon espace</a></li>
                <li><a href="/logout">Déconnexion</a></li>
            <?php else: ?>
                <li><a href="/login">Connexion</a></li>
                <li><a href="/register" class="btn btn-primary">Inscription</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

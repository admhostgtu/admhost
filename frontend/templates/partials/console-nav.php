<?php
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<aside class="console-sidebar" id="console-sidebar">
    <div class="console-brand">AdmHost</div>
    <nav class="console-nav">
        <a href="/dashboard" class="<?= str_starts_with($current, '/dashboard') ? 'active' : '' ?>">Tableau de bord</a>
        <a href="/billing" class="<?= str_starts_with($current, '/billing') ? 'active' : '' ?>">Facturation</a>
        <a href="/settings" class="<?= str_starts_with($current, '/settings') ? 'active' : '' ?>">Paramètres</a>
        <a href="<?= e(vitrine_url('/pricing')) ?>">Nouvelle offre</a>
        <a href="/logout" class="console-nav-danger">Déconnexion</a>
    </nav>
</aside>
<button type="button" class="console-nav-toggle" id="console-nav-toggle" aria-label="Menu">☰</button>

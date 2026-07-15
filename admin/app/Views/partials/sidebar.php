<?php
/**
 * Partial : sidebar admin.
 */
?>
<aside class="sidebar">
    <div class="sidebar-brand">AdmHost Admin</div>
    <nav class="sidebar-nav">
        <a href="<?= e(admin_path('dashboard')) ?>" class="sidebar-link">Dashboard</a>
        <a href="<?= e(admin_path('users')) ?>" class="sidebar-link">Utilisateurs</a>
        <a href="<?= e(admin_path('subscriptions')) ?>" class="sidebar-link">Abonnements</a>
        <a href="<?= e(admin_path('settings')) ?>" class="sidebar-link">Paramètres</a>
        <a href="<?= e(admin_path('logout')) ?>" class="sidebar-link sidebar-link-danger">Déconnexion</a>
    </nav>
</aside>

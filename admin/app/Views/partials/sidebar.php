<?php
/**
 * Partial : sidebar admin.
 */
?>
<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
<aside class="sidebar" id="admin-sidebar">
    <div class="sidebar-brand">AdmHost <span>Admin</span></div>
    <nav class="sidebar-nav" aria-label="Navigation admin">
        <a href="<?= e(admin_path('dashboard')) ?>" class="sidebar-link">Dashboard</a>
        <a href="<?= e(admin_path('users')) ?>" class="sidebar-link">Utilisateurs</a>
        <a href="<?= e(admin_path('subscriptions')) ?>" class="sidebar-link">Abonnements</a>
        <a href="<?= e(admin_path('settings')) ?>" class="sidebar-link">Paramètres</a>
        <a href="<?= e(admin_path('logout')) ?>" class="sidebar-link sidebar-link-danger">Déconnexion</a>
    </nav>
</aside>

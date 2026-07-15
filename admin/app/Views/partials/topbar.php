<?php
/**
 * Partial : barre supérieure admin avec info utilisateur.
 */
?>
<div class="topbar">
    <div style="display:flex;align-items:center;gap:0.75rem;">
        <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-label="Ouvrir le menu">☰</button>
        <span class="topbar-title"><?= e($title ?? '') ?></span>
    </div>
    <span class="topbar-user"><?= e($admin['name'] ?? 'Admin') ?></span>
</div>

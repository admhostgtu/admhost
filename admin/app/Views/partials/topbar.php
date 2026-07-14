<?php
/**
 * Partial : barre supérieure admin avec info utilisateur.
 */
?>
<div class="topbar">
    <h2><?= e($title ?? '') ?></h2>
    <span class="topbar-user"><?= e($admin['name'] ?? 'Admin') ?></span>
</div>

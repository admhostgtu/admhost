<?php
/**
 * Template : page des tarifs / plans d'abonnement.
 */
ob_start();
?>

<section class="pricing">
    <h1>Nos tarifs</h1>
    <div class="pricing-grid">
        <?php foreach ($plans as $plan): ?>
            <div class="pricing-card">
                <h3><?= e($plan['name']) ?></h3>
                <p class="price"><?= e((string) $plan['price']) ?> €<span>/mois</span></p>
                <ul>
                    <?php foreach ($plan['features'] as $feature): ?>
                        <li><?= e($feature) ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="/register" class="btn btn-primary">Choisir</a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';

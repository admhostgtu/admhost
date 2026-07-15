<?php
/**
 * Template : page des tarifs / plans d'abonnement.
 */
ob_start();
?>

<section class="pricing">
    <h1>Nos offres</h1>
    <p class="pricing-intro">Des formules claires, sans engagement caché. Choisissez celle qui correspond à votre usage.</p>
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
                <a href="<?= e(console_url('/register')) ?>" class="btn btn-primary btn-block">Choisir cette offre</a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';

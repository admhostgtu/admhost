<?php
$isVitrine = (app_site() === 'vitrine');
if ($isVitrine) {
    ob_start();
}
?>

<section class="pricing">
    <h1>Nos offres</h1>
    <p class="pricing-intro">Abonnement mensuel ou annuel (-17 %). Paiement sécurisé par Stripe.</p>

    <?php if (!empty($error)): ?>
        <div class="alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="pricing-grid">
        <?php foreach ($plans as $plan): ?>
            <?php
            $features = $plan['features'] ?? '[]';
            if (is_string($features)) {
                $features = json_decode($features, true) ?: [];
            }
            $monthly = (float) ($plan['price_monthly'] ?? 0);
            $annual  = (float) ($plan['price_annual'] ?? ($monthly * 10));
            ?>
            <div class="pricing-card">
                <h3><?= e($plan['name'] ?? '') ?></h3>
                <p class="price"><?= e(number_format($monthly, 0, ',', ' ')) ?> €<span>/mois</span></p>
                <p class="text-muted" style="font-size:0.85rem;margin-bottom:1rem">ou <?= e(number_format($annual, 0, ',', ' ')) ?> €/an</p>
                <ul>
                    <?php foreach ($features as $feature): ?>
                        <li><?= e(is_string($feature) ? $feature : '') ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!empty($_SESSION['user'])): ?>
                    <form method="POST" action="<?= e(console_url('/subscribe')) ?>" style="margin-bottom:0.5rem">
                        <?= csrf_field() ?>
                        <input type="hidden" name="plan_slug" value="<?= e($plan['slug'] ?? '') ?>">
                        <input type="hidden" name="interval" value="monthly">
                        <button type="submit" class="btn btn-primary btn-block">Mensuel</button>
                    </form>
                    <form method="POST" action="<?= e(console_url('/subscribe')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="plan_slug" value="<?= e($plan['slug'] ?? '') ?>">
                        <input type="hidden" name="interval" value="annual">
                        <button type="submit" class="btn btn-outline btn-block">Annuel</button>
                    </form>
                <?php else: ?>
                    <a href="<?= e(console_url('/register')) ?>" class="btn btn-primary btn-block">Créer un compte</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php
if ($isVitrine) {
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/main.php';
}

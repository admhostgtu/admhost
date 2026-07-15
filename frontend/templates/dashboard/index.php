<?php ob_start(); ?>

<div class="dashboard-header">
    <div>
        <h1>Bonjour, <?= e($user['name'] ?? 'Utilisateur') ?></h1>
        <p class="text-muted">Bienvenue dans votre espace client.</p>
    </div>
    <a href="/logout" class="btn btn-outline">Déconnexion</a>
</div>

<!-- Abonnement -->
<section class="dashboard-section">
    <h2>Mon abonnement</h2>
    <?php if ($subscription): ?>
        <div class="sub-card sub-<?= e($subscription['status'] ?? 'active') ?>">
            <div class="sub-info">
                <span class="sub-plan"><?= e($subscription['plan_name'] ?? $subscription['plan_slug'] ?? '—') ?></span>
                <span class="sub-amount"><?= e((string) ($subscription['amount'] ?? 0)) ?> <?= e($subscription['currency'] ?? 'EUR') ?>/mois</span>
            </div>
            <div class="sub-status">
                <span class="status-badge status-<?= e($subscription['status'] ?? '') ?>">
                    <?= e(ucfirst($subscription['status'] ?? 'inconnu')) ?>
                </span>
                <?php if (!empty($subscription['current_period_end'])): ?>
                    <small>Renouvellement : <?= e($subscription['current_period_end']) ?></small>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>Aucun abonnement actif.</p>
            <a href="<?= e(vitrine_url('/pricing')) ?>" class="btn btn-primary">Voir les offres</a>
        </div>
    <?php endif; ?>
</section>

<!-- Services actifs -->
<section class="dashboard-section">
    <h2>Mes services</h2>
    <?php if (empty($services)): ?>
        <div class="empty-state">
            <p>Aucun service actif pour le moment.</p>
        </div>
    <?php else: ?>
        <?php foreach ($services as $service): ?>
            <div class="service-card">
                <div class="service-header">
                    <h3><?= e($service['name'] ?? 'Service') ?></h3>
                    <span class="status-badge status-<?= e($service['status'] ?? '') ?>"><?= e($service['status'] ?? '') ?></span>
                </div>

                <?php if ($service['status'] === 'active'): ?>
                    <div class="credentials-grid">
                        <?php if (!empty($service['ssh_host'])): ?>
                            <div class="cred-block">
                                <h4>Accès SSH</h4>
                                <dl>
                                    <dt>Hôte</dt><dd><?= e($service['ssh_host']) ?></dd>
                                    <dt>Port</dt><dd><?= e((string) ($service['ssh_port'] ?? 22)) ?></dd>
                                    <dt>Utilisateur</dt><dd><code><?= e($service['ssh_username'] ?? '') ?></code></dd>
                                    <dt>Mot de passe</dt><dd><code class="secret"><?= e($service['ssh_password'] ?? '—') ?></code></dd>
                                </dl>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($service['smtp_host'])): ?>
                            <div class="cred-block">
                                <h4>Accès SMTP</h4>
                                <dl>
                                    <dt>Serveur</dt><dd><?= e($service['smtp_host']) ?></dd>
                                    <dt>Port</dt><dd><?= e((string) ($service['smtp_port'] ?? 587)) ?></dd>
                                    <dt>Utilisateur</dt><dd><code><?= e($service['smtp_username'] ?? '') ?></code></dd>
                                    <dt>Mot de passe</dt><dd><code class="secret"><?= e($service['smtp_password'] ?? '—') ?></code></dd>
                                    <dt>Chiffrement</dt><dd><?= e(strtoupper($service['smtp_encryption'] ?? 'TLS')) ?></dd>
                                </dl>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Service en cours de provisionnement…</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';

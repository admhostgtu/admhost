<div class="dashboard-header">
    <div>
        <h1>Bonjour, <?= e($user['name'] ?? 'Utilisateur') ?></h1>
        <p class="text-muted">Gérez vos services, abonnements et accès.</p>
    </div>
</div>

<div class="stats-grid dashboard-stats">
    <div class="stat-card">
        <p>Services actifs</p>
        <div class="stat-value"><?= count(array_filter($services, fn($s) => ($s['status'] ?? '') === 'active')) ?></div>
    </div>
    <div class="stat-card">
        <p>Abonnement</p>
        <div class="stat-value stat-sm"><?= e($subscription['plan_name'] ?? $subscription['plan_slug'] ?? '—') ?></div>
    </div>
</div>

<section class="dashboard-section">
    <h2>Mon abonnement</h2>
    <?php if ($subscription): ?>
        <div class="sub-card sub-<?= e($subscription['status'] ?? 'active') ?>">
            <div class="sub-info">
                <span class="sub-plan"><?= e($subscription['plan_name'] ?? $subscription['plan_slug'] ?? '—') ?></span>
                <span class="sub-amount"><?= e((string) ($subscription['amount'] ?? 0)) ?> <?= e($subscription['currency'] ?? 'EUR') ?>/mois</span>
            </div>
            <div class="sub-status">
                <span class="status-badge status-<?= e($subscription['status'] ?? '') ?>"><?= e(ucfirst($subscription['status'] ?? '')) ?></span>
                <?php if (!empty($subscription['current_period_end'])): ?>
                    <small>Renouvellement : <?= e($subscription['current_period_end']) ?></small>
                <?php endif; ?>
            </div>
        </div>
        <p style="margin-top:0.75rem"><a href="/billing" class="btn btn-outline">Gérer la facturation</a></p>
    <?php else: ?>
        <div class="empty-state">
            <p>Aucun abonnement actif.</p>
            <a href="/pricing" class="btn btn-primary">Voir les offres</a>
        </div>
    <?php endif; ?>
</section>

<section class="dashboard-section">
    <h2>Mes services</h2>
    <?php if (empty($services)): ?>
        <div class="empty-state"><p>Aucun service pour le moment.</p></div>
    <?php else: ?>
        <?php foreach ($services as $service): ?>
            <div class="service-card">
                <div class="service-header">
                    <div>
                        <h3><?= e($service['name'] ?? 'Service') ?></h3>
                        <span class="text-muted"><?= e(strtoupper($service['type'] ?? '')) ?></span>
                        <?php if (!empty($service['subdomain'])): ?>
                            <br><small class="text-muted"><?= e($service['subdomain']) ?>.clients.admhost.fr</small>
                        <?php endif; ?>
                    </div>
                    <span class="status-badge status-<?= e($service['status'] ?? '') ?>"><?= e($service['status'] ?? '') ?></span>
                </div>

                <?php if ($service['status'] === 'active'): ?>
                    <div class="credentials-grid">
                        <?php if (!empty($service['web_url'])): ?>
                            <div class="cred-block">
                                <h4>Accès web</h4>
                                <p><a href="<?= e($service['web_url']) ?>" target="_blank" rel="noopener"><?= e($service['web_url']) ?></a></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($service['ssh_host'])): ?>
                            <div class="cred-block">
                                <h4>SSH</h4>
                                <dl>
                                    <dt>Hôte</dt><dd><?= e($service['ssh_host']) ?></dd>
                                    <dt>Port</dt><dd><?= e((string) ($service['ssh_port'] ?? 22)) ?></dd>
                                    <dt>User</dt><dd><code><?= e($service['ssh_username'] ?? '') ?></code></dd>
                                    <dt>Pass</dt><dd><code class="secret"><?= e($service['ssh_password'] ?? '—') ?></code></dd>
                                </dl>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($service['smtp_host'])): ?>
                            <div class="cred-block">
                                <h4>SMTP / Email</h4>
                                <dl>
                                    <dt>Serveur</dt><dd><?= e($service['smtp_host']) ?></dd>
                                    <dt>Port</dt><dd><?= e((string) ($service['smtp_port'] ?? 587)) ?></dd>
                                    <dt>User</dt><dd><code><?= e($service['smtp_username'] ?? '') ?></code></dd>
                                    <dt>Pass</dt><dd><code class="secret"><?= e($service['smtp_password'] ?? '—') ?></code></dd>
                                </dl>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($service['docker_container_id'])): ?>
                            <div class="cred-block">
                                <h4>Docker</h4>
                                <dl>
                                    <dt>Conteneur</dt><dd><code><?= e($service['docker_container_id']) ?></code></dd>
                                    <dt>Image</dt><dd><?= e($service['docker_image'] ?? '—') ?></dd>
                                </dl>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p style="margin-top:1rem"><a href="/services/<?= e((string) $service['id']) ?>" class="btn btn-outline">Configurer</a></p>
                <?php else: ?>
                    <p class="text-muted">Provisionnement en cours…</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

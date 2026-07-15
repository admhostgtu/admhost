<div class="dashboard-header"><h1>Facturation</h1></div>

<section class="dashboard-section">
    <h2>Abonnement actuel</h2>
    <?php if ($subscription): ?>
        <div class="sub-card">
            <div>
                <strong><?= e($subscription['plan_name'] ?? $subscription['plan_slug'] ?? '') ?></strong>
                — <?= e((string) ($subscription['amount'] ?? 0)) ?> <?= e($subscription['currency'] ?? 'EUR') ?>
            </div>
            <span class="status-badge status-<?= e($subscription['status'] ?? '') ?>"><?= e($subscription['status'] ?? '') ?></span>
        </div>
        <form method="POST" action="/billing/portal" style="margin-top:1rem">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">Portail Stripe (CB, factures, annulation)</button>
        </form>
    <?php else: ?>
        <div class="empty-state"><p>Aucun abonnement.</p><a href="/pricing" class="btn btn-primary">Souscrire</a></div>
    <?php endif; ?>
</section>

<section class="dashboard-section">
    <h2>Historique des paiements</h2>
    <?php if (empty($payments)): ?>
        <p class="text-muted">Aucun paiement enregistré.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Montant</th><th>Statut</th><th>Description</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= e($p['paid_at'] ?? $p['created_at'] ?? '') ?></td>
                        <td><?= e((string) $p['amount']) ?> <?= e($p['currency'] ?? 'EUR') ?></td>
                        <td><?= e($p['status'] ?? '') ?></td>
                        <td><?= e($p['description'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

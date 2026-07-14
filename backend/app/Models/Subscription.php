<?php
/**
 * Modèle Subscription — abonnements Stripe liés aux utilisateurs.
 */

declare(strict_types=1);

namespace Backend\Models;

use Shared\Core\Model;

class Subscription extends Model
{
    protected string $table = 'subscriptions';

    public function findByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT sub.*, sp.name AS plan_name
            FROM {$this->table} sub
            LEFT JOIN service_plans sp ON sp.id = sub.plan_id
            WHERE sub.user_id = :uid
            ORDER BY sub.created_at DESC LIMIT 1
        ");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByStripeId(string $stripeId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} WHERE stripe_subscription_id = :sid LIMIT 1
        ");
        $stmt->execute(['sid' => $stripeId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function allWithUsers(): array
    {
        $stmt = $this->db->query("
            SELECT sub.*, u.name AS user_name, u.email AS user_email, sp.name AS plan_name
            FROM {$this->table} sub
            JOIN users u ON u.id = sub.user_id
            LEFT JOIN service_plans sp ON sp.id = sub.plan_id
            ORDER BY sub.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    public function create(array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table}
            (user_id, service_id, plan_id, stripe_subscription_id, stripe_customer_id, stripe_price_id,
             plan_slug, status, amount, currency, current_period_start, current_period_end)
            VALUES
            (:user_id, :service_id, :plan_id, :stripe_sub_id, :stripe_cust_id, :stripe_price_id,
             :plan_slug, :status, :amount, :currency, :period_start, :period_end)
        ");
        $stmt->execute([
            'user_id'         => $data['user_id'],
            'service_id'      => $data['service_id'] ?? null,
            'plan_id'         => $data['plan_id'] ?? null,
            'stripe_sub_id'   => $data['stripe_subscription_id'] ?? null,
            'stripe_cust_id'  => $data['stripe_customer_id'] ?? null,
            'stripe_price_id' => $data['stripe_price_id'] ?? null,
            'plan_slug'       => $data['plan_slug'] ?? 'starter',
            'status'          => $data['status'] ?? 'active',
            'amount'          => $data['amount'] ?? 0,
            'currency'        => $data['currency'] ?? 'EUR',
            'period_start'    => $data['current_period_start'] ?? null,
            'period_end'      => $data['current_period_end'] ?? null,
        ]);

        return $this->find((int) $this->db->lastInsertId()) ?? [];
    }

    public function updateStatus(int $id, string $status, ?array $extra = null): bool
    {
        $fields = ['status = :status'];
        $params = ['id' => $id, 'status' => $status];

        if ($extra) {
            foreach (['current_period_start', 'current_period_end', 'cancelled_at', 'cancel_at_period_end'] as $key) {
                if (array_key_exists($key, $extra)) {
                    $fields[] = "$key = :$key";
                    $params[$key] = $extra[$key];
                }
            }
        }

        $sql  = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function updateByStripeId(string $stripeId, array $data): bool
    {
        $sub = $this->findByStripeId($stripeId);
        if (!$sub) {
            return false;
        }
        return $this->updateStatus((int) $sub['id'], $data['status'], $data);
    }
}

<?php
/**
 * Modèle Payment — historique des paiements Stripe.
 */

declare(strict_types=1);

namespace Backend\Models;

use Shared\Core\Model;

class Payment extends Model
{
    protected string $table = 'payments';

    public function findByUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} WHERE user_id = :uid ORDER BY created_at DESC
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function findByStripeInvoice(string $invoiceId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} WHERE stripe_invoice_id = :iid LIMIT 1
        ");
        $stmt->execute(['iid' => $invoiceId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table}
            (user_id, subscription_id, stripe_payment_intent_id, stripe_invoice_id, stripe_charge_id,
             amount, currency, status, description, paid_at)
            VALUES
            (:user_id, :sub_id, :pi_id, :inv_id, :charge_id,
             :amount, :currency, :status, :description, :paid_at)
        ");
        $stmt->execute([
            'user_id'     => $data['user_id'],
            'sub_id'      => $data['subscription_id'] ?? null,
            'pi_id'       => $data['stripe_payment_intent_id'] ?? null,
            'inv_id'      => $data['stripe_invoice_id'] ?? null,
            'charge_id'   => $data['stripe_charge_id'] ?? null,
            'amount'      => $data['amount'],
            'currency'    => $data['currency'] ?? 'EUR',
            'status'      => $data['status'] ?? 'pending',
            'description' => $data['description'] ?? null,
            'paid_at'     => $data['paid_at'] ?? null,
        ]);

        return $this->find((int) $this->db->lastInsertId()) ?? [];
    }
}

<?php
/**
 * Modèle pour l'idempotence des webhooks Stripe.
 */

declare(strict_types=1);

namespace Backend\Models;

use Shared\Core\Model;

class WebhookEventModel extends Model
{
    protected string $table = 'stripe_webhook_events';

    /**
     * Réserve atomiquement un événement webhook (idempotence).
     * Retourne false si déjà traité.
     */
    public function tryClaim(string $eventId, string $eventType, string $payloadHash): bool
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO {$this->table} (event_id, event_type, payload_hash)
            VALUES (:eid, :type, :hash)
        ");
        $stmt->execute([
            'eid'  => $eventId,
            'type' => $eventType,
            'hash' => $payloadHash,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @deprecated Utiliser tryClaim() pour éviter les races
     */
    public function alreadyProcessed(string $eventId): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE event_id = :eid LIMIT 1");
        $stmt->execute(['eid' => $eventId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Marque un événement comme traité.
     */
    public function markProcessed(string $eventId, string $eventType, string $payloadHash): void
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO {$this->table} (event_id, event_type, payload_hash)
            VALUES (:eid, :type, :hash)
        ");
        $stmt->execute([
            'eid'  => $eventId,
            'type' => $eventType,
            'hash' => $payloadHash,
        ]);
    }
}

<?php
/**
 * Service Stripe sécurisé — signature webhook, idempotence, logging.
 */

declare(strict_types=1);

namespace Backend\Services;

use Backend\Models\Payment;
use Backend\Models\Service;
use Backend\Models\Subscription;
use Backend\Models\User;
use Backend\Models\WebhookEventModel;
use Shared\Core\ApiException;
use Shared\Core\Database;

class StripeService
{
    private string $secretKey;
    private string $webhookSecret;
    private string $logFile;

    public function __construct()
    {
        $this->secretKey     = env('STRIPE_SECRET_KEY', '');
        $this->webhookSecret = env('STRIPE_WEBHOOK_SECRET', '');
        $this->logFile       = dirname(__DIR__, 3) . '/storage/logs/stripe_webhook.log';
    }

    public function createCheckoutSession(int $userId, string $priceId, string $planSlug, string $interval = 'monthly'): array
    {
        $userModel = new User();
        $user = $userModel->findPublic($userId);

        if (!$user) {
            throw new ApiException('Utilisateur introuvable.', 404);
        }

        $customerId = $user['stripe_customer_id'] ?? null;

        if (!$customerId) {
            $customer = $this->api('POST', '/v1/customers', [
                'email'    => $user['email'],
                'name'     => $user['name'],
                'metadata' => ['user_id' => $userId],
            ]);
            $customerId = $customer['id'];
            $userModel->update($userId, ['stripe_customer_id' => $customerId]);
        }

        $successUrl = rtrim(env('CONSOLE_URL', env('APP_URL', 'http://localhost')), '/') . '/dashboard?checkout=success';
        $cancelUrl  = rtrim(env('VITRINE_URL', env('APP_URL', 'http://localhost')), '/') . '/pricing?checkout=cancelled';

        $session = $this->api('POST', '/v1/checkout/sessions', [
            'customer'             => $customerId,
            'mode'                 => 'subscription',
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => 1,
            'success_url'          => $successUrl,
            'cancel_url'           => $cancelUrl,
            'metadata[user_id]'    => $userId,
            'metadata[plan_slug]'  => $planSlug,
            'metadata[interval]'   => $interval,
            'subscription_data[metadata][user_id]'   => $userId,
            'subscription_data[metadata][plan_slug]' => $planSlug,
            'subscription_data[metadata][interval]'  => $interval,
        ]);

        return [
            'checkout_url' => $session['url'],
            'session_id'   => $session['id'],
        ];
    }

    /**
     * Portail client Stripe — gestion abonnement et moyens de paiement.
     */
    public function createBillingPortalSession(int $userId): string
    {
        $userModel = new User();
        $user = $userModel->findPublic($userId);

        if (!$user || empty($user['stripe_customer_id'])) {
            throw new ApiException('Aucun compte de facturation Stripe associé.', 422);
        }

        $returnUrl = rtrim(env('CONSOLE_URL', env('APP_URL', 'http://localhost')), '/') . '/billing';

        $session = $this->api('POST', '/v1/billing_portal/sessions', [
            'customer'   => $user['stripe_customer_id'],
            'return_url' => $returnUrl,
        ]);

        return $session['url'] ?? '';
    }

    /**
     * Traite un webhook — vérifie signature, idempotence, log chaque événement.
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        if ($signature === '') {
            $this->webhookLog('REJECTED', 'Signature header manquante');
            throw new ApiException('Signature Stripe manquante.', 400);
        }

        $event = $this->verifyWebhookSignature($payload, $signature);
        $eventId   = $event['id'] ?? '';
        $eventType = $event['type'] ?? 'unknown';

        $this->webhookLog('RECEIVED', "event_id=$eventId type=$eventType");

        $webhookModel = new WebhookEventModel();
        $payloadHash = hash('sha256', $payload);

        // Idempotence atomique — réserver l'événement avant traitement
        if ($eventId && !$webhookModel->tryClaim($eventId, $eventType, $payloadHash)) {
            $this->webhookLog('SKIPPED', "event_id=$eventId déjà traité");
            return ['handled' => true, 'action' => 'already_processed', 'event_id' => $eventId];
        }

        try {
            $result = match ($eventType) {
                'checkout.session.completed'    => $this->onCheckoutCompleted($event['data']['object']),
                'customer.subscription.updated'   => $this->onSubscriptionUpdated($event['data']['object']),
                'customer.subscription.deleted'   => $this->onSubscriptionDeleted($event['data']['object']),
                'invoice.paid'                    => $this->onInvoicePaid($event['data']['object']),
                'invoice.payment_failed'          => $this->onInvoiceFailed($event['data']['object']),
                default                           => ['handled' => false, 'type' => $eventType],
            };

            SecurityLogger::log('stripe_webhook_processed', [
                'event_id' => $eventId,
                'type'     => $eventType,
            ]);

            $this->webhookLog('PROCESSED', "event_id=$eventId");

            return $result;

        } catch (\Throwable $e) {
            $this->webhookLog('ERROR', "event_id=$eventId error=" . $e->getMessage());
            SecurityLogger::log('stripe_webhook_error', [
                'event_id' => $eventId,
                'type'     => $eventType,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function onCheckoutCompleted(array $session): array
    {
        $userId   = (int) ($session['metadata']['user_id'] ?? 0);
        $planSlug = $session['metadata']['plan_slug'] ?? 'starter';
        $subId    = $session['subscription'] ?? null;

        if (!$userId || !$subId) {
            return ['handled' => false, 'reason' => 'missing metadata'];
        }

        $stripeSub = $this->api('GET', "/v1/subscriptions/$subId");
        $subModel  = new Subscription();

        if (!$subModel->findByStripeId($subId)) {
            $subModel->create([
                'user_id'                 => $userId,
                'stripe_subscription_id'  => $subId,
                'stripe_customer_id'      => $session['customer'],
                'stripe_price_id'         => $stripeSub['items']['data'][0]['price']['id'] ?? null,
                'plan_slug'               => $planSlug,
                'status'                  => $this->mapStripeStatus($stripeSub['status']),
                'amount'                  => ($stripeSub['items']['data'][0]['price']['unit_amount'] ?? 0) / 100,
                'currency'                => strtoupper($stripeSub['currency'] ?? 'eur'),
                'current_period_start'    => date('Y-m-d H:i:s', $stripeSub['current_period_start']),
                'current_period_end'      => date('Y-m-d H:i:s', $stripeSub['current_period_end']),
            ]);
        }

        $this->provisionAfterPayment($userId, $planSlug);

        return ['handled' => true, 'action' => 'checkout_completed', 'user_id' => $userId];
    }

    private function onSubscriptionUpdated(array $stripeSub): array
    {
        $subModel = new Subscription();
        $updated  = $subModel->updateByStripeId($stripeSub['id'], [
            'status'               => $this->mapStripeStatus($stripeSub['status']),
            'current_period_start' => date('Y-m-d H:i:s', $stripeSub['current_period_start']),
            'current_period_end'   => date('Y-m-d H:i:s', $stripeSub['current_period_end']),
            'cancel_at_period_end' => $stripeSub['cancel_at_period_end'] ? 1 : 0,
        ]);

        return ['handled' => $updated, 'action' => 'subscription_updated'];
    }

    private function onSubscriptionDeleted(array $stripeSub): array
    {
        $subModel = new Subscription();
        $sub = $subModel->findByStripeId($stripeSub['id']);

        if ($sub) {
            $subModel->updateStatus((int) $sub['id'], 'cancelled', [
                'cancelled_at' => date('Y-m-d H:i:s'),
            ]);

            if ($sub['service_id']) {
                (new Service())->update((int) $sub['service_id'], ['status' => 'cancelled']);
            }
        }

        return ['handled' => true, 'action' => 'subscription_deleted'];
    }

    private function onInvoicePaid(array $invoice): array
    {
        $paymentModel = new Payment();

        if ($paymentModel->findByStripeInvoice($invoice['id'])) {
            return ['handled' => true, 'action' => 'invoice_already_recorded'];
        }

        $userId = $this->resolveUserIdFromInvoice($invoice);

        $paymentModel->create([
            'user_id'           => $userId,
            'stripe_invoice_id' => $invoice['id'],
            'stripe_charge_id'  => $invoice['charge'] ?? null,
            'amount'            => ($invoice['amount_paid'] ?? 0) / 100,
            'currency'          => strtoupper($invoice['currency'] ?? 'eur'),
            'status'            => 'paid',
            'description'       => 'Abonnement mensuel',
            'paid_at'           => date('Y-m-d H:i:s', $invoice['status_transitions']['paid_at'] ?? time()),
        ]);

        return ['handled' => true, 'action' => 'invoice_paid'];
    }

    private function onInvoiceFailed(array $invoice): array
    {
        $subModel = new Subscription();
        if (!empty($invoice['subscription'])) {
            $sub = $subModel->findByStripeId($invoice['subscription']);
            if ($sub) {
                $subModel->updateStatus((int) $sub['id'], 'past_due');
            }
        }

        SecurityLogger::log('stripe_payment_failed', [
            'invoice_id' => $invoice['id'] ?? null,
            'customer'   => $invoice['customer'] ?? null,
        ]);

        return ['handled' => true, 'action' => 'invoice_failed'];
    }

    private function resolveUserIdFromInvoice(array $invoice): int
    {
        $userId = (int) ($invoice['metadata']['user_id'] ?? 0);
        if ($userId) {
            return $userId;
        }

        if (!empty($invoice['customer'])) {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT id FROM users WHERE stripe_customer_id = :cid LIMIT 1");
            $stmt->execute(['cid' => $invoice['customer']]);
            return (int) ($stmt->fetchColumn() ?: 0);
        }

        return 0;
    }

    private function provisionAfterPayment(int $userId, string $planSlug): void
    {
        $userModel = new User();
        $user = $userModel->findPublic($userId);
        if (!$user) {
            return;
        }

        $serviceModel = new Service();
        if (!empty($serviceModel->findActiveByUser($userId))) {
            return;
        }

        $service = $serviceModel->create([
            'user_id' => $userId,
            'name'    => "Hébergement $planSlug",
            'type'    => 'hosting',
            'status'  => 'pending',
        ]);

        $provisioner = new ProvisioningService();
        $result = $provisioner->provisionFull($userId, (int) $service['id'], $user['email']);

        if ($result['success']) {
            $serviceModel->activate((int) $service['id'], $result['credentials']);
        }
    }

    private function mapStripeStatus(string $status): string
    {
        return match ($status) {
            'active', 'trialing'                 => 'active',
            'canceled'                           => 'cancelled',
            'past_due', 'unpaid'                 => 'past_due',
            'incomplete', 'incomplete_expired'   => 'incomplete',
            default                              => 'expired',
        };
    }

    /**
     * Vérifie la signature Stripe-Signature header (HMAC SHA-256).
     * Rejette toute requête invalide avec HTTP 400.
     */
    private function verifyWebhookSignature(string $payload, string $signature): array
    {
        if (empty($this->webhookSecret)) {
            throw new ApiException('STRIPE_WEBHOOK_SECRET non configuré.', 500);
        }

        $parts = [];
        foreach (explode(',', $signature) as $item) {
            $item = trim($item);
            if (str_contains($item, '=')) {
                [$k, $v] = explode('=', $item, 2);
                $parts[trim($k)] = trim($v);
            }
        }

        $timestamp = $parts['t'] ?? '';
        $sig       = $parts['v1'] ?? '';

        if ($timestamp === '' || $sig === '') {
            throw new ApiException('Format de signature Stripe invalide.', 400);
        }

        // Tolérance 5 minutes (replay attack protection)
        if (abs(time() - (int) $timestamp) > 300) {
            throw new ApiException('Webhook timestamp expiré.', 400);
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expected      = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        if (!hash_equals($expected, $sig)) {
            $this->webhookLog('REJECTED', 'Signature invalide');
            throw new ApiException('Signature webhook invalide.', 400);
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type'])) {
            throw new ApiException('Payload webhook invalide.', 400);
        }

        return $event;
    }

    private function api(string $method, string $endpoint, array $params = []): array
    {
        if (empty($this->secretKey)) {
            throw new ApiException('STRIPE_SECRET_KEY non configuré.', 500);
        }

        $url = 'https://api.stripe.com' . $endpoint;
        $ch  = curl_init();
        $headers = ['Authorization: Bearer ' . $this->secretKey];

        if ($method === 'GET' && $params) {
            $url .= '?' . http_build_query($params);
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response ?: '', true);

        if ($httpCode >= 400) {
            throw new ApiException($data['error']['message'] ?? 'Erreur Stripe', $httpCode);
        }

        return $data;
    }

    private function webhookLog(string $level, string $message): void
    {
        $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $message);
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

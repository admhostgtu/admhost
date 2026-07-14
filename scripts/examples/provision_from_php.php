<?php
/**
 * Exemple d'exécution sécurisée de scripts bash depuis PHP.
 *
 * Ce fichier montre comment ProvisioningService est utilisé
 * après un paiement Stripe réussi pour créer un utilisateur système.
 *
 * NE PAS exposer ce fichier en production — exemple documenté uniquement.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/autoload.php';

use Backend\Services\ProvisioningService;

// --- Exemple 1 : Provisionnement complet après paiement ---
function provisionAfterPayment(int $userId, int $serviceId, string $email): void
{
    $provisioner = new ProvisioningService();

    // Seuls les scripts de la whitelist interne sont exécutables
    $result = $provisioner->provisionFull($userId, $serviceId, $email);

    if ($result['success']) {
        echo "Provisionnement réussi !\n";
        echo "Linux user : " . ($result['credentials']['linux_username'] ?? 'N/A') . "\n";
        echo "SSH host   : " . ($result['credentials']['ssh_host'] ?? 'N/A') . "\n";
        echo "SMTP host  : " . ($result['credentials']['smtp_host'] ?? 'N/A') . "\n";
    } else {
        echo "Erreur : " . ($result['error'] ?? 'inconnue') . "\n";
    }
}

// --- Exemple 2 : Création individuelle (étape par étape) ---
function provisionStepByStep(string $email): void
{
    $provisioner = new ProvisioningService();
    $username = preg_replace('/[^a-z0-9]/', '', strtolower(explode('@', $email)[0])) . '_demo';

    // Étape 1 : Utilisateur Linux
    $linux = $provisioner->createLinuxUser($username, 1);
    if (!$linux['success']) {
        die('Erreur Linux: ' . $linux['error']);
    }

    // Étape 2 : Accès SSH
    $ssh = $provisioner->createSshAccess($username);

    // Étape 3 : Boîte mail
    $mail = $provisioner->createMailbox($email, $username);

    print_r(compact('linux', 'ssh', 'mail'));
}

// --- Sécurité appliquée par ProvisioningService ---
// 1. Whitelist stricte des scripts (pas de chemin arbitraire)
// 2. Validation regex des paramètres (username, email)
// 3. escapeshellarg() sur chaque argument
// 4. proc_open avec timeout 60s
// 5. Sortie JSON parsée (pas de eval/shell_exec direct)
// 6. Logs dans storage/logs/provisioning.log

// Décommenter pour tester en CLI :
// provisionAfterPayment(1, 1, 'test@example.com');

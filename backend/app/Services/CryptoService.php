<?php
/**
 * Service de chiffrement AES-256-CBC pour credentials sensibles (SSH, SMTP).
 *
 * Format stocké : base64( IV(16 bytes) + ciphertext )
 *
 * Usage :
 *   $crypto = new CryptoService();
 *   $encrypted = $crypto->encrypt('mon-mot-de-passe');
 *   $plain     = $crypto->decrypt($encrypted);
 *
 * Rotation de clé :
 *   Définir APP_ENCRYPTION_KEY_LEGACY avec l'ancienne clé.
 *   decrypt() tentera la clé courante puis la legacy.
 *   Re-chiffrer progressivement les enregistrements avec encrypt().
 *
 * Générer une clé : php -r "echo bin2hex(random_bytes(32));"
 */

declare(strict_types=1);

namespace Backend\Services;

use Shared\Core\ApiException;

class CryptoService
{
    private const CIPHER = 'AES-256-CBC';
    private const IV_LENGTH = 16;

    private string $key;
    private ?string $legacyKey;

    public function __construct()
    {
        $this->key       = $this->resolveKey(env('APP_ENCRYPTION_KEY', ''));
        $legacy          = env('APP_ENCRYPTION_KEY_LEGACY', '');
        $this->legacyKey = $legacy !== '' ? $this->resolveKey($legacy) : null;
    }

    /**
     * Chiffre une chaîne et retourne base64(iv + ciphertext).
     */
    public function encrypt(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $iv = random_bytes(self::IV_LENGTH);

        $ciphertext = openssl_encrypt(
            $data,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($ciphertext === false) {
            throw new ApiException('Échec du chiffrement.', 500);
        }

        return base64_encode($iv . $ciphertext);
    }

    /**
     * Déchiffre une chaîne base64(iv + ciphertext).
     */
    public function decrypt(string $encrypted): string
    {
        if ($encrypted === '') {
            return '';
        }

        // Données non chiffrées (legacy / seed) — retour tel quel si pas base64 valide
        $raw = base64_decode($encrypted, true);
        if ($raw === false || strlen($raw) < self::IV_LENGTH + 1) {
            return $encrypted;
        }

        $result = $this->decryptWithKey($encrypted, $this->key);
        if ($result !== null) {
            return $result;
        }

        if ($this->legacyKey !== null) {
            $result = $this->decryptWithKey($encrypted, $this->legacyKey);
            if ($result !== null) {
                return $result;
            }
        }

        throw new ApiException('Échec du déchiffrement — clé invalide ou données corrompues.', 500);
    }

    /**
     * Indique si une valeur est déjà chiffrée (format attendu).
     */
    public function isEncrypted(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $raw = base64_decode($value, true);
        return $raw !== false && strlen($raw) >= self::IV_LENGTH + 1;
    }

    private function decryptWithKey(string $encrypted, string $key): ?string
    {
        $raw = base64_decode($encrypted, true);
        if ($raw === false || strlen($raw) < self::IV_LENGTH + 1) {
            return null;
        }

        $iv         = substr($raw, 0, self::IV_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH);

        $plain = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $plain !== false ? $plain : null;
    }

    /**
     * Normalise la clé : accepte hex (64 chars) ou raw base64.
     */
    private function resolveKey(string $key): string
    {
        if ($key === '') {
            throw new ApiException(
                'APP_ENCRYPTION_KEY manquante. Générez : php -r "echo bin2hex(random_bytes(32));"',
                500
            );
        }

        if (strlen($key) === 64 && ctype_xdigit($key)) {
            $binary = hex2bin($key);
            if ($binary === false || strlen($binary) !== 32) {
                throw new ApiException('APP_ENCRYPTION_KEY hex invalide (64 caractères attendus).', 500);
            }
            return $binary;
        }

        $decoded = base64_decode($key, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $decoded;
        }

        throw new ApiException('APP_ENCRYPTION_KEY invalide (32 bytes en hex ou base64).', 500);
    }
}

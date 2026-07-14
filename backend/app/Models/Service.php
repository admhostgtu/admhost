<?php
/**
 * Modèle Service — chiffrement AES des credentials SSH/SMTP.
 */

declare(strict_types=1);

namespace Backend\Models;

use Backend\Services\CryptoService;
use Shared\Core\Model;

class Service extends Model
{
    protected string $table = 'services';

    /** @var string[] Champs chiffrés en base */
    private array $encryptedFields = ['ssh_password', 'smtp_password'];

    private ?CryptoService $crypto = null;

    private function crypto(): CryptoService
    {
        return $this->crypto ??= new CryptoService();
    }

    public function findByUser(int $userId, bool $decrypt = true): array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, sp.name AS plan_name, sp.slug AS plan_slug
            FROM {$this->table} s
            LEFT JOIN service_plans sp ON sp.id = s.plan_id
            WHERE s.user_id = :uid
            ORDER BY s.created_at DESC
        ");
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll();

        return $decrypt ? array_map(fn($r) => $this->decryptRow($r), $rows) : $rows;
    }

    public function findActiveByUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, sp.name AS plan_name
            FROM {$this->table} s
            LEFT JOIN service_plans sp ON sp.id = s.plan_id
            WHERE s.user_id = :uid AND s.status = 'active'
            ORDER BY s.created_at DESC
        ");
        $stmt->execute(['uid' => $userId]);
        return array_map(fn($r) => $this->decryptRow($r), $stmt->fetchAll());
    }

    public function findDecrypted(int $id): ?array
    {
        $row = $this->find($id);
        return $row ? $this->decryptRow($row) : null;
    }

    public function allWithUsers(bool $decrypt = false): array
    {
        $stmt = $this->db->query("
            SELECT s.*, u.name AS user_name, u.email AS user_email, sp.name AS plan_name
            FROM {$this->table} s
            JOIN users u ON u.id = s.user_id
            LEFT JOIN service_plans sp ON sp.id = s.plan_id
            ORDER BY s.created_at DESC
        ");
        $rows = $stmt->fetchAll();
        return $decrypt ? array_map(fn($r) => $this->decryptRow($r), $rows) : $this->maskSecrets($rows);
    }

    public function create(array $data): array
    {
        $data = $this->encryptFields($data);

        $stmt = $this->db->prepare("
            INSERT INTO {$this->table}
            (user_id, plan_id, name, type, status, ssh_host, ssh_port, ssh_username, ssh_password,
             smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption,
             linux_username, home_directory, provisioned_at)
            VALUES
            (:user_id, :plan_id, :name, :type, :status, :ssh_host, :ssh_port, :ssh_username, :ssh_password,
             :smtp_host, :smtp_port, :smtp_username, :smtp_password, :smtp_encryption,
             :linux_username, :home_directory, :provisioned_at)
        ");
        $stmt->execute([
            'user_id'         => $data['user_id'],
            'plan_id'         => $data['plan_id'] ?? null,
            'name'            => $data['name'],
            'type'            => $data['type'] ?? 'hosting',
            'status'          => $data['status'] ?? 'pending',
            'ssh_host'        => $data['ssh_host'] ?? null,
            'ssh_port'        => $data['ssh_port'] ?? 22,
            'ssh_username'    => $data['ssh_username'] ?? null,
            'ssh_password'    => $data['ssh_password'] ?? null,
            'smtp_host'       => $data['smtp_host'] ?? null,
            'smtp_port'       => $data['smtp_port'] ?? 587,
            'smtp_username'   => $data['smtp_username'] ?? null,
            'smtp_password'   => $data['smtp_password'] ?? null,
            'smtp_encryption' => $data['smtp_encryption'] ?? 'tls',
            'linux_username'  => $data['linux_username'] ?? null,
            'home_directory'  => $data['home_directory'] ?? null,
            'provisioned_at'  => $data['provisioned_at'] ?? null,
        ]);

        return $this->findDecrypted((int) $this->db->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->encryptFields($data);

        $allowed = [
            'name', 'status', 'ssh_host', 'ssh_port', 'ssh_username', 'ssh_password',
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
            'linux_username', 'home_directory', 'provisioned_at', 'plan_id',
        ];
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql  = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function activate(int $id, array $credentials): bool
    {
        return $this->update($id, array_merge($credentials, [
            'status'         => 'active',
            'provisioned_at' => date('Y-m-d H:i:s'),
        ]));
    }

    private function encryptFields(array $data): array
    {
        foreach ($this->encryptedFields as $field) {
            if (!empty($data[$field]) && !$this->crypto()->isEncrypted($data[$field])) {
                $data[$field] = $this->crypto()->encrypt($data[$field]);
            }
        }
        return $data;
    }

    private function decryptRow(array $row): array
    {
        foreach ($this->encryptedFields as $field) {
            if (!empty($row[$field])) {
                try {
                    $row[$field] = $this->crypto()->decrypt($row[$field]);
                } catch (\Throwable) {
                    $row[$field] = '***';
                }
            }
        }
        return $row;
    }

    /** Masque les secrets pour l'admin list (sans déchiffrement). */
    private function maskSecrets(array $rows): array
    {
        foreach ($rows as &$row) {
            foreach ($this->encryptedFields as $field) {
                if (!empty($row[$field])) {
                    $row[$field] = '********';
                }
            }
        }
        return $rows;
    }
}

<?php
/**
 * Modèle User — CRUD utilisateurs avec sécurité mot de passe.
 */

declare(strict_types=1);

namespace Backend\Models;

use Shared\Core\Model;

class User extends Model
{
    protected string $table = 'users';

    /** @var string[] Champs jamais exposés en API */
    private array $hidden = ['password'];

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findPublic(int $id): ?array
    {
        $user = $this->find($id);
        return $user ? $this->hideFields($user) : null;
    }

    public function allPublic(): array
    {
        $stmt = $this->db->query("
            SELECT id, name, email, role, status, stripe_customer_id, last_login_at, created_at, updated_at
            FROM {$this->table} ORDER BY created_at DESC
        ");
        return $stmt->fetchAll();
    }

    public function create(array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (name, email, password, role, status)
            VALUES (:name, :email, :password, :role, :status)
        ");
        $stmt->execute([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'role'     => $data['role'] ?? 'user',
            'status'   => $data['status'] ?? 'active',
        ]);

        return $this->findPublic((int) $this->db->lastInsertId());
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['name', 'email', 'role', 'status', 'stripe_customer_id', 'last_login_at'];
        $fields  = [];
        $params  = ['id' => $id];

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

    public function updatePassword(int $id, string $hashedPassword): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET password = :pwd WHERE id = :id");
        return $stmt->execute(['pwd' => $hashedPassword, 'id' => $id]);
    }

    public function recordLogin(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET last_login_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    private function hideFields(array $user): array
    {
        foreach ($this->hidden as $field) {
            unset($user[$field]);
        }
        return $user;
    }
}

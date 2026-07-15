<?php
/**
 * Modèle Plan — catalogue service_plans.
 */

declare(strict_types=1);

namespace Backend\Models;

use Shared\Core\Model;

class Plan extends Model
{
    protected string $table = 'service_plans';

    public function allActive(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY price_monthly ASC
        ");
        return $stmt->fetchAll();
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE slug = :slug LIMIT 1");
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table}
            (slug, name, description, type, price_monthly, price_annual,
             stripe_price_id, stripe_price_id_annual, features, config_schema, is_active)
            VALUES
            (:slug, :name, :description, :type, :price_monthly, :price_annual,
             :stripe_price_id, :stripe_price_id_annual, :features, :config_schema, :is_active)
        ");
        $stmt->execute([
            'slug'                   => $data['slug'],
            'name'                   => $data['name'],
            'description'            => $data['description'] ?? null,
            'type'                   => $data['type'] ?? 'hosting',
            'price_monthly'          => $data['price_monthly'] ?? 0,
            'price_annual'           => $data['price_annual'] ?? 0,
            'stripe_price_id'        => $data['stripe_price_id'] ?? null,
            'stripe_price_id_annual' => $data['stripe_price_id_annual'] ?? null,
            'features'               => isset($data['features']) ? json_encode($data['features']) : null,
            'config_schema'          => isset($data['config_schema']) ? json_encode($data['config_schema']) : null,
            'is_active'              => $data['is_active'] ?? 1,
        ]);
        return $this->find((int) $this->db->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): bool
    {
        $allowed = [
            'slug', 'name', 'description', 'type', 'price_monthly', 'price_annual',
            'stripe_price_id', 'stripe_price_id_annual', 'is_active',
        ];
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $fields[] = "$key = :$key";
            $params[$key] = $value;
        }

        if (isset($data['features'])) {
            $fields[] = 'features = :features';
            $params['features'] = json_encode($data['features']);
        }
        if (isset($data['config_schema'])) {
            $fields[] = 'config_schema = :config_schema';
            $params['config_schema'] = json_encode($data['config_schema']);
        }

        if ($fields === []) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->prepare($sql)->execute($params);
    }
}

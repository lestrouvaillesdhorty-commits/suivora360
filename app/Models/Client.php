<?php

namespace App\Models;

use App\Core\Database;

class Client
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM clients WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function allForFiliale(int $filialeId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM clients WHERE filiale_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY nom';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$filialeId]);
        return $stmt->fetchAll();
    }

    public static function visibleFor(array $user): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT c.*, f.nom AS filiale_nom FROM clients c
             INNER JOIN filiales f ON f.id = c.filiale_id
             WHERE c.filiale_id IN ($placeholders)
             ORDER BY c.nom"
        );
        $stmt->execute($filialeIds);
        return $stmt->fetchAll();
    }

    public static function userCanAccess(array $user, array $client): bool
    {
        return Filiale::userCanAccess($user, (int) $client['filiale_id']);
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO clients (filiale_id, nom, email, telephone, pays, ville, adresse, secteur, notes, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([
            $data['filiale_id'],
            $data['nom'],
            $data['email'] ?? '',
            $data['telephone'] ?? '',
            $data['pays'] ?? '',
            $data['ville'] ?? '',
            $data['adresse'] ?? '',
            $data['secteur'] ?? '',
            $data['notes'] ?? '',
            date('Y-m-d H:i:s'),
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE clients SET nom = ?, email = ?, telephone = ?, pays = ?, ville = ?, adresse = ?, secteur = ?, notes = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['nom'],
            $data['email'] ?? '',
            $data['telephone'] ?? '',
            $data['pays'] ?? '',
            $data['ville'] ?? '',
            $data['adresse'] ?? '',
            $data['secteur'] ?? '',
            $data['notes'] ?? '',
            $id,
        ]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE clients SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    public static function nameOf(?int $id): string
    {
        if (!$id) {
            return '—';
        }
        $client = self::find($id);
        return $client ? $client['nom'] : '—';
    }
}

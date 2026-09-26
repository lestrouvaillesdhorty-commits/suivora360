<?php

namespace App\Models;

use App\Core\Database;

class Fournisseur
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM fournisseurs WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function allForFiliale(int $filialeId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM fournisseurs WHERE filiale_id = ?';
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
            "SELECT fo.*, fi.nom AS filiale_nom FROM fournisseurs fo
             INNER JOIN filiales fi ON fi.id = fo.filiale_id
             WHERE fo.filiale_id IN ($placeholders)
             ORDER BY fo.nom"
        );
        $stmt->execute($filialeIds);
        return $stmt->fetchAll();
    }

    public static function userCanAccess(array $user, array $fournisseur): bool
    {
        return Filiale::userCanAccess($user, (int) $fournisseur['filiale_id']);
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO fournisseurs (filiale_id, nom, email, telephone, pays, ville, adresse, devise, secteur, site_web, notes, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([
            $data['filiale_id'],
            $data['nom'],
            $data['email'] ?? '',
            $data['telephone'] ?? '',
            $data['pays'] ?? '',
            $data['ville'] ?? '',
            $data['adresse'] ?? '',
            $data['devise'] ?? '',
            $data['secteur'] ?? '',
            $data['site_web'] ?? '',
            $data['notes'] ?? '',
            date('Y-m-d H:i:s'),
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function nameOf(?int $id): string
    {
        if (!$id) {
            return '—';
        }
        $fournisseur = self::find($id);
        return $fournisseur ? $fournisseur['nom'] : '—';
    }
}

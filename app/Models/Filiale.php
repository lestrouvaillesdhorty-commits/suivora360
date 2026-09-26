<?php

namespace App\Models;

use App\Core\Database;

class Filiale
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM filiales WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function allForOrganisation(int $organisationId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM filiales WHERE organisation_id = ? ORDER BY nom');
        $stmt->execute([$organisationId]);
        return $stmt->fetchAll();
    }

    /**
     * Filiales visibles par un utilisateur donné :
     * - dirigeant : toutes les filiales de son organisation
     * - employé : uniquement celles qui lui sont assignées
     */
    public static function visibleFor(array $user): array
    {
        if ($user['role'] === 'dirigeant') {
            return self::allForOrganisation((int) $user['organisation_id']);
        }

        $stmt = Database::connection()->prepare(
            'SELECT f.* FROM filiales f
             INNER JOIN utilisateur_filiales uf ON uf.filiale_id = f.id
             WHERE uf.utilisateur_id = ?
             ORDER BY f.nom'
        );
        $stmt->execute([$user['id']]);
        return $stmt->fetchAll();
    }

    public static function visibleIdsFor(array $user): array
    {
        return array_map(fn($f) => (int) $f['id'], self::visibleFor($user));
    }

    public static function userCanAccess(array $user, int $filialeId): bool
    {
        if ($user['role'] === 'dirigeant') {
            $filiale = self::find($filialeId);
            return $filiale && (int) $filiale['organisation_id'] === (int) $user['organisation_id'];
        }
        return in_array($filialeId, self::visibleIdsFor($user), true);
    }

    public static function create(int $organisationId, string $nom): int
    {
        $stmt = Database::connection()->prepare('INSERT INTO filiales (organisation_id, nom, created_at) VALUES (?, ?, ?)');
        $stmt->execute([$organisationId, $nom, date('Y-m-d H:i:s')]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function countForOrganisation(int $organisationId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM filiales WHERE organisation_id = ?');
        $stmt->execute([$organisationId]);
        return (int) $stmt->fetchColumn();
    }
}

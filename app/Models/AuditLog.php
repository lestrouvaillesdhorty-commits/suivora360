<?php

namespace App\Models;

use App\Core\Database;

/**
 * Journal d'audit simple : trace qui a fait quoi, sur quelle entité, et quand.
 * Base pour la page "Sécurité" à venir (voir feuille de route V2).
 */
class AuditLog
{
    public static function log(int $filialeId, ?int $utilisateurId, string $action, string $entiteType, ?int $entiteId, ?string $details = null): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO audit_logs (filiale_id, utilisateur_id, action, entite_type, entite_id, details, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $filialeId,
            $utilisateurId,
            $action,
            $entiteType,
            $entiteId,
            $details,
            date('Y-m-d H:i:s'),
        ]);
    }

    public static function recentForFiliale(int $filialeId, int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT a.*, u.nom AS utilisateur_nom FROM audit_logs a
             LEFT JOIN utilisateurs u ON u.id = a.utilisateur_id
             WHERE a.filiale_id = ?
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT $limit"
        );
        $stmt->execute([$filialeId]);
        return $stmt->fetchAll();
    }
}

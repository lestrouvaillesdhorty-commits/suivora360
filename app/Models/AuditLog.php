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

    /**
     * Historique complet d'une entité précise (ex : toutes les actions sur une demande),
     * du plus ancien au plus récent — pensé pour afficher une frise chronologique.
     */
    public static function forEntity(string $entiteType, int $entiteId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.*, u.nom AS utilisateur_nom FROM audit_logs a
             LEFT JOIN utilisateurs u ON u.id = a.utilisateur_id
             WHERE a.entite_type = ? AND a.entite_id = ?
             ORDER BY a.created_at ASC, a.id ASC'
        );
        $stmt->execute([$entiteType, $entiteId]);
        return $stmt->fetchAll();
    }

    /**
     * Historique complet d'un dossier : ses propres actions, plus celles de
     * tout ce qui s'y rattache (consultations, offres, cotation, commande,
     * factures) — chaque table concernée porte directement dossier_id, ce
     * qui permet de tout regrouper en une seule requête.
     */
    public static function forDossier(int $dossierId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT a.*, u.nom AS utilisateur_nom FROM audit_logs a
             LEFT JOIN utilisateurs u ON u.id = a.utilisateur_id
             WHERE (a.entite_type = 'dossier' AND a.entite_id = ?)
                OR (a.entite_type = 'consultation_fournisseur' AND a.entite_id IN (SELECT id FROM consultations_fournisseur WHERE dossier_id = ?))
                OR (a.entite_type = 'offre' AND a.entite_id IN (SELECT id FROM offres WHERE dossier_id = ?))
                OR (a.entite_type = 'cotation' AND a.entite_id IN (SELECT id FROM cotations WHERE dossier_id = ?))
                OR (a.entite_type = 'commande' AND a.entite_id IN (SELECT id FROM commandes WHERE dossier_id = ?))
                OR (a.entite_type = 'facture' AND a.entite_id IN (SELECT id FROM factures WHERE dossier_id = ?))
             ORDER BY a.created_at ASC, a.id ASC"
        );
        $stmt->execute([$dossierId, $dossierId, $dossierId, $dossierId, $dossierId, $dossierId]);
        return $stmt->fetchAll();
    }
}

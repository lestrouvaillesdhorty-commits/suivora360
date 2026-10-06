<?php

namespace App\Models;

use App\Core\Database;

/**
 * Un collaborateur additionnel assigné à un dossier, dédié à un fournisseur
 * précis (ex : une personne côté partenaire local qui suit un fournisseur
 * particulier pour accélérer la collecte des devis sur les demandes
 * urgentes). Plusieurs collaborateurs peuvent donc travailler un même
 * dossier, chacun rattaché à "son" fournisseur.
 *
 * Le système d'accès de l'application est déjà scopé par filiale
 * (Filiale::userCanAccess) et non par responsable_id : rien n'empêche
 * techniquement plusieurs personnes de travailler un dossier. Cette table
 * ajoute seulement la visibilité/le suivi explicite (qui suit quoi) et les
 * notifications qui vont avec — voir Notification::notifier().
 *
 * Retrait automatique : quand une offre est retenue sur un dossier
 * (Offre::retenir), les collaborateurs rattachés aux fournisseurs non
 * retenus n'ont plus de raison de rester assignés — voir retirerNonRetenus().
 */
class DossierCollaborateur
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM dossier_collaborateurs WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Liste des collaborateurs d'un dossier, avec le nom de l'utilisateur et
     * celui du fournisseur associé — utilisé sur la page du dossier.
     */
    public static function forDossier(int $dossierId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT dc.*, u.nom AS utilisateur_nom, u.email AS utilisateur_email, fo.nom AS fournisseur_nom
             FROM dossier_collaborateurs dc
             INNER JOIN utilisateurs u ON u.id = dc.utilisateur_id
             INNER JOIN fournisseurs fo ON fo.id = dc.fournisseur_id
             WHERE dc.dossier_id = ?
             ORDER BY dc.created_at ASC'
        );
        $stmt->execute([$dossierId]);
        return $stmt->fetchAll();
    }

    public static function existePour(int $dossierId, int $fournisseurId, int $utilisateurId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM dossier_collaborateurs WHERE dossier_id = ? AND fournisseur_id = ? AND utilisateur_id = ?'
        );
        $stmt->execute([$dossierId, $fournisseurId, $utilisateurId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Assigne un collaborateur à un dossier pour un fournisseur donné.
     * Idempotent : si déjà assigné (même trio dossier/fournisseur/utilisateur),
     * renvoie l'assignation existante sans en recréer une.
     */
    public static function assigner(int $dossierId, int $filialeId, int $fournisseurId, int $utilisateurId, ?int $assignedBy, ?string $role = null): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM dossier_collaborateurs WHERE dossier_id = ? AND fournisseur_id = ? AND utilisateur_id = ?'
        );
        $stmt->execute([$dossierId, $fournisseurId, $utilisateurId]);
        $existing = $stmt->fetch();
        if ($existing) {
            return (int) $existing['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO dossier_collaborateurs (dossier_id, filiale_id, fournisseur_id, utilisateur_id, assigned_by, role, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $role = $role !== null ? mb_substr(trim($role), 0, 100) : null;
        $stmt->execute([$dossierId, $filialeId, $fournisseurId, $utilisateurId, $assignedBy, ($role !== '' ? $role : null), date('Y-m-d H:i:s')]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function retirer(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM dossier_collaborateurs WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Retire tous les collaborateurs d'un dossier rattachés à un fournisseur
     * autre que celui dont l'offre vient d'être retenue. Appelé par
     * Offre::retenir(). Renvoie les lignes supprimées (avant suppression)
     * pour permettre à l'appelant de notifier les personnes concernées.
     */
    public static function retirerNonRetenus(int $dossierId, int $fournisseurIdRetenu): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT dc.*, u.nom AS utilisateur_nom, u.email AS utilisateur_email, fo.nom AS fournisseur_nom
             FROM dossier_collaborateurs dc
             INNER JOIN utilisateurs u ON u.id = dc.utilisateur_id
             INNER JOIN fournisseurs fo ON fo.id = dc.fournisseur_id
             WHERE dc.dossier_id = ? AND dc.fournisseur_id != ?'
        );
        $stmt->execute([$dossierId, $fournisseurIdRetenu]);
        $retires = $stmt->fetchAll();

        if (!empty($retires)) {
            $delete = $pdo->prepare('DELETE FROM dossier_collaborateurs WHERE dossier_id = ? AND fournisseur_id != ?');
            $delete->execute([$dossierId, $fournisseurIdRetenu]);
        }

        return $retires;
    }

    /**
     * Répartition du nombre de dossiers actuellement suivis par
     * collaborateur, tous filiales visibles confondues — utilisé par le
     * module Pilotage ("Répartition par collaborateur").
     */
    public static function dossiersActifsParCollaborateur(array $filialeIds): array
    {
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT dc.utilisateur_id, u.nom AS utilisateur_nom, COUNT(DISTINCT dc.dossier_id) AS nb_dossiers
             FROM dossier_collaborateurs dc
             INNER JOIN utilisateurs u ON u.id = dc.utilisateur_id
             INNER JOIN dossiers d ON d.id = dc.dossier_id
             WHERE dc.filiale_id IN ($placeholders) AND d.statut = 'actif'
             GROUP BY dc.utilisateur_id, u.nom
             ORDER BY nb_dossiers DESC"
        );
        $stmt->execute($filialeIds);
        return $stmt->fetchAll();
    }
}

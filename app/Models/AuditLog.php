<?php

namespace App\Models;

use App\Core\Database;

/**
 * Journal d'audit simple : trace qui a fait quoi, sur quelle entité, et quand.
 * Base pour la page "Sécurité" à venir (voir feuille de route V2).
 */
class AuditLog
{
    /** Libellés lisibles des actions (page Sécurité). Un code inconnu est affiché tel quel. */
    public const LIBELLES = [
        'creation' => 'Création',
        'modification' => 'Modification',
        'import' => 'Import',
        'qualification' => 'Qualification',
        'qualification_rattachement' => 'Qualification (rattachement)',
        'qualification_reprise' => 'Qualification (reprise)',
        'rejet_demande' => 'Demande rejetée',
        'archivage_demande' => 'Demande archivée',
        'desarchivage_demande' => 'Demande désarchivée',
        'suppression_demande' => 'Demande supprimée',
        'ajout_article' => 'Article ajouté',
        'modification_article' => 'Article modifié',
        'suppression_article' => 'Article supprimé',
        'extraction_ia_articles' => 'Extraction IA des articles',
        'ajout_piece_jointe' => 'Document ajouté',
        'suppression_piece_jointe' => 'Document supprimé',
        'classement_piece_jointe' => 'Document classé',
        'creation_dossier' => 'Dossier créé',
        'maj_type_dossier' => 'Type de dossier modifié',
        'maj_etape_dossier' => 'Étape du dossier mise à jour',
        'maj_prestation_dossier' => 'Prestation mise à jour',
        'maj_budget_dossier' => 'Budget mis à jour',
        'decision_comparateur_annulee' => 'Décision du comparateur annulée',
        'creation_consultation' => 'Consultation envoyée',
        'creation_partage_consultation' => 'Lien de partage créé',
        'partage_marque_envoye' => 'Lien de partage marqué envoyé',
        'partage_revoque' => 'Lien de partage révoqué',
        'creation_offre' => 'Offre enregistrée',
        'revision_offre' => 'Nouvelle version d\'offre',
        'offre_retenue' => 'Offre retenue',
        'creation_cotation' => 'Cotation créée',
        'revision_cotation' => 'Nouvelle version de cotation',
        'changement_statut_cotation' => 'Statut de cotation changé',
        'creation_commande' => 'Commande créée',
        'maj_etape_commande' => 'Étape de commande mise à jour',
        'maj_suivi_commande' => 'Suivi de commande mis à jour',
        'creation_facture' => 'Facture créée',
        'changement_statut_facture' => 'Statut de facture changé',
        'collaborateur_assigne' => 'Collaborateur assigné',
        'collaborateur_retire' => 'Collaborateur retiré',
        'creation_client' => 'Client créé',
        'modification_client' => 'Client modifié',
        'desactivation_client' => 'Client désactivé',
        'reactivation_client' => 'Client réactivé',
        'creation_fournisseur' => 'Fournisseur créé',
        'modification_fournisseur' => 'Fournisseur modifié',
        'desactivation_fournisseur' => 'Fournisseur désactivé',
        'reactivation_fournisseur' => 'Fournisseur réactivé',
        // Administration (journalisées depuis la page Sécurité, 06/10)
        'creation_utilisateur' => 'Utilisateur créé',
        'modification_utilisateur' => 'Utilisateur modifié',
        'changement_role_utilisateur' => 'Rôle d\'un utilisateur changé',
        'creation_organisation' => 'Espace entreprise créé (administrateur Suivora)',
        'suspension_organisation' => 'Accès de l\'entreprise suspendu (administrateur Suivora)',
        'reactivation_organisation' => 'Accès de l\'entreprise réactivé (administrateur Suivora)',
        'modification_abonnement' => 'Abonnement modifié (administrateur Suivora)',
        'acces_filiales_utilisateur' => 'Accès aux filiales modifiés',
        'reset_mot_de_passe_utilisateur' => 'Mot de passe réinitialisé',
        'desactivation_utilisateur' => 'Compte désactivé',
        'reactivation_utilisateur' => 'Compte réactivé',
        'creation_filiale' => 'Filiale créée',
        'renommage_filiale' => 'Filiale renommée',
        'suppression_filiale' => 'Filiale supprimée',
        'maj_parametres' => 'Paramètres modifiés',
        'export_journal_audit' => 'Journal d\'audit exporté',
    ];

    public const ENTITES = [
        'organisation' => 'Entreprise',
        'demande' => 'Demande',
        'dossier' => 'Dossier',
        'client' => 'Client',
        'fournisseur' => 'Fournisseur',
        'consultation_fournisseur' => 'Consultation',
        'offre' => 'Offre',
        'cotation' => 'Cotation',
        'commande' => 'Commande',
        'facture' => 'Facture',
        'utilisateur' => 'Utilisateur',
        'filiale' => 'Filiale',
        'parametres' => 'Paramètres',
        'journal_audit' => 'Journal d\'audit',
    ];

    public static function libelle(string $action): string
    {
        return self::LIBELLES[$action] ?? $action;
    }

    /**
     * Journalise une action d'administration (utilisateurs, filiales,
     * paramètres, export) : rattachée à la première filiale visible de
     * l'acteur, pour rester dans le périmètre de sa propre organisation sur
     * la page Sécurité. Ne journalise jamais de secret (mot de passe...).
     */
    public static function logAdmin(array $acteur, string $action, string $entiteType, ?int $entiteId, ?string $details = null, ?int $filialeId = null): void
    {
        if ($filialeId === null) {
            $ids = \App\Models\Filiale::visibleIdsFor($acteur);
            $filialeId = $ids[0] ?? null;
        }
        if ($filialeId === null) {
            return;
        }
        self::log($filialeId, (int) $acteur['id'], $action, $entiteType, $entiteId, $details);
    }

    private static function whereRecherche(array $filialeIds, array $f, array &$params): string
    {
        if (empty($filialeIds)) {
            return '1 = 0';
        }
        $params = array_map('intval', $filialeIds);
        $where = 'a.filiale_id IN (' . implode(',', array_fill(0, count($filialeIds), '?')) . ')';
        if (!empty($f['utilisateur_id'])) {
            $where .= ' AND a.utilisateur_id = ?';
            $params[] = (int) $f['utilisateur_id'];
        }
        if (!empty($f['entite_type'])) {
            $where .= ' AND a.entite_type = ?';
            $params[] = $f['entite_type'];
        }
        if (!empty($f['action'])) {
            $where .= ' AND a.action = ?';
            $params[] = $f['action'];
        }
        if (!empty($f['filiale_id'])) {
            $where .= ' AND a.filiale_id = ?';
            $params[] = (int) $f['filiale_id'];
        }
        if (!empty($f['date_debut'])) {
            $where .= ' AND a.created_at >= ?';
            $params[] = $f['date_debut'] . ' 00:00:00';
        }
        if (!empty($f['date_fin'])) {
            $where .= ' AND a.created_at <= ?';
            $params[] = $f['date_fin'] . ' 23:59:59';
        }
        if (!empty($f['q'])) {
            $where .= ' AND a.details LIKE ?';
            $params[] = '%' . $f['q'] . '%';
        }
        return $where;
    }

    /** Événements du journal, du plus récent au plus ancien, limités aux filiales données. */
    public static function recherche(array $filialeIds, array $f, int $limit, int $offset): array
    {
        $params = [];
        $where = self::whereRecherche($filialeIds, $f, $params);
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $stmt = Database::connection()->prepare(
            "SELECT a.*, u.nom AS utilisateur_nom, fi.nom AS filiale_nom FROM audit_logs a
             LEFT JOIN utilisateurs u ON u.id = a.utilisateur_id
             LEFT JOIN filiales fi ON fi.id = a.filiale_id
             WHERE $where
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function compter(array $filialeIds, array $f): int
    {
        $params = [];
        $where = self::whereRecherche($filialeIds, $f, $params);
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM audit_logs a WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Utilisateurs apparaissant dans le journal des filiales données (filtre). */
    public static function utilisateursDuJournal(array $filialeIds): array
    {
        if (empty($filialeIds)) {
            return [];
        }
        $in = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT DISTINCT u.id, u.nom FROM audit_logs a INNER JOIN utilisateurs u ON u.id = a.utilisateur_id
             WHERE a.filiale_id IN ($in) ORDER BY u.nom"
        );
        $stmt->execute(array_map('intval', $filialeIds));
        return $stmt->fetchAll();
    }

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

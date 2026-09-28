<?php

namespace App\Models;

use App\Core\Database;

class Offre
{
    public const STATUTS = [
        'recue' => 'Reçue',
        'retenue' => 'Retenue',
        'rejetee' => 'Écartée',
    ];

    public const CONFORMITE = [
        'conforme' => 'Conforme',
        'partielle' => 'Partiellement conforme',
        'non_conforme' => 'Non conforme',
    ];

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM offres WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forConsultation(int $consultationId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT o.*, fo.nom AS fournisseur_nom FROM offres o
             INNER JOIN fournisseurs fo ON fo.id = o.fournisseur_id
             WHERE o.consultation_id = ? ORDER BY o.created_at DESC'
        );
        $stmt->execute([$consultationId]);
        return $stmt->fetchAll();
    }

    /**
     * Toutes les offres reçues pour un dossier, tous fournisseurs confondus
     * — utilisé par le comparateur.
     */
    public static function forDossier(int $dossierId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT o.*, fo.nom AS fournisseur_nom, fo.note_prix, fo.note_qualite, fo.note_delai, fo.note_reactivite, fo.note_conformite, fo.note_engagements,
                    cf.reference AS consultation_reference
             FROM offres o
             INNER JOIN fournisseurs fo ON fo.id = o.fournisseur_id
             INNER JOIN consultations_fournisseur cf ON cf.id = o.consultation_id
             WHERE o.dossier_id = ?
             ORDER BY o.montant_total ASC'
        );
        $stmt->execute([$dossierId]);
        return $stmt->fetchAll();
    }

    public static function retenueForDossier(int $dossierId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT o.*, fo.nom AS fournisseur_nom FROM offres o
             INNER JOIN fournisseurs fo ON fo.id = o.fournisseur_id
             WHERE o.dossier_id = ? AND o.statut = 'retenue' LIMIT 1"
        );
        $stmt->execute([$dossierId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $consultation, array $data, array $items, ?int $createdBy = null): int
    {
        $pdo = Database::connection();
        $filiale = Filiale::find((int) $consultation['filiale_id']);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'offre');
        $reference = Compteur::formatReference('OFR', $numero);
        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO offres
                 (consultation_id, dossier_id, filiale_id, fournisseur_id, reference, montant_total, devise, incoterm_negocie, delai_livraison, validite_offre, statut, notes,
                  pays_origine, lieu_depart, quantite_min, disponibilite, poids_kg, nombre_colis, volume_m3, conformite_technique, conditions_paiement, garantie,
                  transport_montant, assurance_montant, emballage_montant, douane_montant, dedouanement_montant, autres_frais_montant,
                  created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $consultation['id'],
                $consultation['dossier_id'],
                $consultation['filiale_id'],
                $consultation['fournisseur_id'],
                $reference,
                (float) ($data['montant_total'] ?? 0),
                trim($data['devise'] ?? ''),
                trim($data['incoterm_negocie'] ?? ''),
                trim($data['delai_livraison'] ?? ''),
                $data['validite_offre'] ?: null,
                'recue',
                trim($data['notes'] ?? ''),
                trim($data['pays_origine'] ?? ''),
                trim($data['lieu_depart'] ?? ''),
                trim($data['quantite_min'] ?? ''),
                trim($data['disponibilite'] ?? ''),
                self::decimalOrNull($data['poids_kg'] ?? null),
                self::intOrNull($data['nombre_colis'] ?? null),
                self::decimalOrNull($data['volume_m3'] ?? null),
                trim($data['conformite_technique'] ?? '') ?: null,
                trim($data['conditions_paiement'] ?? '') ?: null,
                trim($data['garantie'] ?? ''),
                self::decimalOrNull($data['transport_montant'] ?? null),
                self::decimalOrNull($data['assurance_montant'] ?? null),
                self::decimalOrNull($data['emballage_montant'] ?? null),
                self::decimalOrNull($data['douane_montant'] ?? null),
                self::decimalOrNull($data['dedouanement_montant'] ?? null),
                self::decimalOrNull($data['autres_frais_montant'] ?? null),
                $createdBy,
                $now,
                $now,
            ]);
            $offreId = (int) $pdo->lastInsertId();

            foreach ($items as $item) {
                if (trim($item['designation'] ?? '') === '') {
                    continue;
                }
                OffreItem::create($offreId, $item);
            }

            // Une réponse a été reçue : la consultation associée est marquée en conséquence.
            $updateConsultation = $pdo->prepare(
                "UPDATE consultations_fournisseur SET statut = 'reponse_recue', updated_at = ? WHERE id = ?"
            );
            $updateConsultation->execute([$now, $consultation['id']]);

            $pdo->commit();
            return $offreId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function decimalOrNull($value): ?float
    {
        return ($value !== null && $value !== '') ? (float) $value : null;
    }

    private static function intOrNull($value): ?int
    {
        return ($value !== null && $value !== '') ? (int) $value : null;
    }

    /**
     * Coût rendu = prix marchandises + tous les frais complémentaires
     * détaillés sur l'offre. Reste dans la devise propre de l'offre — la
     * conversion générale multi-devises n'existe pas encore (Phase 4).
     */
    public static function coutRendu(array $offre): float
    {
        return round(
            (float) $offre['montant_total']
            + (float) ($offre['transport_montant'] ?? 0)
            + (float) ($offre['assurance_montant'] ?? 0)
            + (float) ($offre['emballage_montant'] ?? 0)
            + (float) ($offre['douane_montant'] ?? 0)
            + (float) ($offre['dedouanement_montant'] ?? 0)
            + (float) ($offre['autres_frais_montant'] ?? 0),
            2
        );
    }

    /**
     * Marque une offre comme retenue pour le dossier et rejette les autres
     * offres du même dossier (une seule offre retenue à la fois). Exige un
     * motif de décision, conservé sur l'offre retenue.
     */
    public static function retenir(int $dossierId, int $offreId, string $motif): void
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');
        $offreRetenue = self::find($offreId);

        $pdo->beginTransaction();
        try {
            // Toutes les autres offres du dossier (reçues ou précédemment retenues)
            // passent à "écartée" : une seule offre retenue à la fois par dossier.
            $reset = $pdo->prepare(
                "UPDATE offres SET statut = 'rejetee', updated_at = ? WHERE dossier_id = ? AND id != ? AND statut IN ('recue', 'retenue')"
            );
            $reset->execute([$now, $dossierId, $offreId]);

            $retain = $pdo->prepare(
                "UPDATE offres SET statut = 'retenue', motif_decision = ?, updated_at = ? WHERE id = ? AND dossier_id = ?"
            );
            $retain->execute([$motif, $now, $offreId, $dossierId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Hors transaction et volontairement best-effort : un souci ici ne
        // doit jamais remettre en cause la décision qui vient d'être validée
        // ci-dessus. Les collaborateurs rattachés à un fournisseur non
        // retenu n'ont plus de raison de suivre ce dossier — on les retire
        // et on les prévient (cloche + email).
        if ($offreRetenue) {
            try {
                $retires = DossierCollaborateur::retirerNonRetenus($dossierId, (int) $offreRetenue['fournisseur_id']);
                foreach ($retires as $retire) {
                    $utilisateur = Utilisateur::find((int) $retire['utilisateur_id']);
                    if (!$utilisateur) {
                        continue;
                    }
                    Notification::notifier(
                        $utilisateur,
                        (int) $retire['filiale_id'],
                        'collaborateur_retire',
                        'Retiré du suivi d’un dossier',
                        "Le fournisseur " . $retire['fournisseur_nom'] . " n'a pas été retenu sur ce dossier : vous n'avez plus besoin de le suivre.",
                        '/index.php?r=dossiers/' . $dossierId,
                        'dossier',
                        $dossierId
                    );
                }
            } catch (\Throwable $e) {
                // best-effort : ne fait jamais échouer la décision ci-dessus.
            }
        }
    }

    /**
     * Revient sur une décision déjà prise : remet toutes les offres du
     * dossier à "reçue" pour permettre un nouveau choix. Réservé aux
     * dirigeants côté contrôleur ; le motif est tracé dans l'audit, pas sur
     * l'offre (puisque plusieurs offres sont concernées à la fois).
     */
    public static function revenirSurDecision(int $dossierId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE offres SET statut = 'recue', updated_at = ? WHERE dossier_id = ? AND statut IN ('retenue', 'rejetee')"
        );
        $stmt->execute([date('Y-m-d H:i:s'), $dossierId]);
    }

    public static function userCanAccess(array $user, array $offre): bool
    {
        return Filiale::userCanAccess($user, (int) $offre['filiale_id']);
    }

    /**
     * Offres reçues pas encore analysées (décision "retenue"/"écartée" pas
     * encore prise) — bloc "Achats" du tableau de bord.
     */
    public static function aAnalyserCount(array $user): int
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM offres WHERE statut = 'recue' AND filiale_id IN ($placeholders)"
        );
        $stmt->execute($filialeIds);
        return (int) $stmt->fetchColumn();
    }

    public static function aAnalyserFor(array $user, int $limite = 5): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT o.*, d.id AS dossier_id_reel, d.reference AS dossier_reference, d.objet AS dossier_objet, fo.nom AS fournisseur_nom
             FROM offres o
             INNER JOIN dossiers d ON d.id = o.dossier_id
             INNER JOIN fournisseurs fo ON fo.id = o.fournisseur_id
             WHERE o.statut = 'recue' AND o.filiale_id IN ($placeholders)
             ORDER BY o.created_at ASC LIMIT " . (int) $limite
        );
        $stmt->execute($filialeIds);
        return $stmt->fetchAll();
    }

    /**
     * Offres reçues et toujours pas analysées depuis plus de $seuilJours
     * jours — sous-ensemble "à risque" de aAnalyserFor(), pour le bloc
     * Alertes du tableau de bord.
     */
    public static function enAttenteDepuis(array $user, int $seuilJours, int $limite = 10): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $seuilDate = date('Y-m-d H:i:s', strtotime("-$seuilJours days"));
        $stmt = Database::connection()->prepare(
            "SELECT o.*, d.id AS dossier_id_reel, d.reference AS dossier_reference, d.objet AS dossier_objet, fo.nom AS fournisseur_nom
             FROM offres o
             INNER JOIN dossiers d ON d.id = o.dossier_id
             INNER JOIN fournisseurs fo ON fo.id = o.fournisseur_id
             WHERE o.statut = 'recue' AND o.filiale_id IN ($placeholders) AND o.created_at <= ?
             ORDER BY o.created_at ASC LIMIT " . (int) $limite
        );
        $stmt->execute(array_merge($filialeIds, [$seuilDate]));
        return $stmt->fetchAll();
    }
}

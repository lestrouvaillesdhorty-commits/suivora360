<?php

namespace App\Models;

use App\Core\Database;

class Offre
{
    public const STATUTS = [
        'recue' => 'Reçue',
        'retenue' => 'Retenue',
        'rejetee' => 'Écartée',
        // [ajouté 06/10, migrate_v17] Une offre passe à "remplacee" quand le
        // fournisseur en renvoie une version révisée sur la même
        // consultation — l'ancienne reste en base (historique des
        // versions) mais sort des listes "offres actuelles".
        'remplacee' => 'Remplacée (version antérieure)',
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
    /**
     * Offres "courantes" d'un dossier — une seule ligne par offre révisée
     * (la dernière version), les versions antérieures ("remplacee", voir
     * migrate_v17) sont exclues. Utilisé par la Synthèse, le sous-onglet
     * "Offres reçues" et le Comparateur : comparer une ancienne version à
     * côté de sa propre révision n'aurait pas de sens.
     */
    public static function forDossier(int $dossierId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT o.*, fo.nom AS fournisseur_nom, fo.note_prix, fo.note_qualite, fo.note_delai, fo.note_reactivite, fo.note_conformite, fo.note_engagements,
                    cf.reference AS consultation_reference
             FROM offres o
             INNER JOIN fournisseurs fo ON fo.id = o.fournisseur_id
             INNER JOIN consultations_fournisseur cf ON cf.id = o.consultation_id
             WHERE o.dossier_id = ? AND o.statut != 'remplacee'
             ORDER BY o.montant_total ASC"
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

    /**
     * $offrePrecedente : null pour une première réponse (v1, nouvelle
     * référence via Compteur) ; sinon l'offre qu'on révise (le fournisseur
     * a renvoyé une offre modifiée sur la même consultation — demande
     * explicite de Marie Laure le 06/10) — la version reprend alors la
     * même référence, incrémente "version", et l'ancienne ligne passe au
     * statut "remplacee" plutôt que d'être écrasée (historique conservé).
     */
    public static function create(array $consultation, array $data, array $items, ?int $createdBy = null, ?array $offrePrecedente = null): int
    {
        $pdo = Database::connection();
        if ($offrePrecedente) {
            $reference = $offrePrecedente['reference'];
            $version = (int) $offrePrecedente['version'] + 1;
            $offrePrecedenteId = (int) $offrePrecedente['id'];
        } else {
            $filiale = Filiale::find((int) $consultation['filiale_id']);
            $numero = Compteur::next((int) $filiale['organisation_id'], 'offre');
            $reference = Compteur::formatReference('OFR', $numero);
            $version = 1;
            $offrePrecedenteId = null;
        }
        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO offres
                 (consultation_id, dossier_id, filiale_id, fournisseur_id, reference, montant_total, devise, incoterm_negocie, delai_livraison, validite_offre, statut, notes,
                  pays_origine, lieu_depart, quantite_min, disponibilite, poids_kg, nombre_colis, volume_m3, conformite_technique, conditions_paiement, garantie,
                  transport_montant, assurance_montant, emballage_montant, douane_montant, dedouanement_montant, autres_frais_montant,
                  mode_transport, perimetre_mission, version, offre_precedente_id,
                  created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
                trim($data['mode_transport'] ?? '') ?: null,
                trim($data['perimetre_mission'] ?? '') ?: null,
                $version,
                $offrePrecedenteId,
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

            // [10/10] Offre saisie manuellement : on garde la source (web, catalogue, téléphone…) et son lien.
            if (!empty($data['source_type']) && self::colonnesSource()) {
                $pdo->prepare('UPDATE offres SET source_type = ?, source_url = ?, source_date = ? WHERE id = ?')
                    ->execute([$data['source_type'], trim($data['source_url'] ?? '') ?: null, ($data['source_date'] ?? '') ?: null, $offreId]);
            }

            if ($offrePrecedenteId !== null) {
                $pdo->prepare("UPDATE offres SET statut = 'remplacee', updated_at = ? WHERE id = ?")
                    ->execute([$now, $offrePrecedenteId]);
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

    /**
     * [10/10] Correction d'une offre saisie (erreur de frappe, prix mis à jour…) SANS créer de nouvelle
     * version : mêmes champs que create(), le fournisseur, la consultation et la référence ne changent pas.
     */
    public static function modifier(int $id, array $data, array $items): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE offres SET montant_total = ?, devise = ?, incoterm_negocie = ?, delai_livraison = ?, validite_offre = ?, notes = ?,
                  pays_origine = ?, lieu_depart = ?, quantite_min = ?, disponibilite = ?, poids_kg = ?, nombre_colis = ?, volume_m3 = ?,
                  conformite_technique = ?, conditions_paiement = ?, garantie = ?,
                  transport_montant = ?, assurance_montant = ?, emballage_montant = ?, douane_montant = ?, dedouanement_montant = ?, autres_frais_montant = ?,
                  mode_transport = ?, perimetre_mission = ?, updated_at = ?
                 WHERE id = ?'
            )->execute([
                (float) ($data['montant_total'] ?? 0),
                trim($data['devise'] ?? ''),
                trim($data['incoterm_negocie'] ?? ''),
                trim($data['delai_livraison'] ?? ''),
                ($data['validite_offre'] ?? '') ?: null,
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
                trim($data['mode_transport'] ?? '') ?: null,
                trim($data['perimetre_mission'] ?? '') ?: null,
                date('Y-m-d H:i:s'),
                $id,
            ]);
            if (!empty($data['source_type']) && self::colonnesSource()) {
                $pdo->prepare('UPDATE offres SET source_type = ?, source_url = ?, source_date = ? WHERE id = ?')
                    ->execute([$data['source_type'], trim($data['source_url'] ?? '') ?: null, ($data['source_date'] ?? '') ?: null, $id]);
            }
            $pdo->prepare('DELETE FROM offre_items WHERE offre_id = ?')->execute([$id]);
            foreach ($items as $item) {
                if (trim($item['designation'] ?? '') !== '') {
                    OffreItem::create($id, $item);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public const SOURCES = [
        'web' => 'Site web / marketplace',
        'catalogue' => 'Catalogue ou tarif',
        'telephone' => 'Téléphone / WhatsApp',
        'autre' => 'Autre',
    ];

    /** Les colonnes source_type / source_url existent-elles (migration v27 passée) ? */
    public static function colonnesSource(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::connection()->query('SELECT source_type, source_url, source_date FROM offres LIMIT 1');
                $ok = true;
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * Historique des versions d'une offre (de la plus ancienne à la plus
     * récente), en remontant la chaîne offre_precedente_id. Utilisé pour
     * afficher "v1 → v2 → v3" sur le sous-onglet Offres reçues.
     */
    public static function versions(array $offre): array
    {
        $chaine = [$offre];
        $courante = $offre;
        while (!empty($courante['offre_precedente_id'])) {
            $precedente = self::find((int) $courante['offre_precedente_id']);
            if (!$precedente) {
                break;
            }
            $chaine[] = $precedente;
            $courante = $precedente;
        }
        return array_reverse($chaine);
    }

    /**
     * [ajouté 06/10, étape 2 du découpage Dossiers] "Transport inclus" du
     * sous-onglet "Offres reçues" — dérivé de l'incoterm négocié plutôt que
     * stocké (décision Marie Laure, 06/10) : les incoterms où le vendeur
     * prend le transport à sa charge (CFR/CIF/CPT/CIP/DAP/DPU/DDP) comptent
     * comme "inclus" ; ceux où l'acheteur l'organise/le paie lui-même
     * (EXW/FCA/FAS/FOB) comptent comme "non inclus". Retourne null si
     * l'incoterm n'est pas renseigné ou non reconnu (affichage "Non
     * renseigné" plutôt qu'un badge Oui/Non trompeur).
     */
    public const INCOTERMS_TRANSPORT_NON_INCLUS = ['EXW', 'FCA', 'FAS', 'FOB'];
    public const INCOTERMS_TRANSPORT_INCLUS = ['CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'];

    public static function transportInclus(?string $incoterm): ?bool
    {
        $incoterm = strtoupper(trim((string) $incoterm));
        if (in_array($incoterm, self::INCOTERMS_TRANSPORT_INCLUS, true)) {
            return true;
        }
        if (in_array($incoterm, self::INCOTERMS_TRANSPORT_NON_INCLUS, true)) {
            return false;
        }
        return null;
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
    /**
     * [ajouté 03/10] $filialeIds/$activite : filtres optionnels du switcher
     * Tableau de bord (voir DashboardController) — null = comportement
     * d'origine. Jointure sur `demandes` (via le Dossier) dès que
     * `$activite` est fourni, puisque l'Activité vit sur la Demande d'origine.
     */
    public static function aAnalyserCount(array $user, ?array $filialeIds = null, ?string $activite = null): int
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT COUNT(*) FROM offres o WHERE o.statut = 'recue' AND o.filiale_id IN ($placeholders)";
        $params = $filialeIds;
        if ($activite) {
            $sql = "SELECT COUNT(*) FROM offres o
                    INNER JOIN dossiers d ON d.id = o.dossier_id
                    INNER JOIN demandes dm ON dm.id = d.demande_id
                    WHERE o.statut = 'recue' AND o.filiale_id IN ($placeholders) AND dm.activite = ?";
            $params[] = $activite;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function aAnalyserFor(array $user, int $limite = 5, ?array $filialeIds = null, ?string $activite = null): array
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT o.*, d.id AS dossier_id_reel, d.reference AS dossier_reference, d.objet AS dossier_objet, d.responsable_id, fo.nom AS fournisseur_nom
                FROM offres o
                INNER JOIN dossiers d ON d.id = o.dossier_id
                INNER JOIN fournisseurs fo ON fo.id = o.fournisseur_id";
        $params = $filialeIds;
        if ($activite) {
            $sql .= ' INNER JOIN demandes dm ON dm.id = d.demande_id';
        }
        $sql .= " WHERE o.statut = 'recue' AND o.filiale_id IN ($placeholders)";
        if ($activite) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $activite;
        }
        $sql .= ' ORDER BY o.created_at ASC LIMIT ' . (int) $limite;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Offres reçues et toujours pas analysées depuis plus de $seuilJours
     * jours — sous-ensemble "à risque" de aAnalyserFor(), pour le bloc
     * Alertes du tableau de bord.
     */
    public static function enAttenteDepuis(array $user, int $seuilJours, int $limite = 10, ?array $filialeIds = null, ?string $activite = null): array
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $seuilDate = date('Y-m-d H:i:s', strtotime("-$seuilJours days"));
        $sql = "SELECT o.*, d.id AS dossier_id_reel, d.reference AS dossier_reference, d.objet AS dossier_objet, fo.nom AS fournisseur_nom
                FROM offres o
                INNER JOIN dossiers d ON d.id = o.dossier_id
                INNER JOIN fournisseurs fo ON fo.id = o.fournisseur_id";
        if ($activite) {
            $sql .= ' INNER JOIN demandes dm ON dm.id = d.demande_id';
        }
        $sql .= " WHERE o.statut = 'recue' AND o.filiale_id IN ($placeholders) AND o.created_at <= ?";
        $params = array_merge($filialeIds, [$seuilDate]);
        if ($activite) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $activite;
        }
        $sql .= ' ORDER BY o.created_at ASC LIMIT ' . (int) $limite;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

<?php

namespace App\Models;

use App\Core\Database;

class Commande
{
    public const ETAPES_STEPS = [
        'paiement' => 'Paiement',
        'expedition' => 'Expédition',
        'douane' => 'Dédouanement',
        'livraison' => 'Livraison',
        'solde' => 'Solde',
    ];

    public const STEP_STATUTS = [
        'a_faire' => 'À faire',
        'en_cours' => 'En cours',
        'termine' => 'Terminé',
    ];

    /**
     * Libellés spécifiques par étape et par statut — plus explicites que le
     * générique "À faire / En cours / Terminé", qui créait de la confusion
     * (notamment pour la livraison : impossible de savoir si la marchandise
     * était à préparer, expédiée ou reçue).
     */
    public const STEP_STATUT_LABELS = [
        'paiement' => ['a_faire' => 'Paiement à faire', 'en_cours' => 'Paiement en cours', 'termine' => 'Payé'],
        'expedition' => ['a_faire' => 'À préparer', 'en_cours' => 'En préparation', 'termine' => 'Expédiée'],
        'douane' => ['a_faire' => 'Dédouanement à faire', 'en_cours' => 'En cours de dédouanement', 'termine' => 'Dédouané'],
        'livraison' => ['a_faire' => 'À expédier', 'en_cours' => 'En transit', 'termine' => 'Reçue'],
        'solde' => ['a_faire' => 'Solde à payer', 'en_cours' => 'Solde en cours', 'termine' => 'Soldé'],
    ];

    public static function libelleStatutEtape(string $etape, string $statut): string
    {
        return self::STEP_STATUT_LABELS[$etape][$statut] ?? (self::STEP_STATUTS[$statut] ?? $statut);
    }

    /**
     * Avancement automatique (0-100) : chaque étape terminée compte pour
     * une part égale, une étape "en cours" compte pour moitié — pas de
     * ressaisie manuelle du pourcentage.
     */
    public static function progression(array $steps): int
    {
        if (empty($steps)) {
            return 0;
        }
        $total = count($steps);
        $poids = 0.0;
        foreach ($steps as $step) {
            if ($step['statut'] === 'termine') {
                $poids += 1.0;
            } elseif ($step['statut'] === 'en_cours') {
                $poids += 0.5;
            }
        }
        return (int) round(($poids / $total) * 100);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM commandes WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByDossier(int $dossierId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM commandes WHERE dossier_id = ?');
        $stmt->execute([$dossierId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Crée la commande à partir d'une cotation, avec les étapes de suivi
     * opérationnel standard (paiement, expédition, douane, livraison, solde).
     */
    public static function create(int $dossierId, int $filialeId, int $cotationId): int
    {
        $existing = self::findByDossier($dossierId);
        if ($existing) {
            return (int) $existing['id'];
        }

        $pdo = Database::connection();
        $filiale = Filiale::find($filialeId);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'commande');
        $reference = Compteur::formatReference('CMD', $numero);
        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO commandes (dossier_id, filiale_id, cotation_id, reference, statut, etape, notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$dossierId, $filialeId, $cotationId, $reference, 'en_cours', 'paiement', '', $now, $now]);
            $commandeId = (int) $pdo->lastInsertId();

            $ordre = 0;
            $insertStep = $pdo->prepare(
                'INSERT INTO commande_steps (commande_id, libelle, statut, date_prevue, date_reelle, notes, ordre, created_at, updated_at)
                 VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, ?)'
            );
            foreach (self::ETAPES_STEPS as $code => $label) {
                $ordre++;
                $insertStep->execute([$commandeId, $code, $ordre === 1 ? 'en_cours' : 'a_faire', '', $ordre, $now, $now]);
            }

            $pdo->commit();
            return $commandeId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function steps(int $commandeId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM commande_steps WHERE commande_id = ? ORDER BY ordre');
        $stmt->execute([$commandeId]);
        return $stmt->fetchAll();
    }

    public static function updateStep(int $stepId, int $commandeId, string $statut, ?string $dateReelle, string $notes): void
    {
        if (!array_key_exists($statut, self::STEP_STATUTS)) {
            throw new \InvalidArgumentException('Statut d\'étape invalide.');
        }
        $now = date('Y-m-d H:i:s');
        $stmt = Database::connection()->prepare(
            'UPDATE commande_steps SET statut = ?, date_reelle = ?, notes = ?, updated_at = ? WHERE id = ? AND commande_id = ?'
        );
        $stmt->execute([$statut, $dateReelle ?: null, trim($notes), $now, $stepId, $commandeId]);

        self::refreshEtape($commandeId);
    }

    /**
     * Recalcule l'étape courante de la commande (première étape non terminée)
     * et son statut global (terminée si toutes les étapes sont terminées).
     */
    private static function refreshEtape(int $commandeId): void
    {
        $steps = self::steps($commandeId);
        $etapeCourante = null;
        $toutesTerminees = true;
        foreach ($steps as $step) {
            if ($step['statut'] !== 'termine') {
                $toutesTerminees = false;
                if ($etapeCourante === null) {
                    $etapeCourante = $step['libelle'];
                }
            }
        }
        $etape = $toutesTerminees ? 'terminee' : ($etapeCourante ?? 'terminee');
        $statut = $toutesTerminees ? 'terminee' : 'en_cours';

        $stmt = Database::connection()->prepare('UPDATE commandes SET etape = ?, statut = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$etape, $statut, date('Y-m-d H:i:s'), $commandeId]);
    }

    public static function updateSuivi(int $commandeId, string $prochaineAction, ?string $dateRelance): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE commandes SET prochaine_action = ?, date_relance = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([trim($prochaineAction), $dateRelance ?: null, date('Y-m-d H:i:s'), $commandeId]);
    }

    /**
     * Champs additionnels selon le type de dossier (section 9 de la feuille
     * de route) : tracking/dates de transit pour Transport/Logistique,
     * livrables pour Prestation entreprise. Pas de nouvelle table — mêmes
     * colonnes optionnelles sur `commandes`, affichées conditionnellement.
     */
    public static function updateLogistique(int $commandeId, ?string $tracking, ?string $dateDebut, ?string $dateFin): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE commandes SET tracking_numero = ?, date_transit_debut = ?, date_transit_fin = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([trim((string) $tracking) ?: null, $dateDebut ?: null, $dateFin ?: null, date('Y-m-d H:i:s'), $commandeId]);
    }

    public static function updateLivrables(int $commandeId, string $livrables): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE commandes SET livrables = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([trim($livrables) ?: null, date('Y-m-d H:i:s'), $commandeId]);
    }

    public static function estEnRetard(array $commande): bool
    {
        return $commande['statut'] !== 'terminee'
            && !empty($commande['date_relance'])
            && $commande['date_relance'] < date('Y-m-d');
    }

    /**
     * Commandes en cours dont la date de relance est dépassée — pour le
     * bloc Alertes du tableau de bord (même règle que estEnRetard(), en
     * requête directe pour lister les cas plutôt que de les tester un par un).
     */
    /**
     * [ajouté 03/10] $filialeIds/$activite : filtres optionnels du switcher
     * Tableau de bord (voir DashboardController) — null = comportement
     * d'origine. Jointure sur `demandes` (via le Dossier) dès que
     * `$activite` est fourni.
     */
    public static function enRetardFor(array $user, int $limite = 10, ?array $filialeIds = null, ?string $activite = null): array
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT c.*, d.reference AS dossier_reference, d.objet AS dossier_objet
                FROM commandes c
                INNER JOIN dossiers d ON d.id = c.dossier_id";
        if ($activite) {
            $sql .= ' INNER JOIN demandes dm ON dm.id = d.demande_id';
        }
        $sql .= " WHERE c.statut = 'en_cours' AND c.filiale_id IN ($placeholders)
                  AND c.date_relance IS NOT NULL AND c.date_relance < ?";
        $params = array_merge($filialeIds, [date('Y-m-d')]);
        if ($activite) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $activite;
        }
        $sql .= ' ORDER BY c.date_relance ASC LIMIT ' . (int) $limite;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function userCanAccess(array $user, array $commande): bool
    {
        return Filiale::userCanAccess($user, (int) $commande['filiale_id']);
    }

    /**
     * Commandes en cours (exécution) — bloc "Exécution" du tableau de bord.
     */
    public static function enCoursCount(array $user, ?array $filialeIds = null, ?string $activite = null): int
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $params = $filialeIds;
        if ($activite) {
            $sql = "SELECT COUNT(*) FROM commandes c
                    INNER JOIN dossiers d ON d.id = c.dossier_id
                    INNER JOIN demandes dm ON dm.id = d.demande_id
                    WHERE c.statut = 'en_cours' AND c.filiale_id IN ($placeholders) AND dm.activite = ?";
            $params[] = $activite;
        } else {
            $sql = "SELECT COUNT(*) FROM commandes WHERE statut = 'en_cours' AND filiale_id IN ($placeholders)";
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function enCoursFor(array $user, int $limite = 5, ?array $filialeIds = null, ?string $activite = null): array
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT c.*, d.reference AS dossier_reference, d.objet AS dossier_objet, d.responsable_id
                FROM commandes c
                INNER JOIN dossiers d ON d.id = c.dossier_id";
        $params = $filialeIds;
        if ($activite) {
            $sql .= ' INNER JOIN demandes dm ON dm.id = d.demande_id';
        }
        $sql .= " WHERE c.statut = 'en_cours' AND c.filiale_id IN ($placeholders)";
        if ($activite) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $activite;
        }
        $sql .= ' ORDER BY (c.date_relance IS NULL), c.date_relance ASC LIMIT ' . (int) $limite;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Commandes actuellement à l'étape "livraison" (en_cours, pas encore
     * terminée) — bloc "Livraisons à suivre" du tableau de bord. La
     * destination vient de demandes.destination_pays (seul champ de
     * destination existant dans le schéma) et le fournisseur de l'offre
     * retenue du dossier ; l'ETA reprend commande_steps.date_prevue pour
     * l'étape "livraison" (aucun champ ETA dédié n'existe).
     */
    public static function livraisonsEnCoursCount(array $user, ?array $filialeIds = null, ?string $activite = null): int
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $params = $filialeIds;
        if ($activite) {
            $sql = "SELECT COUNT(*) FROM commandes c
                    INNER JOIN dossiers d ON d.id = c.dossier_id
                    INNER JOIN demandes dm ON dm.id = d.demande_id
                    WHERE c.statut = 'en_cours' AND c.etape = 'livraison' AND c.filiale_id IN ($placeholders) AND dm.activite = ?";
            $params[] = $activite;
        } else {
            $sql = "SELECT COUNT(*) FROM commandes WHERE statut = 'en_cours' AND etape = 'livraison' AND filiale_id IN ($placeholders)";
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function livraisonsEnCoursFor(array $user, int $limite = 5, ?array $filialeIds = null, ?string $activite = null): array
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT c.*, d.reference AS dossier_reference, d.objet AS dossier_objet, dm.destination_pays,
                       cs.date_prevue AS livraison_prevue, cs.date_reelle AS livraison_reelle, cs.statut AS livraison_statut
                FROM commandes c
                INNER JOIN dossiers d ON d.id = c.dossier_id
                INNER JOIN demandes dm ON dm.id = d.demande_id
                LEFT JOIN commande_steps cs ON cs.commande_id = c.id AND cs.libelle = 'livraison'
                WHERE c.statut = 'en_cours' AND c.etape = 'livraison' AND c.filiale_id IN ($placeholders)";
        $params = $filialeIds;
        if ($activite) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $activite;
        }
        $sql .= ' ORDER BY (cs.date_prevue IS NULL), cs.date_prevue ASC LIMIT ' . (int) $limite;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $offreRetenue = Offre::retenueForDossier((int) $row['dossier_id']);
            $row['fournisseur_nom'] = $offreRetenue['fournisseur_nom'] ?? null;
        }
        unset($row);
        return $rows;
    }
}

<?php

namespace App\Models;

use App\Core\Database;

/**
 * Module Pilotage (feuille de route, point 5) : analyse par période,
 * volontairement séparée du Tableau de bord qui doit rester orienté action
 * rapide (voir point 4). Ce sont des agrégats transverses à plusieurs
 * modèles (Demande/Dossier/Cotation) — pas la responsabilité d'un seul
 * d'entre eux, d'où une classe dédiée.
 *
 * "Activité" reste un simple champ à liste de valeurs sur la Demande (pas
 * un module séparé — voir Demande::ACTIVITES). Le Dossier n'a pas sa
 * propre colonne activite : on la retrouve toujours via une jointure sur
 * demande_id (dm.id = d.demande_id).
 *
 * Filtres reconnus (tableau $filters) : activite, responsable_id,
 * date_debut, date_fin (bornes sur demandes.recue_le / dossiers.created_at
 * / cotations.created_at selon la table agrégée).
 */
class Pilotage
{
    public static function kpis(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return self::kpisVides();
        }

        $demandesRecues = self::countDemandes($filialeIds, $filters);
        $dossiersCrees = self::countDossiers($filialeIds, $filters);
        [$valeurActive, $margePrevisionnelle] = self::valeurEtMarge($filialeIds, $filters);

        return [
            'demandes_recues' => $demandesRecues,
            'dossiers_crees' => $dossiersCrees,
            'taux_transformation' => $demandesRecues > 0 ? round($dossiersCrees / $demandesRecues * 100, 1) : 0.0,
            'valeur_active' => $valeurActive,
            'marge_previsionnelle' => $margePrevisionnelle,
            'dossiers_en_retard' => self::countDossiersEnRetard($filialeIds, $filters),
        ];
    }

    private static function kpisVides(): array
    {
        return [
            'demandes_recues' => 0, 'dossiers_crees' => 0, 'taux_transformation' => 0.0,
            'valeur_active' => 0.0, 'marge_previsionnelle' => 0.0, 'dossiers_en_retard' => 0,
        ];
    }

    /** @return array{0:string,1:array} */
    private static function whereDemandes(array $filialeIds, array $filters, bool $avecActivite = true): array
    {
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "filiale_id IN ($placeholders)";
        $params = $filialeIds;
        if ($avecActivite && !empty($filters['activite'])) {
            $sql .= ' AND activite = ?';
            $params[] = $filters['activite'];
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND responsable_id = ?';
            $params[] = $filters['responsable_id'];
        }
        if (!empty($filters['date_debut'])) {
            $sql .= ' AND recue_le >= ?';
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $sql .= ' AND recue_le <= ?';
            $params[] = $filters['date_fin'];
        }
        return [$sql, $params];
    }

    private static function countDemandes(array $filialeIds, array $filters): int
    {
        [$where, $params] = self::whereDemandes($filialeIds, $filters);
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM demandes WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Filtres pour une requête sur dossiers d, jointe à demandes dm
     * (dm.id = d.demande_id) — c'est cette jointure qui donne l'activité.
     */
    private static function whereDossiers(array $filialeIds, array $filters, bool $avecActivite = true): array
    {
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "d.filiale_id IN ($placeholders)";
        $params = $filialeIds;
        if ($avecActivite && !empty($filters['activite'])) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $filters['activite'];
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND d.responsable_id = ?';
            $params[] = $filters['responsable_id'];
        }
        if (!empty($filters['date_debut'])) {
            $sql .= ' AND d.created_at >= ?';
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $sql .= ' AND d.created_at <= ?';
            $params[] = $filters['date_fin'] . ' 23:59:59';
        }
        return [$sql, $params];
    }

    private static function countDossiers(array $filialeIds, array $filters): int
    {
        [$where, $params] = self::whereDossiers($filialeIds, $filters);
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM dossiers d INNER JOIN demandes dm ON dm.id = d.demande_id WHERE $where"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private static function countDossiersEnRetard(array $filialeIds, array $filters): int
    {
        [$where, $params] = self::whereDossiers($filialeIds, $filters);
        $where .= " AND d.statut = 'actif' AND d.echeance IS NOT NULL AND d.echeance < ?";
        $params[] = date('Y-m-d');
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM dossiers d INNER JOIN demandes dm ON dm.id = d.demande_id WHERE $where"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Cotations acceptées (valeur active / marge prévisionnelle), jointes à
     * dossiers + demandes pour filiale/activité/responsable. La période
     * s'applique à la date d'acceptation retenue ici comme la date de
     * création de la cotation (aucun champ "acceptee_le" dédié aujourd'hui).
     * Somme brute, toutes devises confondues (pas encore de conversion
     * multi-devises dans l'app — même limite déjà documentée sur Offre).
     */
    private static function whereCotations(array $filialeIds, array $filters, bool $avecActivite = true): array
    {
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "co.filiale_id IN ($placeholders) AND co.statut = 'acceptee'";
        $params = $filialeIds;
        if ($avecActivite && !empty($filters['activite'])) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $filters['activite'];
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND d.responsable_id = ?';
            $params[] = $filters['responsable_id'];
        }
        if (!empty($filters['date_debut'])) {
            $sql .= ' AND co.created_at >= ?';
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $sql .= ' AND co.created_at <= ?';
            $params[] = $filters['date_fin'] . ' 23:59:59';
        }
        return [$sql, $params];
    }

    private static function valeurEtMarge(array $filialeIds, array $filters): array
    {
        [$where, $params] = self::whereCotations($filialeIds, $filters);
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(co.montant_total),0) AS valeur, COALESCE(SUM(co.marge_montant),0) AS marge
             FROM cotations co
             INNER JOIN dossiers d ON d.id = co.dossier_id
             INNER JOIN demandes dm ON dm.id = d.demande_id
             WHERE $where"
        );
        $stmt->execute($params);
        $row = $stmt->fetch();
        return [(float) $row['valeur'], (float) $row['marge']];
    }

    /**
     * Répartition par activité — respecte période/responsable, ignore le
     * filtre activité lui-même puisque c'est l'axe de cette répartition.
     */
    public static function parActivite(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }

        [$whereD, $paramsD] = self::whereDemandes($filialeIds, $filters, false);
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(activite, '') AS a, COUNT(*) AS n FROM demandes WHERE $whereD GROUP BY COALESCE(activite, '')"
        );
        $stmt->execute($paramsD);
        $demandesParA = [];
        foreach ($stmt->fetchAll() as $row) {
            $demandesParA[$row['a']] = (int) $row['n'];
        }

        [$whereDo, $paramsDo] = self::whereDossiers($filialeIds, $filters, false);
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(dm.activite, '') AS a, COUNT(*) AS n
             FROM dossiers d INNER JOIN demandes dm ON dm.id = d.demande_id
             WHERE $whereDo GROUP BY COALESCE(dm.activite, '')"
        );
        $stmt->execute($paramsDo);
        $dossiersParA = [];
        foreach ($stmt->fetchAll() as $row) {
            $dossiersParA[$row['a']] = (int) $row['n'];
        }

        [$whereC, $paramsC] = self::whereCotations($filialeIds, $filters, false);
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(dm.activite, '') AS a, COALESCE(SUM(co.montant_total),0) AS valeur, COALESCE(SUM(co.marge_montant),0) AS marge
             FROM cotations co
             INNER JOIN dossiers d ON d.id = co.dossier_id
             INNER JOIN demandes dm ON dm.id = d.demande_id
             WHERE $whereC GROUP BY COALESCE(dm.activite, '')"
        );
        $stmt->execute($paramsC);
        $valeurParA = [];
        foreach ($stmt->fetchAll() as $row) {
            $valeurParA[$row['a']] = ['valeur' => (float) $row['valeur'], 'marge' => (float) $row['marge']];
        }

        $activites = Demande::ACTIVITES;
        foreach (array_keys($demandesParA + $dossiersParA + $valeurParA) as $a) {
            if ($a !== '' && !in_array($a, $activites, true)) {
                $activites[] = $a;
            }
        }
        $activites[] = ''; // "Non renseigné", toujours en dernier

        $rows = [];
        foreach ($activites as $a) {
            $demandes = $demandesParA[$a] ?? 0;
            $dossiers = $dossiersParA[$a] ?? 0;
            if ($demandes === 0 && $dossiers === 0) {
                continue;
            }
            $rows[] = [
                'activite' => $a === '' ? 'Non renseigné' : $a,
                'demandes' => $demandes,
                'dossiers' => $dossiers,
                'taux_transformation' => $demandes > 0 ? round($dossiers / $demandes * 100, 1) : 0.0,
                'valeur_active' => $valeurParA[$a]['valeur'] ?? 0.0,
                'marge_previsionnelle' => $valeurParA[$a]['marge'] ?? 0.0,
            ];
        }
        return $rows;
    }

    /**
     * Répartition par responsable — respecte période/activité, ignore le
     * filtre responsable lui-même puisque c'est l'axe de cette répartition.
     */
    public static function parResponsable(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $f = $filters;
        unset($f['responsable_id']);

        [$whereD, $paramsD] = self::whereDemandes($filialeIds, $f);
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(responsable_id, 0) AS r, COUNT(*) AS n FROM demandes WHERE $whereD GROUP BY COALESCE(responsable_id, 0)"
        );
        $stmt->execute($paramsD);
        $demandesParR = [];
        foreach ($stmt->fetchAll() as $row) {
            $demandesParR[(int) $row['r']] = (int) $row['n'];
        }

        [$whereDo, $paramsDo] = self::whereDossiers($filialeIds, $f);
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(d.responsable_id, 0) AS r, COUNT(*) AS n
             FROM dossiers d INNER JOIN demandes dm ON dm.id = d.demande_id
             WHERE $whereDo GROUP BY COALESCE(d.responsable_id, 0)"
        );
        $stmt->execute($paramsDo);
        $dossiersParR = [];
        foreach ($stmt->fetchAll() as $row) {
            $dossiersParR[(int) $row['r']] = (int) $row['n'];
        }

        $ids = array_unique(array_merge(array_keys($demandesParR), array_keys($dossiersParR)));
        $rows = [];
        foreach ($ids as $rid) {
            $demandes = $demandesParR[$rid] ?? 0;
            $dossiers = $dossiersParR[$rid] ?? 0;
            $rows[] = [
                'responsable' => $rid > 0 ? Utilisateur::nameOf($rid) : 'Non assigné',
                'demandes' => $demandes,
                'dossiers' => $dossiers,
                'taux_transformation' => $demandes > 0 ? round($dossiers / $demandes * 100, 1) : 0.0,
            ];
        }
        usort($rows, fn($a, $b) => $b['demandes'] <=> $a['demandes']);
        return $rows;
    }

    /**
     * Évolution du nombre de demandes reçues, mois par mois, sur les N
     * derniers mois (respecte activité/responsable ; ignore la période,
     * puisque c'est elle-même une fenêtre temporelle).
     */
    public static function evolutionMensuelle(array $user, array $filters, int $mois = 6): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $f = $filters;
        unset($f['date_debut'], $f['date_fin']);

        [$where, $params] = self::whereDemandes($filialeIds, $f);
        $debut = date('Y-m-01', strtotime('-' . ($mois - 1) . ' months'));
        $where .= ' AND recue_le >= ?';
        $params[] = $debut;

        // Regroupement par mois fait en PHP plutôt qu'en SQL (DATE_FORMAT
        // n'existe pas sous SQLite, utilisé en local pour les tests) —
        // volume mensuel toujours modeste, donc sans impact de performance.
        $stmt = Database::connection()->prepare("SELECT recue_le FROM demandes WHERE $where");
        $stmt->execute($params);
        $parMois = [];
        foreach ($stmt->fetchAll() as $row) {
            $cle = substr((string) $row['recue_le'], 0, 7);
            $parMois[$cle] = ($parMois[$cle] ?? 0) + 1;
        }

        $rows = [];
        for ($i = $mois - 1; $i >= 0; $i--) {
            $cle = date('Y-m', strtotime("-$i months"));
            $rows[] = ['mois' => $cle, 'label' => self::libelleMois($cle), 'n' => $parMois[$cle] ?? 0];
        }
        return $rows;
    }

    private static function libelleMois(string $ym): string
    {
        $noms = [1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr', 5 => 'Mai', 6 => 'Juin', 7 => 'Juil', 8 => 'Août', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'];
        [$y, $m] = explode('-', $ym);
        return $noms[(int) $m] . ' ' . substr($y, 2);
    }

    /**
     * Répartition par collaborateur (dossiers suivis via
     * dossier_collaborateurs + offres saisies via offres.created_by) —
     * respecte période/activité/responsable. Ce sont deux mesures
     * distinctes de l'activité d'un collaborateur : combien de dossiers il
     * a suivi (assignations, tous statuts confondus sur la période) et
     * combien d'offres il a lui-même enregistrées.
     */
    public static function parCollaborateur(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }

        [$whereDossiers, $paramsDossiers] = self::whereDossiers($filialeIds, $filters);
        $stmt = Database::connection()->prepare(
            "SELECT dc.utilisateur_id AS uid, COUNT(DISTINCT dc.dossier_id) AS n
             FROM dossier_collaborateurs dc
             INNER JOIN dossiers d ON d.id = dc.dossier_id
             INNER JOIN demandes dm ON dm.id = d.demande_id
             WHERE $whereDossiers
             GROUP BY dc.utilisateur_id"
        );
        $stmt->execute($paramsDossiers);
        $dossiersParCollab = [];
        foreach ($stmt->fetchAll() as $row) {
            $dossiersParCollab[(int) $row['uid']] = (int) $row['n'];
        }

        [$whereOffres, $paramsOffres] = self::whereDossiers($filialeIds, $filters);
        $stmt = Database::connection()->prepare(
            "SELECT o.created_by AS uid, COUNT(*) AS n
             FROM offres o
             INNER JOIN dossiers d ON d.id = o.dossier_id
             INNER JOIN demandes dm ON dm.id = d.demande_id
             WHERE o.created_by IS NOT NULL AND $whereOffres
             GROUP BY o.created_by"
        );
        $stmt->execute($paramsOffres);
        $offresParCollab = [];
        foreach ($stmt->fetchAll() as $row) {
            $offresParCollab[(int) $row['uid']] = (int) $row['n'];
        }

        $ids = array_unique(array_merge(array_keys($dossiersParCollab), array_keys($offresParCollab)));
        $rows = [];
        foreach ($ids as $uid) {
            if ($uid <= 0) {
                continue;
            }
            $rows[] = [
                'collaborateur' => Utilisateur::nameOf($uid),
                'dossiers_suivis' => $dossiersParCollab[$uid] ?? 0,
                'offres_saisies' => $offresParCollab[$uid] ?? 0,
            ];
        }
        usort($rows, fn($a, $b) => ($b['dossiers_suivis'] + $b['offres_saisies']) <=> ($a['dossiers_suivis'] + $a['offres_saisies']));
        return $rows;
    }

    /**
     * Dossiers actifs en retard (échéance dépassée), respecte les filtres —
     * section "Retards" du module Pilotage.
     */
    public static function retards(array $user, array $filters, int $limite = 10): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        [$where, $params] = self::whereDossiers($filialeIds, $filters);
        $where .= " AND d.statut = 'actif' AND d.echeance IS NOT NULL AND d.echeance < ?";
        $params[] = date('Y-m-d');
        $stmt = Database::connection()->prepare(
            "SELECT d.*, dm.activite FROM dossiers d
             INNER JOIN demandes dm ON dm.id = d.demande_id
             WHERE $where ORDER BY d.echeance ASC LIMIT " . (int) $limite
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

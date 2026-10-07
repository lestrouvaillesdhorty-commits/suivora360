<?php

namespace App\Models;

use App\Core\Database;

/**
 * Listes transverses du menu de gauche : Offres, Cotations, Commandes,
 * Factures, Comparateur — tous dossiers confondus (décision du 29/09).
 *
 * Complément de la vue centralisée par dossier, pas un remplacement.
 * Périmètre : uniquement les filiales visibles de l'utilisateur
 * (Filiale::visibleIdsFor), donc jamais les données d'une autre entreprise.
 */
class Listes
{
    /** Nombre maximal de lignes affichées (les filtres servent à affiner). */
    public const LIMITE = 300;

    private static function dans(array $filialeIds): string
    {
        return implode(',', array_fill(0, count($filialeIds), '?'));
    }

    /** Filtres communs : recherche texte (colonnes données), filiale, période de création. */
    private static function filtresCommuns(array $f, array $filialeIds, string $alias, array $colonnesRecherche, string &$sql, array &$params): void
    {
        if (!empty($f['filiale_id']) && in_array((int) $f['filiale_id'], $filialeIds, true)) {
            $sql .= " AND $alias.filiale_id = ?";
            $params[] = (int) $f['filiale_id'];
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '' && !empty($colonnesRecherche)) {
            $like = '%' . $q . '%';
            $sql .= ' AND (' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $colonnesRecherche)) . ')';
            foreach ($colonnesRecherche as $_) {
                $params[] = $like;
            }
        }
        if (!empty($f['date_debut']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date_debut'])) {
            $sql .= " AND $alias.created_at >= ?";
            $params[] = $f['date_debut'];
        }
        if (!empty($f['date_fin']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date_fin'])) {
            $sql .= " AND $alias.created_at <= ?";
            $params[] = $f['date_fin'] . ' 23:59:59';
        }
    }

    private static function executer(string $sql, array $params): array
    {
        $stmt = Database::connection()->prepare($sql . ' LIMIT ' . self::LIMITE);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Offres courantes (les versions antérieures « remplacées » n'apparaissent que si on les demande). */
    public static function offres(array $user, array $f): array
    {
        $ids = Filiale::visibleIdsFor($user);
        if (empty($ids)) {
            return [];
        }
        $sql = "SELECT o.*, d.reference AS dossier_reference, d.objet AS dossier_objet, fo.nom AS fournisseur_nom,
                       fi.nom AS filiale_nom
                FROM offres o
                INNER JOIN dossiers d ON d.id = o.dossier_id
                INNER JOIN fournisseurs fo ON fo.id = o.fournisseur_id
                INNER JOIN filiales fi ON fi.id = o.filiale_id
                WHERE o.filiale_id IN (" . self::dans($ids) . ')';
        $params = $ids;
        if (!empty($f['statut']) && isset(Offre::STATUTS[$f['statut']])) {
            $sql .= ' AND o.statut = ?';
            $params[] = $f['statut'];
        } else {
            $sql .= " AND o.statut != 'remplacee'";
        }
        if (($f['validite'] ?? '') === 'expiree') {
            $sql .= " AND o.statut = 'recue' AND o.validite_offre IS NOT NULL AND o.validite_offre < ?";
            $params[] = date('Y-m-d');
        }
        self::filtresCommuns($f, $ids, 'o', ['o.reference', 'd.reference', 'd.objet', 'fo.nom'], $sql, $params);
        return self::executer($sql . ' ORDER BY o.created_at DESC', $params);
    }

    /** Cotations courantes (versions « remplacées » exclues sauf demande explicite). */
    public static function cotations(array $user, array $f): array
    {
        $ids = Filiale::visibleIdsFor($user);
        if (empty($ids)) {
            return [];
        }
        $sql = "SELECT c.*, d.reference AS dossier_reference, d.objet AS dossier_objet, cl.nom AS client_nom,
                       fi.nom AS filiale_nom
                FROM cotations c
                INNER JOIN dossiers d ON d.id = c.dossier_id
                INNER JOIN clients cl ON cl.id = c.client_id
                INNER JOIN filiales fi ON fi.id = c.filiale_id
                WHERE c.filiale_id IN (" . self::dans($ids) . ')';
        $params = $ids;
        if (!empty($f['statut']) && isset(Cotation::STATUTS[$f['statut']])) {
            $sql .= ' AND c.statut = ?';
            $params[] = $f['statut'];
        } else {
            $sql .= " AND c.statut != 'remplacee'";
        }
        if (($f['validite'] ?? '') === 'expiree') {
            $sql .= " AND c.statut = 'envoyee' AND c.validite_devis IS NOT NULL AND c.validite_devis < ?";
            $params[] = date('Y-m-d');
        }
        self::filtresCommuns($f, $ids, 'c', ['c.reference', 'd.reference', 'd.objet', 'cl.nom'], $sql, $params);
        return self::executer($sql . ' ORDER BY c.created_at DESC', $params);
    }

    public static function commandes(array $user, array $f): array
    {
        $ids = Filiale::visibleIdsFor($user);
        if (empty($ids)) {
            return [];
        }
        $sql = "SELECT m.*, d.reference AS dossier_reference, d.objet AS dossier_objet, cl.nom AS client_nom,
                       c.montant_total, c.devise, fi.nom AS filiale_nom
                FROM commandes m
                INNER JOIN dossiers d ON d.id = m.dossier_id
                INNER JOIN cotations c ON c.id = m.cotation_id
                INNER JOIN clients cl ON cl.id = c.client_id
                INNER JOIN filiales fi ON fi.id = m.filiale_id
                WHERE m.filiale_id IN (" . self::dans($ids) . ')';
        $params = $ids;
        $statut = $f['statut'] ?? '';
        if ($statut === 'en_cours' || $statut === 'terminee') {
            $sql .= ' AND m.statut = ?';
            $params[] = $statut;
        } elseif ($statut === 'en_retard') {
            $sql .= " AND m.statut != 'terminee' AND m.date_relance IS NOT NULL AND m.date_relance < ?";
            $params[] = date('Y-m-d');
        }
        if (!empty($f['etape']) && isset(Commande::ETAPES_STEPS[$f['etape']])) {
            $sql .= ' AND m.etape = ?';
            $params[] = $f['etape'];
        }
        self::filtresCommuns($f, $ids, 'm', ['m.reference', 'd.reference', 'd.objet', 'cl.nom'], $sql, $params);
        return self::executer($sql . ' ORDER BY m.created_at DESC', $params);
    }

    public static function factures(array $user, array $f): array
    {
        $ids = Filiale::visibleIdsFor($user);
        if (empty($ids)) {
            return [];
        }
        $sql = "SELECT fa.*, d.reference AS dossier_reference, d.objet AS dossier_objet, fi.nom AS filiale_nom
                FROM factures fa
                INNER JOIN dossiers d ON d.id = fa.dossier_id
                INNER JOIN filiales fi ON fi.id = fa.filiale_id
                WHERE fa.filiale_id IN (" . self::dans($ids) . ')';
        $params = $ids;
        $statut = $f['statut'] ?? '';
        if ($statut === 'en_retard') {
            $sql .= " AND fa.statut = 'emise' AND fa.date_echeance IS NOT NULL AND fa.date_echeance < ?";
            $params[] = date('Y-m-d');
        } elseif (isset(Facture::STATUTS[$statut])) {
            $sql .= ' AND fa.statut = ?';
            $params[] = $statut;
        }
        if (!empty($f['type']) && isset(Facture::TYPES[$f['type']])) {
            $sql .= ' AND fa.type = ?';
            $params[] = $f['type'];
        }
        self::filtresCommuns($f, $ids, 'fa', ['fa.reference', 'd.reference', 'd.objet'], $sql, $params);
        return self::executer($sql . ' ORDER BY fa.created_at DESC', $params);
    }

    /**
     * Dossiers ayant au moins une offre courante (reçue ou retenue) — point
     * d'entrée vers le comparateur de chacun. « À trancher » = aucune offre
     * retenue pour l'instant.
     */
    public static function comparateurs(array $user, array $f): array
    {
        $ids = Filiale::visibleIdsFor($user);
        if (empty($ids)) {
            return [];
        }
        $sql = "SELECT d.id, d.reference, d.objet, d.filiale_id, d.created_at, fi.nom AS filiale_nom,
                       (SELECT COUNT(*) FROM offres o WHERE o.dossier_id = d.id AND o.statut IN ('recue','retenue')) AS nb_offres,
                       (SELECT COUNT(*) FROM offres o WHERE o.dossier_id = d.id AND o.statut = 'recue') AS nb_a_comparer,
                       (SELECT fo.nom FROM offres o INNER JOIN fournisseurs fo ON fo.id = o.fournisseur_id
                         WHERE o.dossier_id = d.id AND o.statut = 'retenue' LIMIT 1) AS retenue_fournisseur
                FROM dossiers d
                INNER JOIN filiales fi ON fi.id = d.filiale_id
                WHERE d.filiale_id IN (" . self::dans($ids) . ")
                  AND EXISTS (SELECT 1 FROM offres o WHERE o.dossier_id = d.id AND o.statut IN ('recue','retenue'))";
        $params = $ids;
        if (($f['decision'] ?? '') === 'a_trancher') {
            $sql .= " AND NOT EXISTS (SELECT 1 FROM offres o WHERE o.dossier_id = d.id AND o.statut = 'retenue')";
        } elseif (($f['decision'] ?? '') === 'decide') {
            $sql .= " AND EXISTS (SELECT 1 FROM offres o WHERE o.dossier_id = d.id AND o.statut = 'retenue')";
        }
        self::filtresCommuns($f, $ids, 'd', ['d.reference', 'd.objet'], $sql, $params);
        return self::executer($sql . ' ORDER BY d.created_at DESC', $params);
    }
}

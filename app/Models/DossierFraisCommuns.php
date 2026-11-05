<?php

namespace App\Models;

use App\Core\Database;

/**
 * Frais communs d'un dossier : coûts payés UNE seule fois pour l'ensemble des
 * articles (ex. expédition finale unique d'articles achetés chez plusieurs
 * fournisseurs). Ils s'ajoutent au coût d'achat de la cotation et se
 * répartissent entre les offres retenues au prorata de leur valeur d'achat.
 * Tolère l'absence de la table (migration v43 pas encore lancée).
 */
class DossierFraisCommuns
{
    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM dossier_frais_communs LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function forDossier(int $dossierId): array
    {
        if (!self::disponible()) {
            return [];
        }
        $stmt = Database::connection()->prepare('SELECT * FROM dossier_frais_communs WHERE dossier_id = ? ORDER BY id');
        $stmt->execute([$dossierId]);
        return $stmt->fetchAll();
    }

    public static function ajouter(int $dossierId, int $filialeId, string $libelle, float $montant, string $devise): void
    {
        $libelle = mb_substr(trim($libelle) !== '' ? trim($libelle) : 'Expédition finale', 0, 120);
        Database::connection()->prepare('INSERT INTO dossier_frais_communs (dossier_id, filiale_id, libelle, montant, devise, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$dossierId, $filialeId, $libelle, max(0, $montant), $devise !== '' ? $devise : 'EUR', date('Y-m-d H:i:s')]);
    }

    public static function supprimer(int $dossierId, int $id): void
    {
        Database::connection()->prepare('DELETE FROM dossier_frais_communs WHERE id = ? AND dossier_id = ?')->execute([$id, $dossierId]);
    }

    /**
     * Calcul du coût d'achat avec offres retenues + frais communs.
     * Seules les offres et frais dans la devise de référence (celle de la
     * première offre retenue) sont additionnés ; le reste est signalé.
     * Retourne : devise, offres (id => [fournisseur, prix, frais_propres, part_communs, total]),
     * frais (lignes prises en compte), total_communs, total, ignores (libellés).
     */
    public static function calculer(array $retenues, array $frais): array
    {
        if (!$retenues) {
            return ['devise' => '', 'offres' => [], 'frais' => [], 'total_communs' => 0.0, 'total' => 0.0, 'ignores' => []];
        }
        $devise = $retenues[0]['devise'] ?: 'EUR';
        $ignores = [];
        $offres = [];
        $sommePrix = 0.0;
        foreach ($retenues as $o) {
            if (($o['devise'] ?: 'EUR') !== $devise) {
                $ignores[] = 'offre ' . $o['fournisseur_nom'] . ' (' . $o['devise'] . ')';
                continue;
            }
            $prix = (float) $o['montant_total'];
            $propres = Offre::coutRendu($o) - $prix;
            $offres[(int) $o['id']] = ['fournisseur' => $o['fournisseur_nom'], 'prix' => $prix, 'frais_propres' => $propres, 'part_communs' => 0.0];
            $sommePrix += $prix;
        }
        $lignes = [];
        $totalCommuns = 0.0;
        foreach ($frais as $f) {
            if (($f['devise'] ?: 'EUR') !== $devise) {
                $ignores[] = 'frais « ' . $f['libelle'] . ' » (' . $f['devise'] . ')';
                continue;
            }
            $lignes[] = $f;
            $totalCommuns += (float) $f['montant'];
        }
        $n = count($offres);
        foreach ($offres as $id => &$o) {
            $o['part_communs'] = $totalCommuns > 0 ? ($sommePrix > 0 ? $totalCommuns * $o['prix'] / $sommePrix : $totalCommuns / max(1, $n)) : 0.0;
            $o['total'] = $o['prix'] + $o['frais_propres'] + $o['part_communs'];
        }
        unset($o);
        return [
            'devise' => $devise,
            'offres' => $offres,
            'frais' => $lignes,
            'total_communs' => $totalCommuns,
            'total' => array_sum(array_column($offres, 'total')),
            'ignores' => $ignores,
        ];
    }
}

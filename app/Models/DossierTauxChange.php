<?php

namespace App\Models;

use App\Core\Database;

/**
 * Taux de change d'un dossier : permet d'additionner des offres et des frais
 * en devises différentes dans la devise de référence (celle de la première
 * offre retenue). Tolère l'absence de la table (migration v46 pas encore lancée).
 */
class DossierTauxChange
{
    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM dossier_taux_change LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Taux enregistrés : devise => [cible, taux]. */
    public static function enregistres(int $dossierId): array
    {
        if (!self::disponible()) {
            return [];
        }
        $stmt = Database::connection()->prepare('SELECT devise, devise_cible, taux FROM dossier_taux_change WHERE dossier_id = ?');
        $stmt->execute([$dossierId]);
        $r = [];
        foreach ($stmt->fetchAll() as $row) {
            $r[$row['devise']] = ['cible' => $row['devise_cible'], 'taux' => (float) $row['taux']];
        }
        return $r;
    }

    public static function enregistrer(int $dossierId, string $devise, string $cible, float $taux): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM dossier_taux_change WHERE dossier_id = ? AND devise = ?')->execute([$dossierId, $devise]);
        if ($taux > 0) {
            $pdo->prepare('INSERT INTO dossier_taux_change (dossier_id, devise, devise_cible, taux, created_at) VALUES (?,?,?,?,?)')
                ->execute([$dossierId, $devise, $cible, $taux, date('Y-m-d H:i:s')]);
        }
    }

    /** Taux par défaut (parité fixe EUR / franc CFA) ; null si inconnu. */
    public static function parDefaut(string $de, string $vers, float $eurFcfa): ?float
    {
        if ($de === $vers) {
            return 1.0;
        }
        $cfa = ['XAF', 'XOF'];
        if (in_array($de, $cfa, true) && in_array($vers, $cfa, true)) {
            return 1.0;
        }
        if ($de === 'EUR' && in_array($vers, $cfa, true)) {
            return $eurFcfa;
        }
        if (in_array($de, $cfa, true) && $vers === 'EUR' && $eurFcfa > 0) {
            return 1 / $eurFcfa;
        }
        return null;
    }

    /**
     * Pour un dossier : devise de référence, et pour chaque devise étrangère présente
     * (offres retenues ou frais) le taux appliqué (enregistré, sinon par défaut, sinon null).
     * Retour : ['ref' => 'EUR', 'devises' => [devise => ['taux' => float|null, 'enregistre' => bool]]]
     */
    public static function etat(array $dossier, array $retenues, array $frais): array
    {
        $ref = $retenues ? (($retenues[0]['devise'] ?? '') ?: 'EUR') : '';
        $presentes = [];
        foreach ($retenues as $o) {
            $presentes[] = ($o['devise'] ?? '') ?: 'EUR';
        }
        foreach ($frais as $f) {
            $presentes[] = ($f['devise'] ?? '') ?: 'EUR';
        }
        $param = \App\Models\Parametres::forFiliale((int) $dossier['filiale_id']);
        $eur = (float) ($param['taux_eur_fcfa'] ?? 655.957);
        $saved = self::enregistres((int) $dossier['id']);
        $res = [];
        foreach (array_unique($presentes) as $d) {
            if ($d === $ref) {
                continue;
            }
            $taux = null;
            $enreg = false;
            if (isset($saved[$d]) && $saved[$d]['cible'] === $ref && $saved[$d]['taux'] > 0) {
                $taux = $saved[$d]['taux'];
                $enreg = true;
            } else {
                $taux = self::parDefaut($d, $ref, $eur);
            }
            $res[$d] = ['taux' => $taux, 'enregistre' => $enreg];
        }
        return ['ref' => $ref, 'devises' => $res];
    }
}

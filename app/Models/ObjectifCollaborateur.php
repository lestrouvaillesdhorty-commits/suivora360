<?php

namespace App\Models;

use App\Core\Database;

/**
 * Objectifs mensuels de marge par collaborateur et réglages de commission
 * (taux + base de calcul). Tolère l'absence des tables (migration v40 pas
 * encore lancée) : disponible() permet à l'écran d'afficher un message.
 */
class ObjectifCollaborateur
{
    public const BASES = [
        'responsable' => 'Dossiers dont il est responsable',
        'offres' => 'Dossiers où son offre est retenue',
    ];

    /** Indicateurs d'objectif : clé => [libellé, type] (money = montant, count = nombre). */
    public const INDICATEURS = [
        'marge' => ['Marge', 'money'],
        'ventes' => ["Chiffre d'affaires HT", 'money'],
        'commandes' => ['Nombre de commandes', 'count'],
    ];

    public static function colonneIndicateur(): bool
    {
        try {
            Database::connection()->query('SELECT indicateur FROM reglages_collaborateurs LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM objectifs_collaborateurs LIMIT 1');
            Database::connection()->query('SELECT 1 FROM reglages_collaborateurs LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function moisValide($mois): string
    {
        $mois = is_string($mois) ? $mois : '';
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois) ? $mois : date('Y-m');
    }

    /** @return array<int,array{taux_commission:float,base:string,indicateur:string}> */
    public static function reglages(int $organisationId): array
    {
        $col = self::colonneIndicateur() ? ', indicateur' : '';
        $stmt = Database::connection()->prepare('SELECT utilisateur_id, taux_commission, base' . $col . ' FROM reglages_collaborateurs WHERE organisation_id = ?');
        $stmt->execute([$organisationId]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(int) $r['utilisateur_id']] = ['taux_commission' => (float) $r['taux_commission'], 'base' => $r['base'], 'indicateur' => $r['indicateur'] ?? 'marge'];
        }
        return $out;
    }

    /** @return array<int,array{objectif_marge:float,devise:string}> */
    public static function objectifs(int $organisationId, string $mois): array
    {
        $stmt = Database::connection()->prepare('SELECT utilisateur_id, objectif_marge, devise FROM objectifs_collaborateurs WHERE organisation_id = ? AND mois = ?');
        $stmt->execute([$organisationId, $mois]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(int) $r['utilisateur_id']] = ['objectif_marge' => (float) $r['objectif_marge'], 'devise' => $r['devise']];
        }
        return $out;
    }

    public static function enregistrer(int $organisationId, int $utilisateurId, string $mois, float $objectif, string $devise, float $taux, string $base, string $indicateur = 'marge'): void
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');
        $base = isset(self::BASES[$base]) ? $base : 'responsable';
        $indicateur = isset(self::INDICATEURS[$indicateur]) ? $indicateur : 'marge';
        $avecInd = self::colonneIndicateur();
        $taux = max(0.0, min(100.0, $taux));
        $objectif = max(0.0, $objectif);

        $stmt = $pdo->prepare('SELECT id FROM objectifs_collaborateurs WHERE utilisateur_id = ? AND mois = ?');
        $stmt->execute([$utilisateurId, $mois]);
        if ($stmt->fetchColumn()) {
            $pdo->prepare('UPDATE objectifs_collaborateurs SET objectif_marge = ?, devise = ?, updated_at = ? WHERE utilisateur_id = ? AND mois = ?')
                ->execute([$objectif, $devise, $now, $utilisateurId, $mois]);
        } else {
            $pdo->prepare('INSERT INTO objectifs_collaborateurs (organisation_id, utilisateur_id, mois, objectif_marge, devise, updated_at) VALUES (?,?,?,?,?,?)')
                ->execute([$organisationId, $utilisateurId, $mois, $objectif, $devise, $now]);
        }

        $stmt = $pdo->prepare('SELECT id FROM reglages_collaborateurs WHERE utilisateur_id = ?');
        $stmt->execute([$utilisateurId]);
        if ($stmt->fetchColumn()) {
            $pdo->prepare('UPDATE reglages_collaborateurs SET taux_commission = ?, base = ?, updated_at = ?' . ($avecInd ? ', indicateur = ?' : '') . ' WHERE utilisateur_id = ?')
                ->execute($avecInd ? [$taux, $base, $now, $indicateur, $utilisateurId] : [$taux, $base, $now, $utilisateurId]);
        } else {
            $pdo->prepare('INSERT INTO reglages_collaborateurs (organisation_id, utilisateur_id, taux_commission, base, updated_at' . ($avecInd ? ', indicateur' : '') . ') VALUES (?,?,?,?,?' . ($avecInd ? ',?' : '') . ')')
                ->execute($avecInd ? [$organisationId, $utilisateurId, $taux, $base, $now, $indicateur] : [$organisationId, $utilisateurId, $taux, $base, $now]);
        }
    }

    /** Copie les objectifs du mois précédent vers $mois pour les collaborateurs sans objectif ce mois-ci. */
    public static function copierMoisPrecedent(int $organisationId, string $mois): int
    {
        $prec = date('Y-m', strtotime($mois . '-01 -1 month'));
        $existants = self::objectifs($organisationId, $mois);
        $n = 0;
        foreach (self::objectifs($organisationId, $prec) as $uid => $o) {
            if (isset($existants[$uid])) {
                continue;
            }
            $r = self::reglages($organisationId)[$uid] ?? ['taux_commission' => 0.0, 'base' => 'responsable', 'indicateur' => 'marge'];
            self::enregistrer($organisationId, $uid, $mois, $o['objectif_marge'], $o['devise'], $r['taux_commission'], $r['base'], $r['indicateur']);
            $n++;
        }
        return $n;
    }
}

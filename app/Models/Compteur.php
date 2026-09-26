<?php

namespace App\Models;

use App\Core\Database;

/**
 * Génère des références séquentielles du type DEM-2026-00001 / DOS-2026-00001,
 * remises à zéro chaque année, par organisation.
 */
class Compteur
{
    public static function next(int $organisationId, string $type): int
    {
        $pdo = Database::connection();
        $annee = (int) date('Y');

        $stmt = $pdo->prepare('SELECT id, valeur FROM compteurs WHERE organisation_id = ? AND type = ? AND annee = ?');
        $stmt->execute([$organisationId, $type, $annee]);
        $row = $stmt->fetch();

        if ($row) {
            $nouvelleValeur = (int) $row['valeur'] + 1;
            $update = $pdo->prepare('UPDATE compteurs SET valeur = ? WHERE id = ?');
            $update->execute([$nouvelleValeur, $row['id']]);
            return $nouvelleValeur;
        }

        $insert = $pdo->prepare('INSERT INTO compteurs (organisation_id, type, annee, valeur) VALUES (?, ?, ?, 1)');
        $insert->execute([$organisationId, $type, $annee]);
        return 1;
    }

    public static function formatReference(string $prefix, int $numero): string
    {
        return sprintf('%s-%d-%05d', $prefix, (int) date('Y'), $numero);
    }
}

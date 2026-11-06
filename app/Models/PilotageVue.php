<?php

namespace App\Models;

use App\Core\Database;

/** Vues enregistrées du Pilotage : un onglet + ses filtres, mémorisés sous un nom (par utilisateur). */
class PilotageVue
{
    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM pilotage_vues LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function pourUtilisateur(int $userId): array
    {
        if (!self::disponible()) {
            return [];
        }
        $st = Database::connection()->prepare('SELECT * FROM pilotage_vues WHERE utilisateur_id = ? ORDER BY nom');
        $st->execute([$userId]);
        return $st->fetchAll();
    }

    public static function creer(int $userId, string $nom, string $onglet, string $filtres): void
    {
        $nom = mb_substr(trim($nom), 0, 80);
        if ($nom === '') {
            return;
        }
        Database::connection()->prepare('INSERT INTO pilotage_vues (utilisateur_id, nom, onglet, filtres, created_at) VALUES (?,?,?,?,?)')
            ->execute([$userId, $nom, $onglet, mb_substr($filtres, 0, 1000), date('Y-m-d H:i:s')]);
    }

    public static function supprimer(int $userId, int $id): void
    {
        Database::connection()->prepare('DELETE FROM pilotage_vues WHERE id = ? AND utilisateur_id = ?')->execute([$id, $userId]);
    }
}

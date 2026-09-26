<?php

namespace App\Models;

use App\Core\Database;

class OffreItem
{
    public static function forOffre(int $offreId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM offre_items WHERE offre_id = ? ORDER BY id');
        $stmt->execute([$offreId]);
        return $stmt->fetchAll();
    }

    public static function create(int $offreId, array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO offre_items (offre_id, designation, quantite, unite, prix_unitaire, montant, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $quantite = $data['quantite'] !== '' && $data['quantite'] !== null ? (float) $data['quantite'] : null;
        $prixUnitaire = $data['prix_unitaire'] !== '' && $data['prix_unitaire'] !== null ? (float) $data['prix_unitaire'] : null;
        $montant = ($quantite !== null && $prixUnitaire !== null) ? round($quantite * $prixUnitaire, 2) : null;

        $stmt->execute([
            $offreId,
            $data['designation'],
            $quantite,
            trim($data['unite'] ?? ''),
            $prixUnitaire,
            $montant,
            date('Y-m-d H:i:s'),
        ]);
        return (int) Database::connection()->lastInsertId();
    }
}

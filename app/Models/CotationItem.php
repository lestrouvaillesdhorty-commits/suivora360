<?php

namespace App\Models;

use App\Core\Database;

class CotationItem
{
    public static function forCotation(int $cotationId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM cotation_items WHERE cotation_id = ? ORDER BY id');
        $stmt->execute([$cotationId]);
        return $stmt->fetchAll();
    }

    public static function create(int $cotationId, array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cotation_items (cotation_id, designation, quantite, unite, prix_unitaire, montant, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $quantite = $data['quantite'] !== '' && $data['quantite'] !== null ? (float) $data['quantite'] : null;
        $prixUnitaire = $data['prix_unitaire'] !== '' && $data['prix_unitaire'] !== null ? (float) $data['prix_unitaire'] : null;
        $montant = ($quantite !== null && $prixUnitaire !== null) ? round($quantite * $prixUnitaire, 2) : null;

        $stmt->execute([
            $cotationId,
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

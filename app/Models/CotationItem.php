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

    public static function create(int $parentId, array $data): int
    {
        $quantite = $data['quantite'] !== '' && $data['quantite'] !== null ? (float) $data['quantite'] : null;
        $prixUnitaire = $data['prix_unitaire'] !== '' && $data['prix_unitaire'] !== null ? (float) $data['prix_unitaire'] : null;
        $montant = ($quantite !== null && $prixUnitaire !== null) ? round($quantite * $prixUnitaire, 2) : null;

        return \App\Core\Schema::insert('cotation_items', [
            'cotation_id' => $parentId,
            'designation' => $data['designation'],
            'quantite' => $quantite,
            'unite' => trim($data['unite'] ?? ''),
            'prix_unitaire' => $prixUnitaire,
            'montant' => $montant,
            'created_at' => date('Y-m-d H:i:s'),
        ], [
            'conditionnement' => trim((string) ($data['conditionnement'] ?? '')),
            'reference' => trim((string) ($data['reference'] ?? '')),
            'marque' => trim((string) ($data['marque'] ?? '')),
            'caracteristiques' => trim((string) ($data['caracteristiques'] ?? '')),
        ]);
    }
}

<?php

namespace App\Models;

use App\Core\Database;

class DemandeArticle
{
    public static function forDemande(int $demandeId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM demande_articles WHERE demande_id = ? ORDER BY id');
        $stmt->execute([$demandeId]);
        return $stmt->fetchAll();
    }

    public static function create(int $demandeId, array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO demande_articles (demande_id, designation, quantite, unite, reference, marque, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $demandeId,
            $data['designation'],
            $data['quantite'] ?: null,
            $data['unite'] ?? '',
            $data['reference'] ?? '',
            $data['marque'] ?? '',
            date('Y-m-d H:i:s'),
        ]);
        return (int) Database::connection()->lastInsertId();
    }
}

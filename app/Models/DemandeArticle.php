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

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM demande_articles WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $demandeId, array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO demande_articles (demande_id, designation, quantite, unite, conditionnement, reference, marque, caracteristiques, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $demandeId,
            $data['designation'],
            $data['quantite'] ?: null,
            $data['unite'] ?? '',
            $data['conditionnement'] ?? '',
            $data['reference'] ?? '',
            $data['marque'] ?? '',
            $data['caracteristiques'] ?? '',
            date('Y-m-d H:i:s'),
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    /**
     * [ajouté 04/10] Modification d'une ligne d'article déjà enregistrée —
     * jusqu'ici seule la création existait (via la saisie manuelle sur la
     * fiche, ou l'extraction IA) : aucun moyen de corriger une ligne sans la
     * supprimer et la recréer.
     */
    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE demande_articles SET designation = ?, quantite = ?, unite = ?, conditionnement = ?, reference = ?, marque = ?, caracteristiques = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['designation'],
            $data['quantite'] ?: null,
            $data['unite'] ?? '',
            $data['conditionnement'] ?? '',
            $data['reference'] ?? '',
            $data['marque'] ?? '',
            $data['caracteristiques'] ?? '',
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM demande_articles WHERE id = ?');
        $stmt->execute([$id]);
    }
}

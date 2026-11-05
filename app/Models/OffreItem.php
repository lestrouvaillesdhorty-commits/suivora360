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

    public static function create(int $parentId, array $data): int
    {
        $quantite = $data['quantite'] !== '' && $data['quantite'] !== null ? (float) $data['quantite'] : null;
        $prixUnitaire = $data['prix_unitaire'] !== '' && $data['prix_unitaire'] !== null ? (float) $data['prix_unitaire'] : null;
        $montant = ($quantite !== null && $prixUnitaire !== null) ? round($quantite * $prixUnitaire, 2) : null;

        $articleId = self::articleValide($parentId, $data['demande_article_id'] ?? null, (string) $data['designation']);

        return \App\Core\Schema::insert('offre_items', [
            'offre_id' => $parentId,
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
            'demande_article_id' => $articleId,
            'retenu' => !empty($data['retenu']) ? 1 : 0,
        ]);
    }

    public static function colonneArticle(): bool
    {
        return \App\Core\Schema::colonne('offre_items', 'demande_article_id');
    }

    /**
     * Article de la demande auquel la ligne se rattache : l'identifiant choisi
     * s'il appartient bien à la demande du dossier de l'offre, sinon une
     * désignation identique (casse et espaces ignorés), sinon null.
     */
    private static function articleValide(int $offreId, $id, string $designation): ?int
    {
        if (!self::colonneArticle()) {
            return null;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT da.id, da.designation FROM demande_articles da INNER JOIN dossiers d ON d.demande_id = da.demande_id INNER JOIN offres o ON o.dossier_id = d.id WHERE o.id = ?');
        $stmt->execute([$offreId]);
        $articles = $stmt->fetchAll();
        $id = (int) $id;
        foreach ($articles as $a) {
            if ($id > 0 && (int) $a['id'] === $id) {
                return $id;
            }
        }
        if ($id === -1) {
            return null; // « autre article / hors demande » choisi explicitement
        }
        $norm = fn($t) => mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $t)));
        foreach ($articles as $a) {
            if ($norm($a['designation']) === $norm($designation) && $norm($designation) !== '') {
                return (int) $a['id'];
            }
        }
        return null;
    }

    /** Lignes retenues (gagnantes par article) d'une offre ; vide si aucune n'est marquée. */
    public static function retenuesDe(int $offreId): array
    {
        if (!self::colonneArticle()) {
            return [];
        }
        $stmt = Database::connection()->prepare('SELECT * FROM offre_items WHERE offre_id = ? AND retenu = 1 ORDER BY id');
        $stmt->execute([$offreId]);
        return $stmt->fetchAll();
    }
}

<?php

namespace App\Models;

use App\Core\Database;

class FournisseurPieceJointe
{
    // Mêmes règles que pour les pièces jointes de demande/dossier.
    public const EXTENSIONS_AUTORISEES = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'eml', 'msg'];
    public const TAILLE_MAX = 10 * 1024 * 1024; // 10 Mo

    public const CATEGORIES = [
        'devis' => 'Devis',
        'catalogue' => 'Catalogue',
        'fiche_technique' => 'Fiche technique',
        'certification' => 'Certification',
        'document_legal' => 'Document légal',
        'facture' => 'Facture',
        'autre' => 'Autre',
    ];

    public static function forFournisseur(int $fournisseurId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, u.nom AS uploaded_by_nom FROM fournisseur_pieces_jointes p
             LEFT JOIN utilisateurs u ON u.id = p.uploaded_by
             WHERE p.fournisseur_id = ?
             ORDER BY p.categorie, p.created_at DESC'
        );
        $stmt->execute([$fournisseurId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM fournisseur_pieces_jointes WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO fournisseur_pieces_jointes (fournisseur_id, categorie, nom_original, nom_fichier, taille, type_mime, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['fournisseur_id'],
            $data['categorie'] ?? 'autre',
            $data['nom_original'],
            $data['nom_fichier'],
            $data['taille'],
            $data['type_mime'],
            $data['uploaded_by'],
            date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM fournisseur_pieces_jointes WHERE id = ?');
        $stmt->execute([$id]);
    }
}

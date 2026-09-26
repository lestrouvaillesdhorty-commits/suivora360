<?php

namespace App\Models;

use App\Core\Database;

class DossierPieceJointe
{
    // Mêmes règles que pour les pièces jointes de demande (voir DemandePieceJointe).
    public const EXTENSIONS_AUTORISEES = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'eml', 'msg'];
    public const TAILLE_MAX = 10 * 1024 * 1024; // 10 Mo

    public static function forDossier(int $dossierId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, u.nom AS uploaded_by_nom FROM dossier_pieces_jointes p
             LEFT JOIN utilisateurs u ON u.id = p.uploaded_by
             WHERE p.dossier_id = ?
             ORDER BY p.created_at DESC'
        );
        $stmt->execute([$dossierId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM dossier_pieces_jointes WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO dossier_pieces_jointes (dossier_id, nom_original, nom_fichier, taille, type_mime, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['dossier_id'],
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
        $stmt = Database::connection()->prepare('DELETE FROM dossier_pieces_jointes WHERE id = ?');
        $stmt->execute([$id]);
    }
}

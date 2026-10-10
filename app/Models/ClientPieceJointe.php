<?php

namespace App\Models;

use App\Core\Database;

class ClientPieceJointe
{
    // Mêmes règles que pour les pièces jointes de demande/dossier/fournisseur.
    public const EXTENSIONS_AUTORISEES = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'eml', 'msg'];
    public const TAILLE_MAX = 10 * 1024 * 1024; // 10 Mo

    public const CATEGORIES = [
        'contrat' => 'Contrat / accord',
        'identification' => 'Identification (RCCM, NIU, KBIS…)',
        'devis' => 'Devis',
        'facture' => 'Facture',
        'correspondance' => 'Correspondance',
        'fiche_technique' => 'Fiche technique',
        'certificat' => 'Certificat',
        'autre' => 'Autre',
    ];

    public static function forClient(int $clientId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, u.nom AS uploaded_by_nom FROM client_pieces_jointes p
             LEFT JOIN utilisateurs u ON u.id = p.uploaded_by
             WHERE p.client_id = ?
             ORDER BY p.categorie, p.created_at DESC'
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM client_pieces_jointes WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO client_pieces_jointes (client_id, categorie, nom_original, nom_fichier, taille, type_mime, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['client_id'],
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
        $stmt = Database::connection()->prepare('DELETE FROM client_pieces_jointes WHERE id = ?');
        $stmt->execute([$id]);
    }
}

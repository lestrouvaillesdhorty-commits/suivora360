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
        'certification' => 'Certification / certificat',
        'document_legal' => 'Document administratif',
        'assurance' => 'Assurance',
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
        $cols = ['fournisseur_id', 'categorie', 'nom_original', 'nom_fichier', 'taille', 'type_mime', 'uploaded_by', 'created_at'];
        $vals = [
            $data['fournisseur_id'],
            $data['categorie'] ?? 'autre',
            $data['nom_original'],
            $data['nom_fichier'],
            $data['taille'],
            $data['type_mime'],
            $data['uploaded_by'],
            date('Y-m-d H:i:s'),
        ];
        // Échéance facultative (disponible après migrate_v23.php).
        if (Fournisseur::schemaPret()) {
            $cols[] = 'expire_le';
            $vals[] = !empty($data['expire_le']) ? $data['expire_le'] : null;
        }
        $pdo->prepare('INSERT INTO fournisseur_pieces_jointes (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
            ->execute($vals);
        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM fournisseur_pieces_jointes WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * État d'un document par rapport à son échéance. Un document SANS échéance n'a jamais
     * d'alerte (une certification peut expirer, un catalogue non).
     * @return array{0:string,1:string} [code, libellé]  code : aucune|valide|bientot|expire
     */
    public static function etatEcheance(?string $expireLe): array
    {
        if (empty($expireLe)) {
            return ['aucune', ''];
        }
        $aujourdhui = date('Y-m-d');
        if ($expireLe < $aujourdhui) {
            return ['expire', 'Expiré le ' . date('d/m/Y', strtotime($expireLe))];
        }
        if ($expireLe <= date('Y-m-d', strtotime('+' . Fournisseur::JOURS_ALERTE_DOCUMENT . ' days'))) {
            return ['bientot', 'À renouveler avant le ' . date('d/m/Y', strtotime($expireLe))];
        }
        return ['valide', 'Valable jusqu’au ' . date('d/m/Y', strtotime($expireLe))];
    }
}

<?php

namespace App\Models;

use App\Core\Database;

class DossierPieceJointe
{
    // Mêmes règles que pour les pièces jointes de demande (voir DemandePieceJointe).
    public const EXTENSIONS_AUTORISEES = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'eml', 'msg'];
    public const TAILLE_MAX = 10 * 1024 * 1024; // 10 Mo

    // [ajouté 06/10, étape 4 du découpage Dossiers, migrate_v19] Catégories
    // d'affichage de l'onglet Documents (ordre = ordre d'affichage) et
    // visibilité Interne / Visible client (étiquette — aucun espace client
    // ne la lit encore ; "interne" par défaut).
    public const CATEGORIES = [
        'offres_fournisseurs' => 'Offres fournisseurs',
        'cotations_client' => 'Cotations client',
        'commande_facturation' => 'Commande et facturation',
        'transport_douane' => 'Transport et douane',
        'fiche_technique' => 'Fiches techniques',
        'certificat' => 'Certificats (phytosanitaire, origine, conformité…)',
        'rapport' => 'Rapports et contrôles',
        'autre' => 'Autres documents',
    ];
    public const VISIBILITES = [
        'interne' => 'Interne',
        'client' => 'Visible client',
    ];

    public static function categorieValide(?string $c): string
    {
        return array_key_exists((string) $c, self::CATEGORIES) ? (string) $c : 'autre';
    }

    public static function visibiliteValide(?string $v): string
    {
        return array_key_exists((string) $v, self::VISIBILITES) ? (string) $v : 'interne';
    }

    public static function classer(int $id, string $categorie, string $visibilite): void
    {
        $stmt = Database::connection()->prepare('UPDATE dossier_pieces_jointes SET categorie = ?, visibilite = ? WHERE id = ?');
        $stmt->execute([self::categorieValide($categorie), self::visibiliteValide($visibilite), $id]);
    }

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

    /** La colonne offre_id existe-t-elle (migrate_v28 passée) ? */
    public static function colonneOffre(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::connection()->query('SELECT offre_id FROM dossier_pieces_jointes LIMIT 1');
                $ok = true;
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /** Fichiers du dossier regroupés par offre : [offreId => [pièces…]]. */
    public static function parOffre(array $pieces): array
    {
        $res = [];
        foreach ($pieces as $p) {
            if (!empty($p['offre_id'])) {
                $res[(int) $p['offre_id']][] = $p;
            }
        }
        return $res;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO dossier_pieces_jointes (dossier_id, nom_original, nom_fichier, taille, type_mime, uploaded_by, categorie, visibilite, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['dossier_id'],
            $data['nom_original'],
            $data['nom_fichier'],
            $data['taille'],
            $data['type_mime'],
            $data['uploaded_by'],
            self::categorieValide($data['categorie'] ?? null),
            self::visibiliteValide($data['visibilite'] ?? null),
            date('Y-m-d H:i:s'),
        ]);
        $id = (int) $pdo->lastInsertId();
        if (!empty($data['offre_id']) && self::colonneOffre()) {
            $pdo->prepare('UPDATE dossier_pieces_jointes SET offre_id = ? WHERE id = ?')->execute([(int) $data['offre_id'], $id]);
        }
        return $id;
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM dossier_pieces_jointes WHERE id = ?');
        $stmt->execute([$id]);
    }
}

<?php

namespace App\Models;

use App\Core\Database;

/**
 * Catégories de fournisseur ajoutées par les utilisateurs, en plus des six
 * catégories d'origine (Fournisseur::TYPES). Code stocké dans la fiche
 * fournisseur : « c » + identifiant. Une catégorie ajoutée est neutre
 * (aucun critère propre). Tant que migrate_v37.php n'a pas été lancée,
 * seules les six catégories d'origine existent.
 */
class CategorieFournisseur
{
    private static ?array $cache = null;

    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM categories_fournisseur LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Toutes les catégories ajoutées (même désactivées) : [code => libellé]. Sert à afficher les fiches existantes. */
    public static function toutes(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            if (self::disponible()) {
                foreach (Database::connection()->query('SELECT id, libelle FROM categories_fournisseur ORDER BY libelle')->fetchAll() as $r) {
                    self::$cache['c' . $r['id']] = $r['libelle'];
                }
            }
        }
        return self::$cache;
    }

    /** Catégories actives proposées dans les formulaires, pour les filiales données. */
    public static function actives(array $filialeIds): array
    {
        if (!self::disponible() || !$filialeIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($filialeIds), '?'));
        $st = Database::connection()->prepare("SELECT id, libelle FROM categories_fournisseur WHERE actif = 1 AND filiale_id IN ($in) ORDER BY libelle");
        $st->execute(array_values($filialeIds));
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out['c' . $r['id']] = $r['libelle'];
        }
        return $out;
    }

    /** Crée (ou retrouve) une catégorie dans la filiale. Retourne son code, ou '' si impossible. */
    public static function creer(int $filialeId, string $libelle): string
    {
        $libelle = trim(mb_substr($libelle, 0, 120));
        if ($libelle === '' || !$filialeId || !self::disponible()) {
            return '';
        }
        // Même nom qu'une catégorie d'origine : on utilise celle-ci.
        foreach (Fournisseur::TYPES as $code => $lib) {
            if (mb_strtolower($lib) === mb_strtolower($libelle)) {
                return $code;
            }
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT id FROM categories_fournisseur WHERE filiale_id = ? AND LOWER(libelle) = LOWER(?)');
        $st->execute([$filialeId, $libelle]);
        $id = $st->fetchColumn();
        if ($id) {
            $pdo->prepare('UPDATE categories_fournisseur SET actif = 1 WHERE id = ?')->execute([$id]);
        } else {
            $pdo->prepare('INSERT INTO categories_fournisseur (filiale_id, libelle, actif, created_at) VALUES (?,?,1,?)')
                ->execute([$filialeId, $libelle, date('Y-m-d H:i:s')]);
            $id = $pdo->lastInsertId();
        }
        self::$cache = null;
        return 'c' . $id;
    }

    public static function lignes(int $filialeId): array
    {
        if (!self::disponible()) {
            return [];
        }
        $st = Database::connection()->prepare('SELECT * FROM categories_fournisseur WHERE filiale_id = ? ORDER BY libelle');
        $st->execute([$filialeId]);
        return $st->fetchAll();
    }

    public static function basculer(int $filialeId, int $id): void
    {
        if (self::disponible()) {
            Database::connection()->prepare('UPDATE categories_fournisseur SET actif = 1 - actif WHERE id = ? AND filiale_id = ?')->execute([$id, $filialeId]);
            self::$cache = null;
        }
    }

    public static function renommer(int $filialeId, int $id, string $libelle): void
    {
        $libelle = trim(mb_substr($libelle, 0, 120));
        if ($libelle !== '' && self::disponible()) {
            Database::connection()->prepare('UPDATE categories_fournisseur SET libelle = ? WHERE id = ? AND filiale_id = ?')->execute([$libelle, $id, $filialeId]);
            self::$cache = null;
        }
    }
}

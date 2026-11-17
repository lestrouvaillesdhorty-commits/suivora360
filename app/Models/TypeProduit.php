<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Schema;

/**
 * Types de produit des expéditions, par filiale. Chaque type a une famille (alimentaire ou non
 * alimentaire : elle décide du tarif d'achat du transitaire) et peut exiger une validation du client
 * avant le départ. Les types d'origine (hors format, alcool, dangereux) sont copiés à la première utilisation.
 */
class TypeProduit
{
    private const DEFAUT = [
        ['', 'Alimentaire', 'alimentaire', 0],
        ['', 'Non alimentaire courant', 'non_alimentaire', 0],
        ['hors_format', 'Hors format', 'non_alimentaire', 1],
        ['alcool', 'Alcool', 'non_alimentaire', 1],
        ['dangereux', 'Produit dangereux', 'non_alimentaire', 1],
    ];

    public static function disponible(): bool
    {
        return Schema::colonne('expedition_types_produit', 'libelle') && Schema::colonne('expedition_reservations', 'type_produit_id');
    }

    public static function assurer(int $filialeId): void
    {
        if (!$filialeId || !self::disponible()) {
            return;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT COUNT(*) FROM expedition_types_produit WHERE filiale_id = ?');
        $st->execute([$filialeId]);
        if ((int) $st->fetchColumn() > 0) {
            return;
        }
        $ins = $pdo->prepare('INSERT INTO expedition_types_produit (filiale_id, code, libelle, famille, validation_client, ordre, actif, created_at) VALUES (?,?,?,?,?,?,1,?)');
        foreach (self::DEFAUT as $i => [$code, $lib, $fam, $val]) {
            $ins->execute([$filialeId, $code, $lib, $fam, $val, $i + 1, date('Y-m-d H:i:s')]);
        }
    }

    /** Tous les types de la filiale (actifs ou non), avec le nombre de réservations. */
    public static function lignes(int $filialeId): array
    {
        if (!self::disponible()) {
            return [];
        }
        self::assurer($filialeId);
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT t.*, (SELECT COUNT(*) FROM expedition_reservations r WHERE r.type_produit_id = t.id) AS usages FROM expedition_types_produit t WHERE t.filiale_id = ? ORDER BY t.ordre, t.id');
        $st->execute([$filialeId]);
        return $st->fetchAll();
    }

    public static function actifs(int $filialeId): array
    {
        return array_values(array_filter(self::lignes($filialeId), fn($t) => (int) $t['actif'] === 1));
    }

    public static function find(int $id): ?array
    {
        if (!self::disponible()) {
            return null;
        }
        $st = Database::connection()->prepare('SELECT * FROM expedition_types_produit WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    private static function famille(string $f): string
    {
        return $f === 'alimentaire' ? 'alimentaire' : 'non_alimentaire';
    }

    public static function ajouter(int $filialeId, string $libelle, string $famille, bool $validation): bool
    {
        $libelle = trim(mb_substr($libelle, 0, 120));
        if ($libelle === '' || !self::disponible()) {
            return false;
        }
        self::assurer($filialeId);
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT COALESCE(MAX(ordre), 0) + 1 FROM expedition_types_produit WHERE filiale_id = ?');
        $st->execute([$filialeId]);
        $pdo->prepare('INSERT INTO expedition_types_produit (filiale_id, code, libelle, famille, validation_client, ordre, actif, created_at) VALUES (?,?,?,?,?,?,1,?)')
            ->execute([$filialeId, '', $libelle, self::famille($famille), $validation ? 1 : 0, (int) $st->fetchColumn(), date('Y-m-d H:i:s')]);
        return true;
    }

    public static function modifier(int $filialeId, int $id, string $libelle, string $famille, bool $validation): bool
    {
        $libelle = trim(mb_substr($libelle, 0, 120));
        if ($libelle === '' || !self::disponible()) {
            return false;
        }
        Database::connection()->prepare('UPDATE expedition_types_produit SET libelle = ?, famille = ?, validation_client = ? WHERE id = ? AND filiale_id = ?')
            ->execute([$libelle, self::famille($famille), $validation ? 1 : 0, $id, $filialeId]);
        return true;
    }

    public static function basculer(int $filialeId, int $id): void
    {
        if (self::disponible()) {
            Database::connection()->prepare('UPDATE expedition_types_produit SET actif = 1 - actif WHERE id = ? AND filiale_id = ?')->execute([$id, $filialeId]);
        }
    }
}

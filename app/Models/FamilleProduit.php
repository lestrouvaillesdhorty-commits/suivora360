<?php

namespace App\Models;

use App\Core\Database;

/**
 * Familles de produit des demandes d'achat (filtre du tableau de bord, formulaire de demande), modifiables par filiale.
 * Les familles d'origine (SousActivite::FAMILLES) sont copiées à la première utilisation. Sans la migration V82, la liste d'origine sert.
 */
class FamilleProduit
{
    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM familles_produit LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function assurer(int $filialeId): void
    {
        if (!$filialeId || !self::disponible()) {
            return;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT COUNT(*) FROM familles_produit WHERE filiale_id = ?');
        $st->execute([$filialeId]);
        if ((int) $st->fetchColumn() > 0) {
            return;
        }
        foreach (SousActivite::FAMILLES as $i => $nom) {
            $pdo->prepare('INSERT INTO familles_produit (filiale_id, nom, ordre, actif, created_at) VALUES (?,?,?,1,?)')->execute([$filialeId, $nom, $i + 1, date('Y-m-d H:i:s')]);
        }
    }

    /** Toutes les familles de la filiale (actives ou non), avec le nombre de demandes concernées. */
    public static function lignes(int $filialeId): array
    {
        if (!self::disponible()) {
            return [];
        }
        self::assurer($filialeId);
        $st = Database::connection()->prepare('SELECT * FROM familles_produit WHERE filiale_id = ? ORDER BY ordre, id');
        $st->execute([$filialeId]);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            try {
                $q = Database::connection()->prepare('SELECT COUNT(*) FROM demandes WHERE filiale_id = ? AND famille_produit = ?');
                $q->execute([$filialeId, $r['nom']]);
                $r['usages'] = (int) $q->fetchColumn();
            } catch (\Throwable $e) {
                $r['usages'] = 0;
            }
        }
        return $rows;
    }

    /** Noms des familles actives (liste d'origine si la migration n'est pas passée). */
    public static function noms(int $filialeId): array
    {
        if (!self::disponible() || !$filialeId) {
            return SousActivite::FAMILLES;
        }
        return array_values(array_map(fn($r) => (string) $r['nom'], array_filter(self::lignes($filialeId), fn($r) => (int) $r['actif'] === 1)));
    }

    public static function valide(int $filialeId, string $f): string
    {
        $f = trim($f);
        return $f !== '' && in_array($f, self::noms($filialeId), true) ? $f : '';
    }

    public static function ajouter(int $filialeId, string $nom): bool
    {
        $nom = trim(mb_substr($nom, 0, 120));
        if ($nom === '' || !self::disponible()) {
            return false;
        }
        self::assurer($filialeId);
        foreach (self::lignes($filialeId) as $r) {
            if (mb_strtolower($r['nom']) === mb_strtolower($nom)) {
                return false;
            }
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT COALESCE(MAX(ordre), 0) + 1 FROM familles_produit WHERE filiale_id = ?');
        $st->execute([$filialeId]);
        $pdo->prepare('INSERT INTO familles_produit (filiale_id, nom, ordre, actif, created_at) VALUES (?,?,?,1,?)')->execute([$filialeId, $nom, (int) $st->fetchColumn(), date('Y-m-d H:i:s')]);
        return true;
    }

    /** Renomme une famille ; les demandes déjà classées dedans suivent. */
    public static function renommer(int $filialeId, int $id, string $nom): bool
    {
        $nom = trim(mb_substr($nom, 0, 120));
        if ($nom === '' || !self::disponible()) {
            return false;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT nom FROM familles_produit WHERE id = ? AND filiale_id = ?');
        $st->execute([$id, $filialeId]);
        $ancien = $st->fetchColumn();
        if ($ancien === false) {
            return false;
        }
        foreach (self::lignes($filialeId) as $r) {
            if ((int) $r['id'] !== $id && mb_strtolower($r['nom']) === mb_strtolower($nom)) {
                return false;
            }
        }
        $pdo->prepare('UPDATE familles_produit SET nom = ? WHERE id = ? AND filiale_id = ?')->execute([$nom, $id, $filialeId]);
        try {
            $pdo->prepare('UPDATE demandes SET famille_produit = ? WHERE filiale_id = ? AND famille_produit = ?')->execute([$nom, $filialeId, $ancien]);
        } catch (\Throwable $e) {
        }
        return true;
    }

    public static function basculer(int $filialeId, int $id): void
    {
        if (self::disponible()) {
            Database::connection()->prepare('UPDATE familles_produit SET actif = 1 - actif WHERE id = ? AND filiale_id = ?')->execute([$id, $filialeId]);
        }
    }
}

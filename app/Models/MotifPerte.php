<?php

namespace App\Models;

use App\Core\Database;

/**
 * Motifs de perte (devis refusé, dossier clôturé sans commande), modifiables par filiale.
 * Les 8 motifs d'origine (Cotation::MOTIFS_PERTE) sont copiés dans la table à la première
 * ouverture de la page des listes ou par migrate_v51.php ; ils peuvent être renommés ou
 * désactivés (sauf « Autre »). Un motif ajouté a le code « m » + identifiant.
 * Tant que migrate_v51.php n'a pas été lancée, la liste d'origine sert de repli.
 */
class MotifPerte
{
    private static ?array $cacheLibelles = null;

    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM motifs_perte LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Copie les motifs d'origine dans la filiale s'ils n'y sont pas encore. */
    public static function assurer(int $filialeId): void
    {
        if (!$filialeId || !self::disponible()) {
            return;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT COUNT(*) FROM motifs_perte WHERE filiale_id = ?');
        $st->execute([$filialeId]);
        if ((int) $st->fetchColumn() > 0) {
            return;
        }
        $ins = $pdo->prepare('INSERT INTO motifs_perte (filiale_id, code, libelle, ordre, actif, created_at) VALUES (?,?,?,?,1,?)');
        $i = 0;
        foreach (Cotation::MOTIFS_PERTE as $code => $lib) {
            $ins->execute([$filialeId, $code, $lib, ++$i, date('Y-m-d H:i:s')]);
        }
        self::$cacheLibelles = null;
    }

    /** Motifs proposés dans les listes de la filiale : [code => libellé]. Repli : liste d'origine. */
    public static function actifs(int $filialeId): array
    {
        if (!self::disponible()) {
            return Cotation::MOTIFS_PERTE;
        }
        self::assurer($filialeId);
        $st = Database::connection()->prepare('SELECT code, libelle FROM motifs_perte WHERE filiale_id = ? AND actif = 1 ORDER BY (code = \'autre\'), ordre, id');
        $st->execute([$filialeId]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[$r['code']] = $r['libelle'];
        }
        return $out ?: Cotation::MOTIFS_PERTE;
    }

    /** Ce code est-il choisissable dans cette filiale ? */
    public static function valide(int $filialeId, string $code): bool
    {
        return $code !== '' && isset(self::actifs($filialeId)[$code]);
    }

    /** Libellé d'un code (filiale donnée si connue, sinon n'importe laquelle ; même désactivé). */
    public static function libelle(?string $code, ?int $filialeId = null): string
    {
        $code = (string) $code;
        if ($code === '') {
            return 'Motif non renseigné';
        }
        if (self::$cacheLibelles === null) {
            self::$cacheLibelles = [];
            if (self::disponible()) {
                foreach (Database::connection()->query('SELECT filiale_id, code, libelle FROM motifs_perte ORDER BY id')->fetchAll() as $r) {
                    self::$cacheLibelles[$r['code']][(int) $r['filiale_id']] = $r['libelle'];
                }
            }
        }
        $l = self::$cacheLibelles[$code] ?? [];
        if ($filialeId !== null && isset($l[$filialeId])) {
            return $l[$filialeId];
        }
        return $l ? reset($l) : (Cotation::MOTIFS_PERTE[$code] ?? $code);
    }

    /** Lignes de la page de réglage, avec le nombre d'utilisations. */
    public static function lignes(int $filialeId): array
    {
        if (!self::disponible()) {
            return [];
        }
        self::assurer($filialeId);
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT * FROM motifs_perte WHERE filiale_id = ? ORDER BY (code = \'autre\'), ordre, id');
        $st->execute([$filialeId]);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $n = 0;
            try {
                $q = $pdo->prepare('SELECT COUNT(*) FROM cotations WHERE filiale_id = ? AND motif_perte = ?');
                $q->execute([$filialeId, $r['code']]);
                $n += (int) $q->fetchColumn();
            } catch (\Throwable $e) {
            }
            try {
                $q = $pdo->prepare('SELECT COUNT(*) FROM dossiers WHERE filiale_id = ? AND motif_cloture = ?');
                $q->execute([$filialeId, $r['code']]);
                $n += (int) $q->fetchColumn();
            } catch (\Throwable $e) {
            }
            $r['usages'] = $n;
        }
        return $rows;
    }

    public static function ajouter(int $filialeId, string $libelle): bool
    {
        $libelle = trim(mb_substr($libelle, 0, 120));
        if ($libelle === '' || !self::disponible()) {
            return false;
        }
        self::assurer($filialeId);
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT id, actif FROM motifs_perte WHERE filiale_id = ? AND LOWER(libelle) = LOWER(?)');
        $st->execute([$filialeId, $libelle]);
        if ($ex = $st->fetch()) {
            $pdo->prepare('UPDATE motifs_perte SET actif = 1 WHERE id = ?')->execute([$ex['id']]);
            return true;
        }
        $st = $pdo->prepare('SELECT COALESCE(MAX(ordre), 0) + 1 FROM motifs_perte WHERE filiale_id = ?');
        $st->execute([$filialeId]);
        $ordre = (int) $st->fetchColumn();
        $pdo->prepare('INSERT INTO motifs_perte (filiale_id, code, libelle, ordre, actif, created_at) VALUES (?,?,?,?,1,?)')
            ->execute([$filialeId, 'tmp', $libelle, $ordre, date('Y-m-d H:i:s')]);
        $id = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE motifs_perte SET code = ? WHERE id = ?')->execute(['m' . $id, $id]);
        self::$cacheLibelles = null;
        return true;
    }

    public static function renommer(int $filialeId, int $id, string $libelle): bool
    {
        $libelle = trim(mb_substr($libelle, 0, 120));
        if ($libelle === '' || !self::disponible()) {
            return false;
        }
        Database::connection()->prepare('UPDATE motifs_perte SET libelle = ? WHERE id = ? AND filiale_id = ?')->execute([$libelle, $id, $filialeId]);
        self::$cacheLibelles = null;
        return true;
    }

    /** Active / désactive (« Autre » reste toujours actif). */
    public static function basculer(int $filialeId, int $id): void
    {
        if (self::disponible()) {
            Database::connection()->prepare("UPDATE motifs_perte SET actif = 1 - actif WHERE id = ? AND filiale_id = ? AND code <> 'autre'")->execute([$id, $filialeId]);
        }
    }

    /** Suppression possible seulement pour un motif ajouté et jamais utilisé. */
    public static function supprimer(int $filialeId, int $id): bool
    {
        foreach (self::lignes($filialeId) as $r) {
            if ((int) $r['id'] === $id && str_starts_with((string) $r['code'], 'm') && $r['usages'] === 0) {
                Database::connection()->prepare('DELETE FROM motifs_perte WHERE id = ? AND filiale_id = ?')->execute([$id, $filialeId]);
                self::$cacheLibelles = null;
                return true;
            }
        }
        return false;
    }
}

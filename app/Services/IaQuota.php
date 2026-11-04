<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Filiale;

/**
 * Protège le budget IA : chaque lecture par l'IA est comptée par filiale et par mois,
 * plafonnée (100 par défaut, réglable), et un résultat déjà obtenu pour exactement le même
 * contenu est réutilisé sans nouvel appel ni décompte. Avant migrate_v39.php : aucun
 * plafond, comportement inchangé.
 */
class IaQuota
{
    public const PLAFOND_DEFAUT = 100;
    public const SEUIL_ALERTE = 0.8;
    private const MEMO_JOURS = 60;

    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM ia_usages LIMIT 1');
            Database::connection()->query('SELECT 1 FROM ia_reglages LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function plafond(int $filialeId): int
    {
        if (!self::disponible()) {
            return self::PLAFOND_DEFAUT;
        }
        $st = Database::connection()->prepare('SELECT plafond_mensuel FROM ia_reglages WHERE filiale_id = ?');
        $st->execute([$filialeId]);
        $v = $st->fetchColumn();
        return $v === false ? self::PLAFOND_DEFAUT : (int) $v;
    }

    public static function definirPlafond(int $filialeId, int $plafond): void
    {
        $plafond = max(0, min(100000, $plafond));
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT id FROM ia_reglages WHERE filiale_id = ?');
        $st->execute([$filialeId]);
        if ($id = $st->fetchColumn()) {
            $pdo->prepare('UPDATE ia_reglages SET plafond_mensuel = ?, updated_at = ? WHERE id = ?')->execute([$plafond, date('Y-m-d H:i:s'), $id]);
        } else {
            $pdo->prepare('INSERT INTO ia_reglages (filiale_id, plafond_mensuel, updated_at) VALUES (?,?,?)')->execute([$filialeId, $plafond, date('Y-m-d H:i:s')]);
        }
    }

    public static function utilises(int $filialeId): int
    {
        if (!self::disponible()) {
            return 0;
        }
        $st = Database::connection()->prepare('SELECT COUNT(*) FROM ia_usages WHERE filiale_id = ? AND created_at >= ?');
        $st->execute([$filialeId, date('Y-m-01 00:00:00')]);
        return (int) $st->fetchColumn();
    }

    /** @return array{utilises:int, plafond:int, alerte:bool, atteint:bool} */
    public static function statut(int $filialeId): array
    {
        $u = self::utilises($filialeId);
        $p = self::plafond($filialeId);
        return ['utilises' => $u, 'plafond' => $p, 'alerte' => $p > 0 && $u >= $p * self::SEUIL_ALERTE, 'atteint' => $u >= $p];
    }

    /** Filiale à laquelle rattacher l'appel : celle demandée si accessible, sinon la première de l'utilisateur. */
    public static function filialePour(array $user, ?int $demandee = null): int
    {
        if ($demandee && Filiale::userCanAccess($user, $demandee)) {
            return $demandee;
        }
        $fid = (int) ($_POST['filiale_id'] ?? 0);
        if ($fid && Filiale::userCanAccess($user, $fid)) {
            return $fid;
        }
        return (int) (Filiale::visibleFor($user)[0]['id'] ?? 0);
    }

    /**
     * Exécute $appel (qui retourne un tableau) sous plafond, avec mémoire.
     *
     * @return array{resultat:array, memo:bool, statut:array}
     * @throws \RuntimeException plafond atteint (ou erreur de l'appel)
     */
    public static function executer(array $user, int $filialeId, string $type, string $empreinte, callable $appel): array
    {
        if (!self::disponible() || !$filialeId) {
            return ['resultat' => $appel(), 'memo' => false, 'statut' => ['utilises' => 0, 'plafond' => self::PLAFOND_DEFAUT, 'alerte' => false, 'atteint' => false]];
        }
        $empreinte = hash('sha256', $empreinte);
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT resultat FROM ia_usages WHERE filiale_id = ? AND type = ? AND empreinte = ? AND resultat IS NOT NULL AND created_at >= ? ORDER BY id DESC LIMIT 1');
        $st->execute([$filialeId, $type, $empreinte, date('Y-m-d H:i:s', strtotime('-' . self::MEMO_JOURS . ' days'))]);
        $memo = $st->fetchColumn();
        if ($memo !== false) {
            $res = json_decode((string) $memo, true);
            if (is_array($res)) {
                return ['resultat' => $res, 'memo' => true, 'statut' => self::statut($filialeId)];
            }
        }
        $statut = self::statut($filialeId);
        if ($statut['atteint']) {
            throw new \RuntimeException("Plafond mensuel de lectures par l'IA atteint pour cette filiale ({$statut['utilises']}/{$statut['plafond']}). Saisissez à la main, ou demandez à un administrateur de relever le plafond dans Paramètres › IA.");
        }
        $res = $appel();
        $pdo->prepare('INSERT INTO ia_usages (filiale_id, utilisateur_id, type, empreinte, resultat, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$filialeId, (int) ($user['id'] ?? 0), $type, $empreinte, json_encode($res, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s')]);
        return ['resultat' => $res, 'memo' => false, 'statut' => self::statut($filialeId)];
    }

    /** Message court pour l'écran : « Lectures IA ce mois : 82/100 », avec alerte à 80 %. */
    public static function message(array $statut): string
    {
        $m = "Lectures IA ce mois : {$statut['utilises']}/{$statut['plafond']}.";
        if ($statut['atteint']) {
            return $m . ' Plafond atteint.';
        }
        return $statut['alerte'] ? $m . ' Vous approchez du plafond.' : $m;
    }
}

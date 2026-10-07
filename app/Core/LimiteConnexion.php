<?php

namespace App\Core;

/**
 * Limitation des tentatives de connexion (anti « devinette » de mot de passe).
 *
 * Règle : au bout de 5 échecs en 15 minutes pour une même adresse e-mail, ou de
 * 20 échecs en 15 minutes depuis une même adresse IP, toute nouvelle tentative
 * est refusée (même avec le bon mot de passe) jusqu'à la fin de la fenêtre.
 * Une connexion réussie remet à zéro le compteur de l'adresse e-mail.
 *
 * Sûre avant migration : si la table `tentatives_connexion` n'existe pas encore
 * (code déployé avant migrate_v21), la limitation est simplement inactive — le
 * site ne doit jamais tomber à cause d'une migration pas encore lancée.
 */
class LimiteConnexion
{
    public const MAX_PAR_EMAIL = 5;
    public const MAX_PAR_IP = 20;
    public const FENETRE_MINUTES = 15;

    private static function cle(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    private static function depuis(): string
    {
        return date('Y-m-d H:i:s', time() - self::FENETRE_MINUTES * 60);
    }

    public static function bloque(string $email): bool
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM tentatives_connexion WHERE email = ? AND created_at >= ?');
            $stmt->execute([self::cle($email), self::depuis()]);
            if ((int) $stmt->fetchColumn() >= self::MAX_PAR_EMAIL) {
                return true;
            }
            $ip = self::ip();
            if ($ip !== '') {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM tentatives_connexion WHERE ip = ? AND created_at >= ?');
                $stmt->execute([$ip, self::depuis()]);
                if ((int) $stmt->fetchColumn() >= self::MAX_PAR_IP) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    public static function echec(string $email): void
    {
        try {
            $pdo = Database::connection();
            $pdo->prepare('INSERT INTO tentatives_connexion (email, ip, created_at) VALUES (?, ?, ?)')
                ->execute([substr(self::cle($email), 0, 255), self::ip(), date('Y-m-d H:i:s')]);
            // Ménage : on ne garde que 24 h d'historique.
            $pdo->prepare('DELETE FROM tentatives_connexion WHERE created_at < ?')
                ->execute([date('Y-m-d H:i:s', time() - 86400)]);
        } catch (\Throwable $e) {
            // table absente : limitation inactive
        }
    }

    public static function reussite(string $email): void
    {
        try {
            Database::connection()->prepare('DELETE FROM tentatives_connexion WHERE email = ?')
                ->execute([self::cle($email)]);
        } catch (\Throwable $e) {
        }
    }
}

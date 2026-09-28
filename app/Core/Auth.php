<?php

namespace App\Core;

use App\Models\Utilisateur;

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = Utilisateur::findByEmail($email);
        if (!$user || !$user['actif']) {
            return false;
        }
        if (!password_verify($password, $user['mot_de_passe_hash'])) {
            return false;
        }
        $_SESSION['user_id'] = $user['id'];
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        static $cached = null;
        if ($cached === null) {
            $cached = Utilisateur::find((int) $_SESSION['user_id']);
        }
        return $cached;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /index.php?r=login');
            exit;
        }
    }

    /**
     * @deprecated depuis les rôles fins (Phase 4) — conservé comme alias de
     * isProprietaire() au cas où du code non repéré s'y référerait encore.
     */
    public static function isDirigeant(): bool
    {
        return self::isProprietaire();
    }

    public static function role(): string
    {
        $user = self::user();
        return $user['role'] ?? 'lecture_seule';
    }

    public static function isProprietaire(): bool
    {
        return self::role() === 'proprietaire';
    }

    public static function isAdmin(): bool
    {
        return Permissions::isAdmin(self::role());
    }

    public static function canManageParametres(): bool
    {
        return Permissions::canManageParametres(self::role());
    }

    public static function canSeeMarges(): bool
    {
        return Permissions::canSeeMarges(self::role());
    }

    public static function canValiderOffres(): bool
    {
        return Permissions::canValiderOffres(self::role());
    }

    public static function canGererCotations(): bool
    {
        return Permissions::canGererCotations(self::role());
    }

    public static function canVoirPilotage(): bool
    {
        return Permissions::canVoirPilotage(self::role());
    }

    public static function canWrite(): bool
    {
        return Permissions::canWrite(self::role());
    }

    /**
     * Bloque toute action de création/modification/suppression pour le rôle
     * Lecture seule. À appeler en tête de chaque action d'écriture qui n'a
     * pas déjà une restriction plus spécifique (requireAdmin, etc.).
     */
    public static function requireWrite(): void
    {
        if (!self::canWrite()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    public static function requireParametres(): void
    {
        if (!self::canManageParametres()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    public static function requirePilotage(): void
    {
        if (!self::canVoirPilotage()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return $token !== null && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

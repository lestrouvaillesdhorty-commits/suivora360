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

    public static function isDirigeant(): bool
    {
        $user = self::user();
        return $user && $user['role'] === 'dirigeant';
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

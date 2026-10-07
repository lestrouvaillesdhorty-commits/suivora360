<?php

namespace App\Core;

use App\Models\Parametres;
use App\Models\Utilisateur;

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Cookie de session durci : inaccessible au JavaScript (httponly), jamais
            // envoyé depuis un autre site (samesite) ; « secure » seulement si la
            // connexion est en HTTPS (le site tourne aussi en HTTP).
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => $https,
            ]);
            session_start();
        }
    }

    /** Raison du dernier refus de connexion (pour un message plus précis que « identifiants incorrects »). */
    public static string $dernierRefus = '';

    public static function attempt(string $email, string $password): bool
    {
        self::$dernierRefus = '';
        // Trop d'échecs récents : refus immédiat, même si le mot de passe est bon.
        if (LimiteConnexion::bloque($email)) {
            self::$dernierRefus = 'trop_de_tentatives';
            return false;
        }
        $user = Utilisateur::findByEmail($email);
        if (!$user || !$user['actif']) {
            LimiteConnexion::echec($email);
            return false;
        }
        if (!password_verify($password, $user['mot_de_passe_hash'])) {
            LimiteConnexion::echec($email);
            return false;
        }
        LimiteConnexion::reussite($email);
        // Entreprise suspendue par l'administrateur Suivora : accès fermé (sauf pour l'administrateur Suivora lui-même).
        if (empty($user['is_super_admin']) && !\App\Models\Organisation::estActive((int) $user['organisation_id'])) {
            self::$dernierRefus = 'organisation_suspendue';
            return false;
        }
        // Nouvel identifiant de session à chaque connexion (anti-fixation de session).
        session_regenerate_id(true);
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

    /**
     * Connecté = session ouverte ET compte toujours existant et actif.
     * Un compte désactivé (ou supprimé) perd son accès immédiatement, sans
     * attendre la fin de sa session.
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        static $cached = null;
        static $chargeePour = null;
        if (!isset($_SESSION['user_id'])) {
            $cached = null;
            $chargeePour = null;
            return null;
        }
        $uid = (int) $_SESSION['user_id'];
        if ($chargeePour !== $uid) {
            $u = Utilisateur::find($uid);
            $cached = ($u && !empty($u['actif'])) ? $u : null;
            // Entreprise suspendue en cours de session : accès fermé immédiatement.
            if ($cached && empty($cached['is_super_admin']) && !\App\Models\Organisation::estActive((int) $cached['organisation_id'])) {
                $cached = null;
            }
            $chargeePour = $uid;
        }
        if ($cached === null) {
            unset($_SESSION['user_id']);
            $chargeePour = null;
            return null;
        }
        return $cached;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /index.php?r=login');
            exit;
        }
        // Mot de passe provisoire (compte créé ou réinitialisé par un administrateur) :
        // seule la page de changement de mot de passe (et la déconnexion) reste accessible.
        $u = self::user();
        if (!empty($u['doit_changer_mdp'])) {
            $r = '/' . trim((string) ($_GET['r'] ?? ''), '/');
            if (!in_array($r, ['/mon-mot-de-passe', '/logout'], true)) {
                header('Location: /index.php?r=mon-mot-de-passe');
                exit;
            }
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

    /**
     * Administrateur Suivora : compte au-dessus de toutes les entreprises clientes
     * (colonne utilisateurs.is_super_admin, jamais attribuée par l'application
     * elle-même : uniquement par la migration v21, par e-mail saisi à la main).
     */
    public static function isSuperAdmin(): bool
    {
        $u = self::user();
        return $u !== null && !empty($u['is_super_admin']);
    }

    public static function requireSuperAdmin(): void
    {
        if (!self::isSuperAdmin()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
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

    /**
     * $filialeId est nécessaire pour trancher le cas Commercial : son droit
     * de modifier la marge dépend du paramètre `commercial_peut_modifier_marge`
     * de la filiale du dossier en cours (décision du 03/10 révisée — voir
     * Permissions::canModifierMarge()). Les autres rôles n'en ont pas besoin,
     * mais on le lit systématiquement pour garder un seul point de vérité.
     */
    public static function canModifierMarge(int $filialeId): bool
    {
        $role = self::role();
        if ($role !== 'commercial') {
            return Permissions::canModifierMarge($role);
        }
        $parametres = $filialeId ? Parametres::forFiliale($filialeId) : [];
        return Permissions::canModifierMarge($role, !empty($parametres['commercial_peut_modifier_marge']));
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

<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: /index.php');
            exit;
        }
        View::renderPlain('auth/login', ['csrfToken' => Auth::csrfToken()]);
    }

    public function login(): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=login');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (Auth::attempt($email, $password)) {
            header('Location: /index.php');
            exit;
        }

        if (Auth::$dernierRefus === 'organisation_suspendue') {
            $msg = "L'accès de votre entreprise est suspendu. Contactez l'administrateur Suivora.";
        } elseif (Auth::$dernierRefus === 'trop_de_tentatives') {
            $msg = 'Trop de tentatives de connexion. Réessayez dans ' . \App\Core\LimiteConnexion::FENETRE_MINUTES . ' minutes.';
        } else {
            $msg = 'Email ou mot de passe incorrect.';
        }
        View::flash('erreur', $msg);
        header('Location: /index.php?r=login');
        exit;
    }

    /** Page « Mon mot de passe » : changement volontaire, ou obligatoire (mot de passe provisoire). */
    public function showChangePassword(): void
    {
        $user = Auth::user();
        View::render('auth/mot_de_passe', [
            'csrfToken' => Auth::csrfToken(),
            'obligatoire' => !empty($user['doit_changer_mdp']),
        ]);
    }

    public function changePassword(): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=mon-mot-de-passe');
            exit;
        }
        $user = Auth::user();
        $actuel = (string) ($_POST['mot_de_passe_actuel'] ?? '');
        $nouveau = (string) ($_POST['nouveau_mot_de_passe'] ?? '');
        $confirmation = (string) ($_POST['confirmation'] ?? '');

        $erreur = null;
        if (!password_verify($actuel, $user['mot_de_passe_hash'])) {
            $erreur = 'Le mot de passe actuel est incorrect.';
        } elseif (strlen($nouveau) < 8) {
            $erreur = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        } elseif ($nouveau !== $confirmation) {
            $erreur = 'La confirmation ne correspond pas au nouveau mot de passe.';
        } elseif ($nouveau === $actuel) {
            $erreur = 'Le nouveau mot de passe doit être différent du mot de passe actuel.';
        }
        if ($erreur) {
            View::flash('erreur', $erreur);
            header('Location: /index.php?r=mon-mot-de-passe');
            exit;
        }

        \App\Models\Utilisateur::updatePassword((int) $user['id'], $nouveau, false);
        session_regenerate_id(true);
        View::flash('succes', 'Votre mot de passe a été changé.');
        header('Location: /index.php');
        exit;
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: /index.php?r=login');
        exit;
    }
}

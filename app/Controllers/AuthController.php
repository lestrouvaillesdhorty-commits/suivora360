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

        View::flash('erreur', 'Email ou mot de passe incorrect.');
        header('Location: /index.php?r=login');
        exit;
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: /index.php?r=login');
        exit;
    }
}

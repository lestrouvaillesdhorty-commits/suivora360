<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Filiale;
use App\Models\Utilisateur;

/**
 * Gestion des utilisateurs et de leurs accès aux filiales — réservé au dirigeant.
 */
class UtilisateurController
{
    public function index(): void
    {
        $this->requireDirigeant();
        $user = Auth::user();
        $utilisateurs = Utilisateur::allForOrganisation((int) $user['organisation_id']);
        $filiales = Filiale::allForOrganisation((int) $user['organisation_id']);

        $accesParUtilisateur = [];
        foreach ($utilisateurs as $u) {
            $accesParUtilisateur[$u['id']] = Utilisateur::filialeIds((int) $u['id']);
        }

        View::render('users/index', [
            'utilisateurs' => $utilisateurs,
            'filiales' => $filiales,
            'accesParUtilisateur' => $accesParUtilisateur,
        ]);
    }

    public function store(): void
    {
        $this->requireDirigeant();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $user = Auth::user();
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $role = $_POST['role'] ?? 'employe';
        $filialeIds = $_POST['filiale_ids'] ?? [];

        if ($nom === '' || $email === '' || strlen($motDePasse) < 6) {
            View::flash('erreur', 'Nom, email et mot de passe (6 caractères min.) sont obligatoires.');
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        if (Utilisateur::findByEmail($email)) {
            View::flash('erreur', 'Cet email est déjà utilisé.');
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $newId = Utilisateur::create([
            'organisation_id' => $user['organisation_id'],
            'nom' => $nom,
            'email' => $email,
            'mot_de_passe' => $motDePasse,
            'role' => $role === 'dirigeant' ? 'dirigeant' : 'employe',
        ]);

        if ($role !== 'dirigeant') {
            Utilisateur::setFiliales($newId, array_map('intval', $filialeIds));
        }

        View::flash('succes', 'Utilisateur créé.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }

    public function updateAcces(array $params): void
    {
        $this->requireDirigeant();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $filialeIds = $_POST['filiale_ids'] ?? [];
        Utilisateur::setFiliales((int) $params['id'], array_map('intval', $filialeIds));

        View::flash('succes', 'Accès mis à jour.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }

    private function requireDirigeant(): void
    {
        if (!Auth::isDirigeant()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }
}

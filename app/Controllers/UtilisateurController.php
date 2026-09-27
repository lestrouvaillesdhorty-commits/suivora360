<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Permissions;
use App\Core\View;
use App\Models\Filiale;
use App\Models\Utilisateur;

/**
 * Gestion des utilisateurs et de leurs accès aux filiales — réservée aux
 * rôles Propriétaire et Admin d'organisation.
 */
class UtilisateurController
{
    public function index(): void
    {
        Auth::requireAdmin();
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
            'currentUserId' => (int) $user['id'],
        ]);
    }

    public function store(): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $user = Auth::user();
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $role = $_POST['role'] ?? 'lecture_seule';
        $filialeIds = $_POST['filiale_ids'] ?? [];

        if (!Permissions::isValidRole($role)) {
            $role = 'lecture_seule';
        }
        if (!Permissions::canAssignRole(Auth::role(), $role)) {
            View::flash('erreur', "Seul un Propriétaire peut attribuer le rôle Propriétaire.");
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

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
            'role' => $role,
        ]);

        if (!Permissions::seesAllFiliales($role)) {
            Utilisateur::setFiliales($newId, array_map('intval', $filialeIds));
        }

        View::flash('succes', 'Utilisateur créé.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }

    public function updateAcces(array $params): void
    {
        Auth::requireAdmin();

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

    /**
     * Changement de rôle d'un utilisateur existant — introduit avec les
     * rôles fins pour permettre de répartir les comptes "employé" migrés
     * automatiquement vers Commercial (voir migrate_v10.php) vers leur rôle
     * définitif, un par un et sans se presser.
     */
    public function updateRole(array $params): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $cible = Utilisateur::find((int) $params['id']);
        if (!$cible) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $role = $_POST['role'] ?? '';
        if (!Permissions::isValidRole($role)) {
            View::flash('erreur', 'Rôle invalide.');
            header('Location: /index.php?r=utilisateurs');
            exit;
        }
        if (!Permissions::canAssignRole(Auth::role(), $role)) {
            View::flash('erreur', "Seul un Propriétaire peut attribuer le rôle Propriétaire.");
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        Utilisateur::updateRole((int) $cible['id'], $role);
        if (Permissions::seesAllFiliales($role)) {
            Utilisateur::setFiliales((int) $cible['id'], []);
        }

        View::flash('succes', 'Rôle mis à jour.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }

    /**
     * Modification du nom / email d'un utilisateur existant.
     */
    public function update(array $params): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $cible = Utilisateur::find((int) $params['id']);
        $user = Auth::user();
        if (!$cible || (int) $cible['organisation_id'] !== (int) $user['organisation_id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($nom === '' || $email === '') {
            View::flash('erreur', 'Nom et email sont obligatoires.');
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $existant = Utilisateur::findByEmail($email);
        if ($existant && (int) $existant['id'] !== (int) $cible['id']) {
            View::flash('erreur', 'Cet email est déjà utilisé par un autre compte.');
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        Utilisateur::updateInfo((int) $cible['id'], $nom, $email);

        View::flash('succes', 'Utilisateur modifié.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }

    /**
     * Désactivation d'un compte — bloque la connexion (voir Auth::attempt)
     * sans supprimer les données. On empêche de se désactiver soi-même et
     * de désactiver le dernier Propriétaire actif de l'organisation.
     */
    public function desactiver(array $params): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $cible = Utilisateur::find((int) $params['id']);
        $user = Auth::user();
        if (!$cible || (int) $cible['organisation_id'] !== (int) $user['organisation_id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        if ((int) $cible['id'] === (int) $user['id']) {
            View::flash('erreur', 'Vous ne pouvez pas désactiver votre propre compte.');
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        if ($cible['role'] === 'proprietaire'
            && Utilisateur::countProprietairesActifs((int) $user['organisation_id'], (int) $cible['id']) === 0
        ) {
            View::flash('erreur', "Impossible de désactiver le dernier compte Propriétaire actif.");
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        Utilisateur::setActive((int) $cible['id'], false);
        View::flash('succes', 'Compte désactivé. Il ne peut plus se connecter mais reste visible et peut être réactivé.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }

    public function activer(array $params): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $cible = Utilisateur::find((int) $params['id']);
        $user = Auth::user();
        if (!$cible || (int) $cible['organisation_id'] !== (int) $user['organisation_id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        Utilisateur::setActive((int) $cible['id'], true);
        View::flash('succes', 'Compte réactivé.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }

    /**
     * Réinitialisation du mot de passe par un admin/propriétaire — le mot
     * de passe existant n'est jamais affiché (il n'est même pas stocké en
     * clair), on ne peut qu'en définir un nouveau.
     */
    public function resetPassword(array $params): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $cible = Utilisateur::find((int) $params['id']);
        $user = Auth::user();
        if (!$cible || (int) $cible['organisation_id'] !== (int) $user['organisation_id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $motDePasse = $_POST['mot_de_passe'] ?? '';
        if (strlen($motDePasse) < 6) {
            View::flash('erreur', 'Le mot de passe doit contenir au moins 6 caractères.');
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        Utilisateur::updatePassword((int) $cible['id'], $motDePasse);
        View::flash('succes', 'Mot de passe réinitialisé.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }
}

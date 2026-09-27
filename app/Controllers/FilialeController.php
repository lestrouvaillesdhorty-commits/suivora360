<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Filiale;

/**
 * Gestion des filiales — réservée aux rôles Propriétaire et Admin d'organisation.
 */
class FilialeController
{
    private const MAX_FILIALES = 5;

    public function index(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();
        $filiales = Filiale::allForOrganisation((int) $user['organisation_id']);
        View::render('branches/index', [
            'filiales' => $filiales,
            'maxAtteint' => count($filiales) >= self::MAX_FILIALES,
        ]);
    }

    public function store(): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=filiales');
            exit;
        }

        $user = Auth::user();
        $count = Filiale::countForOrganisation((int) $user['organisation_id']);

        if ($count >= self::MAX_FILIALES) {
            View::flash('erreur', 'Nombre maximum de filiales atteint (' . self::MAX_FILIALES . ').');
            header('Location: /index.php?r=filiales');
            exit;
        }

        $nom = trim($_POST['nom'] ?? '');
        if ($nom === '') {
            View::flash('erreur', 'Le nom de la filiale est obligatoire.');
            header('Location: /index.php?r=filiales');
            exit;
        }

        Filiale::create((int) $user['organisation_id'], $nom);
        View::flash('succes', 'Filiale créée.');
        header('Location: /index.php?r=filiales');
        exit;
    }
}

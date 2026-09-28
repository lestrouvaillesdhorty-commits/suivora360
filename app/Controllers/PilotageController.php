<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Filiale;
use App\Models\Pilotage;
use App\Models\Utilisateur;

/**
 * Module Pilotage (feuille de route, point 5) : analyse par période,
 * distincte du Tableau de bord qui reste orienté action rapide. Réservé à
 * Propriétaire/Admin d'organisation/Finance — mêmes rôles que la Marge sur
 * une Cotation et les Paramètres, la page expose une "valeur active" et une
 * "marge prévisionnelle" au même niveau de sensibilité.
 */
class PilotageController
{
    public function index(): void
    {
        Auth::requirePilotage();
        $user = Auth::user();

        $filters = [
            'activite' => trim($_GET['activite'] ?? ''),
            'responsable_id' => $_GET['responsable_id'] ?? '',
            'date_debut' => $_GET['date_debut'] ?? '',
            'date_fin' => $_GET['date_fin'] ?? '',
        ];

        $utilisateurs = Utilisateur::allForOrganisation((int) $user['organisation_id']);

        View::render('pilotage/index', [
            'filters' => $filters,
            'utilisateurs' => $utilisateurs,
            'kpis' => Pilotage::kpis($user, $filters),
            'parActivite' => Pilotage::parActivite($user, $filters),
            'parResponsable' => Pilotage::parResponsable($user, $filters),
            'evolution' => Pilotage::evolutionMensuelle($user, $filters, 6),
            'retards' => Pilotage::retards($user, $filters, 10),
            'parCollaborateur' => Pilotage::parCollaborateur($user, $filters),
            'filiales' => Filiale::visibleFor($user),
        ]);
    }
}

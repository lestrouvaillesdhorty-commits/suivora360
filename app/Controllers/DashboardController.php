<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Demande;
use App\Models\Dossier;
use App\Models\Filiale;

class DashboardController
{
    public function index(): void
    {
        $user = Auth::user();
        $demandeCounts = Demande::counts($user);
        $dossierCounts = Dossier::counts($user);
        $filiales = Filiale::visibleFor($user);

        View::render('dashboard/index', [
            'demandeCounts' => $demandeCounts,
            'dossierCounts' => $dossierCounts,
            'filiales' => $filiales,
        ]);
    }
}

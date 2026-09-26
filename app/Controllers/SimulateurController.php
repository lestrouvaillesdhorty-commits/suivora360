<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Filiale;
use App\Models\Parametres;
use App\Services\Simulateur;

class SimulateurController
{
    public function index(): void
    {
        $user = Auth::user();
        $filiales = Filiale::visibleFor($user);
        $filialeId = (int) ($_GET['filiale_id'] ?? ($filiales[0]['id'] ?? 0));
        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            $filialeId = (int) ($filiales[0]['id'] ?? 0);
        }
        $parametres = $filialeId ? Parametres::forFiliale($filialeId) : Parametres::DEFAUTS;

        $hasInput = isset($_GET['achat']);
        $resultat = null;
        if ($hasInput) {
            $resultat = Simulateur::calculer([
                'achat' => $_GET['achat'] ?? 0,
                'poids' => $_GET['poids'] ?? 0,
                'transport' => $_GET['transport'] ?? 0,
                'emballage' => $_GET['emballage'] ?? 0,
                'assurance' => $_GET['assurance'] ?? ($parametres['assurance_defaut'] ?? 0),
                'douane' => $_GET['douane'] ?? 0,
                'dedouanement' => $_GET['dedouanement'] ?? ($parametres['dedouanement_defaut'] ?? 0),
                'autres_frais' => $_GET['autres_frais'] ?? 0,
                'quantite' => $_GET['quantite'] ?? 0,
                'majoration_pourcentage' => $_GET['majoration_pourcentage'] ?? ($parametres['marge_defaut_pourcentage'] ?? 0),
                'tva_pourcentage' => $_GET['tva_pourcentage'] ?? ($parametres['tva_defaut_pourcentage'] ?? 0),
                'taux_fcfa' => $_GET['taux_fcfa'] ?? ($parametres['taux_eur_fcfa'] ?? 0),
                'mode_transport' => $_GET['mode_transport'] ?? '',
                'longueur_cm' => $_GET['longueur_cm'] ?? 0,
                'largeur_cm' => $_GET['largeur_cm'] ?? 0,
                'hauteur_cm' => $_GET['hauteur_cm'] ?? 0,
                'diviseur_volumetrique_aerien' => $parametres['diviseur_volumetrique_aerien'] ?? 6000,
                'diviseur_volumetrique_maritime' => $parametres['diviseur_volumetrique_maritime'] ?? 1000,
            ]);
        }

        View::render('simulateur/index', [
            'filiales' => $filiales,
            'filialeId' => $filialeId,
            'parametres' => $parametres,
            'resultat' => $resultat,
            'input' => $_GET,
        ]);
    }
}

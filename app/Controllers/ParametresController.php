<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Filiale;
use App\Models\Parametres;

/**
 * Paramètres de calcul (taux de change, majoration/TVA par défaut, frais
 * par défaut) — réservé aux rôles Propriétaire, Admin d'organisation et
 * Finance. Modifier ces valeurs ne
 * recalcule jamais rétroactivement une simulation, une offre ou une
 * commande déjà enregistrée : elles ne servent qu'à préremplir les
 * futures simulations.
 */
class ParametresController
{
    public function index(): void
    {
        Auth::requireParametres();
        $user = Auth::user();
        $filiales = Filiale::visibleFor($user);
        $filialeId = (int) ($_GET['filiale_id'] ?? ($filiales[0]['id'] ?? 0));
        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            $filialeId = (int) ($filiales[0]['id'] ?? 0);
        }
        $parametres = $filialeId ? Parametres::forFiliale($filialeId) : Parametres::DEFAUTS;

        View::render('parametres/index', [
            'filiales' => $filiales,
            'filialeId' => $filialeId,
            'parametres' => $parametres,
        ]);
    }

    public function update(): void
    {
        Auth::requireParametres();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=parametres');
            exit;
        }

        $user = Auth::user();
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);
        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            View::flash('erreur', "Vous n'avez pas accès à cette filiale.");
            header('Location: /index.php?r=parametres');
            exit;
        }

        $taux = (float) ($_POST['taux_eur_fcfa'] ?? 0);
        if ($taux <= 0) {
            View::flash('erreur', 'Le taux de conversion doit être strictement positif.');
            header('Location: /index.php?r=parametres&filiale_id=' . $filialeId);
            exit;
        }

        Parametres::update($filialeId, [
            'taux_eur_fcfa' => $taux,
            'marge_defaut_pourcentage' => $_POST['marge_defaut_pourcentage'] ?? 0,
            'tva_defaut_pourcentage' => $_POST['tva_defaut_pourcentage'] ?? 0,
            'assurance_defaut' => $_POST['assurance_defaut'] ?? 0,
            'dedouanement_defaut' => $_POST['dedouanement_defaut'] ?? 0,
            'taux_date_maj' => $_POST['taux_date_maj'] ?? null,
            'taux_source' => $_POST['taux_source'] ?? '',
            // Corrigé en même temps que l'ajout du verrou marge ci-dessous :
            // ces deux champs étaient soumis par le formulaire mais jamais
            // transmis ici, donc Parametres::update() les réinitialisait
            // silencieusement aux valeurs par défaut (6000/1000) à chaque
            // "Enregistrer", effaçant toute valeur personnalisée.
            'diviseur_volumetrique_aerien' => $_POST['diviseur_volumetrique_aerien'] ?? Parametres::DEFAUTS['diviseur_volumetrique_aerien'],
            'diviseur_volumetrique_maritime' => $_POST['diviseur_volumetrique_maritime'] ?? Parametres::DEFAUTS['diviseur_volumetrique_maritime'],
            // Verrouillage/déverrouillage de la saisie de marge pour le
            // Commercial, décidé par filiale par le Propriétaire/Admin/Finance
            // (décision du 03/10 révisée). Case absente du POST = décochée.
            'commercial_peut_modifier_marge' => !empty($_POST['commercial_peut_modifier_marge']),
        ]);

        View::flash('succes', 'Paramètres enregistrés. Les simulations, offres et commandes déjà enregistrées ne sont jamais recalculées.');
        header('Location: /index.php?r=parametres&filiale_id=' . $filialeId);
        exit;
    }
}

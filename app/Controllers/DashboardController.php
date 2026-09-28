<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Commande;
use App\Models\Cotation;
use App\Models\Demande;
use App\Models\Dossier;
use App\Models\Filiale;
use App\Models\Offre;

class DashboardController
{
    public function index(): void
    {
        $user = Auth::user();
        $demandeCounts = Demande::counts($user);
        $dossierCounts = Dossier::counts($user);
        $filiales = Filiale::visibleFor($user);

        // Indicateurs Achats/commercial/Exécution (feuille de route, point 4) —
        // le tableau de bord reste orienté action rapide : un compteur +
        // une courte liste cliquable, jamais d'analyse détaillée (ça, c'est
        // le rôle du module Pilotage).
        $offresAAnalyser = Offre::aAnalyserFor($user, 5);
        $offresAAnalyserCount = Offre::aAnalyserCount($user);
        $cotationsARelancer = Cotation::aRelancerFor($user, 5);
        $cotationsARelancerCount = Cotation::aRelancerCount($user);
        $commandesEnCours = Commande::enCoursFor($user, 5);
        $commandesEnCoursCount = Commande::enCoursCount($user);

        $dernieresDemandes = Demande::recentesFor($user, 5);

        // Échéances à venir : demandes pas encore qualifiées + dossiers
        // actifs, échéance dans les 7 prochains jours (pas encore en
        // retard — ça, c'est déjà couvert par les compteurs rouges).
        $echeancesDemandes = array_map(
            fn($d) => ['type' => 'demande', 'reference' => $d['reference'], 'objet' => $d['objet'], 'echeance' => $d['echeance'], 'id' => $d['id']],
            Demande::echeancesAVenirFor($user, 7, 10)
        );
        $echeancesDossiers = array_map(
            fn($d) => ['type' => 'dossier', 'reference' => $d['reference'], 'objet' => $d['objet'], 'echeance' => $d['echeance'], 'id' => $d['id']],
            Dossier::echeancesAVenirFor($user, 7, 10)
        );
        $echeancesAVenir = array_merge($echeancesDemandes, $echeancesDossiers);
        usort($echeancesAVenir, fn($a, $b) => strcmp($a['echeance'], $b['echeance']));
        $echeancesAVenir = array_slice($echeancesAVenir, 0, 6);

        View::render('dashboard/index', [
            'demandeCounts' => $demandeCounts,
            'dossierCounts' => $dossierCounts,
            'filiales' => $filiales,
            'offresAAnalyser' => $offresAAnalyser,
            'offresAAnalyserCount' => $offresAAnalyserCount,
            'cotationsARelancer' => $cotationsARelancer,
            'cotationsARelancerCount' => $cotationsARelancerCount,
            'commandesEnCours' => $commandesEnCours,
            'commandesEnCoursCount' => $commandesEnCoursCount,
            'dernieresDemandes' => $dernieresDemandes,
            'echeancesAVenir' => $echeancesAVenir,
        ]);
    }
}

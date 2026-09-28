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

/**
 * Tableau de bord — reste consacré aux priorités quotidiennes (le module
 * Pilotage sert à l'analyse des résultats, voir spec Pilotage §1). Structure
 * alignée sur la maquette "Vos priorités et opérations à suivre" : cartes
 * d'alerte, "Suivi des opérations" (volumes), "Mes priorités", "Échéances à
 * venir", "Dernières demandes", "Livraisons à suivre". Toutes les données
 * sont réelles (issues de la base), jamais fabriquées pour remplir la
 * maquette — voir le script de données de démonstration pour peupler un
 * environnement vide.
 */
class DashboardController
{
    public function index(): void
    {
        $user = Auth::user();
        $demandeCounts = Demande::counts($user);
        $dossierCounts = Dossier::counts($user);
        $filiales = Filiale::visibleFor($user);

        $offresAAnalyser = Offre::aAnalyserFor($user, 5);
        $offresAAnalyserCount = Offre::aAnalyserCount($user);
        $cotationsARelancer = Cotation::aRelancerFor($user, 5);
        $cotationsARelancerCount = Cotation::aRelancerCount($user);
        $commandesEnCours = Commande::enCoursFor($user, 5);
        $commandesEnCoursCount = Commande::enCoursCount($user);
        $livraisonsEnCours = Commande::livraisonsEnCoursFor($user, 5);
        $livraisonsEnCoursCount = Commande::livraisonsEnCoursCount($user);

        $dernieresDemandes = Demande::recentesFor($user, 5);

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

        // "Mes priorités" — les commandes en cours, triées par relance la
        // plus proche (même population que "Commandes en cours", présentée
        // ici sous l'angle "quelle est la prochaine action").
        $mesPriorites = array_slice($commandesEnCours, 0, 5);

        // Alertes — ce qui traîne au-delà d'un seuil raisonnable, tous
        // types confondus, triées par ancienneté décroissante (le plus
        // urgent en premier). Seuils : cotation envoyée sans réponse depuis
        // 7 jours, offre reçue non analysée depuis 5 jours, commande dont
        // la date de relance est dépassée (même règle que Commande::estEnRetard).
        $alertes = [];
        foreach (Cotation::enAttenteDepuis($user, 7, 8) as $c) {
            $alertes[] = [
                'type' => 'Cotation sans réponse',
                'objet' => $c['dossier_reference'] . ' — ' . $c['client_nom'],
                'jours' => (int) floor((time() - strtotime($c['created_at'])) / 86400),
                'lien' => '/index.php?r=dossiers/' . $c['dossier_id'],
            ];
        }
        foreach (Offre::enAttenteDepuis($user, 5, 8) as $o) {
            $alertes[] = [
                'type' => 'Offre non analysée',
                'objet' => $o['dossier_reference'] . ' — ' . $o['fournisseur_nom'],
                'jours' => (int) floor((time() - strtotime($o['created_at'])) / 86400),
                'lien' => '/index.php?r=dossiers/' . $o['dossier_id_reel'],
            ];
        }
        foreach (Commande::enRetardFor($user, 8) as $c) {
            $alertes[] = [
                'type' => 'Commande en retard de relance',
                'objet' => $c['dossier_reference'] . ' — ' . $c['dossier_objet'],
                'jours' => (int) floor((time() - strtotime($c['date_relance'])) / 86400),
                'lien' => '/index.php?r=dossiers/' . $c['dossier_id'] . '/commande',
            ];
        }
        usort($alertes, fn($a, $b) => $b['jours'] <=> $a['jours']);
        $alertes = array_slice($alertes, 0, 8);

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
            'livraisonsEnCours' => $livraisonsEnCours,
            'livraisonsEnCoursCount' => $livraisonsEnCoursCount,
            'dernieresDemandes' => $dernieresDemandes,
            'echeancesAVenir' => $echeancesAVenir,
            'mesPriorites' => $mesPriorites,
            'alertes' => $alertes,
            'suivi' => [
                'demandes' => Demande::enCoursCount($user),
                'dossiers' => $dossierCounts['actifs'],
                'offres' => $offresAAnalyserCount,
                'cotations' => $cotationsARelancerCount,
                'commandes' => $commandesEnCoursCount,
                'livraisons' => $livraisonsEnCoursCount,
            ],
        ]);
    }
}

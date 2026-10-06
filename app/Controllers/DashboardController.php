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

        // [ajouté 03/10, demande explicite de Marie Laure] Switcher
        // filiale/activité du tableau de bord — jusqu'ici $filiales était
        // chargé mais jamais utilisé pour filtrer, et aucun filtre activité
        // n'existait. `Filiale::resoudreFiltreIds()` ignore silencieusement
        // tout filiale_id auquel l'utilisateur n'a pas accès (jamais une
        // erreur qui laisserait deviner une filiale d'une autre organisation).
        $filiales = Filiale::visibleFor($user);
        $filialeIdDemande = isset($_GET['filiale_id']) ? (int) $_GET['filiale_id'] : null;
        $filialeIds = Filiale::resoudreFiltreIds($user, $filialeIdDemande);
        $filialeIdSelection = (count($filialeIds) === 1) ? $filialeIds[0] : null;
        $activite = trim((string) ($_GET['activite'] ?? ''));
        $activite = in_array($activite, Demande::ACTIVITES, true) ? $activite : '';

        $demandeCounts = Demande::counts($user, $filialeIds, $activite ?: null);
        $dossierCounts = Dossier::counts($user, $filialeIds, $activite ?: null);

        $offresAAnalyserCount = Offre::aAnalyserCount($user, $filialeIds, $activite ?: null);
        $cotationsARelancerCount = Cotation::aRelancerCount($user, $filialeIds, $activite ?: null);
        $commandesEnCoursCount = Commande::enCoursCount($user, $filialeIds, $activite ?: null);
        $livraisonsEnCours = Commande::livraisonsEnCoursFor($user, 10, $filialeIds, $activite ?: null);
        $livraisonsEnCoursCount = Commande::livraisonsEnCoursCount($user, $filialeIds, $activite ?: null);

        $dernieresDemandes = Demande::recentesFor($user, 10, $filialeIds, $activite ?: null);

        $echeancesDemandes = array_map(
            fn($d) => ['type' => 'demande', 'reference' => $d['reference'], 'objet' => $d['objet'], 'echeance' => $d['echeance'], 'id' => $d['id']],
            Demande::echeancesAVenirFor($user, 7, 10, $filialeIds, $activite ?: null)
        );
        $echeancesDossiers = array_map(
            fn($d) => ['type' => 'dossier', 'reference' => $d['reference'], 'objet' => $d['objet'], 'echeance' => $d['echeance'], 'id' => $d['id']],
            Dossier::echeancesAVenirFor($user, 7, 10, $filialeIds, $activite ?: null)
        );
        $echeancesAVenir = array_merge($echeancesDemandes, $echeancesDossiers);
        usort($echeancesAVenir, fn($a, $b) => strcmp($a['echeance'], $b['echeance']));
        $echeancesAVenir = array_slice($echeancesAVenir, 0, 6);

        $actionsPrioritaires = $this->construireActionsPrioritaires($user, $filialeIds, $activite ?: null);
        $actionsEnRetardCount = count($actionsPrioritaires['retard']);

        View::render('dashboard/index', [
            'demandeCounts' => $demandeCounts,
            'dossierCounts' => $dossierCounts,
            'filiales' => $filiales,
            'filialeIdSelection' => $filialeIdSelection,
            'activiteSelection' => $activite,
            'activites' => Demande::ACTIVITES,
            'offresAAnalyserCount' => $offresAAnalyserCount,
            'cotationsARelancerCount' => $cotationsARelancerCount,
            'commandesEnCoursCount' => $commandesEnCoursCount,
            'livraisonsEnCours' => $livraisonsEnCours,
            'livraisonsEnCoursCount' => $livraisonsEnCoursCount,
            'dernieresDemandes' => $dernieresDemandes,
            'echeancesAVenir' => $echeancesAVenir,
            'actionsPrioritaires' => $actionsPrioritaires,
            'actionsEnRetardCount' => $actionsEnRetardCount,
            'suivi' => [
                'demandes' => Demande::enCoursCount($user, $filialeIds, $activite ?: null),
                'dossiers' => $dossierCounts['actifs'],
                'offres' => $offresAAnalyserCount,
                'cotations' => $cotationsARelancerCount,
                'commandes' => $commandesEnCoursCount,
                'livraisons' => $livraisonsEnCoursCount,
            ],
        ]);
    }

    /**
     * [ajouté 04/10, réorganisation du tableau de bord demandée par Marie
     * Laure sur la base de sa maquette de référence] Liste unique "Actions
     * prioritaires", qui remplace les anciens blocs "Alertes" et "Mes
     * priorités" — ceux-ci piochaient dans des requêtes qui se
     * recoupaient (une commande en retard de relance apparaissait à la
     * fois dans Alertes ET dans Mes priorités), ce qui faisait dire au
     * tableau de bord qu'il y avait plus d'éléments qu'en réalité. Ici,
     * chaque commande/cotation/offre/demande n'est interrogée qu'une
     * seule fois et ne peut donc apparaître qu'une seule fois, dans
     * l'onglet correspondant à son échéance (passée = "retard",
     * aujourd'hui, ou future = "à venir").
     *
     * Échéance par type :
     * - Demande : échéance réelle (colonne `demandes.echeance`).
     * - Commande : date de relance réelle (`commandes.date_relance`),
     *   saisie manuellement par l'équipe. Une commande sans date de
     *   relance n'a pas d'échéance connue : elle n'est délibérément PAS
     *   incluse ici plutôt que de lui inventer une date, mais reste
     *   visible via "Suivi des opérations" et sur sa fiche dossier.
     * - Cotation / Offre : il n'existe aucune colonne "échéance" sur ces
     *   deux tables (seulement `created_at`). Une échéance SYNTHÉTIQUE
     *   est donc calculée — created_at + 7 jours pour une cotation,
     *   + 5 jours pour une offre — en reprenant exactement les seuils
     *   déjà utilisés par `enAttenteDepuis()` pour l'ancien bloc Alertes.
     *   C'est une approximation (indiquée comme telle à Marie Laure),
     *   pas une vraie date stockée.
     */
    private function construireActionsPrioritaires(array $user, array $filialeIds, ?string $activite): array
    {
        $aujourdhui = date('Y-m-d');

        $etapeActions = [
            'paiement' => 'Confirmer le paiement fournisseur',
            'expedition' => "Relancer le fournisseur pour l'expédition",
            'douane' => 'Suivre le dédouanement',
            'livraison' => 'Suivre la livraison',
            'solde' => 'Émettre la facture de solde',
        ];
        $etapeBoutons = [
            'paiement' => 'Confirmer',
            'expedition' => 'Relancer',
            'douane' => 'Suivre',
            'livraison' => 'Suivre',
            'solde' => 'Facturer',
        ];

        $items = [];

        foreach (Demande::enRetardFor($user, 100, $filialeIds, $activite) as $d) {
            $items[] = [
                'type' => 'demande',
                'type_label' => 'Demande',
                'id' => (int) $d['id'],
                'reference' => $d['reference'],
                'titre' => $d['objet'],
                'echeance' => $d['echeance'],
                'echeance_synthetique' => false,
                'action' => 'Qualifier la demande',
                'bouton' => 'Qualifier',
                'lien' => "/index.php?r=demandes/{$d['id']}/qualifier",
                'responsable_id' => $d['responsable_id'] ?? null,
            ];
        }
        foreach (Demande::echeancesAVenirFor($user, 7, 100, $filialeIds, $activite) as $d) {
            $items[] = [
                'type' => 'demande',
                'type_label' => 'Demande',
                'id' => (int) $d['id'],
                'reference' => $d['reference'],
                'titre' => $d['objet'],
                'echeance' => $d['echeance'],
                'echeance_synthetique' => false,
                'action' => 'Qualifier la demande',
                'bouton' => 'Qualifier',
                'lien' => "/index.php?r=demandes/{$d['id']}/qualifier",
                'responsable_id' => $d['responsable_id'] ?? null,
            ];
        }

        foreach (Commande::enCoursFor($user, 200, $filialeIds, $activite) as $c) {
            if (empty($c['date_relance'])) {
                continue;
            }
            $etape = $c['etape'] ?: 'paiement';
            $items[] = [
                'type' => 'commande',
                'type_label' => 'Commande',
                'id' => (int) $c['id'],
                'dossier_id' => (int) $c['dossier_id'],
                'reference' => $c['dossier_reference'],
                'titre' => $c['dossier_objet'],
                'echeance' => $c['date_relance'],
                'echeance_synthetique' => false,
                'action' => $c['prochaine_action'] ?: ($etapeActions[$etape] ?? Commande::libelleStatutEtape($etape, 'en_cours')),
                'bouton' => $etapeBoutons[$etape] ?? 'Traiter',
                'lien' => "/index.php?r=dossiers/{$c['dossier_id']}/commande",
                'responsable_id' => $c['responsable_id'] ?? null,
            ];
        }

        foreach (Cotation::aRelancerFor($user, 200, $filialeIds, $activite) as $co) {
            $items[] = [
                'type' => 'cotation',
                'type_label' => 'Cotation',
                'id' => (int) $co['id'],
                'dossier_id' => (int) $co['dossier_id'],
                'reference' => $co['dossier_reference'],
                'titre' => $co['dossier_objet'] ?? $co['client_nom'],
                'echeance' => date('Y-m-d', strtotime($co['created_at'] . ' +7 days')),
                'echeance_synthetique' => true,
                'action' => 'Relancer le client pour la cotation',
                'bouton' => 'Relancer',
                'lien' => "/index.php?r=cotations/{$co['id']}",
                'responsable_id' => $co['responsable_id'] ?? null,
            ];
        }

        foreach (Offre::aAnalyserFor($user, 200, $filialeIds, $activite) as $o) {
            $dossierId = (int) ($o['dossier_id_reel'] ?? $o['dossier_id']);
            $items[] = [
                'type' => 'offre',
                'type_label' => 'Offre',
                'id' => (int) $o['id'],
                'dossier_id' => $dossierId,
                'reference' => $o['dossier_reference'],
                'titre' => $o['dossier_objet'],
                'echeance' => date('Y-m-d', strtotime($o['created_at'] . ' +5 days')),
                'echeance_synthetique' => true,
                'action' => 'Analyser l\'offre reçue',
                'bouton' => 'Analyser',
                'lien' => "/index.php?r=dossiers/{$dossierId}/comparateur",
                'responsable_id' => $o['responsable_id'] ?? null,
            ];
        }

        $buckets = ['retard' => [], 'aujourd_hui' => [], 'a_venir' => []];
        foreach ($items as $item) {
            if ($item['echeance'] < $aujourdhui) {
                $buckets['retard'][] = $item;
            } elseif ($item['echeance'] === $aujourdhui) {
                $buckets['aujourd_hui'][] = $item;
            } else {
                $buckets['a_venir'][] = $item;
            }
        }
        foreach ($buckets as &$bucket) {
            usort($bucket, fn($a, $b) => strcmp($a['echeance'], $b['echeance']));
        }
        unset($bucket);

        return $buckets;
    }
}

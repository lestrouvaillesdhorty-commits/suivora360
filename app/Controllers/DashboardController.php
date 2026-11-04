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
        // Dernière activité consultée : enregistrée sur le compte (migrate_v35) pour la retrouver sur tous
        // les appareils, avec un cookie en secours si la colonne n'existe pas encore. Un choix explicite
        // (y compris « Toutes les activités », valeur vide) remplace le souvenir.
        $colonneActivite = \App\Core\Schema::colonne('utilisateurs', 'derniere_activite');
        if (array_key_exists('activite', $_GET)) {
            $activite = trim((string) $_GET['activite']);
            $activite = in_array($activite, Demande::ACTIVITES, true) ? $activite : '';
            if ($colonneActivite) {
                try {
                    \App\Core\Database::connection()->prepare('UPDATE utilisateurs SET derniere_activite = ? WHERE id = ?')->execute([$activite !== '' ? $activite : null, (int) $user['id']]);
                } catch (\Throwable $e) {
                }
            }
            if (!headers_sent()) {
                setcookie('suivora_activite', $activite, ['expires' => time() + 31536000, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
            }
        } else {
            $activite = '';
            if ($colonneActivite) {
                try {
                    $st = \App\Core\Database::connection()->prepare('SELECT derniere_activite FROM utilisateurs WHERE id = ?');
                    $st->execute([(int) $user['id']]);
                    $activite = trim((string) $st->fetchColumn());
                } catch (\Throwable $e) {
                    $activite = '';
                }
            } else {
                $activite = trim((string) ($_COOKIE['suivora_activite'] ?? ''));
            }
            $activite = in_array($activite, Demande::ACTIVITES, true) ? $activite : '';
        }

        // Filtres sous-activité et famille de produit (migrate_v37) : possibles seulement une fois l'activité choisie.
        $sousOptions = [];
        $sousSelection = '';
        $familleSelection = '';
        $familleDispo = false;
        if ($activite !== '' && \App\Models\SousActivite::disponible() && \App\Models\SousActivite::colonnesDemandes()) {
            foreach ($filialeIds as $fid) {
                foreach ((\App\Models\SousActivite::nomsActifs((int) $fid)[$activite] ?? []) as $n) {
                    $sousOptions[$n] = $n;
                }
            }
            $sousOptions = array_values($sousOptions);
            $sousSelection = trim((string) ($_GET['sous_activite'] ?? ''));
            $sousSelection = in_array($sousSelection, $sousOptions, true) ? $sousSelection : '';
            $familleDispo = \App\Models\Dossier::deduireType($activite) === 'achat_sourcing';
            $familleSelection = $familleDispo ? \App\Models\SousActivite::familleValide(trim((string) ($_GET['famille_produit'] ?? ''))) : '';
            Demande::$filtresExtra = ['sous_activite' => $sousSelection, 'famille_produit' => $familleSelection];
        }

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

        // Dossiers en cours (bloc « avancement » du tableau de bord) : les plus récents, avec leur étape.
        $dossiersEnCours = [];
        try {
            $dossiersEnCours = Dossier::visibleFor($user, array_filter([
                'statut' => 'actif',
                'filiale_id' => $filialeIdSelection,
            ]));
            if ($activite) {
                $typeAttendu = Dossier::deduireType($activite);
                $dossiersEnCours = array_values(array_filter($dossiersEnCours, fn($d) => ($d['type_dossier'] ?? 'autre') === $typeAttendu));
            }
            $dossiersEnCours = array_slice($dossiersEnCours, 0, 6);
        } catch (\Throwable $e) {
            $dossiersEnCours = [];
        }

        $typeVue = $activite ? Dossier::deduireType($activite) : 'tout';
        $estPrestation = $typeVue === 'prestation_entreprise';
        $estTransport = $typeVue === 'transport_logistique';
        $livrablesARemettre = [];
        if ($estPrestation) {
            try {
                $livrablesARemettre = Commande::livrablesARemettreFor($user, 6, $filialeIds, $activite ?: null);
            } catch (\Throwable $e) {
                $livrablesARemettre = [];
            }
        }
        $fluxEnCours = [];
        try {
            $fluxEnCours = $estPrestation ? [] : Commande::fluxEnCours($user, $estTransport ? 8 : 4, $filialeIds, $activite ?: null);
        } catch (\Throwable $e) {
            $fluxEnCours = [];
        }

        $actionsPrioritaires = $this->construireActionsPrioritaires($user, $filialeIds, $activite ?: null);
        $actionsEnRetardCount = count($actionsPrioritaires['retard']);

        View::render('dashboard/index', [
            'demandeCounts' => $demandeCounts,
            'dossierCounts' => $dossierCounts,
            'filiales' => $filiales,
            'filialeIdSelection' => $filialeIdSelection,
            'activiteSelection' => $activite,
            'sousOptions' => $sousOptions,
            'sousSelection' => $sousSelection,
            'familleDispo' => $familleDispo,
            'familleSelection' => $familleSelection,
            'activites' => Demande::ACTIVITES,
            'offresAAnalyserCount' => $offresAAnalyserCount,
            'cotationsARelancerCount' => $cotationsARelancerCount,
            'commandesEnCoursCount' => $commandesEnCoursCount,
            'livraisonsEnCours' => $livraisonsEnCours,
            'livraisonsEnCoursCount' => $livraisonsEnCoursCount,
            'dernieresDemandes' => $dernieresDemandes,
            'echeancesAVenir' => $echeancesAVenir,
            'dossiersEnCours' => $dossiersEnCours,
            'fluxEnCours' => $fluxEnCours,
            'estPrestation' => $estPrestation,
            'estTransport' => $estTransport,
            'livrablesARemettre' => $livrablesARemettre,
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
                'action' => 'Relancer le client pour le devis ' . ($co['reference'] ?? ''),
                'bouton' => 'Relancer',
                'lien' => "/index.php?r=cotations/{$co['id']}",
                'responsable_id' => $co['responsable_id'] ?? null,
            ];
        }

        $offresParDossier = [];
        foreach (Offre::aAnalyserFor($user, 200, $filialeIds, $activite) as $o) {
            $dossierId = (int) ($o['dossier_id_reel'] ?? $o['dossier_id']);
            $ech = date('Y-m-d', strtotime($o['created_at'] . ' +5 days'));
            if (!isset($offresParDossier[$dossierId])) {
                $offresParDossier[$dossierId] = [
                    'type' => 'offre',
                    'type_label' => 'Offre',
                    'id' => (int) $o['id'],
                    'dossier_id' => $dossierId,
                    'reference' => $o['dossier_reference'],
                    'titre' => $o['dossier_objet'],
                    'echeance' => $ech,
                    'echeance_synthetique' => true,
                    'nb' => 0,
                    'bouton' => 'Comparer',
                    'lien' => "/index.php?r=dossiers/{$dossierId}/comparateur",
                    'responsable_id' => $o['responsable_id'] ?? null,
                ];
            }
            $offresParDossier[$dossierId]['nb']++;
            if ($ech < $offresParDossier[$dossierId]['echeance']) {
                $offresParDossier[$dossierId]['echeance'] = $ech;
            }
        }
        foreach ($offresParDossier as $it) {
            $it['action'] = $it['nb'] > 1 ? 'Analyser les ' . $it['nb'] . ' offres reçues' : "Analyser l'offre reçue";
            $items[] = $it;
        }

        if ($activite && Dossier::deduireType($activite) === 'prestation_entreprise') {
            $items = array_values(array_filter($items, fn($it) => $it['type'] !== 'offre'));
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

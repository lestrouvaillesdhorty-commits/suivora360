<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
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
    /** Sommaire des Paramètres : une carte par sujet. */
    public function accueil(): void
    {
        Auth::requireParametres();
        $user = Auth::user();
        $filiales = Filiale::visibleFor($user);
        $filialeId = (int) ($_GET['filiale_id'] ?? ($filiales[0]['id'] ?? 0));
        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            $filialeId = (int) ($filiales[0]['id'] ?? 0);
        }
        View::render('parametres/accueil', ['filialeId' => $filialeId]);
    }

    /** Page « Calcul des prix ». */
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
            header('Location: /index.php?r=parametres/calcul');
            exit;
        }

        $user = Auth::user();
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);
        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            View::flash('erreur', "Vous n'avez pas accès à cette filiale.");
            header('Location: /index.php?r=parametres/calcul');
            exit;
        }

        $taux = (float) ($_POST['taux_eur_fcfa'] ?? 0);
        if ($taux <= 0) {
            View::flash('erreur', 'Le taux de conversion doit être strictement positif.');
            header('Location: /index.php?r=parametres/calcul&filiale_id=' . $filialeId);
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

        AuditLog::log((int) $filialeId, (int) Auth::user()['id'], 'maj_parametres', 'parametres', (int) $filialeId, 'Paramètres de calcul enregistrés');
        View::flash('succes', 'Paramètres enregistrés. Les simulations, offres et commandes déjà enregistrées ne sont jamais recalculées.');
        header('Location: /index.php?r=parametres/calcul&filiale_id=' . $filialeId);
        exit;
    }

    /** Page « Sous-activités et catégories » : listes configurables par filiale. */
    public function listes(): void
    {
        Auth::requireParametres();
        $user = Auth::user();
        $filiales = Filiale::visibleFor($user);
        $filialeId = (int) ($_GET['filiale_id'] ?? ($filiales[0]['id'] ?? 0));
        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            $filialeId = (int) ($filiales[0]['id'] ?? 0);
        }
        View::render('parametres/listes', [
            'filiales' => $filiales,
            'filialeId' => $filialeId,
            'sousActivites' => \App\Models\SousActivite::parActivite($filialeId),
            'usages' => \App\Models\SousActivite::usages($filialeId),
            'categories' => \App\Models\CategorieFournisseur::lignes($filialeId),
            'disponible' => \App\Models\SousActivite::disponible(),
            'motifs' => \App\Models\MotifPerte::lignes($filialeId),
            'motifsDispo' => \App\Models\MotifPerte::disponible(),
        ]);
    }

    public function listesUpdate(): void
    {
        Auth::requireParametres();
        $user = Auth::user();
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);
        $retour = '/index.php?r=parametres/listes&filiale_id=' . $filialeId;
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: ' . $retour);
            exit;
        }
        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            View::flash('erreur', "Vous n'avez pas accès à cette filiale.");
            header('Location: /index.php?r=parametres/listes');
            exit;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $nom = (string) ($_POST['nom'] ?? '');
        $ok = true;
        switch ($_POST['action'] ?? '') {
            case 'sa_ajouter':
                $ok = \App\Models\SousActivite::ajouter($filialeId, (string) ($_POST['activite'] ?? ''), $nom);
                break;
            case 'sa_renommer':
                $ok = \App\Models\SousActivite::renommer($filialeId, $id, $nom);
                break;
            case 'sa_basculer':
                $ok = \App\Models\SousActivite::basculer($filialeId, $id);
                break;
            case 'cat_ajouter':
                $ok = \App\Models\CategorieFournisseur::creer($filialeId, $nom) !== '';
                break;
            case 'cat_renommer':
                \App\Models\CategorieFournisseur::renommer($filialeId, $id, $nom);
                break;
            case 'cat_basculer':
                \App\Models\CategorieFournisseur::basculer($filialeId, $id);
                break;
            case 'mp_ajouter':
                $ok = \App\Models\MotifPerte::ajouter($filialeId, $nom);
                break;
            case 'mp_renommer':
                $ok = \App\Models\MotifPerte::renommer($filialeId, $id, $nom);
                break;
            case 'mp_basculer':
                \App\Models\MotifPerte::basculer($filialeId, $id);
                break;
            case 'mp_supprimer':
                $ok = \App\Models\MotifPerte::supprimer($filialeId, $id);
                break;
            default:
                $ok = false;
        }
        AuditLog::log($filialeId, (int) $user['id'], 'modification_listes', 'parametres', $filialeId, (string) ($_POST['action'] ?? ''));
        View::flash($ok ? 'succes' : 'erreur', $ok ? 'Liste mise à jour.' : "Modification impossible : vérifiez le nom saisi (ou lancez migrate_v37.php).");
        header('Location: ' . $retour);
        exit;
    }

    /** Page « IA » : lectures du mois et plafond par filiale. */
    public function ia(): void
    {
        Auth::requireParametres();
        $user = Auth::user();
        $filiales = Filiale::visibleFor($user);
        $lignes = [];
        foreach ($filiales as $f) {
            $lignes[] = ['filiale' => $f] + \App\Services\IaQuota::statut((int) $f['id']);
        }
        View::render('parametres/ia', [
            'lignes' => $lignes,
            'disponible' => \App\Services\IaQuota::disponible(),
            'cleIa' => \App\Services\AiExtracteur::estDisponible(),
            'modele' => trim((string) \App\Core\Env::get('ANTHROPIC_MODEL_EXTRACTION', '')),
        ]);
    }

    public function iaUpdate(): void
    {
        Auth::requireParametres();
        $user = Auth::user();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=parametres/ia');
            exit;
        }
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);
        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId) || !\App\Services\IaQuota::disponible()) {
            View::flash('erreur', "Modification impossible (accès à la filiale ou migrate_v39.php manquant).");
            header('Location: /index.php?r=parametres/ia');
            exit;
        }
        \App\Services\IaQuota::definirPlafond($filialeId, (int) ($_POST['plafond'] ?? 0));
        AuditLog::log($filialeId, (int) $user['id'], 'plafond_ia', 'parametres', $filialeId, (string) (int) ($_POST['plafond'] ?? 0));
        View::flash('succes', 'Plafond mis à jour.');
        header('Location: /index.php?r=parametres/ia');
        exit;
    }
}

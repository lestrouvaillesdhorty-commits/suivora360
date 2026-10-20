<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Demande;
use App\Models\Dossier;
use App\Models\Filiale;
use App\Models\Offre;
use App\Models\OffreItem;

class ComparateurController
{
    public function index(array $params): void
    {
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $offres = Offre::forDossier((int) $dossier['id']);
        $itemsByOffre = [];
        foreach ($offres as $offre) {
            $itemsByOffre[$offre['id']] = OffreItem::forOffre((int) $offre['id']);
        }

        // Meilleur (le plus bas) coût rendu et prix marchandises, groupés par
        // devise puisqu'on ne convertit pas encore entre devises (Phase 4).
        $meilleurCoutRenduParDevise = [];
        $meilleurPrixParDevise = [];
        $uneRetenue = false;
        foreach ($offres as $offre) {
            if ($offre['statut'] === 'retenue') {
                $uneRetenue = true;
            }
        }
        foreach ($offres as $offre) {
            // Une offre écartée individuellement ne compte plus pour le « meilleur » tant qu'aucune offre n'est retenue.
            if ($offre['statut'] === 'rejetee' && !$uneRetenue) {
                continue;
            }
            $devise = $offre['devise'] ?: '—';
            $coutRendu = Offre::coutRendu($offre);
            if (!isset($meilleurCoutRenduParDevise[$devise]) || $coutRendu < $meilleurCoutRenduParDevise[$devise]) {
                $meilleurCoutRenduParDevise[$devise] = $coutRendu;
            }
            $prix = (float) $offre['montant_total'];
            if (!isset($meilleurPrixParDevise[$devise]) || $prix < $meilleurPrixParDevise[$devise]) {
                $meilleurPrixParDevise[$devise] = $prix;
            }
        }

        $decisionExistante = false;
        foreach ($offres as $offre) {
            // Une décision n'existe que lorsqu'une offre est retenue ; écarter une offre isolée ne tranche pas.
            if ($offre['statut'] === 'retenue') {
                $decisionExistante = true;
                break;
            }
        }

        // [ajouté 06/10, étape 2 du découpage Dossiers] $filiale/$demande et
        // 'onglet' => 'achats' : requis par views/folders/_dossier_header.php,
        // désormais réutilisée ici pour que cette page (route restée
        // séparée — accès direct depuis le menu, décision du 29/09) affiche
        // la même coquille de navigation que la fiche Dossier plutôt que
        // son propre lien de retour isolé.
        View::render('comparateur/index', [
            'dossier' => $dossier,
            'filiale' => Filiale::find((int) $dossier['filiale_id']),
            'demande' => Demande::find((int) $dossier['demande_id']),
            'onglet' => 'achats',
            'offres' => $offres,
            'itemsByOffre' => $itemsByOffre,
            'meilleurCoutRenduParDevise' => $meilleurCoutRenduParDevise,
            'meilleurPrixParDevise' => $meilleurPrixParDevise,
            'decisionExistante' => $decisionExistante,
            'peutValider' => Auth::canValiderOffres(),
        ]);
    }

    public function retenir(array $params): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=dossiers/' . $params['id'] . '/comparateur');
            exit;
        }

        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        Dossier::refuserSiAnnule($dossier);
        if (!Auth::canValiderOffres()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }

        $offreId = (int) \App\Core\Tenant::offreDuDossier($_POST['offre_id'] ?? 0, (int) $dossier['id']);
        $motif = trim($_POST['motif_decision'] ?? '');
        if ($offreId <= 0 || $motif === '') {
            View::flash('erreur', 'Merci d\'indiquer un motif de décision avant de retenir une offre.');
            header('Location: /index.php?r=dossiers/' . $dossier['id'] . '/comparateur');
            exit;
        }

        Offre::retenir((int) $dossier['id'], $offreId, $motif);
        \App\Models\Dossier::avancerA((int) $dossier['id'], 'cotation', (int) $user['id']); // parcours guidé : offre retenue → étape Cotation
        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'offre_retenue', 'offre', $offreId, $motif);
        View::flash('succes', 'Offre retenue pour ce dossier.');

        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '/comparateur');
        exit;
    }

    /**
     * Revenir sur une décision déjà prise (remet toutes les offres du
     * dossier à "reçue"). Réservé à Propriétaire + Achats (comme "Retenir
     * cette offre"), motif obligatoire, toujours tracé dans l'audit.
     */
    public function revenir(array $params): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=dossiers/' . $params['id'] . '/comparateur');
            exit;
        }

        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::canValiderOffres()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }

        $motif = trim($_POST['motif_revenir'] ?? '');
        if ($motif === '') {
            View::flash('erreur', 'Merci d\'indiquer un motif pour revenir sur la décision.');
            header('Location: /index.php?r=dossiers/' . $dossier['id'] . '/comparateur');
            exit;
        }

        Offre::revenirSurDecision((int) $dossier['id']);
        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'decision_comparateur_annulee', 'dossier', (int) $dossier['id'], $motif);
        View::flash('succes', 'La décision précédente a été annulée, toutes les offres sont de nouveau comparables.');

        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '/comparateur');
        exit;
    }

    /** Écarter une seule offre (motif obligatoire) ou la remettre en comparaison. */
    public function ecarter(array $params): void
    {
        $this->actionOffre($params, 'ecarter');
    }

    public function reexaminer(array $params): void
    {
        $this->actionOffre($params, 'reexaminer');
    }

    private function actionOffre(array $params, string $action): void
    {
        $retour = '/index.php?r=dossiers/' . (int) $params['id'] . '/comparateur';
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: ' . $retour);
            exit;
        }
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        Dossier::refuserSiAnnule($dossier);
        if (!Auth::canValiderOffres()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }
        $offreId = (int) \App\Core\Tenant::offreDuDossier($_POST['offre_id'] ?? 0, (int) $dossier['id']);
        if ($offreId <= 0) {
            header('Location: ' . $retour);
            exit;
        }
        if ($action === 'ecarter') {
            $motif = trim($_POST['motif_ecart'] ?? '');
            if ($motif === '') {
                View::flash('erreur', "Merci d'indiquer un motif avant d'écarter une offre.");
            } elseif (Offre::ecarter((int) $dossier['id'], $offreId, $motif)) {
                AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'offre_ecartee', 'offre', $offreId, $motif);
                View::flash('succes', 'Offre écartée : elle reste consultable mais sort de la comparaison active.');
            }
        } else {
            // Réexaminer : impossible tant qu'une offre est retenue (il faut d'abord annuler la décision).
            if (Offre::retenueForDossier((int) $dossier['id'])) {
                View::flash('erreur', "Une offre est déjà retenue : annulez d'abord la décision pour remettre une offre en comparaison.");
                header('Location: ' . $retour);
                exit;
            }
            if (Offre::reexaminer((int) $dossier['id'], $offreId)) {
                AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'offre_reexaminee', 'offre', $offreId);
                View::flash('succes', 'Offre remise en comparaison.');
            }
        }
        header('Location: ' . $retour);
        exit;
    }
}

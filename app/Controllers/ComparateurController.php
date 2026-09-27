<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Dossier;
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
        foreach ($offres as $offre) {
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
            if (in_array($offre['statut'], ['retenue', 'rejetee'], true)) {
                $decisionExistante = true;
                break;
            }
        }

        View::render('comparateur/index', [
            'dossier' => $dossier,
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
        if (!Auth::canValiderOffres()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }

        $offreId = (int) ($_POST['offre_id'] ?? 0);
        $motif = trim($_POST['motif_decision'] ?? '');
        if ($offreId <= 0 || $motif === '') {
            View::flash('erreur', 'Merci d\'indiquer un motif de décision avant de retenir une offre.');
            header('Location: /index.php?r=dossiers/' . $dossier['id'] . '/comparateur');
            exit;
        }

        Offre::retenir((int) $dossier['id'], $offreId, $motif);
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
}

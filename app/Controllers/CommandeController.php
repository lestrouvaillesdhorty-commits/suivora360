<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Commande;
use App\Models\Cotation;
use App\Models\Dossier;
use App\Models\Facture;

class CommandeController
{
    public function store(array $params): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=dossiers/' . $params['id']);
            exit;
        }

        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $cotationId = (int) ($_POST['cotation_id'] ?? 0);
        $cotation = $cotationId ? Cotation::find($cotationId) : null;
        if (!$cotation || (int) $cotation['dossier_id'] !== (int) $dossier['id']) {
            View::flash('erreur', 'Veuillez sélectionner une cotation valide pour ce dossier.');
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }

        $commandeId = Commande::create((int) $dossier['id'], (int) $dossier['filiale_id'], $cotationId);
        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'creation_commande', 'commande', $commandeId);
        View::flash('succes', 'Commande créée, suivi opérationnel démarré.');
        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '/commande');
        exit;
    }

    public function show(array $params): void
    {
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $commande = Commande::findByDossier((int) $dossier['id']);
        if (!$commande) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $steps = Commande::steps((int) $commande['id']);
        $factures = Facture::forDossier((int) $dossier['id']);

        View::render('commandes/show', [
            'dossier' => $dossier,
            'commande' => $commande,
            'steps' => $steps,
            'factures' => $factures,
            'progression' => Commande::progression($steps),
        ]);
    }

    public function updateStep(array $params): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=commandes/' . $params['id']);
            exit;
        }

        $user = Auth::user();
        $commande = Commande::find((int) $params['id']);
        if (!$commande || !Commande::userCanAccess($user, $commande)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        Commande::updateStep(
            (int) $params['stepId'],
            (int) $commande['id'],
            $_POST['statut'] ?? '',
            $_POST['date_reelle'] ?? null,
            $_POST['notes'] ?? ''
        );
        AuditLog::log((int) $commande['filiale_id'], (int) $user['id'], 'maj_etape_commande', 'commande', $commande['id']);
        View::flash('succes', 'Étape mise à jour.');
        header('Location: /index.php?r=dossiers/' . $commande['dossier_id'] . '/commande');
        exit;
    }

    public function updateSuivi(array $params): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=commandes/' . $params['id']);
            exit;
        }

        $user = Auth::user();
        $commande = Commande::find((int) $params['id']);
        if (!$commande || !Commande::userCanAccess($user, $commande)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        Commande::updateSuivi((int) $commande['id'], $_POST['prochaine_action'] ?? '', $_POST['date_relance'] ?? null);
        AuditLog::log((int) $commande['filiale_id'], (int) $user['id'], 'maj_suivi_commande', 'commande', $commande['id']);
        View::flash('succes', 'Suivi de la commande mis à jour.');
        header('Location: /index.php?r=dossiers/' . $commande['dossier_id'] . '/commande');
        exit;
    }
}

<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Commande;
use App\Models\Cotation;
use App\Models\Dossier;
use App\Models\Facture;

class FactureController
{
    public function create(array $params): void
    {
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $commande = Commande::findByDossier((int) $dossier['id']);
        $cotations = Cotation::forDossier((int) $dossier['id']);

        View::render('factures/create', [
            'dossier' => $dossier,
            'commande' => $commande,
            'cotations' => $cotations,
        ]);
    }

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

        $factureId = Facture::create((int) $dossier['id'], (int) $dossier['filiale_id'], $_POST);
        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'creation_facture', 'facture', $factureId);
        View::flash('succes', 'Facture enregistrée.');

        $commande = Commande::findByDossier((int) $dossier['id']);
        $redirect = $commande ? '/index.php?r=dossiers/' . $dossier['id'] . '/commande' : '/index.php?r=dossiers/' . $dossier['id'];
        header('Location: ' . $redirect);
        exit;
    }

    public function updateStatut(array $params): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=dossiers');
            exit;
        }

        $user = Auth::user();
        $facture = Facture::find((int) $params['id']);
        if (!$facture || !Facture::userCanAccess($user, $facture)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        Facture::updateStatut((int) $facture['id'], $_POST['statut'] ?? '');
        AuditLog::log((int) $facture['filiale_id'], (int) $user['id'], 'changement_statut_facture', 'facture', $facture['id'], $_POST['statut'] ?? '');
        View::flash('succes', 'Statut de la facture mis à jour.');

        $commande = Commande::findByDossier((int) $facture['dossier_id']);
        $redirect = $commande ? '/index.php?r=dossiers/' . $facture['dossier_id'] . '/commande' : '/index.php?r=dossiers/' . $facture['dossier_id'];
        header('Location: ' . $redirect);
        exit;
    }
}

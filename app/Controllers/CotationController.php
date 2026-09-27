<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Cotation;
use App\Models\CotationItem;
use App\Models\Demande;
use App\Models\Dossier;
use App\Models\Offre;

class CotationController
{
    public function create(array $params): void
    {
        if (!Auth::canGererCotations()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $demande = Demande::find((int) $dossier['demande_id']);
        $clients = Client::allForFiliale((int) $dossier['filiale_id']);
        $offreRetenue = Offre::retenueForDossier((int) $dossier['id']);

        View::render('cotations/create', [
            'dossier' => $dossier,
            'demande' => $demande,
            'clients' => $clients,
            'offreRetenue' => $offreRetenue,
        ]);
    }

    public function store(array $params): void
    {
        if (!Auth::canGererCotations()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }
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

        if (empty($_POST['client_id'])) {
            View::flash('erreur', 'Veuillez sélectionner un client.');
            header('Location: /index.php?r=dossiers/' . $dossier['id'] . '/cotations/nouvelle');
            exit;
        }

        $items = [];
        $designations = $_POST['item_designation'] ?? [];
        foreach ($designations as $i => $designation) {
            $items[] = [
                'designation' => trim($designation),
                'quantite' => $_POST['item_quantite'][$i] ?? null,
                'unite' => $_POST['item_unite'][$i] ?? '',
                'prix_unitaire' => $_POST['item_prix_unitaire'][$i] ?? null,
            ];
        }

        $cotationId = Cotation::create((int) $dossier['id'], (int) $dossier['filiale_id'], $_POST, $items);

        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'creation_cotation', 'cotation', $cotationId);
        View::flash('succes', 'Cotation créée.');
        header('Location: /index.php?r=cotations/' . $cotationId);
        exit;
    }

    public function show(array $params): void
    {
        $user = Auth::user();
        $cotation = Cotation::findWithDetails((int) $params['id']);
        if (!$cotation || !Cotation::userCanAccess($user, $cotation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $items = CotationItem::forCotation((int) $cotation['id']);
        View::render('cotations/show', [
            'cotation' => $cotation,
            'items' => $items,
        ]);
    }

    public function updateStatut(array $params): void
    {
        if (!Auth::canGererCotations()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=cotations/' . $params['id']);
            exit;
        }

        $user = Auth::user();
        $cotation = Cotation::find((int) $params['id']);
        if (!$cotation || !Cotation::userCanAccess($user, $cotation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        Cotation::updateStatut((int) $cotation['id'], $_POST['statut'] ?? '');
        AuditLog::log((int) $cotation['filiale_id'], (int) $user['id'], 'changement_statut_cotation', 'cotation', $cotation['id'], $_POST['statut'] ?? '');
        View::flash('succes', 'Statut de la cotation mis à jour.');
        header('Location: /index.php?r=cotations/' . $cotation['id']);
        exit;
    }
}

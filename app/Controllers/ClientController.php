<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Filiale;

class ClientController
{
    public function index(): void
    {
        $user = Auth::user();
        $clients = Client::visibleFor($user);
        View::render('clients/index', ['clients' => $clients]);
    }

    public function create(): void
    {
        $user = Auth::user();
        $filiales = Filiale::visibleFor($user);
        View::render('clients/create', ['filiales' => $filiales]);
    }

    public function store(): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=clients/nouveau');
            exit;
        }

        $user = Auth::user();
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);

        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            View::flash('erreur', "Vous n'avez pas accès à cette filiale.");
            header('Location: /index.php?r=clients/nouveau');
            exit;
        }
        if (trim($_POST['nom'] ?? '') === '') {
            View::flash('erreur', 'Le nom du client est obligatoire.');
            header('Location: /index.php?r=clients/nouveau');
            exit;
        }

        $clientId = Client::create([
            'filiale_id' => $filialeId,
            'nom' => trim($_POST['nom']),
            'email' => trim($_POST['email'] ?? ''),
            'telephone' => trim($_POST['telephone'] ?? ''),
            'pays' => trim($_POST['pays'] ?? ''),
            'ville' => trim($_POST['ville'] ?? ''),
            'adresse' => trim($_POST['adresse'] ?? ''),
            'secteur' => trim($_POST['secteur'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ]);

        AuditLog::log($filialeId, (int) $user['id'], 'creation_client', 'client', $clientId);
        View::flash('succes', 'Client créé avec succès.');
        header('Location: /index.php?r=clients/' . $clientId);
        exit;
    }

    public function show(array $params): void
    {
        $user = Auth::user();
        $client = Client::find((int) $params['id']);
        if (!$client || !Client::userCanAccess($user, $client)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        View::render('clients/show', ['client' => $client]);
    }

    public function edit(array $params): void
    {
        $user = Auth::user();
        $client = Client::find((int) $params['id']);
        if (!$client || !Client::userCanAccess($user, $client)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        View::render('clients/edit', ['client' => $client]);
    }

    public function update(array $params): void
    {
        $user = Auth::user();
        $client = Client::find((int) $params['id']);
        if (!$client || !Client::userCanAccess($user, $client)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=clients/' . $client['id'] . '/modifier');
            exit;
        }
        if (trim($_POST['nom'] ?? '') === '') {
            View::flash('erreur', 'Le nom du client est obligatoire.');
            header('Location: /index.php?r=clients/' . $client['id'] . '/modifier');
            exit;
        }

        Client::update((int) $client['id'], [
            'nom' => trim($_POST['nom']),
            'email' => trim($_POST['email'] ?? ''),
            'telephone' => trim($_POST['telephone'] ?? ''),
            'pays' => trim($_POST['pays'] ?? ''),
            'ville' => trim($_POST['ville'] ?? ''),
            'adresse' => trim($_POST['adresse'] ?? ''),
            'secteur' => trim($_POST['secteur'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ]);

        AuditLog::log((int) $client['filiale_id'], (int) $user['id'], 'modification_client', 'client', (int) $client['id']);
        View::flash('succes', 'Client mis à jour.');
        header('Location: /index.php?r=clients/' . $client['id']);
        exit;
    }

    public function desactiver(array $params): void
    {
        $user = Auth::user();
        $client = Client::find((int) $params['id']);
        if (!$client || !Client::userCanAccess($user, $client)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=clients');
            exit;
        }
        Client::setActive((int) $client['id'], false);
        AuditLog::log((int) $client['filiale_id'], (int) $user['id'], 'desactivation_client', 'client', (int) $client['id']);
        View::flash('succes', 'Client désactivé. Il reste visible et peut être réactivé à tout moment.');
        header('Location: /index.php?r=clients');
        exit;
    }

    public function activer(array $params): void
    {
        $user = Auth::user();
        $client = Client::find((int) $params['id']);
        if (!$client || !Client::userCanAccess($user, $client)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=clients');
            exit;
        }
        Client::setActive((int) $client['id'], true);
        AuditLog::log((int) $client['filiale_id'], (int) $user['id'], 'reactivation_client', 'client', (int) $client['id']);
        View::flash('succes', 'Client réactivé.');
        header('Location: /index.php?r=clients');
        exit;
    }
}

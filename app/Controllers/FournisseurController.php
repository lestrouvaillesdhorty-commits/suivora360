<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Filiale;
use App\Models\Fournisseur;

class FournisseurController
{
    public function index(): void
    {
        $user = Auth::user();
        $fournisseurs = Fournisseur::visibleFor($user);
        View::render('fournisseurs/index', ['fournisseurs' => $fournisseurs]);
    }

    public function create(): void
    {
        $user = Auth::user();
        $filiales = Filiale::visibleFor($user);
        View::render('fournisseurs/create', ['filiales' => $filiales]);
    }

    public function store(): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=fournisseurs/nouveau');
            exit;
        }

        $user = Auth::user();
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);

        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            View::flash('erreur', "Vous n'avez pas accès à cette filiale.");
            header('Location: /index.php?r=fournisseurs/nouveau');
            exit;
        }
        if (trim($_POST['nom'] ?? '') === '') {
            View::flash('erreur', 'Le nom du fournisseur est obligatoire.');
            header('Location: /index.php?r=fournisseurs/nouveau');
            exit;
        }

        $fournisseurId = Fournisseur::create([
            'filiale_id' => $filialeId,
            'nom' => trim($_POST['nom']),
            'email' => trim($_POST['email'] ?? ''),
            'telephone' => trim($_POST['telephone'] ?? ''),
            'pays' => trim($_POST['pays'] ?? ''),
            'ville' => trim($_POST['ville'] ?? ''),
            'adresse' => trim($_POST['adresse'] ?? ''),
            'devise' => trim($_POST['devise'] ?? ''),
            'secteur' => trim($_POST['secteur'] ?? ''),
            'site_web' => trim($_POST['site_web'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ]);

        AuditLog::log($filialeId, (int) $user['id'], 'creation_fournisseur', 'fournisseur', $fournisseurId);
        View::flash('succes', 'Fournisseur créé avec succès.');
        header('Location: /index.php?r=fournisseurs/' . $fournisseurId);
        exit;
    }

    public function show(array $params): void
    {
        $user = Auth::user();
        $fournisseur = Fournisseur::find((int) $params['id']);
        if (!$fournisseur || !Fournisseur::userCanAccess($user, $fournisseur)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        View::render('fournisseurs/show', ['fournisseur' => $fournisseur]);
    }

    public function edit(array $params): void
    {
        $user = Auth::user();
        $fournisseur = Fournisseur::find((int) $params['id']);
        if (!$fournisseur || !Fournisseur::userCanAccess($user, $fournisseur)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        View::render('fournisseurs/edit', ['fournisseur' => $fournisseur]);
    }

    public function update(array $params): void
    {
        $user = Auth::user();
        $fournisseur = Fournisseur::find((int) $params['id']);
        if (!$fournisseur || !Fournisseur::userCanAccess($user, $fournisseur)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=fournisseurs/' . $fournisseur['id'] . '/modifier');
            exit;
        }
        if (trim($_POST['nom'] ?? '') === '') {
            View::flash('erreur', 'Le nom du fournisseur est obligatoire.');
            header('Location: /index.php?r=fournisseurs/' . $fournisseur['id'] . '/modifier');
            exit;
        }

        Fournisseur::update((int) $fournisseur['id'], [
            'nom' => trim($_POST['nom']),
            'email' => trim($_POST['email'] ?? ''),
            'telephone' => trim($_POST['telephone'] ?? ''),
            'pays' => trim($_POST['pays'] ?? ''),
            'ville' => trim($_POST['ville'] ?? ''),
            'adresse' => trim($_POST['adresse'] ?? ''),
            'devise' => trim($_POST['devise'] ?? ''),
            'secteur' => trim($_POST['secteur'] ?? ''),
            'site_web' => trim($_POST['site_web'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ]);

        AuditLog::log((int) $fournisseur['filiale_id'], (int) $user['id'], 'modification_fournisseur', 'fournisseur', (int) $fournisseur['id']);
        View::flash('succes', 'Fournisseur mis à jour.');
        header('Location: /index.php?r=fournisseurs/' . $fournisseur['id']);
        exit;
    }

    public function desactiver(array $params): void
    {
        $user = Auth::user();
        $fournisseur = Fournisseur::find((int) $params['id']);
        if (!$fournisseur || !Fournisseur::userCanAccess($user, $fournisseur)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=fournisseurs');
            exit;
        }
        Fournisseur::setActive((int) $fournisseur['id'], false);
        AuditLog::log((int) $fournisseur['filiale_id'], (int) $user['id'], 'desactivation_fournisseur', 'fournisseur', (int) $fournisseur['id']);
        View::flash('succes', 'Fournisseur désactivé. Il reste visible et peut être réactivé à tout moment.');
        header('Location: /index.php?r=fournisseurs');
        exit;
    }

    public function activer(array $params): void
    {
        $user = Auth::user();
        $fournisseur = Fournisseur::find((int) $params['id']);
        if (!$fournisseur || !Fournisseur::userCanAccess($user, $fournisseur)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=fournisseurs');
            exit;
        }
        Fournisseur::setActive((int) $fournisseur['id'], true);
        AuditLog::log((int) $fournisseur['filiale_id'], (int) $user['id'], 'reactivation_fournisseur', 'fournisseur', (int) $fournisseur['id']);
        View::flash('succes', 'Fournisseur réactivé.');
        header('Location: /index.php?r=fournisseurs');
        exit;
    }
}

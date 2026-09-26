<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Demande;
use App\Models\DemandeArticle;
use App\Models\Dossier;
use App\Models\Filiale;

class DossierController
{
    public function index(): void
    {
        $user = Auth::user();
        $dossiers = Dossier::visibleFor($user);
        View::render('folders/index', ['dossiers' => $dossiers]);
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

        $demande = Demande::find((int) $dossier['demande_id']);
        $articles = DemandeArticle::forDemande($dossier['demande_id']);
        $filiale = Filiale::find((int) $dossier['filiale_id']);

        View::render('folders/show', [
            'dossier' => $dossier,
            'demande' => $demande,
            'articles' => $articles,
            'filiale' => $filiale,
        ]);
    }

    public function updateEtape(array $params): void
    {
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

        Dossier::updateEtape((int) $dossier['id'], $_POST['etape'] ?? '');
        View::flash('succes', 'Étape mise à jour.');
        header('Location: /index.php?r=dossiers/' . $dossier['id']);
        exit;
    }

    public function addArticle(array $params): void
    {
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

        if (trim($_POST['designation'] ?? '') !== '') {
            DemandeArticle::create((int) $dossier['demande_id'], [
                'designation' => trim($_POST['designation']),
                'quantite' => $_POST['quantite'] ?? null,
                'unite' => trim($_POST['unite'] ?? ''),
                'reference' => trim($_POST['reference'] ?? ''),
                'marque' => trim($_POST['marque'] ?? ''),
            ]);
            View::flash('succes', 'Article ajouté.');
        }

        header('Location: /index.php?r=dossiers/' . $dossier['id']);
        exit;
    }

    public function updateNotes(array $params): void
    {
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

        Dossier::updateNotes((int) $dossier['id'], trim($_POST['notes'] ?? ''));
        View::flash('succes', 'Notes enregistrées.');
        header('Location: /index.php?r=dossiers/' . $dossier['id']);
        exit;
    }
}

<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Demande;
use App\Models\DemandeArticle;
use App\Models\Dossier;
use App\Models\Filiale;
use App\Models\Utilisateur;

class DemandeController
{
    public function index(): void
    {
        $user = Auth::user();
        $filters = [
            'statut' => $_GET['statut'] ?? null,
            'recherche' => $_GET['q'] ?? null,
        ];
        $demandes = Demande::visibleFor($user, $filters);

        View::render('requests/index', [
            'demandes' => $demandes,
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        $user = Auth::user();
        $filiales = Filiale::visibleFor($user);
        View::render('requests/create', ['filiales' => $filiales]);
    }

    public function store(): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/nouvelle');
            exit;
        }

        $user = Auth::user();
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);

        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            View::flash('erreur', "Vous n'avez pas accès à cette filiale.");
            header('Location: /index.php?r=demandes/nouvelle');
            exit;
        }

        if (trim($_POST['objet'] ?? '') === '') {
            View::flash('erreur', "L'objet de la demande est obligatoire.");
            header('Location: /index.php?r=demandes/nouvelle');
            exit;
        }

        $demandeId = Demande::create([
            'filiale_id' => $filialeId,
            'objet' => trim($_POST['objet']),
            'message' => trim($_POST['message'] ?? ''),
            'canal' => $_POST['canal'] ?? 'Formulaire',
            'expediteur_nom' => trim($_POST['expediteur_nom'] ?? ''),
            'expediteur_entreprise' => trim($_POST['expediteur_entreprise'] ?? ''),
            'expediteur_email' => trim($_POST['expediteur_email'] ?? ''),
            'expediteur_telephone' => trim($_POST['expediteur_telephone'] ?? ''),
            'recue_le' => $_POST['recue_le'] ?? date('Y-m-d'),
            'activite' => trim($_POST['activite'] ?? ''),
            'responsable_id' => $_POST['responsable_id'] ?? null,
            'priorite' => $_POST['priorite'] ?? 'normale',
            'echeance' => $_POST['echeance'] ?? null,
        ]);

        View::flash('succes', 'Demande créée avec succès.');
        header('Location: /index.php?r=demandes/' . $demandeId);
        exit;
    }

    public function show(array $params): void
    {
        $user = Auth::user();
        $demande = Demande::find((int) $params['id']);

        if (!$demande || !Filiale::userCanAccess($user, (int) $demande['filiale_id'])) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $articles = DemandeArticle::forDemande($demande['id']);
        $dossier = Dossier::findByDemande($demande['id']);
        $filiale = \App\Models\Filiale::find((int) $demande['filiale_id']);

        View::render('requests/show', [
            'demande' => $demande,
            'articles' => $articles,
            'dossier' => $dossier,
            'filiale' => $filiale,
        ]);
    }

    public function qualifier(array $params): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $params['id']);
            exit;
        }

        $user = Auth::user();
        $demande = Demande::find((int) $params['id']);

        if (!$demande || !Filiale::userCanAccess($user, (int) $demande['filiale_id'])) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $dossierId = Dossier::createFromDemande((int) $demande['id'], [
            'responsable_id' => $_POST['responsable_id'] ?? null,
            'priorite' => $_POST['priorite'] ?? null,
            'echeance' => $_POST['echeance'] ?? null,
        ]);

        View::flash('succes', 'Demande qualifiée : le dossier a été créé.');
        header('Location: /index.php?r=dossiers/' . $dossierId);
        exit;
    }
}

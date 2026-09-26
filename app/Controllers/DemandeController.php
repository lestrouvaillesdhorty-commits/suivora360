<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Client;
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
        $client = !empty($demande['client_id']) ? Client::find((int) $demande['client_id']) : null;
        $linkedDemande = !empty($demande['linked_request_id']) ? Demande::find((int) $demande['linked_request_id']) : null;
        $linkedDossier = !empty($demande['linked_dossier_id']) ? Dossier::find((int) $demande['linked_dossier_id']) : null;

        View::render('requests/show', [
            'demande' => $demande,
            'articles' => $articles,
            'dossier' => $dossier,
            'filiale' => $filiale,
            'client' => $client,
            'linkedDemande' => $linkedDemande,
            'linkedDossier' => $linkedDossier,
        ]);
    }

    /**
     * Affiche le choix des 3 voies de qualification. Jamais de nature
     * attribuée automatiquement : la personne choisit explicitement.
     */
    public function qualifierForm(array $params): void
    {
        $user = Auth::user();
        $demande = $this->loadDemandeOr404($user, $params);
        if (!$demande) {
            return;
        }

        $filiale = Filiale::find((int) $demande['filiale_id']);
        $clients = Client::allForFiliale((int) $demande['filiale_id']);
        $utilisateurs = Utilisateur::allForOrganisation((int) $filiale['organisation_id']);

        $termeRecherche = trim($_GET['q'] ?? '');
        $resultats = [];
        if ($termeRecherche !== '') {
            $resultats = Demande::searchForAddition(Filiale::visibleIdsFor($user), $termeRecherche, (int) $demande['id']);
        }

        View::render('requests/qualifier', [
            'demande' => $demande,
            'clients' => $clients,
            'utilisateurs' => $utilisateurs,
            'termeRecherche' => $termeRecherche,
            'resultats' => $resultats,
        ]);
    }

    public function qualifierNouvelle(array $params): void
    {
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id'] . '/qualifier');
            exit;
        }

        Demande::qualifyNew((int) $demande['id'], $_POST, (int) $user['id']);
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'qualification', 'demande', (int) $demande['id'], 'Voie : Nouvelle demande');

        View::flash('succes', 'Demande qualifiée (nouvelle demande). Vous pouvez maintenant créer le dossier.');
        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    public function qualifierComplement(array $params): void
    {
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id'] . '/qualifier');
            exit;
        }

        $linkedType = $_POST['type'] ?? '';
        $linkedId = (int) ($_POST['id'] ?? 0);

        if (!in_array($linkedType, ['demande', 'dossier'], true) || !$linkedId) {
            View::flash('erreur', 'Sélection invalide.');
            header('Location: /index.php?r=demandes/' . $demande['id'] . '/qualifier');
            exit;
        }

        // Vérifie que l'élément rattaché est bien visible par l'utilisateur (même isolation multi-filiale)
        if ($linkedType === 'dossier') {
            $cible = Dossier::find($linkedId);
            $accessOk = $cible && Dossier::userCanAccess($user, $cible);
            if ($cible && $cible['statut'] === 'cloture') {
                View::flash('erreur', "Ce dossier est clôturé — le rattachement reste possible mais vérifiez qu'il ne faut pas plutôt créer une nouvelle demande.");
            }
        } else {
            $cible = Demande::find($linkedId);
            $accessOk = $cible && Filiale::userCanAccess($user, (int) $cible['filiale_id']);
        }
        if (!$accessOk) {
            View::flash('erreur', "L'élément sélectionné est introuvable ou hors de votre périmètre.");
            header('Location: /index.php?r=demandes/' . $demande['id'] . '/qualifier');
            exit;
        }

        Demande::qualifyAddition((int) $demande['id'], $linkedType, $linkedId, $_POST['notes'] ?? '', (int) $user['id']);
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'qualification_rattachement', 'demande', (int) $demande['id'], "Rattachée à $linkedType #$linkedId");

        View::flash('succes', 'Demande rattachée avec succès. Aucun nouveau dossier créé.');
        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    public function qualifierReprise(array $params): void
    {
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id'] . '/qualifier');
            exit;
        }

        Demande::qualifyTakeover((int) $demande['id'], $_POST, (int) $user['id']);
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'qualification_reprise', 'demande', (int) $demande['id'], 'Voie : Reprise hors Suivora');

        View::flash('succes', 'Reprise enregistrée. Vous pouvez maintenant créer le dossier.');
        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    /**
     * Crée le dossier une fois la demande qualifiée (voies Nouvelle demande / Reprise uniquement).
     */
    public function creerDossier(array $params): void
    {
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        try {
            $dossierId = Dossier::createFromDemande((int) $demande['id'], [
                'responsable_id' => $_POST['responsable_id'] ?? null,
                'priorite' => $_POST['priorite'] ?? null,
                'echeance' => $_POST['echeance'] ?? null,
            ]);
            AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'creation_dossier', 'dossier', $dossierId, 'Depuis demande #' . $demande['id']);
            View::flash('succes', 'Dossier créé avec succès.');
            header('Location: /index.php?r=dossiers/' . $dossierId);
        } catch (\Throwable $e) {
            View::flash('erreur', $e->getMessage());
            header('Location: /index.php?r=demandes/' . $demande['id']);
        }
        exit;
    }

    public function rejeter(array $params): void
    {
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        $motif = trim($_POST['motif'] ?? '');
        if ($motif === '') {
            View::flash('erreur', 'Le motif de rejet est obligatoire.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        Demande::reject((int) $demande['id'], $motif);
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'rejet_demande', 'demande', (int) $demande['id'], $motif);

        View::flash('succes', 'Demande rejetée.');
        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    private function loadDemandeOr404(array $user, array $params): ?array
    {
        $demande = Demande::find((int) $params['id']);
        if (!$demande || !Filiale::userCanAccess($user, (int) $demande['filiale_id'])) {
            http_response_code(404);
            View::render('errors/404');
            return null;
        }
        return $demande;
    }

    private function loadDemandeOrRedirect(array $user, array $params): ?array
    {
        $demande = Demande::find((int) $params['id']);
        if (!$demande || !Filiale::userCanAccess($user, (int) $demande['filiale_id'])) {
            http_response_code(404);
            View::render('errors/404');
            return null;
        }
        return $demande;
    }
}

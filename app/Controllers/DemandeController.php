<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Storage;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Demande;
use App\Models\DemandeArticle;
use App\Models\DemandePieceJointe;
use App\Models\Dossier;
use App\Models\Filiale;
use App\Models\Utilisateur;
use App\Services\AiExtracteur;

class DemandeController
{
    public function index(): void
    {
        $user = Auth::user();
        $filters = [
            'statut' => $_GET['statut'] ?? null,
            'recherche' => $_GET['q'] ?? null,
            'responsable_id' => $_GET['responsable_id'] ?? null,
            'date_debut' => $_GET['date_debut'] ?? null,
            'date_fin' => $_GET['date_fin'] ?? null,
        ];
        $demandes = Demande::visibleFor($user, $filters);
        $utilisateurs = Utilisateur::allForOrganisation((int) $user['organisation_id']);

        View::render('requests/index', [
            'demandes' => $demandes,
            'filters' => $filters,
            'utilisateurs' => $utilisateurs,
        ]);
    }

    public function create(): void
    {
        $user = Auth::user();
        $filiales = Filiale::visibleFor($user);
        $utilisateurs = Utilisateur::allForOrganisation((int) $user['organisation_id']);
        View::render('requests/create', ['filiales' => $filiales, 'utilisateurs' => $utilisateurs]);
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
        AuditLog::log($filialeId, (int) $user['id'], 'creation', 'demande', $demandeId, 'Demande créée');

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
        $historique = AuditLog::forEntity('demande', (int) $demande['id']);
        $piecesJointes = DemandePieceJointe::forDemande((int) $demande['id']);

        View::render('requests/show', [
            'demande' => $demande,
            'articles' => $articles,
            'dossier' => $dossier,
            'filiale' => $filiale,
            'client' => $client,
            'linkedDemande' => $linkedDemande,
            'linkedDossier' => $linkedDossier,
            'historique' => $historique,
            'piecesJointes' => $piecesJointes,
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

    /**
     * Ajoute une pièce jointe à une demande (message original, devis reçu,
     * capture WhatsApp...). Le fichier est stocké hors du webroot ; tout
     * accès repasse obligatoirement par telechargerPiece() ci-dessous.
     */
    public function uploadPiece(array $params): void
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

        if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] === UPLOAD_ERR_NO_FILE) {
            View::flash('erreur', 'Aucun fichier sélectionné.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        $fichier = $_FILES['fichier'];
        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            View::flash('erreur', "Échec de l'envoi du fichier.");
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }
        if ($fichier['size'] > DemandePieceJointe::TAILLE_MAX) {
            View::flash('erreur', 'Le fichier dépasse la taille maximale autorisée (10 Mo).');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, DemandePieceJointe::EXTENSIONS_AUTORISEES, true)) {
            View::flash('erreur', 'Type de fichier non autorisé (formats acceptés : ' . implode(', ', DemandePieceJointe::EXTENSIONS_AUTORISEES) . ').');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        $nomFichier = bin2hex(random_bytes(16)) . '.' . $extension;
        $chemin = Storage::path('uploads/demandes/' . $demande['id'] . '/' . $nomFichier);
        if (!move_uploaded_file($fichier['tmp_name'], $chemin)) {
            View::flash('erreur', "Impossible d'enregistrer le fichier.");
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        DemandePieceJointe::create([
            'demande_id' => $demande['id'],
            'nom_original' => $fichier['name'],
            'nom_fichier' => $nomFichier,
            'taille' => $fichier['size'],
            'type_mime' => $fichier['type'] ?: 'application/octet-stream',
            'uploaded_by' => (int) $user['id'],
        ]);
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'ajout_piece_jointe', 'demande', (int) $demande['id'], $fichier['name']);

        View::flash('succes', 'Pièce jointe ajoutée.');
        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    public function telechargerPiece(array $params): void
    {
        $user = Auth::user();
        $demande = $this->loadDemandeOr404($user, $params);
        if (!$demande) {
            return;
        }
        $piece = DemandePieceJointe::find((int) $params['pieceId']);
        if (!$piece || (int) $piece['demande_id'] !== (int) $demande['id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $chemin = Storage::path('uploads/demandes/' . $demande['id'] . '/' . $piece['nom_fichier']);
        if (!is_file($chemin)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $disposition = (!empty($_GET['apercu']) && Storage::estPrevisualisable($piece['type_mime'])) ? 'inline' : 'attachment';
        header('Content-Type: ' . $piece['type_mime']);
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($piece['nom_original']) . '"');
        header('Content-Length: ' . filesize($chemin));
        readfile($chemin);
        exit;
    }

    public function supprimerPiece(array $params): void
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

        $piece = DemandePieceJointe::find((int) ($params['pieceId'] ?? 0));
        if ($piece && (int) $piece['demande_id'] === (int) $demande['id']) {
            $chemin = Storage::path('uploads/demandes/' . $demande['id'] . '/' . $piece['nom_fichier']);
            if (is_file($chemin)) {
                unlink($chemin);
            }
            DemandePieceJointe::delete($piece['id']);
            AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'suppression_piece_jointe', 'demande', (int) $demande['id'], $piece['nom_original']);
            View::flash('succes', 'Pièce jointe supprimée.');
        }

        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    /**
     * Lance l'extraction IA des articles depuis le message brut de la
     * demande, puis affiche une page de relecture (rien n'est enregistré
     * tant que confirmerExtractionIa() n'a pas été appelé).
     */
    public function extraireIa(array $params): void
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
            $suggestions = AiExtracteur::extraireArticles($demande['message'] ?? '');
        } catch (\Throwable $e) {
            View::flash('erreur', $e->getMessage());
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        if (empty($suggestions)) {
            View::flash('erreur', "L'IA n'a identifié aucun article dans le message de cette demande.");
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        View::render('requests/extraction_ia', [
            'demande' => $demande,
            'suggestions' => $suggestions,
        ]);
    }

    public function confirmerExtractionIa(array $params): void
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

        $designations = $_POST['designation'] ?? [];
        $quantites = $_POST['quantite'] ?? [];
        $unites = $_POST['unite'] ?? [];
        $inclure = $_POST['inclure'] ?? [];

        $ajoutes = 0;
        foreach ($designations as $i => $designation) {
            if (!in_array((string) $i, $inclure, true)) {
                continue;
            }
            $designation = trim((string) $designation);
            if ($designation === '') {
                continue;
            }
            DemandeArticle::create((int) $demande['id'], [
                'designation' => $designation,
                'quantite' => $quantites[$i] ?? null,
                'unite' => trim((string) ($unites[$i] ?? '')),
            ]);
            $ajoutes++;
        }

        if ($ajoutes > 0) {
            AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'extraction_ia_articles', 'demande', (int) $demande['id'], "$ajoutes article(s) ajouté(s) via IA");
            View::flash('succes', "$ajoutes article(s) ajouté(s).");
        } else {
            View::flash('erreur', 'Aucun article sélectionné.');
        }

        header('Location: /index.php?r=demandes/' . $demande['id']);
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

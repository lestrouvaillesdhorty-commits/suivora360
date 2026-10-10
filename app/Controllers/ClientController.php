<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csv;
use App\Core\Storage;
use App\Core\Telephone;
use App\Core\Tenant;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientPieceJointe;
use App\Models\Filiale;
use App\Models\Utilisateur;

/**
 * Module Clients (refonte 07/10) : liste filtrée et paginée, formulaire unique
 * de création/modification, fiche à 5 onglets (Vue d'ensemble, Contacts et
 * adresses, Demandes et dossiers, Documents, Finances).
 */
class ClientController
{
    private const ONGLETS = ['vue', 'contacts', 'operations', 'documents', 'finances'];

    // ------------------------------------------------------------------
    // Liste
    // ------------------------------------------------------------------

    public function index(): void
    {
        $user = Auth::user();
        $filtres = Client::filtresDepuis($_GET);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $liste = Client::liste($user, $filtres, $page);

        // Conserve les filtres pour le retour depuis une fiche.
        $_SESSION['clients_liste_query'] = $this->queryListe($filtres, $liste['page']);

        View::render('clients/index', [
            'liste' => $liste,
            'filtres' => $filtres,
            'indicateurs' => Client::indicateurs($user, $filtres),
            'filiales' => Filiale::visibleFor($user),
            'utilisateurs' => Utilisateur::allForOrganisation((int) $user['organisation_id']),
            'paysListe' => Client::paysUtilises($user),
            'peutExporter' => Auth::canVoirFinancesClient(),
            'schemaPret' => Client::schemaPret(),
        ]);
    }

    /** Chaîne de requête de la liste (sans valeurs vides) pour les liens et le retour. */
    private function queryListe(array $f, int $page = 1): string
    {
        $q = ['r' => 'clients'];
        foreach (['q', 'pays', 'type', 'relation'] as $k) {
            if ($f[$k] !== '') {
                $q[$k] = $f[$k];
            }
        }
        foreach (['filiale_id', 'responsable_id'] as $k) {
            if (!empty($f[$k])) {
                $q[$k] = $f[$k];
            }
        }
        if ($f['onglet'] !== 'tous') {
            $q['onglet'] = $f['onglet'];
        }
        if ($page > 1) {
            $q['page'] = $page;
        }
        return http_build_query($q);
    }

    public function exportCsv(): void
    {
        $user = Auth::user();
        if (!Auth::canVoirFinancesClient()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }
        $filtres = Client::filtresDepuis($_GET);
        $clients = Client::listeComplete($user, $filtres);

        AuditLog::log((int) (Filiale::visibleIdsFor($user)[0] ?? 0), (int) $user['id'], 'export_clients', 'client', null, count($clients) . ' client(s)');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="clients_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Clients — Suivora360'], ';', '"', '\\');
        fputcsv($out, ['Exporté le', date('d/m/Y H:i')], ';', '"', '\\');
        fputcsv($out, ['Nombre de résultats', count($clients)], ';', '"', '\\');
        fputcsv($out, [], ';', '"', '\\');
        fputcsv($out, ['Référence', 'Nom / raison sociale', 'Type', 'Relation', 'Statut', 'Pays', 'Ville', 'Filiale', 'Responsable', 'Contact principal', 'Fonction', 'E-mail', 'Téléphone', 'Dossiers actifs'], ';', '"', '\\');
        foreach ($clients as $c) {
            fputcsv($out, Csv::row([
                $c['code'],
                $c['nom'],
                Client::TYPES[$c['type'] ?? ''] ?? ($c['type'] ?? ''),
                Client::RELATIONS[$c['relation'] ?? 'client'] ?? '',
                ((int) $c['is_active'] === 1) ? 'Actif' : 'Inactif',
                $c['pays'],
                $c['ville'],
                $c['filiale_nom'],
                $c['responsable_nom'] ?? '',
                trim(($c['contact_prenom'] ?? '') . ' ' . ($c['contact_nom'] ?? '')),
                $c['fonction_contact'],
                $c['email'],
                $c['telephone'],
                (int) $c['nb_dossiers_actifs'],
            ]), ';', '"', '\\');
        }
        fclose($out);
        exit;
    }

    // ------------------------------------------------------------------
    // Création / modification (formulaire unique)
    // ------------------------------------------------------------------

    private function donneesFormulaire(array $user, ?array $client, array $old = [], array $erreurs = [], array $doublons = []): array
    {
        return [
            'client' => $client,
            'old' => $old,
            'erreurs' => $erreurs,
            'doublons' => $doublons,
            'filiales' => Filiale::visibleFor($user),
            'utilisateurs' => Utilisateur::allForOrganisation((int) $user['organisation_id']),
            'defauts' => Client::defautsOrganisation($user),
            'schemaPret' => Client::schemaPret(),
            'retour' => '/index.php?' . ($_SESSION['clients_liste_query'] ?? 'r=clients'),
        ];
    }

    public function create(): void
    {
        $user = Auth::user();
        View::render('clients/form', $this->donneesFormulaire($user, null));
    }

    /** Lit et nettoie les champs communs du formulaire (création et modification). */
    private function lireChamps(array $user): array
    {
        $p = fn(string $k) => trim((string) ($_POST[$k] ?? ''));
        $orgId = (int) $user['organisation_id'];
        $devise = strtoupper($p('devise_preferee'));
        return [
            'nom' => $p('nom'),
            'type' => array_key_exists($p('type'), Client::TYPES) ? $p('type') : '',
            'pays' => $p('pays'),
            'ville' => $p('ville'),
            'adresse' => $p('adresse'),
            'code_postal' => $p('code_postal'),
            'siret' => $p('siret'),
            'tva' => $p('tva'),
            'secteur' => $p('secteur'),
            'contact_prenom' => $p('contact_prenom'),
            'contact_nom' => $p('contact_nom'),
            'fonction_contact' => $p('fonction_contact'),
            'email' => $p('email'),
            'telephone' => Telephone::composer($p('tel_indicatif'), $p('tel_numero')),
            'responsable_id' => Tenant::utilisateurDOrganisation($_POST['responsable_id'] ?? 0, $orgId) ?: null,
            'relation' => array_key_exists($p('relation'), Client::RELATIONS) ? $p('relation') : 'client',
            'statut' => $p('statut') === 'inactif' ? 'inactif' : 'actif',
            'devise_preferee' => in_array($devise, Client::DEVISES, true) ? $devise : '',
            'notes' => $p('notes'),
            'adresse_livraison' => $p('adresse_livraison'),
            'incoterm_habituel' => $p('incoterm_habituel'),
            'mode_transport_habituel' => array_key_exists($p('mode_transport_habituel'), Client::MODES_TRANSPORT) ? $p('mode_transport_habituel') : '',
            'conditions_paiement' => array_key_exists($p('conditions_paiement'), Client::CONDITIONS_PAIEMENT) ? $p('conditions_paiement') : '',
        ];
    }

    private function valider(array $d): array
    {
        $e = [];
        if ($d['nom'] === '') {
            $e['nom'] = 'Le nom ou la raison sociale est obligatoire.';
        }
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $e['email'] = "L'adresse e-mail n'est pas valide.";
        }
        return $e;
    }

    public function store(): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::render('clients/form', $this->donneesFormulaire($user, null, $_POST, ['_general' => 'Session expirée : vos saisies sont conservées, validez à nouveau.']));
            return;
        }
        $d = $this->lireChamps($user);
        $erreurs = $this->valider($d);
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);
        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            $erreurs['filiale_id'] = "Choisissez une filiale à laquelle vous avez accès.";
        }
        if ($erreurs) {
            View::render('clients/form', $this->donneesFormulaire($user, null, $_POST, $erreurs));
            return;
        }
        if (empty($_POST['confirmer_doublon'])) {
            $doublons = Client::doublonsPotentiels($user, $d['nom'], $d['email'], $d['telephone']);
            if ($doublons) {
                View::render('clients/form', $this->donneesFormulaire($user, null, $_POST, [], $doublons));
                return;
            }
        }

        $d['filiale_id'] = $filialeId;
        $clientId = Client::create($d);
        AuditLog::log($filialeId, (int) $user['id'], 'creation_client', 'client', $clientId);
        View::flash('succes', 'Client créé avec succès.');
        header('Location: /index.php?r=clients/' . $clientId);
        exit;
    }

    private function chargerOu404(array $params, bool $ecriture = false): ?array
    {
        if ($ecriture) {
            Auth::requireWrite();
        }
        $user = Auth::user();
        $client = Client::find((int) ($params['id'] ?? 0));
        if (!$client || !Client::userCanAccess($user, $client)) {
            http_response_code(404);
            View::render('errors/404');
            return null;
        }
        return $client;
    }

    public function edit(array $params): void
    {
        $client = $this->chargerOu404($params);
        if (!$client) {
            return;
        }
        View::render('clients/form', $this->donneesFormulaire(Auth::user(), $client));
    }

    public function update(array $params): void
    {
        $client = $this->chargerOu404($params, true);
        if (!$client) {
            return;
        }
        $user = Auth::user();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::render('clients/form', $this->donneesFormulaire($user, $client, $_POST, ['_general' => 'Session expirée : vos saisies sont conservées, validez à nouveau.']));
            return;
        }
        $d = $this->lireChamps($user);
        $erreurs = $this->valider($d);
        if ($erreurs) {
            View::render('clients/form', $this->donneesFormulaire($user, $client, $_POST, $erreurs));
            return;
        }
        $changeIdentite = mb_strtolower($d['nom']) !== mb_strtolower((string) $client['nom'])
            || mb_strtolower($d['email']) !== mb_strtolower((string) $client['email'])
            || $d['telephone'] !== (string) $client['telephone'];
        if ($changeIdentite && empty($_POST['confirmer_doublon'])) {
            $doublons = Client::doublonsPotentiels($user, $d['nom'], $d['email'], $d['telephone'], (int) $client['id']);
            if ($doublons) {
                View::render('clients/form', $this->donneesFormulaire($user, $client, $_POST, [], $doublons));
                return;
            }
        }

        Client::update((int) $client['id'], $d);
        AuditLog::log((int) $client['filiale_id'], (int) $user['id'], 'modification_client', 'client', (int) $client['id']);
        View::flash('succes', 'Client mis à jour.');
        header('Location: /index.php?r=clients/' . $client['id']);
        exit;
    }

    // ------------------------------------------------------------------
    // Fiche
    // ------------------------------------------------------------------

    public function show(array $params): void
    {
        $client = $this->chargerOu404($params);
        if (!$client) {
            return;
        }
        $user = Auth::user();
        $onglet = $_GET['onglet'] ?? 'vue';
        $financesAutorisees = Auth::canVoirFinancesClient();
        if (!in_array($onglet, self::ONGLETS, true) || ($onglet === 'finances' && !$financesAutorisees)) {
            $onglet = 'vue';
        }
        $clientId = (int) $client['id'];
        $operations = Client::operations($clientId);
        $periode = in_array($_GET['periode'] ?? '', ['annee', '12m'], true) ? $_GET['periode'] : 'tout';

        $data = [
            'client' => $client,
            'onglet' => $onglet,
            'operations' => $operations,
            'contacts' => Client::contacts($clientId),
            'adresses' => Client::adresses($clientId),
            'pieces' => Client::schemaPret() ? ClientPieceJointe::forClient($clientId) : [],
            'financesAutorisees' => $financesAutorisees,
            'finances' => ($onglet === 'finances' || $onglet === 'vue') && $financesAutorisees ? Client::finances($clientId, Client::debutPeriode($periode)) : null,
            'periode' => $periode,
            'responsableNom' => !empty($client['responsable_id']) ? Utilisateur::nameOf((int) $client['responsable_id']) : '',
            'filiale' => Filiale::find((int) $client['filiale_id']),
            'schemaPret' => Client::schemaPret(),
            'retour' => '/index.php?' . ($_SESSION['clients_liste_query'] ?? 'r=clients'),
            'peutEcrire' => Auth::canWrite(),
            'liensPortail' => $financesAutorisees ? \App\Models\PortailClient::liensDuClient($clientId) : [],
            'portailPret' => \App\Models\PortailClient::schemaPret(),
            'urlBase' => (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? ''),
        ];
        View::render('clients/show', $data);
    }

    private function versFiche(int $id, string $onglet = 'vue'): void
    {
        header('Location: /index.php?r=clients/' . $id . ($onglet !== 'vue' ? '&onglet=' . $onglet : ''));
        exit;
    }

    private function csrfOuRetour(int $id, string $onglet): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            $this->versFiche($id, $onglet);
        }
    }

    // ------------------------------------------------------------------
    // Désactivation / réactivation (jamais de suppression)
    // ------------------------------------------------------------------

    public function desactiver(array $params): void
    {
        $this->basculerStatut($params, false);
    }

    public function activer(array $params): void
    {
        $this->basculerStatut($params, true);
    }

    private function basculerStatut(array $params, bool $actif): void
    {
        $client = $this->chargerOu404($params, true);
        if (!$client) {
            return;
        }
        $user = Auth::user();
        $retour = ($_POST['retour'] ?? '') === 'fiche';
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
        } else {
            Client::setActive((int) $client['id'], $actif);
            AuditLog::log((int) $client['filiale_id'], (int) $user['id'], $actif ? 'reactivation_client' : 'desactivation_client', 'client', (int) $client['id']);
            View::flash('succes', $actif
                ? 'Client réactivé.'
                : 'Client désactivé. Ses demandes, dossiers et documents sont conservés ; il peut être réactivé à tout moment.');
        }
        if ($retour) {
            $this->versFiche((int) $client['id']);
        }
        header('Location: /index.php?' . ($_SESSION['clients_liste_query'] ?? 'r=clients'));
        exit;
    }

    // ------------------------------------------------------------------
    // Contacts supplémentaires
    // ------------------------------------------------------------------

    public function enregistrerContact(array $params): void
    {
        $client = $this->chargerOu404($params, true);
        if (!$client) {
            return;
        }
        $user = Auth::user();
        $id = (int) $client['id'];
        $this->csrfOuRetour($id, 'contacts');
        if (!Client::schemaPret()) {
            View::flash('erreur', 'Mise à jour de la base requise (migration V21) avant d’ajouter des contacts.');
            $this->versFiche($id, 'contacts');
        }
        $p = fn(string $k) => trim((string) ($_POST[$k] ?? ''));
        $d = [
            'prenom' => $p('prenom'), 'nom' => $p('nom'), 'fonction' => $p('fonction'), 'email' => $p('email'),
            'telephone' => Telephone::composer($p('tel_indicatif'), $p('tel_numero')),
        ];
        if ($d['prenom'] === '' && $d['nom'] === '') {
            View::flash('erreur', 'Indiquez au moins un prénom ou un nom pour le contact.');
            $this->versFiche($id, 'contacts');
        }
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            View::flash('erreur', "L'adresse e-mail du contact n'est pas valide.");
            $this->versFiche($id, 'contacts');
        }
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        if ($contactId && !Client::contact($id, $contactId)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $contactId = Client::enregistrerContact($id, $contactId ?: null, $d);
        AuditLog::log((int) $client['filiale_id'], (int) $user['id'], 'contact_client', 'client', $id, trim($d['prenom'] . ' ' . $d['nom']));
        View::flash('succes', 'Contact enregistré.');
        $this->versFiche($id, 'contacts');
    }

    public function actionContact(array $params): void
    {
        $client = $this->chargerOu404($params, true);
        if (!$client) {
            return;
        }
        $id = (int) $client['id'];
        $cid = (int) ($params['contactId'] ?? 0);
        $this->csrfOuRetour($id, 'contacts');
        if (!Client::contact($id, $cid)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $action = $params['action'] ?? '';
        $user = Auth::user();
        if ($action === 'principal') {
            Client::definirContactPrincipal($id, $cid);
            View::flash('succes', 'Contact principal modifié ; l’ancien contact principal reste dans la liste.');
        } elseif ($action === 'desactiver') {
            Client::basculerContact($id, $cid, false);
            View::flash('succes', 'Contact désactivé (conservé dans l’historique).');
        } elseif ($action === 'reactiver') {
            Client::basculerContact($id, $cid, true);
            View::flash('succes', 'Contact réactivé.');
        }
        AuditLog::log((int) $client['filiale_id'], (int) $user['id'], 'contact_client', 'client', $id, $action);
        $this->versFiche($id, 'contacts');
    }

    // ------------------------------------------------------------------
    // Adresses supplémentaires
    // ------------------------------------------------------------------

    public function enregistrerAdresse(array $params): void
    {
        $client = $this->chargerOu404($params, true);
        if (!$client) {
            return;
        }
        $user = Auth::user();
        $id = (int) $client['id'];
        $this->csrfOuRetour($id, 'contacts');
        if (!Client::schemaPret()) {
            View::flash('erreur', 'Mise à jour de la base requise (migration V21) avant d’ajouter des adresses.');
            $this->versFiche($id, 'contacts');
        }
        $p = fn(string $k) => trim((string) ($_POST[$k] ?? ''));
        $d = ['type' => $p('type'), 'libelle' => $p('libelle'), 'adresse' => $p('adresse'), 'code_postal' => $p('code_postal'), 'ville' => $p('ville'), 'pays' => $p('pays')];
        if ($d['adresse'] === '' && $d['ville'] === '') {
            View::flash('erreur', 'Indiquez au moins une adresse ou une ville.');
            $this->versFiche($id, 'contacts');
        }
        $adresseId = (int) ($_POST['adresse_id'] ?? 0);
        if ($adresseId && !Client::adresse($id, $adresseId)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        Client::enregistrerAdresse($id, $adresseId ?: null, $d);
        AuditLog::log((int) $client['filiale_id'], (int) $user['id'], 'adresse_client', 'client', $id, $d['libelle'] ?: $d['ville']);
        View::flash('succes', 'Adresse enregistrée.');
        $this->versFiche($id, 'contacts');
    }

    public function actionAdresse(array $params): void
    {
        $client = $this->chargerOu404($params, true);
        if (!$client) {
            return;
        }
        $id = (int) $client['id'];
        $aid = (int) ($params['adresseId'] ?? 0);
        $this->csrfOuRetour($id, 'contacts');
        if (!Client::adresse($id, $aid)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $action = $params['action'] ?? '';
        $user = Auth::user();
        if ($action === 'defaut') {
            Client::definirAdresseParDefaut($id, $aid);
            View::flash('succes', 'Adresse par défaut modifiée.');
        } elseif ($action === 'desactiver') {
            Client::basculerAdresse($id, $aid, false);
            View::flash('succes', 'Adresse désactivée (conservée dans l’historique).');
        } elseif ($action === 'reactiver') {
            Client::basculerAdresse($id, $aid, true);
            View::flash('succes', 'Adresse réactivée.');
        }
        AuditLog::log((int) $client['filiale_id'], (int) $user['id'], 'adresse_client', 'client', $id, $action);
        $this->versFiche($id, 'contacts');
    }

    // ------------------------------------------------------------------
    // Documents du client
    // ------------------------------------------------------------------

    public function uploadPiece(array $params): void
    {
        $client = $this->chargerOu404($params, true);
        if (!$client) {
            return;
        }
        $user = Auth::user();
        $id = (int) $client['id'];
        $this->csrfOuRetour($id, 'documents');
        if (!Client::schemaPret()) {
            View::flash('erreur', 'Mise à jour de la base requise (migration V21) avant d’ajouter des documents.');
            $this->versFiche($id, 'documents');
        }
        if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] === UPLOAD_ERR_NO_FILE) {
            View::flash('erreur', 'Aucun fichier sélectionné.');
            $this->versFiche($id, 'documents');
        }
        $fichier = $_FILES['fichier'];
        if ($fichier['error'] === UPLOAD_ERR_INI_SIZE || $fichier['error'] === UPLOAD_ERR_FORM_SIZE) {
            View::flash('erreur', 'Le fichier dépasse la taille maximale autorisée (10 Mo).');
            $this->versFiche($id, 'documents');
        }
        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            View::flash('erreur', "Échec de l'envoi du fichier, merci de réessayer.");
            $this->versFiche($id, 'documents');
        }
        if ($fichier['size'] > ClientPieceJointe::TAILLE_MAX) {
            View::flash('erreur', 'Le fichier dépasse la taille maximale autorisée (10 Mo).');
            $this->versFiche($id, 'documents');
        }
        $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ClientPieceJointe::EXTENSIONS_AUTORISEES, true)) {
            View::flash('erreur', 'Type de fichier non autorisé (formats acceptés : ' . implode(', ', ClientPieceJointe::EXTENSIONS_AUTORISEES) . ').');
            $this->versFiche($id, 'documents');
        }
        $categorie = $_POST['categorie'] ?? 'autre';
        if (!array_key_exists($categorie, ClientPieceJointe::CATEGORIES)) {
            $categorie = 'autre';
        }
        $nomFichier = bin2hex(random_bytes(16)) . '.' . $extension;
        $chemin = Storage::path('uploads/clients/' . $id . '/' . $nomFichier);
        if (!move_uploaded_file($fichier['tmp_name'], $chemin)) {
            View::flash('erreur', "Impossible d'enregistrer le fichier sur le serveur.");
            $this->versFiche($id, 'documents');
        }
        ClientPieceJointe::create([
            'client_id' => $id,
            'categorie' => $categorie,
            'nom_original' => $fichier['name'],
            'nom_fichier' => $nomFichier,
            'taille' => $fichier['size'],
            'type_mime' => $fichier['type'] ?: 'application/octet-stream',
            'uploaded_by' => (int) $user['id'],
        ]);
        AuditLog::log((int) $client['filiale_id'], (int) $user['id'], 'ajout_piece_jointe', 'client', $id, $fichier['name']);
        View::flash('succes', 'Document ajouté.');
        $this->versFiche($id, 'documents');
    }

    public function telechargerPiece(array $params): void
    {
        $client = $this->chargerOu404($params);
        if (!$client) {
            return;
        }
        $piece = Client::schemaPret() ? ClientPieceJointe::find((int) ($params['pieceId'] ?? 0)) : null;
        if (!$piece || (int) $piece['client_id'] !== (int) $client['id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $chemin = Storage::path('uploads/clients/' . $client['id'] . '/' . $piece['nom_fichier']);
        if (!is_file($chemin)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $disposition = (!empty($_GET['apercu']) && Storage::estPrevisualisable($piece['type_mime'])) ? 'inline' : 'attachment';
        header('Content-Type: ' . $piece['type_mime']);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace(['"', "\r", "\n"], '', basename($piece['nom_original'])) . '"');
        header('Content-Length: ' . filesize($chemin));
        readfile($chemin);
        exit;
    }

    public function supprimerPiece(array $params): void
    {
        $client = $this->chargerOu404($params, true);
        if (!$client) {
            return;
        }
        $user = Auth::user();
        $id = (int) $client['id'];
        $this->csrfOuRetour($id, 'documents');
        $piece = Client::schemaPret() ? ClientPieceJointe::find((int) ($params['pieceId'] ?? 0)) : null;
        if ($piece && (int) $piece['client_id'] === $id) {
            $chemin = Storage::path('uploads/clients/' . $id . '/' . $piece['nom_fichier']);
            if (is_file($chemin)) {
                unlink($chemin);
            }
            ClientPieceJointe::delete((int) $piece['id']);
            AuditLog::log((int) $client['filiale_id'], (int) $user['id'], 'suppression_piece_jointe', 'client', $id, $piece['nom_original']);
            View::flash('succes', 'Document supprimé.');
        }
        $this->versFiche($id, 'documents');
    }

    // ------------------------------------------------------------------
    // Espace client externe : liens privés (07/10)
    // ------------------------------------------------------------------

    public function creerLienPortail(array $params): void
    {
        $client = $this->chargerOu404($params, true);
        if (!$client) {
            return;
        }
        $user = Auth::user();
        $id = (int) $client['id'];
        $this->csrfOuRetour($id, 'vue');
        // Le lien donne accès aux cotations et factures : mêmes rôles que l'onglet Finances.
        if (!Auth::canVoirFinancesClient()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }
        if (!\App\Models\PortailClient::schemaPret()) {
            View::flash('erreur', 'Mise à jour de la base requise (migration V22) avant de créer un lien.');
            $this->versFiche($id);
        }
        if ((int) $client['is_active'] !== 1) {
            View::flash('erreur', 'Réactivez d’abord ce client pour lui donner accès à son espace.');
            $this->versFiche($id);
        }
        $jours = (int) ($_POST['jours'] ?? \App\Models\PortailClient::DUREE_DEFAUT);
        \App\Models\PortailClient::creerLien($id, $jours, (int) $user['id']);
        AuditLog::log((int) $client['filiale_id'], (int) $user['id'], 'lien_espace_client_cree', 'client', $id, $jours . ' jours');
        View::flash('succes', 'Lien créé. Copiez-le et envoyez-le à votre client (e-mail, WhatsApp…).');
        $this->versFiche($id);
    }

    public function revoquerLienPortail(array $params): void
    {
        $client = $this->chargerOu404($params, true);
        if (!$client) {
            return;
        }
        $user = Auth::user();
        $id = (int) $client['id'];
        $this->csrfOuRetour($id, 'vue');
        if (!Auth::canVoirFinancesClient()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }
        $lien = \App\Models\PortailClient::schemaPret() ? \App\Models\PortailClient::lienDuClient($id, (int) ($params['lienId'] ?? 0)) : null;
        if ($lien) {
            \App\Models\PortailClient::revoquer((int) $lien['id']);
            AuditLog::log((int) $client['filiale_id'], (int) $user['id'], 'lien_espace_client_revoque', 'client', $id);
            View::flash('succes', 'Lien révoqué : il ne fonctionne plus.');
        }
        $this->versFiche($id);
    }

    // ------------------------------------------------------------------
    // Création rapide depuis une demande (AJAX, inchangé)
    // ------------------------------------------------------------------

    public function creationRapide(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::canWrite()) {
            http_response_code(403);
            echo json_encode(['erreur' => "Vous n'avez pas le droit de créer un client."]);
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['erreur' => 'Session expirée, merci de recharger la page.']);
            return;
        }

        $user = Auth::user();
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            http_response_code(403);
            echo json_encode(['erreur' => "Vous n'avez pas accès à cette filiale."]);
            return;
        }
        if ($nom === '') {
            http_response_code(422);
            echo json_encode(['erreur' => 'Le nom du client est obligatoire.']);
            return;
        }

        $force = !empty($_POST['force']);
        if (!$force) {
            $doublon = Client::rechercherDoublon($filialeId, $nom, $email);
            if ($doublon) {
                echo json_encode([
                    'doublon' => true,
                    'existant' => [
                        'id' => (int) $doublon['id'],
                        'nom' => $doublon['nom'],
                        'email' => $doublon['email'],
                        'telephone' => $doublon['telephone'],
                    ],
                ]);
                return;
            }
        }

        $clientId = Client::create([
            'filiale_id' => $filialeId,
            'nom' => $nom,
            'type' => trim($_POST['type'] ?? ''),
            'email' => $email,
            'telephone' => trim($_POST['telephone'] ?? ''),
            'fonction_contact' => trim($_POST['fonction_contact'] ?? ''),
            'relation' => 'prospect',
        ]);
        AuditLog::log($filialeId, (int) $user['id'], 'creation_client', 'client', $clientId, 'Création rapide depuis une demande');

        $client = Client::find($clientId);
        echo json_encode([
            'id' => $clientId,
            'nom' => $client['nom'],
            'email' => $client['email'],
            'telephone' => $client['telephone'],
        ]);
    }
}

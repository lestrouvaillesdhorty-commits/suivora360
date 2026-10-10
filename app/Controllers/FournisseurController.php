<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csv;
use App\Core\Storage;
use App\Core\Telephone;
use App\Core\Tenant;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Filiale;
use App\Models\Fournisseur;
use App\Models\FournisseurPieceJointe;
use App\Models\Utilisateur;

/**
 * Module Fournisseurs (refonte 07/10) : liste filtrée et paginée, formulaire unique
 * de création/modification, fiche à 7 onglets (Vue générale, Contacts, Offres,
 * Commandes, Documents, Évaluation, Finances).
 */
class FournisseurController
{
    private const ONGLETS = ['vue', 'contacts', 'offres', 'commandes', 'documents', 'evaluation', 'finances'];

    // ------------------------------------------------------------------
    // Liste
    // ------------------------------------------------------------------

    public function index(): void
    {
        $user = Auth::user();
        $filtres = Fournisseur::filtresDepuis($_GET);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $liste = Fournisseur::liste($user, $filtres, $page);

        // Conserve les filtres pour le retour depuis une fiche.
        $_SESSION['fournisseurs_liste_query'] = $this->queryListe($filtres, $liste['page']);

        View::render('fournisseurs/index', [
            'liste' => $liste,
            'filtres' => $filtres,
            'indicateurs' => Fournisseur::indicateurs($user, $filtres),
            'filiales' => Filiale::visibleFor($user),
            'utilisateurs' => Utilisateur::allForOrganisation((int) $user['organisation_id']),
            'paysListe' => Fournisseur::paysUtilises($user),
            'specialitesListe' => Fournisseur::specialitesUtilisees($user),
            'peutExporter' => Auth::canVoirFinancesFournisseur(),
            'schemaPret' => Fournisseur::schemaPret(),
        ]);
    }

    private function queryListe(array $f, int $page = 1): string
    {
        $q = ['r' => 'fournisseurs'];
        foreach (['q', 'pays', 'specialite', 'type', 'qualification', 'alerte'] as $k) {
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
        if ($f['tri'] !== 'nom') {
            $q['tri'] = $f['tri'];
        }
        if ($f['dir'] !== 'asc') {
            $q['dir'] = $f['dir'];
        }
        if ($page > 1) {
            $q['page'] = $page;
        }
        return http_build_query($q);
    }

    public function exportCsv(): void
    {
        $user = Auth::user();
        if (!Auth::canVoirFinancesFournisseur()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }
        $filtres = Fournisseur::filtresDepuis($_GET);
        $lignes = Fournisseur::listeComplete($user, $filtres);
        AuditLog::log((int) (Filiale::visibleIdsFor($user)[0] ?? 0), (int) $user['id'], 'export_fournisseurs', 'fournisseur', null, count($lignes) . ' fournisseur(s)');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="fournisseurs_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Fournisseurs — Suivora360'], ';', '"', '\\');
        fputcsv($out, ['Exporté le', date('d/m/Y H:i')], ';', '"', '\\');
        fputcsv($out, ['Nombre de résultats', count($lignes)], ';', '"', '\\');
        fputcsv($out, [], ';', '"', '\\');
        fputcsv($out, ['Référence', 'Raison sociale', 'Types', 'Spécialités', 'Pays', 'Ville', 'Filiale', 'Responsable', 'Contact principal', 'Fonction', 'E-mail', 'Téléphone', 'Qualification', 'Statut', 'Consultations en cours'], ';', '"', '\\');
        foreach ($lignes as $f) {
            fputcsv($out, Csv::row([
                $f['code'],
                $f['nom'],
                implode(', ', array_map(fn($t) => Fournisseur::TYPES[$t], Fournisseur::typesDe($f))),
                implode(', ', Fournisseur::specialitesDe($f)),
                $f['pays'],
                $f['ville'],
                $f['filiale_nom'],
                $f['responsable_nom'] ?? '',
                trim(($f['contact_prenom'] ?? '') . ' ' . ($f['contact_nom'] ?? '')),
                $f['fonction_contact'],
                $f['email'],
                $f['telephone'],
                Fournisseur::QUALIFICATIONS[Fournisseur::qualificationDe($f)],
                ((int) $f['is_active'] === 1) ? 'Actif' : 'Inactif',
                (int) $f['nb_consultations_en_cours'],
            ]), ';', '"', '\\');
        }
        fclose($out);
        exit;
    }

    // ------------------------------------------------------------------
    // Création / modification (formulaire unique)
    // ------------------------------------------------------------------

    private function donneesFormulaire(array $user, ?array $fournisseur, array $old = [], array $erreurs = [], array $doublons = []): array
    {
        return [
            'fournisseur' => $fournisseur,
            'old' => $old,
            'erreurs' => $erreurs,
            'doublons' => $doublons,
            'filiales' => Filiale::visibleFor($user),
            'utilisateurs' => Utilisateur::allForOrganisation((int) $user['organisation_id']),
            'clients' => Fournisseur::schemaPret() ? Fournisseur::clientsLiables($user) : [],
            'defauts' => Fournisseur::defautsOrganisation($user),
            'schemaPret' => Fournisseur::schemaPret(),
            'retour' => '/index.php?' . ($_SESSION['fournisseurs_liste_query'] ?? 'r=fournisseurs'),
        ];
    }

    public function create(): void
    {
        Auth::requireWrite();
        View::render('fournisseurs/form', $this->donneesFormulaire(Auth::user(), null));
    }

    private function lireChamps(array $user): array
    {
        $p = fn(string $k) => trim((string) ($_POST[$k] ?? ''));
        $orgId = (int) $user['organisation_id'];
        $liste = fn(string $k, array $autorises) => array_values(array_filter((array) ($_POST[$k] ?? []), fn($v) => in_array($v, $autorises, true)));
        $devise = strtoupper($p('devise'));
        $site = $p('site_web');
        if ($site !== '' && !preg_match('#^https?://#i', $site)) {
            $site = 'https://' . $site;
        }
        $clientId = (int) ($_POST['client_id'] ?? 0);
        if ($clientId) {
            $c = \App\Models\Client::find($clientId);
            $clientId = ($c && \App\Models\Client::userCanAccess($user, $c)) ? $clientId : 0;
        }
        $specialites = implode(', ', array_filter(array_map('trim', explode(',', $p('specialites'))), fn($s) => $s !== ''));
        return [
            'nom' => $p('nom'),
            'nom_commercial' => $p('nom_commercial'),
            'types_partenaire' => Fournisseur::typesStockes((array) ($_POST['types'] ?? [])),
            'specialites' => $specialites,
            'activites' => implode(',', $liste('activites', \App\Models\Demande::ACTIVITES)),
            'secteur' => $p('secteur'),
            'categories_produits' => $specialites,
            'marques' => $p('marques'),
            'pays' => $p('pays'),
            'ville' => $p('ville'),
            'adresse' => $p('adresse'),
            'code_postal' => $p('code_postal'),
            'site_web' => $site,
            'siret' => $p('siret'),
            'tva' => $p('tva'),
            'contact_prenom' => $p('contact_prenom'),
            'contact_nom' => $p('contact_nom'),
            'fonction_contact' => $p('fonction_contact'),
            'email' => $p('email'),
            'telephone' => Telephone::composer($p('tel_indicatif'), $p('tel_numero')),
            'devises_proposees' => implode(',', $liste('devises_proposees', Fournisseur::DEVISES)),
            'devise' => in_array($devise, Fournisseur::DEVISES, true) ? $devise : '',
            'conditions_paiement' => array_key_exists($p('conditions_paiement'), \App\Models\Client::CONDITIONS_PAIEMENT) ? $p('conditions_paiement') : '',
            'delai_indicatif' => $p('delai_indicatif'),
            'pays_desservis' => $p('pays_desservis'),
            'quantite_min' => $p('quantite_min'),
            'incoterms_pratiques' => implode(',', $liste('incoterms_pratiques', array_keys(\App\Models\Demande::INCOTERMS))),
            'conditions_livraison' => $p('conditions_livraison'),
            'notes' => $p('notes'),
            'responsable_id' => Tenant::utilisateurDOrganisation($_POST['responsable_id'] ?? 0, $orgId) ?: null,
            'origine_contact' => array_key_exists($p('origine_contact'), Fournisseur::ORIGINES) ? $p('origine_contact') : '',
            'client_id' => $clientId ?: null,
            'statut' => $p('statut') === 'inactif' ? 'inactif' : 'actif',
            'note_prix' => $_POST['note_prix'] ?? null,
            'note_qualite' => $_POST['note_qualite'] ?? null,
            'note_delai' => $_POST['note_delai'] ?? null,
            'note_reactivite' => $_POST['note_reactivite'] ?? null,
            'note_conformite' => $_POST['note_conformite'] ?? null,
            'note_engagements' => $_POST['note_engagements'] ?? null,
        ];
    }

    private function valider(array $d): array
    {
        $e = [];
        if ($d['nom'] === '') {
            $e['nom'] = 'La raison sociale est obligatoire.';
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
            View::render('fournisseurs/form', $this->donneesFormulaire($user, null, $_POST, ['_general' => 'Session expirée : vos saisies sont conservées, validez à nouveau.']));
            return;
        }
        $d = $this->lireChamps($user);
        $erreurs = $this->valider($d);
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);
        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            $erreurs['filiale_id'] = 'Choisissez une filiale à laquelle vous avez accès.';
        }
        if ($erreurs) {
            View::render('fournisseurs/form', $this->donneesFormulaire($user, null, $_POST, $erreurs));
            return;
        }
        if (empty($_POST['confirmer_doublon'])) {
            $doublons = Fournisseur::doublonsPotentiels($user, $d['nom'], $d['email'], $d['telephone'], $d['site_web']);
            if ($doublons) {
                View::render('fournisseurs/form', $this->donneesFormulaire($user, null, $_POST, [], $doublons));
                return;
            }
        }
        $d['filiale_id'] = $filialeId;
        $d['qualification'] = 'a_qualifier';
        $id = Fournisseur::create($d);
        AuditLog::log($filialeId, (int) $user['id'], 'creation_fournisseur', 'fournisseur', $id);
        View::flash('succes', 'Fournisseur créé avec succès.');
        header('Location: /index.php?r=fournisseurs/' . $id);
        exit;
    }

    private function chargerOu404(array $params, bool $ecriture = false): ?array
    {
        if ($ecriture) {
            Auth::requireWrite();
        }
        $f = Fournisseur::find((int) ($params['id'] ?? 0));
        if (!$f || !Fournisseur::userCanAccess(Auth::user(), $f)) {
            http_response_code(404);
            View::render('errors/404');
            return null;
        }
        return $f;
    }

    public function edit(array $params): void
    {
        Auth::requireWrite();
        $f = $this->chargerOu404($params);
        if ($f) {
            View::render('fournisseurs/form', $this->donneesFormulaire(Auth::user(), $f));
        }
    }

    public function update(array $params): void
    {
        $f = $this->chargerOu404($params, true);
        if (!$f) {
            return;
        }
        $user = Auth::user();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::render('fournisseurs/form', $this->donneesFormulaire($user, $f, $_POST, ['_general' => 'Session expirée : vos saisies sont conservées, validez à nouveau.']));
            return;
        }
        $d = $this->lireChamps($user);
        $erreurs = $this->valider($d);
        if ($erreurs) {
            View::render('fournisseurs/form', $this->donneesFormulaire($user, $f, $_POST, $erreurs));
            return;
        }
        $changeIdentite = mb_strtolower($d['nom']) !== mb_strtolower((string) $f['nom'])
            || mb_strtolower($d['email']) !== mb_strtolower((string) $f['email'])
            || $d['telephone'] !== (string) $f['telephone'];
        if ($changeIdentite && empty($_POST['confirmer_doublon'])) {
            $doublons = Fournisseur::doublonsPotentiels($user, $d['nom'], $d['email'], $d['telephone'], $d['site_web'], (int) $f['id']);
            if ($doublons) {
                View::render('fournisseurs/form', $this->donneesFormulaire($user, $f, $_POST, [], $doublons));
                return;
            }
        }
        Fournisseur::update((int) $f['id'], $d);
        AuditLog::log((int) $f['filiale_id'], (int) $user['id'], 'modification_fournisseur', 'fournisseur', (int) $f['id']);
        View::flash('succes', 'Fournisseur mis à jour. Les offres, commandes et documents déjà émis conservent leurs propres conditions.');
        header('Location: /index.php?r=fournisseurs/' . $f['id']);
        exit;
    }

    // ------------------------------------------------------------------
    // Fiche
    // ------------------------------------------------------------------

    public function show(array $params): void
    {
        $f = $this->chargerOu404($params);
        if (!$f) {
            return;
        }
        $user = Auth::user();
        $id = (int) $f['id'];
        $financesAutorisees = Auth::canVoirFinancesFournisseur();
        $onglet = $_GET['onglet'] ?? 'vue';
        if (!in_array($onglet, self::ONGLETS, true) || ($onglet === 'finances' && !$financesAutorisees)) {
            $onglet = 'vue';
        }
        $periode = in_array($_GET['periode'] ?? '', ['annee', '12m'], true) ? $_GET['periode'] : 'tout';
        $pret = Fournisseur::schemaPret();
        $consultations = Fournisseur::consultations($id);
        $commandes = Fournisseur::commandes($id);
        $types = Fournisseur::typesDe($f);

        View::render('fournisseurs/show', [
            'fournisseur' => $f,
            'onglet' => $onglet,
            'types' => $types,
            'contacts' => Fournisseur::contacts($id),
            'adresses' => Fournisseur::adresses($id),
            'pieces' => FournisseurPieceJointe::forFournisseur($id),
            'consultations' => $consultations,
            'offres' => in_array($onglet, ['vue', 'offres'], true) ? Fournisseur::offres($id) : [],
            'commandes' => $commandes,
            'performance' => in_array($onglet, ['vue', 'evaluation'], true) ? Fournisseur::performance($id, Fournisseur::debutPeriode($periode)) : null,
            'periode' => $periode,
            'qualifications' => $onglet === 'evaluation' ? Fournisseur::qualifications($id) : [],
            'evaluations' => $onglet === 'evaluation' ? Fournisseur::evaluations($id) : [],
            'criteresQualification' => Fournisseur::criteresPour(Fournisseur::CRITERES_QUALIFICATION, $types ?: array_keys(Fournisseur::TYPES)),
            'criteresEvaluation' => Fournisseur::criteresPour(Fournisseur::CRITERES_EVALUATION, $types ?: array_keys(Fournisseur::TYPES)),
            'alertesDocuments' => Fournisseur::documentsAlertes($id),
            'dossiersActifs' => (Auth::canWrite() && (int) $f['is_active'] === 1)
                ? array_values(array_filter(\App\Models\Dossier::visibleFor($user, ['statut' => 'actif']), fn($d) => (int) $d['filiale_id'] === (int) $f['filiale_id']))
                : [],
            'responsableNom' => !empty($f['responsable_id']) ? Utilisateur::nameOf((int) $f['responsable_id']) : '',
            'clientLie' => !empty($f['client_id']) ? \App\Models\Client::find((int) $f['client_id']) : null,
            'filiale' => Filiale::find((int) $f['filiale_id']),
            'financesAutorisees' => $financesAutorisees,
            'peutQualifier' => Auth::canQualifierFournisseur() && Auth::canWrite(),
            'peutEcrire' => Auth::canWrite(),
            'schemaPret' => $pret,
            'bonsCommande' => (Auth::canVoirFinancesFournisseur() ? \App\Models\BonCommandeFournisseur::pourFournisseur($id) : []),
            'bonsPret' => \App\Models\BonCommandeFournisseur::schemaPret(),
            'peutGererBon' => Auth::canWrite() && Auth::canGererBonCommandeFournisseur() && (int) $f['is_active'] === 1,
            'peutVoirBons' => Auth::canVoirFinancesFournisseur(),
            'retour' => '/index.php?' . ($_SESSION['fournisseurs_liste_query'] ?? 'r=fournisseurs'),
        ]);
    }

    private function versFiche(int $id, string $onglet = 'vue'): void
    {
        header('Location: /index.php?r=fournisseurs/' . $id . ($onglet !== 'vue' ? '&onglet=' . $onglet : ''));
        exit;
    }

    private function csrfOuRetour(int $id, string $onglet): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            $this->versFiche($id, $onglet);
        }
    }

    private function migrationRequise(int $id, string $onglet, string $quoi): void
    {
        if (!Fournisseur::schemaPret()) {
            View::flash('erreur', 'Mise à jour de la base requise (migration V23) avant ' . $quoi . '.');
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
        $f = $this->chargerOu404($params, true);
        if (!$f) {
            return;
        }
        $user = Auth::user();
        $retourFiche = ($_POST['retour'] ?? '') === 'fiche';
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
        } else {
            Fournisseur::setActive((int) $f['id'], $actif);
            AuditLog::log((int) $f['filiale_id'], (int) $user['id'], $actif ? 'reactivation_fournisseur' : 'desactivation_fournisseur', 'fournisseur', (int) $f['id']);
            View::flash('succes', $actif
                ? 'Fournisseur réactivé.'
                : 'Fournisseur désactivé. Ses consultations, offres, commandes et documents sont conservés ; il peut être réactivé à tout moment.');
        }
        if ($retourFiche) {
            $this->versFiche((int) $f['id']);
        }
        header('Location: /index.php?' . ($_SESSION['fournisseurs_liste_query'] ?? 'r=fournisseurs'));
        exit;
    }

    // ------------------------------------------------------------------
    // Contacts et adresses supplémentaires
    // ------------------------------------------------------------------

    public function enregistrerContact(array $params): void
    {
        $f = $this->chargerOu404($params, true);
        if (!$f) {
            return;
        }
        $id = (int) $f['id'];
        $this->csrfOuRetour($id, 'contacts');
        $this->migrationRequise($id, 'contacts', 'd’ajouter des contacts');
        $p = fn(string $k) => trim((string) ($_POST[$k] ?? ''));
        $d = ['prenom' => $p('prenom'), 'nom' => $p('nom'), 'fonction' => $p('fonction'), 'email' => $p('email'),
            'telephone' => Telephone::composer($p('tel_indicatif'), $p('tel_numero'))];
        if ($d['prenom'] === '' && $d['nom'] === '') {
            View::flash('erreur', 'Indiquez au moins un prénom ou un nom pour le contact.');
            $this->versFiche($id, 'contacts');
        }
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            View::flash('erreur', "L'adresse e-mail du contact n'est pas valide.");
            $this->versFiche($id, 'contacts');
        }
        $cid = (int) ($_POST['contact_id'] ?? 0);
        if ($cid && !Fournisseur::contact($id, $cid)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        Fournisseur::enregistrerContact($id, $cid ?: null, $d);
        AuditLog::log((int) $f['filiale_id'], (int) Auth::user()['id'], 'contact_fournisseur', 'fournisseur', $id, trim($d['prenom'] . ' ' . $d['nom']));
        View::flash('succes', 'Contact enregistré.');
        $this->versFiche($id, 'contacts');
    }

    public function actionContact(array $params): void
    {
        $f = $this->chargerOu404($params, true);
        if (!$f) {
            return;
        }
        $id = (int) $f['id'];
        $cid = (int) ($params['contactId'] ?? 0);
        $this->csrfOuRetour($id, 'contacts');
        if (!Fournisseur::contact($id, $cid)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $action = $params['action'] ?? '';
        if ($action === 'principal') {
            Fournisseur::definirContactPrincipal($id, $cid);
            View::flash('succes', 'Contact principal modifié ; l’ancien contact principal reste dans la liste.');
        } elseif ($action === 'desactiver') {
            Fournisseur::basculerContact($id, $cid, false);
            View::flash('succes', 'Contact désactivé (conservé dans l’historique).');
        } elseif ($action === 'reactiver') {
            Fournisseur::basculerContact($id, $cid, true);
            View::flash('succes', 'Contact réactivé.');
        }
        AuditLog::log((int) $f['filiale_id'], (int) Auth::user()['id'], 'contact_fournisseur', 'fournisseur', $id, $action);
        $this->versFiche($id, 'contacts');
    }

    public function enregistrerAdresse(array $params): void
    {
        $f = $this->chargerOu404($params, true);
        if (!$f) {
            return;
        }
        $id = (int) $f['id'];
        $this->csrfOuRetour($id, 'contacts');
        $this->migrationRequise($id, 'contacts', 'd’ajouter des adresses');
        $p = fn(string $k) => trim((string) ($_POST[$k] ?? ''));
        $d = ['type' => $p('type'), 'libelle' => $p('libelle'), 'adresse' => $p('adresse'), 'code_postal' => $p('code_postal'), 'ville' => $p('ville'), 'pays' => $p('pays')];
        if ($d['adresse'] === '' && $d['ville'] === '') {
            View::flash('erreur', 'Indiquez au moins une adresse ou une ville.');
            $this->versFiche($id, 'contacts');
        }
        $aid = (int) ($_POST['adresse_id'] ?? 0);
        if ($aid && !Fournisseur::adresse($id, $aid)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        Fournisseur::enregistrerAdresse($id, $aid ?: null, $d);
        AuditLog::log((int) $f['filiale_id'], (int) Auth::user()['id'], 'adresse_fournisseur', 'fournisseur', $id, $d['libelle'] ?: $d['ville']);
        View::flash('succes', 'Adresse enregistrée.');
        $this->versFiche($id, 'contacts');
    }

    public function actionAdresse(array $params): void
    {
        $f = $this->chargerOu404($params, true);
        if (!$f) {
            return;
        }
        $id = (int) $f['id'];
        $aid = (int) ($params['adresseId'] ?? 0);
        $this->csrfOuRetour($id, 'contacts');
        if (!Fournisseur::adresse($id, $aid)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $action = $params['action'] ?? '';
        if ($action === 'principale') {
            Fournisseur::definirAdressePrincipale($id, $aid);
            View::flash('succes', 'Adresse principale modifiée ; l’ancienne adresse reste dans la liste.');
        } elseif ($action === 'desactiver') {
            Fournisseur::basculerAdresse($id, $aid, false);
            View::flash('succes', 'Adresse désactivée (conservée dans l’historique).');
        } elseif ($action === 'reactiver') {
            Fournisseur::basculerAdresse($id, $aid, true);
            View::flash('succes', 'Adresse réactivée.');
        }
        AuditLog::log((int) $f['filiale_id'], (int) Auth::user()['id'], 'adresse_fournisseur', 'fournisseur', $id, $action);
        $this->versFiche($id, 'contacts');
    }

    // ------------------------------------------------------------------
    // Qualification et évaluations documentées
    // ------------------------------------------------------------------

    private function dateValide(string $d): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false ? $d : null;
    }

    public function enregistrerQualification(array $params): void
    {
        $f = $this->chargerOu404($params, true);
        if (!$f) {
            return;
        }
        $id = (int) $f['id'];
        $this->csrfOuRetour($id, 'evaluation');
        if (!Auth::canQualifierFournisseur()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }
        $this->migrationRequise($id, 'evaluation', 'de qualifier un fournisseur');
        $decision = (string) ($_POST['decision'] ?? '');
        if (!isset(Fournisseur::QUALIFICATIONS[$decision])) {
            View::flash('erreur', 'Choisissez un niveau de qualification.');
            $this->versFiche($id, 'evaluation');
        }
        $commentaire = trim((string) ($_POST['commentaire'] ?? ''));
        if (in_array($decision, ['valide', 'non_retenu'], true) && $commentaire === '') {
            View::flash('erreur', 'Un commentaire est obligatoire pour valider ou ne pas retenir un fournisseur (il explique la décision dans l’historique).');
            $this->versFiche($id, 'evaluation');
        }
        $types = Fournisseur::typesDe($f);
        $autorises = Fournisseur::criteresPour(Fournisseur::CRITERES_QUALIFICATION, $types ?: array_keys(Fournisseur::TYPES));
        $criteres = [];
        foreach ($autorises as $code => $libelle) {
            $r = (string) ($_POST['criteres'][$code] ?? '');
            if (isset(Fournisseur::RESULTATS_CRITERE[$r])) {
                $criteres[] = ['code' => $code, 'libelle' => $libelle, 'resultat' => $r];
            }
        }
        $piecesIds = [];
        foreach ((array) ($_POST['justificatifs'] ?? []) as $pid) {
            $pj = FournisseurPieceJointe::find((int) $pid);
            if ($pj && (int) $pj['fournisseur_id'] === $id) {
                $piecesIds[] = (int) $pj['id'];
            }
        }
        $reexamen = $this->dateValide((string) ($_POST['date_reexamen'] ?? ''));
        Fournisseur::enregistrerQualification($id, $decision, $criteres, $commentaire, $piecesIds, (int) Auth::user()['id'], $reexamen);
        AuditLog::log((int) $f['filiale_id'], (int) Auth::user()['id'], 'qualification_fournisseur', 'fournisseur', $id, Fournisseur::QUALIFICATIONS[$decision]);
        View::flash('succes', 'Qualification enregistrée : « ' . Fournisseur::QUALIFICATIONS[$decision] . ' ». L’historique est conservé.');
        $this->versFiche($id, 'evaluation');
    }

    public function ajouterEvaluation(array $params): void
    {
        $f = $this->chargerOu404($params, true);
        if (!$f) {
            return;
        }
        $id = (int) $f['id'];
        $this->csrfOuRetour($id, 'evaluation');
        $this->migrationRequise($id, 'evaluation', 'd’ajouter une évaluation');
        $types = Fournisseur::typesDe($f);
        $autorises = Fournisseur::criteresPour(Fournisseur::CRITERES_EVALUATION, $types ?: array_keys(Fournisseur::TYPES));
        $critere = (string) ($_POST['critere'] ?? '');
        $resultat = (string) ($_POST['resultat'] ?? '');
        if (!isset($autorises[$critere]) || !isset(Fournisseur::RESULTATS_EVALUATION[$resultat])) {
            View::flash('erreur', 'Choisissez un critère et un résultat.');
            $this->versFiche($id, 'evaluation');
        }
        // Le dossier doit réellement concerner ce fournisseur (consultation ou offre).
        $dossierId = (int) ($_POST['dossier_id'] ?? 0);
        if ($dossierId) {
            $ok = array_filter(Fournisseur::consultations($id), fn($c) => (int) $c['dossier_id'] === $dossierId);
            if (!$ok) {
                View::flash('erreur', 'Ce dossier n’est lié à aucune consultation de ce fournisseur.');
                $this->versFiche($id, 'evaluation');
            }
        }
        $pieceId = (int) ($_POST['piece_id'] ?? 0);
        if ($pieceId) {
            $pj = FournisseurPieceJointe::find($pieceId);
            $pieceId = ($pj && (int) $pj['fournisseur_id'] === $id) ? $pieceId : 0;
        }
        Fournisseur::ajouterEvaluation($id, [
            'dossier_id' => $dossierId,
            'critere' => $critere,
            'resultat' => $resultat,
            'commentaire' => trim((string) ($_POST['commentaire'] ?? '')),
            'piece_id' => $pieceId,
            'date_evaluation' => $this->dateValide((string) ($_POST['date_evaluation'] ?? '')) ?: date('Y-m-d'),
        ], (int) Auth::user()['id']);
        AuditLog::log((int) $f['filiale_id'], (int) Auth::user()['id'], 'evaluation_fournisseur', 'fournisseur', $id, $autorises[$critere]);
        View::flash('succes', 'Évaluation enregistrée.');
        $this->versFiche($id, 'evaluation');
    }

    // ------------------------------------------------------------------
    // Documents du fournisseur
    // ------------------------------------------------------------------

    public function uploadPiece(array $params): void
    {
        $f = $this->chargerOu404($params, true);
        if (!$f) {
            return;
        }
        $user = Auth::user();
        $id = (int) $f['id'];
        $this->csrfOuRetour($id, 'documents');
        if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] === UPLOAD_ERR_NO_FILE) {
            View::flash('erreur', 'Aucun fichier sélectionné.');
            $this->versFiche($id, 'documents');
        }
        $fichier = $_FILES['fichier'];
        if ($fichier['error'] === UPLOAD_ERR_INI_SIZE || $fichier['error'] === UPLOAD_ERR_FORM_SIZE || $fichier['size'] > FournisseurPieceJointe::TAILLE_MAX) {
            View::flash('erreur', 'Le fichier dépasse la taille maximale autorisée (10 Mo).');
            $this->versFiche($id, 'documents');
        }
        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            View::flash('erreur', "Échec de l'envoi du fichier, merci de réessayer.");
            $this->versFiche($id, 'documents');
        }
        $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, FournisseurPieceJointe::EXTENSIONS_AUTORISEES, true)) {
            View::flash('erreur', 'Type de fichier non autorisé (formats acceptés : ' . implode(', ', FournisseurPieceJointe::EXTENSIONS_AUTORISEES) . ').');
            $this->versFiche($id, 'documents');
        }
        $categorie = $_POST['categorie'] ?? 'autre';
        if (!array_key_exists($categorie, FournisseurPieceJointe::CATEGORIES)) {
            $categorie = 'autre';
        }
        $expire = trim((string) ($_POST['expire_le'] ?? ''));
        if ($expire !== '' && !$this->dateValide($expire)) {
            View::flash('erreur', 'La date d’échéance du document n’est pas valide.');
            $this->versFiche($id, 'documents');
        }
        $nomFichier = bin2hex(random_bytes(16)) . '.' . $extension;
        $chemin = Storage::path('uploads/fournisseurs/' . $id . '/' . $nomFichier);
        if (!move_uploaded_file($fichier['tmp_name'], $chemin)) {
            View::flash('erreur', "Impossible d'enregistrer le fichier sur le serveur.");
            $this->versFiche($id, 'documents');
        }
        FournisseurPieceJointe::create([
            'fournisseur_id' => $id,
            'categorie' => $categorie,
            'nom_original' => $fichier['name'],
            'nom_fichier' => $nomFichier,
            'taille' => $fichier['size'],
            'type_mime' => $fichier['type'] ?: 'application/octet-stream',
            'uploaded_by' => (int) $user['id'],
            'expire_le' => $expire !== '' ? $expire : null,
        ]);
        AuditLog::log((int) $f['filiale_id'], (int) $user['id'], 'ajout_piece_jointe', 'fournisseur', $id, $fichier['name']);
        View::flash('succes', 'Document ajouté.');
        $this->versFiche($id, 'documents');
    }

    public function telechargerPiece(array $params): void
    {
        $f = $this->chargerOu404($params);
        if (!$f) {
            return;
        }
        $piece = FournisseurPieceJointe::find((int) ($params['pieceId'] ?? 0));
        if (!$piece || (int) $piece['fournisseur_id'] !== (int) $f['id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $chemin = Storage::path('uploads/fournisseurs/' . $f['id'] . '/' . $piece['nom_fichier']);
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
        $f = $this->chargerOu404($params, true);
        if (!$f) {
            return;
        }
        $user = Auth::user();
        $id = (int) $f['id'];
        $this->csrfOuRetour($id, 'documents');
        $piece = FournisseurPieceJointe::find((int) ($params['pieceId'] ?? 0));
        if ($piece && (int) $piece['fournisseur_id'] === $id) {
            $chemin = Storage::path('uploads/fournisseurs/' . $id . '/' . $piece['nom_fichier']);
            if (is_file($chemin)) {
                unlink($chemin);
            }
            FournisseurPieceJointe::delete((int) $piece['id']);
            AuditLog::log((int) $f['filiale_id'], (int) $user['id'], 'suppression_piece_jointe', 'fournisseur', $id, $piece['nom_original']);
            View::flash('succes', 'Document supprimé.');
        }
        $this->versFiche($id, 'documents');
    }

    // ------------------------------------------------------------------
    // Création rapide (depuis une consultation : la saisie en cours n'est pas perdue)
    // ------------------------------------------------------------------

    public function creationRapide(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::canWrite()) {
            http_response_code(403);
            echo json_encode(['erreur' => "Vous n'avez pas le droit de créer un fournisseur."]);
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
        if ($nom === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            http_response_code(422);
            echo json_encode(['erreur' => $nom === '' ? 'La raison sociale est obligatoire.' : "L'adresse e-mail n'est pas valide."]);
            return;
        }
        if (empty($_POST['force'])) {
            $doublons = Fournisseur::doublonsPotentiels($user, $nom, $email, trim($_POST['telephone'] ?? ''));
            if ($doublons) {
                echo json_encode(['doublon' => true, 'existant' => ['id' => (int) $doublons[0]['id'], 'nom' => $doublons[0]['nom'], 'email' => $doublons[0]['email']]]);
                return;
            }
        }
        $type = (string) ($_POST['type'] ?? '');
        $id = Fournisseur::create([
            'filiale_id' => $filialeId,
            'nom' => $nom,
            'email' => $email,
            'telephone' => trim($_POST['telephone'] ?? ''),
            'pays' => trim($_POST['pays'] ?? ''),
            'types_partenaire' => isset(Fournisseur::TYPES[$type]) ? Fournisseur::typesStockes([$type]) : '',
            'qualification' => 'a_qualifier',
        ]);
        AuditLog::log($filialeId, (int) $user['id'], 'creation_fournisseur', 'fournisseur', $id, 'Création rapide depuis une consultation');
        $f = Fournisseur::find($id);
        echo json_encode(['id' => $id, 'nom' => $f['nom'], 'pays' => $f['pays']]);
    }
}

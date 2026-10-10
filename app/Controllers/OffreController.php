<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\ConsultationFournisseur;
use App\Models\Offre;
use App\Models\OffreItem;

class OffreController
{
    public function create(array $params): void
    {
        $user = Auth::user();
        $consultation = ConsultationFournisseur::findWithDetails((int) $params['id']);
        if (!$consultation || !ConsultationFournisseur::userCanAccess($user, $consultation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        // [ajouté 06/10, étape 2 du découpage Dossiers] ?version_de=<offreId>
        // : le fournisseur a renvoyé une offre révisée sur la même
        // consultation (demande explicite de Marie Laure, 06/10) — on
        // préremplit le formulaire à partir de la version précédente plutôt
        // que de repartir d'une page vierge.
        $offrePrecedente = null;
        $itemsPrecedents = [];
        $versionDeId = (int) ($_GET['version_de'] ?? 0);
        if ($versionDeId > 0) {
            $candidate = Offre::find($versionDeId);
            if ($candidate && (int) $candidate['consultation_id'] === (int) $consultation['id']) {
                $offrePrecedente = $candidate;
                $itemsPrecedents = OffreItem::forOffre($versionDeId);
            }
        }

        View::render('offres/create', [
            'consultation' => $consultation,
            'offrePrecedente' => $offrePrecedente,
            'itemsPrecedents' => $itemsPrecedents,
        ]);
    }

    public function store(array $params): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=consultations/' . $params['id']);
            exit;
        }

        $user = Auth::user();
        $consultation = ConsultationFournisseur::find((int) $params['id']);
        if (!$consultation || !ConsultationFournisseur::userCanAccess($user, $consultation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        // [ajouté 06/10, étape 2 du découpage Dossiers] Révision d'une offre
        // existante : le champ caché offre_precedente_id (posté par
        // views/offres/create.php quand on arrive via "+ Nouvelle version")
        // n'est honoré que s'il appartient bien à cette même consultation.
        $offrePrecedente = null;
        $offrePrecedenteId = (int) ($_POST['offre_precedente_id'] ?? 0);
        if ($offrePrecedenteId > 0) {
            $candidate = Offre::find($offrePrecedenteId);
            if ($candidate && (int) $candidate['consultation_id'] === (int) $consultation['id']) {
                $offrePrecedente = $candidate;
            }
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

        $offreId = Offre::create($consultation, $_POST, $items, (int) $user['id'], $offrePrecedente);

        AuditLog::log(
            (int) $consultation['filiale_id'],
            (int) $user['id'],
            $offrePrecedente ? 'revision_offre' : 'creation_offre',
            'offre',
            $offreId
        );
        // [08/10] Prévenir le responsable du dossier qu'une offre est à analyser (cloche).
        try {
            $dossierOffre = !empty($consultation['dossier_id']) ? \App\Models\Dossier::find((int) $consultation['dossier_id']) : null;
            if ($dossierOffre) {
                \App\Models\Notification::notifierTous(
                    \App\Models\Notification::destinatairesDossier($dossierOffre, [(int) $user['id']]),
                    (int) $consultation['filiale_id'], 'offre_recue',
                    $offrePrecedente ? 'Offre révisée à analyser' : 'Nouvelle offre à analyser',
                    'Une offre fournisseur a été enregistrée sur le dossier ' . $dossierOffre['reference'] . ' (' . $dossierOffre['objet'] . ').',
                    '/index.php?r=dossiers/' . $dossierOffre['id'], 'offre', (int) $offreId
                );
            }
        } catch (\Throwable $e) {
        }
        $avert = null;
        if (!empty($dossierOffre)) {
            $avert = $this->joindreFichierOffre($dossierOffre, $user, (int) $offreId);
        }
        if ($avert) {
            View::flash('erreur', $avert);
        }
        View::flash('succes', $offrePrecedente ? 'Nouvelle version de l\'offre enregistrée.' : ('Offre enregistrée' . (isset($_FILES['fichier_offre']) && $_FILES['fichier_offre']['error'] === UPLOAD_ERR_OK && !$avert ? ' ; fichier rangé dans les documents du dossier.' : '.')));
        header('Location: /index.php?r=consultations/' . $consultation['id']);
        exit;
    }

    /**
     * [10/10] Saisie manuelle d'une offre (prix trouvé sur internet, catalogue, appel…) sans consultation
     * envoyée : une consultation « saisie manuellement » est créée automatiquement pour garder le
     * circuit Dossier → Consultation → Offre → Comparateur intact.
     */
    public function createManuelle(array $params): void
    {
        $user = Auth::user();
        $dossier = \App\Models\Dossier::find((int) $params['id']);
        if (!$dossier || !\App\Models\Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        \App\Models\Dossier::refuserSiAnnule($dossier);
        View::render('offres/create', [
            'manuel' => true,
            'dossier' => $dossier,
            'fournisseurs' => \App\Models\Fournisseur::allForFiliale((int) $dossier['filiale_id']),
            'consultation' => [
                'id' => 0, 'reference' => '', 'type_dossier' => $dossier['type_dossier'] ?? 'autre',
                'fournisseur_nom' => '', 'dossier_reference' => $dossier['reference'], 'dossier_id' => $dossier['id'],
            ],
            'offrePrecedente' => null,
            'itemsPrecedents' => [],
        ]);
    }

    public function storeManuelle(array $params): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=dossiers/' . $params['id']);
            exit;
        }
        $user = Auth::user();
        $dossier = \App\Models\Dossier::find((int) $params['id']);
        if (!$dossier || !\App\Models\Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        \App\Models\Dossier::refuserSiAnnule($dossier);
        $retour = '/index.php?r=dossiers/' . $dossier['id'] . '/offres/manuelle';

        $source = array_key_exists($_POST['source_type'] ?? '', Offre::SOURCES) ? $_POST['source_type'] : 'autre';
        $filialeId = (int) $dossier['filiale_id'];

        // Vendeur : fournisseur existant de la filiale, sinon création d'une fiche minimale (à qualifier).
        $fournisseurId = (int) ($_POST['fournisseur_id'] ?? 0);
        if ($fournisseurId > 0 && !\App\Core\Tenant::fournisseurDeFiliale($fournisseurId, $filialeId)) {
            $fournisseurId = 0;
        }
        if ($fournisseurId === 0) {
            $nom = trim($_POST['nouveau_vendeur'] ?? '');
            if ($nom === '') {
                View::flash('erreur', 'Choisissez un fournisseur existant ou indiquez le nom du vendeur.');
                header('Location: ' . $retour);
                exit;
            }
            $fournisseurId = \App\Models\Fournisseur::create([
                'filiale_id' => $filialeId,
                'nom' => $nom,
                'site_web' => $source === 'web' ? trim($_POST['source_url'] ?? '') : '',
                'origine_contact' => 'Offre saisie manuellement',
            ]);
            AuditLog::log($filialeId, (int) $user['id'], 'creation_fournisseur', 'fournisseur', $fournisseurId, $nom);
        }

        $items = [];
        foreach (($_POST['item_designation'] ?? []) as $i => $designation) {
            $items[] = [
                'designation' => trim($designation),
                'quantite' => $_POST['item_quantite'][$i] ?? null,
                'unite' => $_POST['item_unite'][$i] ?? '',
                'prix_unitaire' => $_POST['item_prix_unitaire'][$i] ?? null,
            ];
        }

        $consultationId = \App\Models\ConsultationFournisseur::create((int) $dossier['id'], $filialeId, [
            'fournisseur_id' => $fournisseurId,
            'notes' => 'Offre saisie manuellement — source : ' . Offre::SOURCES[$source],
        ], (int) $user['id']);
        \App\Models\ConsultationFournisseur::updateStatut($consultationId, 'reponse_recue');
        $consultation = \App\Models\ConsultationFournisseur::find($consultationId);

        $data = $_POST;
        $data['source_type'] = $source;
        $offreId = Offre::create($consultation, $data, $items, (int) $user['id'], null);

        AuditLog::log($filialeId, (int) $user['id'], 'creation_offre', 'offre', $offreId, 'Saisie manuelle (' . Offre::SOURCES[$source] . ')');
        $avert = $this->joindreFichierOffre($dossier, $user, (int) $offreId);
        if ($avert) {
            View::flash('erreur', $avert);
        }
        View::flash('succes', 'Offre enregistrée (saisie manuelle)' . (isset($_FILES['fichier_offre']) && $_FILES['fichier_offre']['error'] === UPLOAD_ERR_OK && !$avert ? ' ; fichier rangé dans les documents du dossier.' : '.'));
        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '&onglet=achats&sous=offres');
        exit;
    }

    /**
     * [10/10] Préremplissage d'une offre à partir d'un fichier (PDF, image, Excel, Word).
     * Répond en JSON ; rien n'est enregistré, le formulaire est rempli côté navigateur.
     */
    public function extraireIa(array $params): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $sortie = static function (array $d, int $code = 200): void {
            http_response_code($code);
            echo json_encode($d, JSON_UNESCAPED_UNICODE);
            exit;
        };
        if (!Auth::canWrite()) {
            $sortie(['erreur' => 'Action non autorisée.'], 403);
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $sortie(['erreur' => 'Session expirée, rechargez la page.'], 400);
        }
        $f = $_FILES['fichier'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $sortie(['erreur' => "Aucun fichier reçu (5 Mo maximum)."], 400);
        }
        try {
            $fichier = \App\Services\AiExtracteur::preparerFichier($f['tmp_name'], (string) $f['name']);
            $champs = \App\Services\AiExtracteur::extraireOffre([$fichier]);
        } catch (\Throwable $e) {
            $sortie(['erreur' => $e->getMessage()], 422);
        }
        if (!$champs || (count($champs) === 1 && empty($champs['articles']))) {
            $sortie(['erreur' => "L'IA n'a reconnu aucune information d'offre dans ce fichier."], 422);
        }
        $sortie(['champs' => $champs]);
    }

    /** [10/10] Charge l'offre à corriger et vérifie droits et statut ; renvoie null (réponse déjà envoyée) sinon. */
    private function offreModifiable(array $params): ?array
    {
        $user = Auth::user();
        $offre = Offre::find((int) $params['id']);
        $consultation = $offre ? ConsultationFournisseur::findWithDetails((int) $offre['consultation_id']) : null;
        if (!$offre || !$consultation || !ConsultationFournisseur::userCanAccess($user, $consultation)) {
            http_response_code(404);
            View::render('errors/404');
            return null;
        }
        $dossier = \App\Models\Dossier::find((int) $offre['dossier_id']);
        \App\Models\Dossier::refuserSiAnnule($dossier);
        $retour = '/index.php?r=dossiers/' . $offre['dossier_id'] . '&onglet=achats&sous=offres';
        if ($offre['statut'] === 'retenue') {
            View::flash('erreur', "Cette offre est retenue : annulez d'abord la décision pour la modifier.");
            header('Location: ' . $retour);
            exit;
        }
        if ($offre['statut'] === 'remplacee') {
            View::flash('erreur', 'Cette version a été remplacée : modifiez la version actuelle.');
            header('Location: ' . $retour);
            exit;
        }
        return ['offre' => $offre, 'consultation' => $consultation, 'dossier' => $dossier];
    }

    public function edit(array $params): void
    {
        Auth::requireWrite();
        $ctx = $this->offreModifiable($params);
        if (!$ctx) {
            return;
        }
        $offre = $ctx['offre'];
        View::render('offres/create', [
            'edition' => true,
            'manuel' => false,
            'dossier' => $ctx['dossier'],
            'consultation' => $ctx['consultation'],
            'offrePrecedente' => $offre,
            'itemsPrecedents' => OffreItem::forOffre((int) $offre['id']),
        ]);
    }

    public function update(array $params): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=offres/' . (int) $params['id'] . '/modifier');
            exit;
        }
        $ctx = $this->offreModifiable($params);
        if (!$ctx) {
            return;
        }
        $offre = $ctx['offre'];
        $user = Auth::user();
        $items = [];
        foreach (($_POST['item_designation'] ?? []) as $i => $designation) {
            $items[] = [
                'designation' => trim($designation),
                'quantite' => $_POST['item_quantite'][$i] ?? null,
                'unite' => $_POST['item_unite'][$i] ?? '',
                'prix_unitaire' => $_POST['item_prix_unitaire'][$i] ?? null,
            ];
        }
        $data = $_POST;
        if (!empty($data['source_type']) && !array_key_exists($data['source_type'], Offre::SOURCES)) {
            $data['source_type'] = 'autre';
        }
        Offre::modifier((int) $offre['id'], $data, $items);
        AuditLog::log((int) $offre['filiale_id'], (int) $user['id'], 'modification_offre', 'offre', (int) $offre['id'], $offre['reference']);

        $avert = $this->joindreFichierOffre($ctx['dossier'], $user, (int) $offre['id']);
        if ($avert) {
            View::flash('erreur', $avert);
        }
        View::flash('succes', 'Offre ' . $offre['reference'] . ' modifiée.');
        header('Location: /index.php?r=dossiers/' . $offre['dossier_id'] . '&onglet=achats&sous=offres');
        exit;
    }

    /**
     * [10/10] Le fichier choisi dans le formulaire (PDF, photo…) est rangé dans les documents du
     * dossier, catégorie « Offres fournisseurs », visibilité interne. Retourne un message d'avertissement
     * si le fichier n'a pas pu être conservé (l'offre, elle, est déjà enregistrée).
     */
    private function joindreFichierOffre(array $dossier, array $user, int $offreId = 0): ?string
    {
        $f = $_FILES['fichier_offre'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > \App\Models\DossierPieceJointe::TAILLE_MAX) {
            return "Le fichier n'a pas pu être joint (10 Mo maximum).";
        }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, \App\Models\DossierPieceJointe::EXTENSIONS_AUTORISEES, true)) {
            return "Le fichier n'a pas été joint : format ." . $ext . ' non accepté dans les documents du dossier.';
        }
        $nom = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], \App\Core\Storage::path('uploads/dossiers/' . $dossier['id'] . '/' . $nom))) {
            return "Le fichier n'a pas pu être enregistré.";
        }
        \App\Models\DossierPieceJointe::create([
            'dossier_id' => $dossier['id'],
            'nom_original' => $f['name'],
            'nom_fichier' => $nom,
            'taille' => $f['size'],
            'type_mime' => $f['type'] ?: 'application/octet-stream',
            'uploaded_by' => (int) $user['id'],
            'categorie' => 'offres_fournisseurs',
            'visibilite' => 'interne',
            'offre_id' => $offreId,
        ]);
        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'ajout_piece_jointe', 'dossier', (int) $dossier['id'], $f['name']);
        return null;
    }
}

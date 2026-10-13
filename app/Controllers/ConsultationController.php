<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\ConsultationFournisseur;
use App\Models\ConsultationPartage;
use App\Models\Dossier;
use App\Models\DossierPieceJointe;
use App\Models\Fournisseur;
use App\Models\Offre;

class ConsultationController
{
    public function create(array $params): void
    {
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        Dossier::refuserSiAnnule($dossier);

        $fournisseurs = Fournisseur::allForFiliale((int) $dossier['filiale_id']);
        $demande = !empty($dossier['demande_id']) ? \App\Models\Demande::find((int) $dossier['demande_id']) : null;
        View::render('consultations/create', [
            'dossier' => $dossier,
            'apercuArticles' => $demande ? \App\Models\DemandeArticle::forDemande((int) $demande['id']) : [],
            'apercuDetails' => $demande ? \App\Models\Demande::detailsTransmissibles($demande, true) : [],
            'apercuDemande' => $demande,
            'fournisseurs' => $fournisseurs,
            'fournisseurPreselection' => (int) ($_GET['fournisseur_id'] ?? 0),
        ]);
    }

    public function store(array $params): void
    {
        Auth::requireWrite();
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
        Dossier::refuserSiAnnule($dossier);

        $filialeId = (int) $dossier['filiale_id'];
        $source = array_key_exists($_POST['source_type'] ?? '', Offre::SOURCES) ? $_POST['source_type'] : '';
        $lien = trim($_POST['source_url'] ?? '');
        if ($lien !== '' && !preg_match('#^https?://#i', $lien)) {
            $lien = '';
        }
        $noteSource = '';
        if ($source !== '') {
            $noteSource = 'Source : ' . Offre::SOURCES[$source] . ($lien !== '' ? ' — ' . $lien : '');
        } elseif ($lien !== '') {
            $noteSource = 'Lien : ' . $lien;
        }

        // Autres vendeurs (un par ligne) : fiches minimales « à qualifier ».
        $noms = [];
        foreach (preg_split('/\R/', (string) ($_POST['autres_vendeurs'] ?? '')) as $ligne) {
            $ligne = trim($ligne);
            if ($ligne !== '' && count($noms) < 20) {
                $noms[] = mb_substr($ligne, 0, 150);
            }
        }

        // Le fournisseur choisi doit appartenir à la même filiale que le dossier (isolation entre entreprises).
        $fournisseurIds = [];
        if (\App\Core\Tenant::fournisseurDeFiliale($_POST['fournisseur_id'] ?? 0, $filialeId)) {
            $fournisseurIds[] = (int) $_POST['fournisseur_id'];
        }
        if (!$fournisseurIds && !$noms) {
            View::flash('erreur', 'Veuillez sélectionner un fournisseur ou indiquer au moins un vendeur.');
            header('Location: /index.php?r=dossiers/' . $dossier['id'] . '/consultations/nouvelle');
            exit;
        }
        // [13/10] Articles de la demande choisis pour ces fournisseurs ; tous cochés (ou aucune case) = tous.
        $articlesIds = null;
        if (ConsultationFournisseur::colonneArticles() && !empty($dossier['demande_id']) && isset($_POST['articles_choix'])) {
            $tous = array_map(static fn($a) => (int) $a['id'], \App\Models\DemandeArticle::forDemande((int) $dossier['demande_id']));
            $choisis = array_values(array_intersect($tous, array_map('intval', (array) ($_POST['articles'] ?? []))));
            if ($tous && !$choisis) {
                View::flash('erreur', 'Cochez au moins un article à envoyer au fournisseur.');
                header('Location: /index.php?r=dossiers/' . $dossier['id'] . '/consultations/nouvelle');
                exit;
            }
            if ($choisis && count($choisis) < count($tous)) {
                $articlesIds = $choisis;
            }
        }
        foreach ($noms as $nom) {
            $fid = Fournisseur::create([
                'filiale_id' => $filialeId,
                'nom' => $nom,
                'site_web' => $source === 'web' ? $lien : '',
                'origine_contact' => $source !== '' ? 'Consultation : ' . Offre::SOURCES[$source] : 'Consultation',
            ]);
            AuditLog::log($filialeId, (int) $user['id'], 'creation_fournisseur', 'fournisseur', $fid, $nom);
            $fournisseurIds[] = $fid;
        }

        $donnees = $_POST;
        $donnees['articles_ids'] = $articlesIds;
        $donnees['notes'] = trim(($noteSource !== '' ? $noteSource . "\n" : '') . trim($_POST['notes'] ?? ''));
        $dernier = 0;
        foreach ($fournisseurIds as $fid) {
            $donnees['fournisseur_id'] = $fid;
            $dernier = ConsultationFournisseur::create((int) $dossier['id'], $filialeId, $donnees, (int) $user['id']);
            AuditLog::log($filialeId, (int) $user['id'], 'creation_consultation', 'consultation_fournisseur', $dernier);
        }

        $n = count($fournisseurIds);
        View::flash('succes', ($n > 1 ? $n . ' consultations enregistrées' : 'Consultation enregistrée') . ' — pas encore envoyée : préparez le récapitulatif et choisissez le canal d\'envoi.');
        header('Location: /index.php?r=dossiers/' . $dossier['id']);
        exit;
    }

    public function show(array $params): void
    {
        $user = Auth::user();
        $consultation = ConsultationFournisseur::findWithDetails((int) $params['id']);
        if (!$consultation || !ConsultationFournisseur::userCanAccess($user, $consultation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $offres = Offre::forConsultation((int) $consultation['id']);
        View::render('consultations/show', [
            'consultation' => $consultation,
            'offres' => $offres,
        ]);
    }

    public function updateStatut(array $params): void
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

        ConsultationFournisseur::updateStatut(
            (int) $consultation['id'],
            $_POST['statut'] ?? '',
            $_POST['date_relance'] ?? null,
            $_POST['canal_envoi'] ?? null
        );
        View::flash('succes', 'Statut de la consultation mis à jour.');
        header('Location: /index.php?r=consultations/' . $consultation['id']);
        exit;
    }

    /**
     * Parcours "Demander une offre" : formulaire de préparation du
     * récapitulatif (masquage client, pièces à partager, échéance, durée
     * du lien) avant de générer le lien sécurisé + le message WhatsApp.
     */
    public function preparerPartage(array $params): void
    {
        $user = Auth::user();
        $consultation = ConsultationFournisseur::findWithDetails((int) $params['id']);
        if (!$consultation || !ConsultationFournisseur::userCanAccess($user, $consultation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $dossier = Dossier::find((int) $consultation['dossier_id']);
        $pieces = DossierPieceJointe::forDossier((int) $dossier['id']);
        $partages = ConsultationPartage::forConsultation((int) $consultation['id']);

        View::render('consultations/partage_preparer', [
            'consultation' => $consultation,
            'pieces' => $pieces,
            'partages' => $partages,
        ]);
    }

    /** Aperçu du récapitulatif AVANT génération du lien (aucun enregistrement). */
    public function apercuAvantCreation(array $params): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=consultations/' . (int) $params['id'] . '/partages/nouvelle');
            exit;
        }
        $user = Auth::user();
        $consultation = ConsultationFournisseur::findWithDetails((int) $params['id']);
        if (!$consultation || !ConsultationFournisseur::userCanAccess($user, $consultation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $echeance = trim((string) ($_POST['echeance_reponse'] ?? ''));
        $partage = [
            'token' => '',
            'masquer_client' => !empty($_POST['masquer_client']) ? 1 : 0,
            'echeance_reponse' => $echeance !== '' ? $echeance : null,
            'pieces_ids' => json_encode(array_values(array_filter(array_map('intval', $_POST['pieces_ids'] ?? [])))),
        ];
        View::renderPlain('partages/public', PartagePublicController::donneesRecap($partage, $consultation, true));
    }

    /** Récapitulatif de la consultation (sans lien) : page + PDF. ?masquer_client=0 pour afficher le client. */
    private function chargerRecapConsultation(array $params): ?array
    {
        $user = Auth::user();
        $consultation = ConsultationFournisseur::findWithDetails((int) $params['id']);
        if (!$consultation || !ConsultationFournisseur::userCanAccess($user, $consultation)) {
            http_response_code(404);
            View::render('errors/404');
            return null;
        }
        $partage = ['token' => '', 'masquer_client' => (($_GET['masquer_client'] ?? '1') === '0') ? 0 : 1, 'echeance_reponse' => null, 'pieces_ids' => '[]'];
        return PartagePublicController::donneesRecap($partage, $consultation, true);
    }

    public function recapConsultation(array $params): void
    {
        $d = $this->chargerRecapConsultation($params);
        if ($d === null) {
            return;
        }
        $base = '/index.php?r=consultations/' . (int) $params['id'] . '/recap';
        $d['pdfUrl'] = $base . '/pdf&masquer_client=' . $d['partage']['masquer_client'];
        $d['basculeUrl'] = $base . '&masquer_client=' . ($d['partage']['masquer_client'] ? '0' : '1');
        View::renderPlain('partages/public', $d);
    }

    public function recapConsultationPdf(array $params): void
    {
        $d = $this->chargerRecapConsultation($params);
        if ($d === null) {
            return;
        }
        \App\Services\RecapPdf::sortir(\App\Services\RecapPdf::generer($d), \App\Services\RecapPdf::nomFichier($d['consultation']));
    }

    /** PDF d'un lien déjà généré (mêmes réglages que le lien : client masqué, pièces, échéance). */
    public function partagePdf(array $params): void
    {
        $user = Auth::user();
        $consultation = ConsultationFournisseur::findWithDetails((int) $params['id']);
        if (!$consultation || !ConsultationFournisseur::userCanAccess($user, $consultation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $partage = ConsultationPartage::find((int) $params['partageId']);
        if (!$partage || (int) $partage['consultation_id'] !== (int) $consultation['id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        \App\Services\RecapPdf::sortir(\App\Services\RecapPdf::generer(PartagePublicController::donneesRecap($partage, $consultation, true)), \App\Services\RecapPdf::nomFichier($consultation));
    }

    /** Aperçu du récapitulatif d'un lien déjà généré (ne compte pas comme une consultation). */
    public function apercuPartage(array $params): void
    {
        $user = Auth::user();
        $consultation = ConsultationFournisseur::findWithDetails((int) $params['id']);
        if (!$consultation || !ConsultationFournisseur::userCanAccess($user, $consultation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $partage = ConsultationPartage::find((int) $params['partageId']);
        if (!$partage || (int) $partage['consultation_id'] !== (int) $consultation['id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $d = PartagePublicController::donneesRecap($partage, $consultation, true);
        $d['pdfUrl'] = '/index.php?r=consultations/' . (int) $consultation['id'] . '/partages/' . (int) $partage['id'] . '/pdf';
        View::renderPlain('partages/public', $d);
    }

    public function creerPartage(array $params): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=consultations/' . $params['id']);
            exit;
        }

        $user = Auth::user();
        $consultation = ConsultationFournisseur::findWithDetails((int) $params['id']);
        if (!$consultation || !ConsultationFournisseur::userCanAccess($user, $consultation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $partage = ConsultationPartage::create((int) $consultation['id'], $_POST, (int) $user['id']);
        AuditLog::log((int) $consultation['filiale_id'], (int) $user['id'], 'creation_partage_consultation', 'consultation_fournisseur', (int) $consultation['id']);

        header('Location: /index.php?r=consultations/' . $consultation['id'] . '/partages/' . $partage['id']);
        exit;
    }

    /**
     * Écran du lien généré : lien à copier, message WhatsApp prérempli, et
     * bouton "Marquer comme envoyé" — jamais marqué automatiquement.
     */
    public function afficherPartage(array $params): void
    {
        $user = Auth::user();
        $consultation = ConsultationFournisseur::findWithDetails((int) $params['id']);
        if (!$consultation || !ConsultationFournisseur::userCanAccess($user, $consultation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $partage = ConsultationPartage::find((int) $params['partageId']);
        if (!$partage || (int) $partage['consultation_id'] !== (int) $consultation['id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $lienPublic = self::urlPublique($partage['token']);
        $fournisseur = Fournisseur::find((int) $consultation['fournisseur_id']);
        $telephone = preg_replace('/[^0-9]/', '', $fournisseur['telephone'] ?? '');
        $message = "Bonjour {$consultation['fournisseur_nom']}, merci de bien vouloir nous transmettre votre meilleure offre pour la demande {$consultation['dossier_reference']}"
            . (!empty($partage['echeance_reponse']) ? ' (échéance de réponse : ' . date('d/m/Y', strtotime($partage['echeance_reponse'])) . ')' : '')
            . ". Vous trouverez le récapitulatif ici : {$lienPublic}";

        // [13/10] Envoi par e-mail : texte plus complet que le message WhatsApp, signé par l'utilisateur.
        $emailFournisseur = trim((string) ($fournisseur['email'] ?? ''));
        $sujetEmail = 'Demande d\'offre — ' . $consultation['dossier_reference'];
        $corpsEmail = "Bonjour,\n\nNous vous remercions de bien vouloir nous transmettre votre meilleure offre pour notre demande " . $consultation['dossier_reference']
            . (!empty($partage['echeance_reponse']) ? ' (merci de répondre avant le ' . date('d/m/Y', strtotime($partage['echeance_reponse'])) . ')' : '')
            . ".\n\nVous trouverez le récapitulatif et les pièces à consulter à cette adresse :\n{$lienPublic}\n\nCordialement,\n" . ($user['nom'] ?? '');

        View::render('consultations/partage_lien', [
            'consultation' => $consultation,
            'partage' => $partage,
            'lienPublic' => $lienPublic,
            'emailFournisseur' => $emailFournisseur,
            'sujetEmail' => $sujetEmail,
            'corpsEmail' => $corpsEmail,
            'mailServeurPret' => (string) \App\Core\Env::get('MAIL_FROM_ADDRESS', '') !== '',
            'lienWhatsapp' => $telephone !== '' ? ('https://wa.me/' . $telephone . '?text=' . rawurlencode($message)) : null,
            'messagePrepare' => $message,
        ]);
    }

    /** [13/10] Envoi du lien par e-mail depuis le serveur (si MAIL_FROM_ADDRESS est configuré). */
    public function envoyerPartageEmail(array $params): void
    {
        Auth::requireWrite();
        $retour = '/index.php?r=consultations/' . (int) $params['id'] . '/partages/' . (int) $params['partageId'];
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: ' . $retour);
            exit;
        }
        $user = Auth::user();
        $consultation = ConsultationFournisseur::findWithDetails((int) $params['id']);
        if (!$consultation || !ConsultationFournisseur::userCanAccess($user, $consultation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $partage = ConsultationPartage::find((int) $params['partageId']);
        if (!$partage || (int) $partage['consultation_id'] !== (int) $consultation['id'] || !ConsultationPartage::estValide($partage)) {
            View::flash('erreur', "Ce lien n'est plus actif : générez-en un nouveau.");
            header('Location: ' . $retour);
            exit;
        }
        $destinataire = trim($_POST['destinataire'] ?? '');
        if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
            View::flash('erreur', "Adresse e-mail du destinataire invalide.");
            header('Location: ' . $retour);
            exit;
        }
        $lien = self::urlPublique($partage['token']);
        $sujet = mb_substr(trim($_POST['sujet'] ?? ''), 0, 200);
        $corps = trim($_POST['corps'] ?? '');
        // Le lien doit rester dans le message, quoi qu'ait modifié l'utilisateur.
        if ($sujet === '' || $corps === '' || strpos($corps, $lien) === false) {
            View::flash('erreur', "Le message doit contenir le sujet et le lien sécurisé.");
            header('Location: ' . $retour);
            exit;
        }
        $pieceJointe = null;
        if (!empty($_POST['joindre_pdf'])) {
            $recap = PartagePublicController::donneesRecap($partage, $consultation, true);
            $pieceJointe = ['nom' => \App\Services\RecapPdf::nomFichier($consultation), 'contenu' => \App\Services\RecapPdf::generer($recap), 'type' => 'application/pdf'];
        }
        if (!\App\Services\Mailer::envoyer($destinataire, $sujet, $corps, $user['email'] ?? null, $pieceJointe)) {
            View::flash('erreur', "L'envoi depuis Suivora n'a pas abouti (e-mail serveur non configuré ?). Utilisez « Ouvrir dans ma messagerie ».");
            header('Location: ' . $retour);
            exit;
        }
        ConsultationPartage::marquerEnvoye((int) $partage['id']);
        ConsultationFournisseur::marquerEnvoyee((int) $consultation['id'], 'email');
        AuditLog::log((int) $consultation['filiale_id'], (int) $user['id'], 'partage_envoye_email', 'consultation_fournisseur', (int) $consultation['id'], $destinataire);
        View::flash('succes', 'E-mail envoyé à ' . $destinataire . ($pieceJointe ? ' (récapitulatif PDF joint)' : '') . '. Les réponses arriveront sur votre adresse.');
        header('Location: ' . $retour);
        exit;
    }

    public function marquerPartageEnvoye(array $params): void
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
        $partage = ConsultationPartage::find((int) $params['partageId']);
        if ($partage && (int) $partage['consultation_id'] === (int) $consultation['id']) {
            $canal = (string) ($_POST['canal_envoi'] ?? '');
            if (!array_key_exists($canal, ConsultationFournisseur::CANAUX)) {
                View::flash('erreur', "Choisissez d'abord le canal utilisé (WhatsApp, e-mail ou autre).");
            } else {
                ConsultationPartage::marquerEnvoye((int) $partage['id']);
                ConsultationFournisseur::marquerEnvoyee((int) $consultation['id'], $canal);
                AuditLog::log((int) $consultation['filiale_id'], (int) $user['id'], 'partage_marque_envoye', 'consultation_fournisseur', (int) $consultation['id'], $canal);
                View::flash('succes', 'Consultation marquée comme envoyée (' . ConsultationFournisseur::CANAUX[$canal] . ').');
            }
        }
        header('Location: /index.php?r=consultations/' . $consultation['id'] . '/partages/' . $params['partageId']);
        exit;
    }

    public function revoquerPartage(array $params): void
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
        $partage = ConsultationPartage::find((int) $params['partageId']);
        if ($partage && (int) $partage['consultation_id'] === (int) $consultation['id']) {
            ConsultationPartage::revoquer((int) $partage['id']);
            AuditLog::log((int) $consultation['filiale_id'], (int) $user['id'], 'partage_revoque', 'consultation_fournisseur', (int) $consultation['id']);
            View::flash('succes', 'Lien révoqué : il n\'est plus accessible.');
        }
        header('Location: /index.php?r=consultations/' . $consultation['id']);
        exit;
    }

    private static function urlPublique(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . '/index.php?r=partage-public/' . $token;
    }
}

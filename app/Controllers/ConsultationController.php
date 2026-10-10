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
        View::render('consultations/create', [
            'dossier' => $dossier,
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
        $donnees['notes'] = trim(($noteSource !== '' ? $noteSource . "\n" : '') . trim($_POST['notes'] ?? ''));
        $dernier = 0;
        foreach ($fournisseurIds as $fid) {
            $donnees['fournisseur_id'] = $fid;
            $dernier = ConsultationFournisseur::create((int) $dossier['id'], $filialeId, $donnees, (int) $user['id']);
            AuditLog::log($filialeId, (int) $user['id'], 'creation_consultation', 'consultation_fournisseur', $dernier);
        }

        $n = count($fournisseurIds);
        View::flash('succes', $n > 1 ? $n . ' consultations enregistrées.' : 'Consultation enregistrée.');
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
            $_POST['date_relance'] ?? null
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

        View::render('consultations/partage_lien', [
            'consultation' => $consultation,
            'partage' => $partage,
            'lienPublic' => $lienPublic,
            'lienWhatsapp' => $telephone !== '' ? ('https://wa.me/' . $telephone . '?text=' . rawurlencode($message)) : null,
            'messagePrepare' => $message,
        ]);
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
            ConsultationPartage::marquerEnvoye((int) $partage['id']);
            AuditLog::log((int) $consultation['filiale_id'], (int) $user['id'], 'partage_marque_envoye', 'consultation_fournisseur', (int) $consultation['id']);
            View::flash('succes', 'Marqué comme envoyé.');
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

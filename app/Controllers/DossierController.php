<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Storage;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Commande;
use App\Models\ConsultationFournisseur;
use App\Models\Cotation;
use App\Models\Demande;
use App\Models\DemandeArticle;
use App\Models\Dossier;
use App\Models\DossierCollaborateur;
use App\Models\DossierPieceJointe;
use App\Models\Facture;
use App\Models\Filiale;
use App\Models\Fournisseur;
use App\Models\Notification;
use App\Models\Offre;
use App\Models\Utilisateur;

class DossierController
{
    public function index(): void
    {
        $user = Auth::user();
        $filters = [
            'statut' => $_GET['statut'] ?? null,
            'etape' => $_GET['etape'] ?? null,
            'responsable_id' => $_GET['responsable_id'] ?? null,
            'recherche' => $_GET['q'] ?? null,
            'date_debut' => $_GET['date_debut'] ?? null,
            'date_fin' => $_GET['date_fin'] ?? null,
        ];
        $dossiers = Dossier::visibleFor($user, $filters);
        $utilisateurs = Utilisateur::allForOrganisation((int) $user['organisation_id']);
        View::render('folders/index', [
            'dossiers' => $dossiers,
            'filters' => $filters,
            'utilisateurs' => $utilisateurs,
        ]);
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

        $consultations = ConsultationFournisseur::forDossier((int) $dossier['id']);
        $offres = Offre::forDossier((int) $dossier['id']);
        $offreRetenue = Offre::retenueForDossier((int) $dossier['id']);
        $cotation = Cotation::latestForDossier((int) $dossier['id']);
        $commande = Commande::findByDossier((int) $dossier['id']);
        $factures = Facture::forDossier((int) $dossier['id']);
        $historique = AuditLog::forDossier((int) $dossier['id']);
        $piecesJointes = DossierPieceJointe::forDossier((int) $dossier['id']);
        $collaborateurs = DossierCollaborateur::forDossier((int) $dossier['id']);
        // Candidats à l'assignation : utilisateurs actifs de l'organisation
        // ayant déjà accès à la filiale du dossier (sinon ils ne pourraient
        // pas ouvrir le dossier une fois assignés).
        $collaborateursPossibles = array_values(array_filter(
            Utilisateur::allForOrganisation((int) $user['organisation_id']),
            fn($u) => (bool) $u['actif'] && Filiale::userCanAccess($u, (int) $dossier['filiale_id'])
        ));

        View::render('folders/show', [
            'dossier' => $dossier,
            'demande' => $demande,
            'articles' => $articles,
            'filiale' => $filiale,
            'consultations' => $consultations,
            'offres' => $offres,
            'offreRetenue' => $offreRetenue,
            'cotation' => $cotation,
            'commande' => $commande,
            'factures' => $factures,
            'historique' => $historique,
            'piecesJointes' => $piecesJointes,
            'collaborateurs' => $collaborateurs,
            'collaborateursPossibles' => $collaborateursPossibles,
        ]);
    }

    public function updateEtape(array $params): void
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

        Dossier::updateEtape((int) $dossier['id'], $_POST['etape'] ?? '');
        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'maj_etape_dossier', 'dossier', (int) $dossier['id'], $_POST['etape'] ?? '');
        View::flash('succes', 'Étape mise à jour.');
        header('Location: /index.php?r=dossiers/' . $dossier['id']);
        exit;
    }

    public function addArticle(array $params): void
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

        Dossier::updateNotes((int) $dossier['id'], trim($_POST['notes'] ?? ''));
        View::flash('succes', 'Notes enregistrées.');
        header('Location: /index.php?r=dossiers/' . $dossier['id']);
        exit;
    }

    /**
     * Pièces jointes du dossier (factures pro forma, bons de commande,
     * connaissements...). Même mécanique que pour les demandes : fichier
     * stocké hors du webroot, accès uniquement via ce contrôleur.
     */
    public function uploadPiece(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }

        if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] === UPLOAD_ERR_NO_FILE) {
            View::flash('erreur', 'Aucun fichier sélectionné.');
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }

        $fichier = $_FILES['fichier'];
        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            View::flash('erreur', "Échec de l'envoi du fichier.");
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }
        if ($fichier['size'] > DossierPieceJointe::TAILLE_MAX) {
            View::flash('erreur', 'Le fichier dépasse la taille maximale autorisée (10 Mo).');
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }

        $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, DossierPieceJointe::EXTENSIONS_AUTORISEES, true)) {
            View::flash('erreur', 'Type de fichier non autorisé (formats acceptés : ' . implode(', ', DossierPieceJointe::EXTENSIONS_AUTORISEES) . ').');
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }

        $nomFichier = bin2hex(random_bytes(16)) . '.' . $extension;
        $chemin = Storage::path('uploads/dossiers/' . $dossier['id'] . '/' . $nomFichier);
        if (!move_uploaded_file($fichier['tmp_name'], $chemin)) {
            View::flash('erreur', "Impossible d'enregistrer le fichier.");
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }

        DossierPieceJointe::create([
            'dossier_id' => $dossier['id'],
            'nom_original' => $fichier['name'],
            'nom_fichier' => $nomFichier,
            'taille' => $fichier['size'],
            'type_mime' => $fichier['type'] ?: 'application/octet-stream',
            'uploaded_by' => (int) $user['id'],
        ]);
        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'ajout_piece_jointe', 'dossier', (int) $dossier['id'], $fichier['name']);

        View::flash('succes', 'Pièce jointe ajoutée.');
        header('Location: /index.php?r=dossiers/' . $dossier['id']);
        exit;
    }

    public function telechargerPiece(array $params): void
    {
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $piece = DossierPieceJointe::find((int) $params['pieceId']);
        if (!$piece || (int) $piece['dossier_id'] !== (int) $dossier['id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $chemin = Storage::path('uploads/dossiers/' . $dossier['id'] . '/' . $piece['nom_fichier']);
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
        Auth::requireWrite();
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }

        $piece = DossierPieceJointe::find((int) ($params['pieceId'] ?? 0));
        if ($piece && (int) $piece['dossier_id'] === (int) $dossier['id']) {
            $chemin = Storage::path('uploads/dossiers/' . $dossier['id'] . '/' . $piece['nom_fichier']);
            if (is_file($chemin)) {
                unlink($chemin);
            }
            DossierPieceJointe::delete($piece['id']);
            AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'suppression_piece_jointe', 'dossier', (int) $dossier['id'], $piece['nom_original']);
            View::flash('succes', 'Pièce jointe supprimée.');
        }

        header('Location: /index.php?r=dossiers/' . $dossier['id']);
        exit;
    }

    /**
     * Associe un collaborateur additionnel au dossier, dédié à un
     * fournisseur précis (déjà consulté sur ce dossier) — pour accélérer la
     * collecte des devis sur les demandes urgentes. Notifie la personne
     * assignée (cloche + email best-effort).
     */
    public function assignerCollaborateur(array $params): void
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

        $fournisseurId = (int) ($_POST['fournisseur_id'] ?? 0);
        $utilisateurId = (int) ($_POST['utilisateur_id'] ?? 0);

        $fournisseursDuDossier = array_map(
            fn($c) => (int) $c['fournisseur_id'],
            ConsultationFournisseur::forDossier((int) $dossier['id'])
        );
        if ($fournisseurId <= 0 || !in_array($fournisseurId, $fournisseursDuDossier, true)) {
            View::flash('erreur', 'Fournisseur invalide : il doit avoir déjà été consulté sur ce dossier.');
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }

        $collaborateur = Utilisateur::find($utilisateurId);
        if (
            !$collaborateur
            || (int) $collaborateur['organisation_id'] !== (int) $user['organisation_id']
            || !$collaborateur['actif']
        ) {
            View::flash('erreur', 'Utilisateur invalide.');
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }
        if (!Filiale::userCanAccess($collaborateur, (int) $dossier['filiale_id'])) {
            View::flash('erreur', "Cette personne n'a pas accès à la filiale de ce dossier — donnez-lui d'abord accès depuis Paramètres > Utilisateurs.");
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }

        DossierCollaborateur::assigner(
            (int) $dossier['id'],
            (int) $dossier['filiale_id'],
            $fournisseurId,
            $utilisateurId,
            (int) $user['id']
        );
        AuditLog::log(
            (int) $dossier['filiale_id'],
            (int) $user['id'],
            'collaborateur_assigne',
            'dossier',
            (int) $dossier['id'],
            $collaborateur['nom']
        );

        $fournisseur = Fournisseur::find($fournisseurId);
        Notification::notifier(
            $collaborateur,
            (int) $dossier['filiale_id'],
            'collaborateur_assigne',
            'Nouveau dossier à suivre',
            'Vous avez été ajouté au suivi du dossier ' . $dossier['reference'] . ' (' . $dossier['objet'] . ')'
                . ' pour le fournisseur ' . ($fournisseur['nom'] ?? '—') . '.',
            '/index.php?r=dossiers/' . $dossier['id'],
            'dossier',
            (int) $dossier['id']
        );

        View::flash('succes', 'Collaborateur assigné et notifié.');
        header('Location: /index.php?r=dossiers/' . $dossier['id']);
        exit;
    }

    public function retirerCollaborateur(array $params): void
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

        $collaboration = DossierCollaborateur::find((int) ($params['collaborateurId'] ?? 0));
        if ($collaboration && (int) $collaboration['dossier_id'] === (int) $dossier['id']) {
            DossierCollaborateur::retirer((int) $collaboration['id']);
            AuditLog::log(
                (int) $dossier['filiale_id'],
                (int) $user['id'],
                'collaborateur_retire',
                'dossier',
                (int) $dossier['id'],
                Utilisateur::nameOf((int) $collaboration['utilisateur_id'])
            );
            View::flash('succes', 'Collaborateur retiré du dossier.');
        }

        header('Location: /index.php?r=dossiers/' . $dossier['id']);
        exit;
    }
}

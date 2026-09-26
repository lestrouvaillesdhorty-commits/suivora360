<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Storage;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\ConsultationFournisseur;
use App\Models\Filiale;
use App\Models\Fournisseur;
use App\Models\FournisseurPieceJointe;

class FournisseurController
{
    public function index(): void
    {
        $user = Auth::user();
        $fournisseurs = Fournisseur::visibleFor($user);
        View::render('fournisseurs/index', ['fournisseurs' => $fournisseurs]);
    }

    public function create(): void
    {
        $user = Auth::user();
        $filiales = Filiale::visibleFor($user);
        View::render('fournisseurs/create', ['filiales' => $filiales]);
    }

    public function store(): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=fournisseurs/nouveau');
            exit;
        }

        $user = Auth::user();
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);

        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            View::flash('erreur', "Vous n'avez pas accès à cette filiale.");
            header('Location: /index.php?r=fournisseurs/nouveau');
            exit;
        }
        if (trim($_POST['nom'] ?? '') === '') {
            View::flash('erreur', 'Le nom du fournisseur est obligatoire.');
            header('Location: /index.php?r=fournisseurs/nouveau');
            exit;
        }

        $fournisseurId = Fournisseur::create([
            'filiale_id' => $filialeId,
            'nom' => trim($_POST['nom']),
            'statut' => $_POST['statut'] ?? 'a_qualifier',
            'email' => trim($_POST['email'] ?? ''),
            'telephone' => trim($_POST['telephone'] ?? ''),
            'pays' => trim($_POST['pays'] ?? ''),
            'ville' => trim($_POST['ville'] ?? ''),
            'adresse' => trim($_POST['adresse'] ?? ''),
            'devise' => trim($_POST['devise'] ?? ''),
            'secteur' => trim($_POST['secteur'] ?? ''),
            'site_web' => trim($_POST['site_web'] ?? ''),
            'categories_produits' => trim($_POST['categories_produits'] ?? ''),
            'marques' => trim($_POST['marques'] ?? ''),
            'pays_desservis' => trim($_POST['pays_desservis'] ?? ''),
            'incoterms_pratiques' => isset($_POST['incoterms_pratiques']) ? implode(',', (array) $_POST['incoterms_pratiques']) : '',
            'quantite_min' => trim($_POST['quantite_min'] ?? ''),
            'fonction_contact' => trim($_POST['fonction_contact'] ?? ''),
            'note_prix' => $_POST['note_prix'] ?? null,
            'note_qualite' => $_POST['note_qualite'] ?? null,
            'note_delai' => $_POST['note_delai'] ?? null,
            'note_reactivite' => $_POST['note_reactivite'] ?? null,
            'note_conformite' => $_POST['note_conformite'] ?? null,
            'note_engagements' => $_POST['note_engagements'] ?? null,
            'notes' => trim($_POST['notes'] ?? ''),
        ]);

        AuditLog::log($filialeId, (int) $user['id'], 'creation_fournisseur', 'fournisseur', $fournisseurId);
        View::flash('succes', 'Fournisseur créé avec succès.');
        header('Location: /index.php?r=fournisseurs/' . $fournisseurId);
        exit;
    }

    public function show(array $params): void
    {
        $user = Auth::user();
        $fournisseur = Fournisseur::find((int) $params['id']);
        if (!$fournisseur || !Fournisseur::userCanAccess($user, $fournisseur)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        View::render('fournisseurs/show', [
            'fournisseur' => $fournisseur,
            'piecesJointes' => FournisseurPieceJointe::forFournisseur((int) $fournisseur['id']),
            'historique' => ConsultationFournisseur::forFournisseur((int) $fournisseur['id']),
        ]);
    }

    public function uploadPiece(array $params): void
    {
        $user = Auth::user();
        $fournisseur = Fournisseur::find((int) $params['id']);
        if (!$fournisseur || !Fournisseur::userCanAccess($user, $fournisseur)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=fournisseurs/' . $fournisseur['id']);
            exit;
        }

        if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] === UPLOAD_ERR_NO_FILE) {
            View::flash('erreur', 'Aucun fichier sélectionné.');
            header('Location: /index.php?r=fournisseurs/' . $fournisseur['id']);
            exit;
        }

        $fichier = $_FILES['fichier'];
        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            View::flash('erreur', "Échec de l'envoi du fichier.");
            header('Location: /index.php?r=fournisseurs/' . $fournisseur['id']);
            exit;
        }
        if ($fichier['size'] > FournisseurPieceJointe::TAILLE_MAX) {
            View::flash('erreur', 'Le fichier dépasse la taille maximale autorisée (10 Mo).');
            header('Location: /index.php?r=fournisseurs/' . $fournisseur['id']);
            exit;
        }

        $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, FournisseurPieceJointe::EXTENSIONS_AUTORISEES, true)) {
            View::flash('erreur', 'Type de fichier non autorisé (formats acceptés : ' . implode(', ', FournisseurPieceJointe::EXTENSIONS_AUTORISEES) . ').');
            header('Location: /index.php?r=fournisseurs/' . $fournisseur['id']);
            exit;
        }

        $categorie = $_POST['categorie'] ?? 'autre';
        if (!array_key_exists($categorie, FournisseurPieceJointe::CATEGORIES)) {
            $categorie = 'autre';
        }

        $nomFichier = bin2hex(random_bytes(16)) . '.' . $extension;
        $chemin = Storage::path('uploads/fournisseurs/' . $fournisseur['id'] . '/' . $nomFichier);
        if (!move_uploaded_file($fichier['tmp_name'], $chemin)) {
            View::flash('erreur', "Impossible d'enregistrer le fichier.");
            header('Location: /index.php?r=fournisseurs/' . $fournisseur['id']);
            exit;
        }

        FournisseurPieceJointe::create([
            'fournisseur_id' => $fournisseur['id'],
            'categorie' => $categorie,
            'nom_original' => $fichier['name'],
            'nom_fichier' => $nomFichier,
            'taille' => $fichier['size'],
            'type_mime' => $fichier['type'] ?: 'application/octet-stream',
            'uploaded_by' => (int) $user['id'],
        ]);
        AuditLog::log((int) $fournisseur['filiale_id'], (int) $user['id'], 'ajout_piece_jointe', 'fournisseur', (int) $fournisseur['id'], $fichier['name']);

        View::flash('succes', 'Pièce jointe ajoutée.');
        header('Location: /index.php?r=fournisseurs/' . $fournisseur['id']);
        exit;
    }

    public function telechargerPiece(array $params): void
    {
        $user = Auth::user();
        $fournisseur = Fournisseur::find((int) $params['id']);
        if (!$fournisseur || !Fournisseur::userCanAccess($user, $fournisseur)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $piece = FournisseurPieceJointe::find((int) $params['pieceId']);
        if (!$piece || (int) $piece['fournisseur_id'] !== (int) $fournisseur['id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $chemin = Storage::path('uploads/fournisseurs/' . $fournisseur['id'] . '/' . $piece['nom_fichier']);
        if (!is_file($chemin)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        header('Content-Type: ' . $piece['type_mime']);
        header('Content-Disposition: attachment; filename="' . basename($piece['nom_original']) . '"');
        header('Content-Length: ' . filesize($chemin));
        readfile($chemin);
        exit;
    }

    public function supprimerPiece(array $params): void
    {
        $user = Auth::user();
        $fournisseur = Fournisseur::find((int) $params['id']);
        if (!$fournisseur || !Fournisseur::userCanAccess($user, $fournisseur)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=fournisseurs/' . $fournisseur['id']);
            exit;
        }

        $piece = FournisseurPieceJointe::find((int) ($params['pieceId'] ?? 0));
        if ($piece && (int) $piece['fournisseur_id'] === (int) $fournisseur['id']) {
            $chemin = Storage::path('uploads/fournisseurs/' . $fournisseur['id'] . '/' . $piece['nom_fichier']);
            if (is_file($chemin)) {
                unlink($chemin);
            }
            FournisseurPieceJointe::delete($piece['id']);
            AuditLog::log((int) $fournisseur['filiale_id'], (int) $user['id'], 'suppression_piece_jointe', 'fournisseur', (int) $fournisseur['id'], $piece['nom_original']);
            View::flash('succes', 'Pièce jointe supprimée.');
        }

        header('Location: /index.php?r=fournisseurs/' . $fournisseur['id']);
        exit;
    }

    public function edit(array $params): void
    {
        $user = Auth::user();
        $fournisseur = Fournisseur::find((int) $params['id']);
        if (!$fournisseur || !Fournisseur::userCanAccess($user, $fournisseur)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        View::render('fournisseurs/edit', ['fournisseur' => $fournisseur]);
    }

    public function update(array $params): void
    {
        $user = Auth::user();
        $fournisseur = Fournisseur::find((int) $params['id']);
        if (!$fournisseur || !Fournisseur::userCanAccess($user, $fournisseur)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=fournisseurs/' . $fournisseur['id'] . '/modifier');
            exit;
        }
        if (trim($_POST['nom'] ?? '') === '') {
            View::flash('erreur', 'Le nom du fournisseur est obligatoire.');
            header('Location: /index.php?r=fournisseurs/' . $fournisseur['id'] . '/modifier');
            exit;
        }

        Fournisseur::update((int) $fournisseur['id'], [
            'nom' => trim($_POST['nom']),
            'statut' => $_POST['statut'] ?? 'a_qualifier',
            'email' => trim($_POST['email'] ?? ''),
            'telephone' => trim($_POST['telephone'] ?? ''),
            'pays' => trim($_POST['pays'] ?? ''),
            'ville' => trim($_POST['ville'] ?? ''),
            'adresse' => trim($_POST['adresse'] ?? ''),
            'devise' => trim($_POST['devise'] ?? ''),
            'secteur' => trim($_POST['secteur'] ?? ''),
            'site_web' => trim($_POST['site_web'] ?? ''),
            'categories_produits' => trim($_POST['categories_produits'] ?? ''),
            'marques' => trim($_POST['marques'] ?? ''),
            'pays_desservis' => trim($_POST['pays_desservis'] ?? ''),
            'incoterms_pratiques' => isset($_POST['incoterms_pratiques']) ? implode(',', (array) $_POST['incoterms_pratiques']) : '',
            'quantite_min' => trim($_POST['quantite_min'] ?? ''),
            'fonction_contact' => trim($_POST['fonction_contact'] ?? ''),
            'note_prix' => $_POST['note_prix'] ?? null,
            'note_qualite' => $_POST['note_qualite'] ?? null,
            'note_delai' => $_POST['note_delai'] ?? null,
            'note_reactivite' => $_POST['note_reactivite'] ?? null,
            'note_conformite' => $_POST['note_conformite'] ?? null,
            'note_engagements' => $_POST['note_engagements'] ?? null,
            'notes' => trim($_POST['notes'] ?? ''),
        ]);

        AuditLog::log((int) $fournisseur['filiale_id'], (int) $user['id'], 'modification_fournisseur', 'fournisseur', (int) $fournisseur['id']);
        View::flash('succes', 'Fournisseur mis à jour.');
        header('Location: /index.php?r=fournisseurs/' . $fournisseur['id']);
        exit;
    }

    public function desactiver(array $params): void
    {
        $user = Auth::user();
        $fournisseur = Fournisseur::find((int) $params['id']);
        if (!$fournisseur || !Fournisseur::userCanAccess($user, $fournisseur)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=fournisseurs');
            exit;
        }
        Fournisseur::setActive((int) $fournisseur['id'], false);
        AuditLog::log((int) $fournisseur['filiale_id'], (int) $user['id'], 'desactivation_fournisseur', 'fournisseur', (int) $fournisseur['id']);
        View::flash('succes', 'Fournisseur désactivé. Il reste visible et peut être réactivé à tout moment.');
        header('Location: /index.php?r=fournisseurs');
        exit;
    }

    public function activer(array $params): void
    {
        $user = Auth::user();
        $fournisseur = Fournisseur::find((int) $params['id']);
        if (!$fournisseur || !Fournisseur::userCanAccess($user, $fournisseur)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=fournisseurs');
            exit;
        }
        Fournisseur::setActive((int) $fournisseur['id'], true);
        AuditLog::log((int) $fournisseur['filiale_id'], (int) $user['id'], 'reactivation_fournisseur', 'fournisseur', (int) $fournisseur['id']);
        View::flash('succes', 'Fournisseur réactivé.');
        header('Location: /index.php?r=fournisseurs');
        exit;
    }
}

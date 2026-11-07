<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Commande;
use App\Models\Cotation;
use App\Models\Dossier;
use App\Models\Facture;

class FactureController
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

        $commande = Commande::findByDossier((int) $dossier['id']);
        // [ajouté 06/10, étape 3] Les versions remplacées partagent la même
        // référence que leur version courante : on ne propose que les actives.
        $cotations = array_values(array_filter(
            Cotation::forDossier((int) $dossier['id']),
            fn($c) => $c['statut'] !== 'remplacee'
        ));

        View::render('factures/create', [
            'dossier' => $dossier,
            'commande' => $commande,
            'cotations' => $cotations,
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

        // Cotation et commande liées : doivent appartenir à ce dossier (isolation entre entreprises).
        $_POST['cotation_id'] = \App\Core\Tenant::cotationDuDossier($_POST['cotation_id'] ?? 0, (int) $dossier['id']);
        $_POST['commande_id'] = \App\Core\Tenant::commandeDuDossier($_POST['commande_id'] ?? 0, (int) $dossier['id']);
        $_POST['fichier'] = $_POST['fichier_nom'] = '';
        $f = $_FILES['fichier'] ?? null;
        if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true) || $f['size'] > 10 * 1024 * 1024) {
                View::flash('erreur', 'Pièce jointe refusée : PDF, JPG ou PNG de 10 Mo maximum.');
                header('Location: /index.php?r=dossiers/' . $dossier['id'] . '/factures/nouvelle');
                exit;
            }
            $nom = 'facture-' . (int) $dossier['id'] . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], \App\Core\Storage::path('uploads/factures/' . $nom))) {
                $_POST['fichier'] = $nom;
                $_POST['fichier_nom'] = basename((string) $f['name']);
            }
        }
        $factureId = Facture::create((int) $dossier['id'], (int) $dossier['filiale_id'], $_POST);
        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'creation_facture', 'facture', $factureId);
        View::flash('succes', 'Facture enregistrée.');

        $commande = Commande::findByDossier((int) $dossier['id']);
        $redirect = $commande ? '/index.php?r=dossiers/' . $dossier['id'] . '/commande' : '/index.php?r=dossiers/' . $dossier['id'];
        header('Location: ' . $redirect);
        exit;
    }

    /** Télécharge la pièce jointe d'une facture (accès réservé aux personnes qui voient le dossier). */
    public function fichier(array $params): void
    {
        $facture = Facture::find((int) $params['id']);
        if (!$facture || !Facture::userCanAccess(Auth::user(), $facture) || empty($facture['fichier'])) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $chemin = \App\Core\Storage::path('uploads/factures/' . basename((string) $facture['fichier']));
        if (!is_file($chemin)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $ext = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
        header('Content-Type: ' . (['pdf' => 'application/pdf', 'png' => 'image/png'][$ext] ?? 'image/jpeg'));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', basename((string) ($facture['fichier_nom'] ?: $facture['fichier']))) . '"');
        header('Content-Length: ' . filesize($chemin));
        readfile($chemin);
        exit;
    }

    public function updateStatut(array $params): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=dossiers');
            exit;
        }

        $user = Auth::user();
        $facture = Facture::find((int) $params['id']);
        if (!$facture || !Facture::userCanAccess($user, $facture)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        Facture::updateStatut((int) $facture['id'], $_POST['statut'] ?? '');
        AuditLog::log((int) $facture['filiale_id'], (int) $user['id'], 'changement_statut_facture', 'facture', $facture['id'], $_POST['statut'] ?? '');
        View::flash('succes', 'Statut de la facture mis à jour.');

        $commande = Commande::findByDossier((int) $facture['dossier_id']);
        $redirect = $commande ? '/index.php?r=dossiers/' . $facture['dossier_id'] . '/commande' : '/index.php?r=dossiers/' . $facture['dossier_id'];
        header('Location: ' . $redirect);
        exit;
    }
}

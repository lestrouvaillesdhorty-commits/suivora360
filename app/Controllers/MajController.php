<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Services\MiseAJour;

/** Mise à jour de l'application par dépôt d'un zip — réservée à l'administrateur Suivora. */
class MajController
{
    public function index(): void
    {
        Auth::requireSuperAdmin();
        View::render('maj/index', [
            'csrfToken' => Auth::csrfToken(),
            'sauvegardes' => MiseAJour::sauvegardes(),
            'resultat' => null,
        ]);
    }

    public function installer(): void
    {
        Auth::requireSuperAdmin();
        $resultat = null;
        $f = $_FILES['zip'] ?? null;
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $resultat = ['ok' => false, 'lignes' => [], 'erreur' => 'Session expirée, rechargez la page.'];
        } elseif (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $resultat = ['ok' => false, 'lignes' => [], 'erreur' => 'Aucun fichier reçu (ou trop gros pour le serveur : limite ' . ini_get('upload_max_filesize') . ').'];
        } elseif (strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION)) !== 'zip') {
            $resultat = ['ok' => false, 'lignes' => [], 'erreur' => 'Choisissez le fichier .zip de la mise à jour.'];
        } else {
            @set_time_limit(300);
            $resultat = MiseAJour::installer($f['tmp_name']);
            try {
                $u = Auth::user();
                \App\Models\AuditLog::log((int) ($u['filiale_id'] ?? 0), (int) $u['id'], 'mise_a_jour_application', 'application', 0, mb_substr((string) $f['name'] . ' : ' . implode(' ', $resultat['lignes']), 0, 500));
            } catch (\Throwable $e) {
            }
        }
        View::render('maj/index', ['csrfToken' => Auth::csrfToken(), 'sauvegardes' => MiseAJour::sauvegardes(), 'resultat' => $resultat]);
    }
}

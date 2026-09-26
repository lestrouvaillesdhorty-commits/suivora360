<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Dossier;
use App\Models\Offre;
use App\Models\OffreItem;

class ComparateurController
{
    public function index(array $params): void
    {
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $offres = Offre::forDossier((int) $dossier['id']);
        $itemsByOffre = [];
        foreach ($offres as $offre) {
            $itemsByOffre[$offre['id']] = OffreItem::forOffre((int) $offre['id']);
        }

        View::render('comparateur/index', [
            'dossier' => $dossier,
            'offres' => $offres,
            'itemsByOffre' => $itemsByOffre,
        ]);
    }

    public function retenir(array $params): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=dossiers/' . $params['id'] . '/comparateur');
            exit;
        }

        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $offreId = (int) ($_POST['offre_id'] ?? 0);
        if ($offreId > 0) {
            Offre::retenir((int) $dossier['id'], $offreId);
            AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'offre_retenue', 'offre', $offreId);
            View::flash('succes', 'Offre retenue pour ce dossier.');
        }

        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '/comparateur');
        exit;
    }
}

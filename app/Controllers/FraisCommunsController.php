<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Dossier;
use App\Models\DossierFraisCommuns;

/** Frais communs d'un dossier (expédition finale unique, etc.) — Achats / Propriétaire. */
class FraisCommunsController
{
    private function charger(array $params): ?array
    {
        $retour = '/index.php?r=dossiers/' . (int) $params['id'] . '&onglet=achats&sous=offres';
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: ' . $retour);
            exit;
        }
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return null;
        }
        Dossier::refuserSiAnnule($dossier);
        if (!Auth::canValiderOffres() && !Auth::canGererCotations()) {
            http_response_code(403);
            View::render('errors/403');
            return null;
        }
        if (!DossierFraisCommuns::disponible()) {
            View::flash('erreur', 'Lancez d\'abord la migration v43.');
            header('Location: ' . $retour);
            exit;
        }
        return $dossier;
    }

    public function ajouter(array $params): void
    {
        $dossier = $this->charger($params);
        if (!$dossier) {
            return;
        }
        $montant = (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['montant'] ?? 0));
        if ($montant <= 0) {
            View::flash('erreur', 'Indiquez un montant supérieur à zéro.');
        } else {
            DossierFraisCommuns::ajouter((int) $dossier['id'], (int) $dossier['filiale_id'], (string) ($_POST['libelle'] ?? ''), $montant, strtoupper(trim((string) ($_POST['devise'] ?? 'EUR'))));
            AuditLog::log((int) $dossier['filiale_id'], (int) Auth::user()['id'], 'frais_commun_ajoute', 'dossier', (int) $dossier['id'], (string) ($_POST['libelle'] ?? '') . ' ' . $montant);
            View::flash('succes', 'Frais commun ajouté : il sera inclus dans le coût d\'achat de la cotation.');
        }
        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '&onglet=achats&sous=offres');
        exit;
    }

    public function supprimer(array $params): void
    {
        $dossier = $this->charger($params);
        if (!$dossier) {
            return;
        }
        DossierFraisCommuns::supprimer((int) $dossier['id'], (int) $params['fid']);
        View::flash('succes', 'Frais commun supprimé.');
        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '&onglet=achats&sous=offres');
        exit;
    }
}

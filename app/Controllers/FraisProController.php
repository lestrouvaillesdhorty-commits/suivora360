<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Dossier;
use App\Models\DossierFraisPro;
use App\Models\Utilisateur;

/** Frais professionnels d'un dossier — réservé à la direction (Propriétaire / Admin / Finance). */
class FraisProController
{
    private function charger(array $params): ?array
    {
        $retour = '/index.php?r=dossiers/' . (int) $params['id'] . '&onglet=equipe';
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: ' . $retour);
            exit;
        }
        Auth::requirePilotage();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess(Auth::user(), $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return null;
        }
        if (!DossierFraisPro::disponible()) {
            View::flash('erreur', 'Lancez d\'abord la migration v48.');
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
            $uid = (int) ($_POST['utilisateur_id'] ?? 0);
            $user = Auth::user();
            $valide = $uid > 0 && in_array($uid, array_map(fn($u) => (int) $u['id'], Utilisateur::allForOrganisation((int) $user['organisation_id'])), true);
            DossierFraisPro::ajouter((int) $dossier['id'], (int) $dossier['filiale_id'], $valide ? $uid : null, (string) ($_POST['libelle'] ?? ''), $montant, strtoupper(trim((string) ($_POST['devise'] ?? 'EUR'))), (string) ($_POST['date_frais'] ?? ''));
            AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'frais_pro_ajoute', 'dossier', (int) $dossier['id'], (string) ($_POST['libelle'] ?? '') . ' ' . $montant);
            View::flash('succes', 'Frais ajouté : il compte dans les coûts du collaborateur.');
        }
        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '&onglet=equipe');
        exit;
    }

    public function supprimer(array $params): void
    {
        $dossier = $this->charger($params);
        if (!$dossier) {
            return;
        }
        DossierFraisPro::supprimer((int) $dossier['id'], (int) $params['fid']);
        View::flash('succes', 'Frais supprimé.');
        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '&onglet=equipe');
        exit;
    }
}

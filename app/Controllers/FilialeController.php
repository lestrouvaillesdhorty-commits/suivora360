<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Filiale;

/**
 * Gestion des filiales — réservée aux rôles Propriétaire et Admin d'organisation.
 */
class FilialeController
{
    private const MAX_FILIALES = 5;

    public function index(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();
        $filiales = Filiale::allForOrganisation((int) $user['organisation_id']);
        View::render('branches/index', [
            'filiales' => $filiales,
            'maxAtteint' => count($filiales) >= self::MAX_FILIALES,
        ]);
    }

    public function store(): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=filiales');
            exit;
        }

        $user = Auth::user();
        $count = Filiale::countForOrganisation((int) $user['organisation_id']);

        if ($count >= self::MAX_FILIALES) {
            View::flash('erreur', 'Nombre maximum de filiales atteint (' . self::MAX_FILIALES . ').');
            header('Location: /index.php?r=filiales');
            exit;
        }

        $nom = trim($_POST['nom'] ?? '');
        if ($nom === '') {
            View::flash('erreur', 'Le nom de la filiale est obligatoire.');
            header('Location: /index.php?r=filiales');
            exit;
        }

        $nouvelleId = Filiale::create((int) $user['organisation_id'], $nom);
        AuditLog::log((int) $nouvelleId, (int) $user['id'], 'creation_filiale', 'filiale', (int) $nouvelleId, $nom);
        View::flash('succes', 'Filiale créée.');
        header('Location: /index.php?r=filiales');
        exit;
    }

    /**
     * [ajouté 03/10, demande explicite de Marie Laure] Renommer une filiale
     * existante.
     */
    public function renommer(array $params): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=filiales');
            exit;
        }

        $user = Auth::user();
        $filiale = Filiale::find((int) $params['id']);
        if (!$filiale || (int) $filiale['organisation_id'] !== (int) $user['organisation_id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $nom = trim($_POST['nom'] ?? '');
        if ($nom === '') {
            View::flash('erreur', 'Le nom de la filiale est obligatoire.');
            header('Location: /index.php?r=filiales');
            exit;
        }

        Filiale::rename((int) $filiale['id'], $nom);
        AuditLog::log((int) $filiale['id'], (int) $user['id'], 'renommage_filiale', 'filiale', (int) $filiale['id'], $filiale['nom'] . ' → ' . $nom);
        View::flash('succes', 'Filiale renommée.');
        header('Location: /index.php?r=filiales');
        exit;
    }

    /**
     * [ajouté 03/10, demande explicite de Marie Laure] Supprimer une
     * filiale — refusé si elle contient déjà de la donnée réelle (Demandes,
     * Clients ou Fournisseurs), pour éviter de perdre des données ou de
     * laisser des dossiers/offres/cotations/commandes orphelins ; refusé
     * aussi si c'est la dernière filiale de l'organisation (l'app suppose
     * qu'il en existe toujours au moins une).
     */
    public function supprimer(array $params): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=filiales');
            exit;
        }

        $user = Auth::user();
        $filiale = Filiale::find((int) $params['id']);
        if (!$filiale || (int) $filiale['organisation_id'] !== (int) $user['organisation_id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        if (Filiale::countForOrganisation((int) $user['organisation_id']) <= 1) {
            View::flash('erreur', "Impossible de supprimer la dernière filiale de l'organisation.");
            header('Location: /index.php?r=filiales');
            exit;
        }

        $usages = Filiale::usagesBloquants((int) $filiale['id']);
        if (!empty($usages)) {
            $detail = implode(', ', array_map(fn($label, $n) => "$n $label", array_keys($usages), $usages));
            View::flash('erreur', "Impossible de supprimer cette filiale : elle contient déjà des données ($detail).");
            header('Location: /index.php?r=filiales');
            exit;
        }

        Filiale::delete((int) $filiale['id']);
        AuditLog::logAdmin($user, 'suppression_filiale', 'filiale', (int) $filiale['id'], $filiale['nom']);
        View::flash('succes', 'Filiale supprimée.');
        header('Location: /index.php?r=filiales');
        exit;
    }
}

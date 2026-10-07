<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Cotation;
use App\Models\CotationItem;
use App\Models\Demande;
use App\Models\Dossier;
use App\Models\Offre;

class CotationController
{
    public function create(array $params): void
    {
        if (!Auth::canGererCotations()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $demande = Demande::find((int) $dossier['demande_id']);
        $clients = Client::allForFiliale((int) $dossier['filiale_id']);
        $offreRetenue = Offre::retenueForDossier((int) $dossier['id']);

        // [ajouté 06/10, étape 3] ?version_de=<cotationId> : nouvelle version
        // pré-remplie à partir de la cotation indiquée (doit appartenir au
        // même dossier et ne pas être déjà remplacée).
        $cotationPrecedente = null;
        $itemsPrecedents = [];
        $versionDe = (int) ($_GET['version_de'] ?? 0);
        if ($versionDe > 0) {
            $candidate = Cotation::find($versionDe);
            if ($candidate && (int) $candidate['dossier_id'] === (int) $dossier['id'] && $candidate['statut'] !== 'remplacee') {
                $cotationPrecedente = $candidate;
                $itemsPrecedents = CotationItem::forCotation((int) $candidate['id']);
            }
        }

        View::render('cotations/create', [
            'dossier' => $dossier,
            'demande' => $demande,
            'clients' => $clients,
            'offreRetenue' => $offreRetenue,
            'cotationPrecedente' => $cotationPrecedente,
            'itemsPrecedents' => $itemsPrecedents,
        ]);
    }

    public function store(array $params): void
    {
        if (!Auth::canGererCotations()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }
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

        // Client de la même filiale ; offre du même dossier (isolation entre entreprises).
        if (!\App\Core\Tenant::clientDeFiliale($_POST['client_id'] ?? 0, (int) $dossier['filiale_id'])) {
            View::flash('erreur', 'Veuillez sélectionner un client.');
            header('Location: /index.php?r=dossiers/' . $dossier['id'] . '/cotations/nouvelle');
            exit;
        }

        $items = [];
        $designations = $_POST['item_designation'] ?? [];
        foreach ($designations as $i => $designation) {
            $items[] = [
                'designation' => trim($designation),
                'quantite' => $_POST['item_quantite'][$i] ?? null,
                'unite' => $_POST['item_unite'][$i] ?? '',
                'prix_unitaire' => $_POST['item_prix_unitaire'][$i] ?? null,
            ];
        }

        $cotationPrecedente = null;
        $precedenteId = (int) ($_POST['cotation_precedente_id'] ?? 0);
        if ($precedenteId > 0) {
            $candidate = Cotation::find($precedenteId);
            if ($candidate && (int) $candidate['dossier_id'] === (int) $dossier['id'] && $candidate['statut'] !== 'remplacee') {
                $cotationPrecedente = $candidate;
            }
        }

        $_POST['offre_id'] = \App\Core\Tenant::offreDuDossier($_POST['offre_id'] ?? 0, (int) $dossier['id']);
        $cotationId = Cotation::create((int) $dossier['id'], (int) $dossier['filiale_id'], $_POST, $items, $cotationPrecedente);

        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], $cotationPrecedente ? 'revision_cotation' : 'creation_cotation', 'cotation', $cotationId);
        View::flash('succes', $cotationPrecedente ? 'Nouvelle version de la cotation créée.' : 'Cotation créée.');
        header('Location: /index.php?r=cotations/' . $cotationId);
        exit;
    }

    public function show(array $params): void
    {
        $user = Auth::user();
        $cotation = Cotation::findWithDetails((int) $params['id']);
        if (!$cotation || !Cotation::userCanAccess($user, $cotation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $items = CotationItem::forCotation((int) $cotation['id']);
        View::render('cotations/show', [
            'cotation' => $cotation,
            'items' => $items,
        ]);
    }

    public function updateStatut(array $params): void
    {
        if (!Auth::canGererCotations()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=cotations/' . $params['id']);
            exit;
        }

        $user = Auth::user();
        $cotation = Cotation::find((int) $params['id']);
        if (!$cotation || !Cotation::userCanAccess($user, $cotation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        // [ajouté 06/10, étape 3] Une version remplacée est figée ("conservée
        // pour historique, non modifiable" — maquette) ; "remplacee" n'est
        // jamais choisi à la main.
        $nouveauStatut = $_POST['statut'] ?? '';
        if ($cotation['statut'] === 'remplacee' || !array_key_exists($nouveauStatut, Cotation::statutsManuels())) {
            View::flash('erreur', 'Statut non modifiable pour cette cotation.');
            header('Location: /index.php?r=cotations/' . $cotation['id']);
            exit;
        }

        Cotation::updateStatut((int) $cotation['id'], $nouveauStatut);
        AuditLog::log((int) $cotation['filiale_id'], (int) $user['id'], 'changement_statut_cotation', 'cotation', $cotation['id'], $_POST['statut'] ?? '');
        View::flash('succes', 'Statut de la cotation mis à jour.');
        // [ajouté 06/10, étape 3] Les boutons de l'onglet Cotations client
        // renvoient ici avec retour=dossier pour revenir sur l'onglet.
        if (($_POST['retour'] ?? '') === 'dossier') {
            header('Location: /index.php?r=dossiers/' . $cotation['dossier_id'] . '&onglet=cotations');
        } else {
            header('Location: /index.php?r=cotations/' . $cotation['id']);
        }
        exit;
    }
}

<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\ConsultationFournisseur;
use App\Models\Offre;
use App\Models\OffreItem;

class OffreController
{
    public function create(array $params): void
    {
        $user = Auth::user();
        $consultation = ConsultationFournisseur::findWithDetails((int) $params['id']);
        if (!$consultation || !ConsultationFournisseur::userCanAccess($user, $consultation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        // [ajouté 06/10, étape 2 du découpage Dossiers] ?version_de=<offreId>
        // : le fournisseur a renvoyé une offre révisée sur la même
        // consultation (demande explicite de Marie Laure, 06/10) — on
        // préremplit le formulaire à partir de la version précédente plutôt
        // que de repartir d'une page vierge.
        $offrePrecedente = null;
        $itemsPrecedents = [];
        $versionDeId = (int) ($_GET['version_de'] ?? 0);
        if ($versionDeId > 0) {
            $candidate = Offre::find($versionDeId);
            if ($candidate && (int) $candidate['consultation_id'] === (int) $consultation['id']) {
                $offrePrecedente = $candidate;
                $itemsPrecedents = OffreItem::forOffre($versionDeId);
            }
        }

        View::render('offres/create', [
            'consultation' => $consultation,
            'offrePrecedente' => $offrePrecedente,
            'itemsPrecedents' => $itemsPrecedents,
        ]);
    }

    public function store(array $params): void
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

        // [ajouté 06/10, étape 2 du découpage Dossiers] Révision d'une offre
        // existante : le champ caché offre_precedente_id (posté par
        // views/offres/create.php quand on arrive via "+ Nouvelle version")
        // n'est honoré que s'il appartient bien à cette même consultation.
        $offrePrecedente = null;
        $offrePrecedenteId = (int) ($_POST['offre_precedente_id'] ?? 0);
        if ($offrePrecedenteId > 0) {
            $candidate = Offre::find($offrePrecedenteId);
            if ($candidate && (int) $candidate['consultation_id'] === (int) $consultation['id']) {
                $offrePrecedente = $candidate;
            }
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

        $offreId = Offre::create($consultation, $_POST, $items, (int) $user['id'], $offrePrecedente);

        AuditLog::log(
            (int) $consultation['filiale_id'],
            (int) $user['id'],
            $offrePrecedente ? 'revision_offre' : 'creation_offre',
            'offre',
            $offreId
        );
        View::flash('succes', $offrePrecedente ? 'Nouvelle version de l\'offre enregistrée.' : 'Offre enregistrée.');
        header('Location: /index.php?r=consultations/' . $consultation['id']);
        exit;
    }
}

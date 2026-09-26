<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\ConsultationFournisseur;
use App\Models\Dossier;
use App\Models\Fournisseur;
use App\Models\Offre;

class ConsultationController
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

        $fournisseurs = Fournisseur::allForFiliale((int) $dossier['filiale_id']);
        View::render('consultations/create', [
            'dossier' => $dossier,
            'fournisseurs' => $fournisseurs,
        ]);
    }

    public function store(array $params): void
    {
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

        if (empty($_POST['fournisseur_id'])) {
            View::flash('erreur', 'Veuillez sélectionner un fournisseur.');
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }

        $consultationId = ConsultationFournisseur::create(
            (int) $dossier['id'],
            (int) $dossier['filiale_id'],
            $_POST,
            (int) $user['id']
        );

        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'creation_consultation', 'consultation_fournisseur', $consultationId);
        View::flash('succes', 'Consultation envoyée au fournisseur.');
        header('Location: /index.php?r=dossiers/' . $dossier['id']);
        exit;
    }

    public function show(array $params): void
    {
        $user = Auth::user();
        $consultation = ConsultationFournisseur::findWithDetails((int) $params['id']);
        if (!$consultation || !ConsultationFournisseur::userCanAccess($user, $consultation)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $offres = Offre::forConsultation((int) $consultation['id']);
        View::render('consultations/show', [
            'consultation' => $consultation,
            'offres' => $offres,
        ]);
    }

    public function updateStatut(array $params): void
    {
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

        ConsultationFournisseur::updateStatut(
            (int) $consultation['id'],
            $_POST['statut'] ?? '',
            $_POST['date_relance'] ?? null
        );
        View::flash('succes', 'Statut de la consultation mis à jour.');
        header('Location: /index.php?r=consultations/' . $consultation['id']);
        exit;
    }
}

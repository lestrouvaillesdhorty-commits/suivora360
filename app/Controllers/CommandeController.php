<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Commande;
use App\Models\Cotation;
use App\Models\Dossier;
use App\Models\Facture;

class CommandeController
{
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

        $cotationId = (int) ($_POST['cotation_id'] ?? 0);
        $cotation = $cotationId ? Cotation::find($cotationId) : null;
        if (!$cotation || (int) $cotation['dossier_id'] !== (int) $dossier['id']) {
            View::flash('erreur', 'Veuillez sélectionner une cotation valide pour ce dossier.');
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }
        if ($cotation['statut'] !== 'acceptee') {
            View::flash('erreur', 'Une commande ne peut être créée qu\'à partir d\'une cotation acceptée.');
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }

        $commandeId = Commande::create((int) $dossier['id'], (int) $dossier['filiale_id'], $cotationId);
        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'creation_commande', 'commande', $commandeId);
        View::flash('succes', 'Commande créée, suivi opérationnel démarré.');
        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '/commande');
        exit;
    }

    public function show(array $params): void
    {
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $commande = Commande::findByDossier((int) $dossier['id']);
        if (!$commande) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $steps = Commande::steps((int) $commande['id']);
        $factures = Facture::forDossier((int) $dossier['id']);

        View::render('commandes/show', [
            'dossier' => $dossier,
            'commande' => $commande,
            'steps' => $steps,
            'factures' => $factures,
            'progression' => Commande::progression($steps),
        ]);
    }

    public function updateStep(array $params): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=commandes/' . $params['id']);
            exit;
        }

        $user = Auth::user();
        $commande = Commande::find((int) $params['id']);
        if (!$commande || !Commande::userCanAccess($user, $commande)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $libelleEtape = null;
        foreach (Commande::steps((int) $commande['id']) as $stp) {
            if ((int) $stp['id'] === (int) $params['stepId']) { $libelleEtape = $stp['libelle']; }
        }
        Commande::updateStep(
            (int) $params['stepId'],
            (int) $commande['id'],
            $_POST['statut'] ?? '',
            $_POST['date_reelle'] ?? null,
            $_POST['notes'] ?? ''
        );
        AuditLog::log((int) $commande['filiale_id'], (int) $user['id'], 'maj_etape_commande', 'commande', $commande['id']);
        // [08/10] Livraison en transit / reçue : prévenir le responsable du dossier (cloche).
        try {
            $statutEtape = $_POST['statut'] ?? '';
            if ($libelleEtape === 'livraison' && in_array($statutEtape, ['en_cours', 'termine'], true)) {
                $dossierCmd = \App\Models\Dossier::find((int) $commande['dossier_id']);
                if ($dossierCmd) {
                    \App\Models\Notification::notifierTous(
                        \App\Models\Notification::destinatairesDossier($dossierCmd, [(int) $user['id']]),
                        (int) $commande['filiale_id'], 'livraison_maj',
                        $statutEtape === 'termine' ? 'Livraison reçue' : 'Livraison en transit',
                        'Commande ' . ($commande['reference'] ?? '') . ' (dossier ' . $dossierCmd['reference'] . ') : ' . ($statutEtape === 'termine' ? 'livraison reçue.' : 'la marchandise est en transit.'),
                        '/index.php?r=dossiers/' . $dossierCmd['id'] . '/commande', 'commande', (int) $commande['id']
                    );
                }
            }
        } catch (\Throwable $e) {
        }
        View::flash('succes', 'Étape mise à jour.');
        header('Location: /index.php?r=dossiers/' . $commande['dossier_id'] . '/commande');
        exit;
    }

    public function updateSuivi(array $params): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=commandes/' . $params['id']);
            exit;
        }

        $user = Auth::user();
        $commande = Commande::find((int) $params['id']);
        if (!$commande || !Commande::userCanAccess($user, $commande)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        Commande::updateSuivi((int) $commande['id'], $_POST['prochaine_action'] ?? '', $_POST['date_relance'] ?? null);
        AuditLog::log((int) $commande['filiale_id'], (int) $user['id'], 'maj_suivi_commande', 'commande', $commande['id']);
        View::flash('succes', 'Suivi de la commande mis à jour.');
        header('Location: /index.php?r=dossiers/' . $commande['dossier_id'] . '/commande');
        exit;
    }

    /**
     * Section 9 de la feuille de route : champs additionnels selon le type
     * de dossier — tracking/dates de transit (Transport/Logistique) ou
     * livrables (Prestation entreprise). Mêmes garde-fous que updateSuivi().
     */
    public function updateLogistique(array $params): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=commandes/' . $params['id']);
            exit;
        }

        $user = Auth::user();
        $commande = Commande::find((int) $params['id']);
        if (!$commande || !Commande::userCanAccess($user, $commande)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        Commande::updateLogistique(
            (int) $commande['id'],
            $_POST['tracking_numero'] ?? null,
            $_POST['date_transit_debut'] ?? null,
            $_POST['date_transit_fin'] ?? null
        );
        AuditLog::log((int) $commande['filiale_id'], (int) $user['id'], 'maj_suivi_commande', 'commande', $commande['id']);
        View::flash('succes', 'Suivi logistique mis à jour.');
        header('Location: /index.php?r=dossiers/' . $commande['dossier_id'] . '/commande');
        exit;
    }

    public function updateLivrables(array $params): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=commandes/' . $params['id']);
            exit;
        }

        $user = Auth::user();
        $commande = Commande::find((int) $params['id']);
        if (!$commande || !Commande::userCanAccess($user, $commande)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        Commande::updateLivrables((int) $commande['id'], $_POST['livrables'] ?? '');
        AuditLog::log((int) $commande['filiale_id'], (int) $user['id'], 'maj_suivi_commande', 'commande', $commande['id']);
        View::flash('succes', 'Livrables mis à jour.');
        header('Location: /index.php?r=dossiers/' . $commande['dossier_id'] . '/commande');
        exit;
    }
}

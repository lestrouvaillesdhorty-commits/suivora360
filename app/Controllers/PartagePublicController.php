<?php

namespace App\Controllers;

use App\Core\Storage;
use App\Core\View;
use App\Models\ConsultationFournisseur;
use App\Models\ConsultationPartage;
use App\Models\Demande;
use App\Models\DemandeArticle;
use App\Models\Dossier;
use App\Models\DossierPieceJointe;

/**
 * Contrôleur PUBLIC (aucune authentification) pour le parcours "Demander
 * une offre" : accessible uniquement via un token aléatoire (192 bits),
 * limité dans le temps, révocable, et qui ne donne accès qu'au
 * récapitulatif et aux pièces explicitement sélectionnées — jamais au
 * reste de l'application.
 */
class PartagePublicController
{
    public function show(array $params): void
    {
        $partage = $this->chargerPartageValide($params['token'] ?? '');
        if (!$partage) {
            $this->afficherLienInvalide();
            return;
        }

        $consultation = ConsultationFournisseur::findWithDetails((int) $partage['consultation_id']);
        $dossier = Dossier::find((int) $consultation['dossier_id']);
        $demande = $dossier ? Demande::find((int) $dossier['demande_id']) : null;
        $articles = $demande ? DemandeArticle::forDemande((int) $demande['id']) : [];

        $piecesAutorisees = ConsultationPartage::piecesIds($partage);
        $pieces = [];
        if (!empty($piecesAutorisees)) {
            foreach (DossierPieceJointe::forDossier((int) $dossier['id']) as $piece) {
                if (in_array((int) $piece['id'], $piecesAutorisees, true)) {
                    $pieces[] = $piece;
                }
            }
        }

        ConsultationPartage::enregistrerAcces((int) $partage['id']);

        View::renderPlain('partages/public', [
            'partage' => $partage,
            'consultation' => $consultation,
            'dossier' => $dossier,
            'demande' => $demande,
            'articles' => $articles,
            'pieces' => $pieces,
            'masquerClient' => (int) $partage['masquer_client'] === 1,
        ]);
    }

    public function telechargerPiece(array $params): void
    {
        $partage = $this->chargerPartageValide($params['token'] ?? '');
        if (!$partage) {
            $this->afficherLienInvalide();
            return;
        }

        $pieceId = (int) ($params['pieceId'] ?? 0);
        if (!in_array($pieceId, ConsultationPartage::piecesIds($partage), true)) {
            http_response_code(404);
            echo 'Fichier non trouvé ou non partagé.';
            return;
        }

        $consultation = ConsultationFournisseur::find((int) $partage['consultation_id']);
        $piece = DossierPieceJointe::find($pieceId);
        if (!$piece || !$consultation || (int) $piece['dossier_id'] !== (int) $consultation['dossier_id']) {
            http_response_code(404);
            echo 'Fichier non trouvé.';
            return;
        }

        $chemin = Storage::path('uploads/dossiers/' . $piece['dossier_id'] . '/' . $piece['nom_fichier']);
        if (!is_file($chemin)) {
            http_response_code(404);
            echo 'Fichier introuvable.';
            return;
        }

        header('Content-Type: ' . $piece['type_mime']);
        header('Content-Disposition: inline; filename="' . basename($piece['nom_original']) . '"');
        header('Content-Length: ' . filesize($chemin));
        readfile($chemin);
        exit;
    }

    private function chargerPartageValide(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $partage = ConsultationPartage::findByToken($token);
        if (!$partage || !ConsultationPartage::estValide($partage)) {
            return null;
        }
        return $partage;
    }

    private function afficherLienInvalide(): void
    {
        http_response_code(410);
        View::renderPlain('partages/invalide', []);
    }
}

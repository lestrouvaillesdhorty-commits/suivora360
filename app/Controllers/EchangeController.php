<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\BonCommandeFournisseur;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Echange;
use App\Models\Fournisseur;

/**
 * Côté équipe (connectée) des échanges avec le client et le fournisseur :
 * notes de suivi du dossier, réponses aux messages, choix du contact destinataire d'un lien.
 */
class EchangeController
{
    // ------------------------------------------------------------------ Notes de suivi

    public function ajouterNote(array $params): void
    {
        $dossier = $this->dossier($params);
        $retour = '/index.php?r=dossiers/' . (int) $dossier['id'] . '&onglet=suivi';
        $this->verifier($retour);
        $texte = trim((string) ($_POST['texte'] ?? ''));
        $visC = !empty($_POST['visible_client']);
        $visF = !empty($_POST['visible_fournisseur']);
        $id = Echange::ajouterNote($dossier, (int) Auth::user()['id'], $texte, $visC, $visF);
        if (!$id) {
            View::flash('erreur', 'Écrivez votre note avant de l’ajouter.');
            $this->aller($retour);
        }
        AuditLog::log((int) $dossier['filiale_id'], (int) Auth::user()['id'], 'note_suivi', 'dossier', (int) $dossier['id'], $visC ? 'visible par le client' : ($visF ? 'visible par le fournisseur' : 'privée'));
        if ($visC) {
            Echange::prevenirExterne(Echange::CLIENT, $dossier, null, 'Nouvelle information sur votre dossier ' . $dossier['reference'], mb_substr($texte, 0, 600));
        }
        View::flash('succes', $visC || $visF ? 'Note ajoutée : elle est visible sur le lien.' : 'Note ajoutée (privée).');
        $this->aller($retour);
    }

    public function modifierNote(array $params): void
    {
        $dossier = $this->dossier($params);
        $retour = '/index.php?r=dossiers/' . (int) $dossier['id'] . '&onglet=suivi';
        $this->verifier($retour);
        if (Echange::modifierNote((int) $dossier['id'], (int) ($params['nid'] ?? 0), (string) ($_POST['texte'] ?? ''), !empty($_POST['visible_client']), !empty($_POST['visible_fournisseur']))) {
            View::flash('succes', 'Note modifiée.');
        } else {
            View::flash('erreur', 'La note ne peut pas être vide.');
        }
        $this->aller($retour);
    }

    public function retirerNote(array $params): void
    {
        $dossier = $this->dossier($params);
        $retour = '/index.php?r=dossiers/' . (int) $dossier['id'] . '&onglet=suivi';
        $this->verifier($retour);
        Echange::retirerNote((int) $dossier['id'], (int) ($params['nid'] ?? 0));
        View::flash('succes', 'Note retirée.');
        $this->aller($retour);
    }

    // ------------------------------------------------------------------ Messages

    /** Réponse de l'équipe dans le dossier : fil client, ou fil d'une commande fournisseur. */
    public function repondre(array $params): void
    {
        $dossier = $this->dossier($params);
        $retour = '/index.php?r=dossiers/' . (int) $dossier['id'] . '&onglet=messages';
        $this->verifier($retour);
        $canal = ($_POST['canal'] ?? '') === Echange::FOURNISSEUR ? Echange::FOURNISSEUR : Echange::CLIENT;
        $bon = null;
        if ($canal === Echange::FOURNISSEUR) {
            $bon = BonCommandeFournisseur::find((int) ($_POST['bon_id'] ?? 0));
            if (!$bon || (int) ($bon['dossier_id'] ?? 0) !== (int) $dossier['id']) {
                $this->aller($retour);
            }
        }
        $this->enregistrerReponse($canal, $dossier, $bon, $retour);
    }

    /** Réponse depuis la fiche d'un bon de commande (fournisseur), avec ou sans dossier. */
    public function repondreBon(array $params): void
    {
        Auth::requireWrite();
        $bon = BonCommandeFournisseur::find((int) ($params['id'] ?? 0));
        if (!$bon || !BonCommandeFournisseur::userCanAccess(Auth::user(), $bon)) {
            $this->introuvable();
        }
        $retour = '/index.php?r=bons-commande/' . (int) $bon['id'] . '#messages';
        $this->verifier($retour);
        $dossier = !empty($bon['dossier_id']) ? Dossier::find((int) $bon['dossier_id']) : null;
        $dossier = $dossier ?: ['id' => 0, 'filiale_id' => (int) $bon['filiale_id'], 'responsable_id' => null, 'reference' => '', 'demande_id' => 0];
        $this->enregistrerReponse(Echange::FOURNISSEUR, $dossier, $bon, $retour);
    }

    private function enregistrerReponse(string $canal, array $dossier, ?array $bon, string $retour): void
    {
        $user = Auth::user();
        $nom = trim((string) \App\Models\Utilisateur::nameOf((int) $user['id'])) ?: 'Équipe';
        [$id, $erreur] = Echange::envoyer($canal, $dossier, $bon ? (int) $bon['id'] : null, 'interne', (int) $user['id'], $nom, (string) ($_POST['texte'] ?? ''), $_FILES['fichier'] ?? null);
        if (!$id) {
            View::flash('erreur', (string) $erreur);
            $this->aller($retour);
        }
        $ref = $bon ? $bon['reference'] : ($dossier['reference'] ?? '');
        $texte = mb_substr(trim((string) ($_POST['texte'] ?? '')), 0, 600);
        Echange::prevenirExterne($canal, $dossier, $bon, 'Réponse sur ' . ($bon ? 'la commande ' : 'votre dossier ') . $ref, $texte !== '' ? $texte : 'Un document vous a été envoyé.');
        View::flash('succes', 'Réponse envoyée.');
        $this->aller($retour);
    }

    /** Téléchargement d'une pièce jointe, côté équipe. */
    public function fichier(array $params): void
    {
        Auth::requireLogin();
        $m = Echange::message((int) ($params['mid'] ?? 0));
        if (!$m || !\App\Models\Filiale::userCanAccess(Auth::user(), (int) $m['filiale_id'])) {
            $this->introuvable();
        }
        Echange::servirFichier($m);
    }

    // ------------------------------------------------------------------ Contact destinataire d'un lien

    public function contactLienClient(array $params): void
    {
        Auth::requireWrite();
        $client = Client::find((int) ($params['id'] ?? 0));
        if (!$client || !Client::userCanAccess(Auth::user(), $client)) {
            $this->introuvable();
        }
        $retour = '/index.php?r=clients/' . (int) $client['id'] . '#espace-client';
        $this->verifier($retour);
        Echange::choisirContact(Echange::CLIENT, $client, (int) ($params['lienId'] ?? 0), (int) ($_POST['contact_id'] ?? 0));
        View::flash('succes', 'Contact destinataire modifié.');
        $this->aller($retour);
    }

    public function contactLienFournisseur(array $params): void
    {
        Auth::requireWrite();
        $f = Fournisseur::find((int) ($params['id'] ?? 0));
        if (!$f || !Fournisseur::userCanAccess(Auth::user(), $f)) {
            $this->introuvable();
        }
        $retour = '/index.php?r=fournisseurs/' . (int) $f['id'] . '#lien-fournisseur';
        $this->verifier($retour);
        Echange::choisirContact(Echange::FOURNISSEUR, $f, (int) ($params['lienId'] ?? 0), (int) ($_POST['contact_id'] ?? 0));
        View::flash('succes', 'Contact destinataire modifié.');
        $this->aller($retour);
    }

    // ------------------------------------------------------------------ Utilitaires

    private function dossier(array $params): array
    {
        Auth::requireWrite();
        $dossier = Dossier::find((int) ($params['id'] ?? 0));
        if (!$dossier || !Dossier::userCanAccess(Auth::user(), $dossier) || !Echange::pret()) {
            $this->introuvable();
        }
        return $dossier;
    }

    private function verifier(string $retour): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Page expirée : merci de réessayer.');
            $this->aller($retour);
        }
    }

    private function aller(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    private function introuvable(): void
    {
        http_response_code(404);
        View::render('errors/404');
        exit;
    }
}

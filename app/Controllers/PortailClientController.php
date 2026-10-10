<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\LimiteConnexion;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Cotation;
use App\Models\Demande;
use App\Models\Dossier;
use App\Models\Filiale;
use App\Models\Notification;
use App\Models\PortailClient;
use App\Models\Utilisateur;

/**
 * Espace client externe (07/10) — contrôleur PUBLIC : aucun compte, accès par
 * lien privé (192 bits), limité dans le temps et révocable. Lecture seule des
 * dossiers / cotations envoyées / factures du client, plus deux actions
 * encadrées : accepter ou refuser une cotation « envoyée » et encore valable,
 * et déposer une nouvelle demande (rattachée au client, qui arrive dans le
 * module Demandes).
 */
class PortailClientController
{
    private const ONGLETS = ['dossiers', 'cotations', 'factures', 'demande'];

    // ------------------------------------------------------------------
    // Page principale
    // ------------------------------------------------------------------

    public function show(array $params): void
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return;
        }
        [$lien, $client] = $ctx;

        PortailClient::enregistrerAcces((int) $lien['id']);
        $onglet = in_array($_GET['onglet'] ?? '', self::ONGLETS, true) ? $_GET['onglet'] : 'dossiers';
        $clientId = (int) $client['id'];
        $cotations = PortailClient::cotations($clientId);

        $this->rendre('portail/espace', [
            'lien' => $lien,
            'client' => $client,
            'onglet' => $onglet,
            'dossiers' => PortailClient::dossiers($clientId),
            'cotations' => $cotations,
            'factures' => PortailClient::factures($clientId),
            'nbAttente' => count(array_filter($cotations, fn($c) => PortailClient::decisionPossible($c))),
            'emetteur' => $this->nomEmetteur($client),
            'base' => '/index.php?r=espace-client/' . $lien['token'],
            'erreurs' => [],
            'old' => [],
        ]);
    }

    // ------------------------------------------------------------------
    // Cotation : détail imprimable + décision
    // ------------------------------------------------------------------

    public function cotation(array $params): void
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return;
        }
        [$lien, $client] = $ctx;
        $cotation = PortailClient::cotation((int) $client['id'], (int) ($params['id'] ?? 0));
        if (!$cotation) {
            $this->introuvable();
            return;
        }
        $this->rendre('portail/cotation', [
            'lien' => $lien,
            'client' => $client,
            'cotation' => $cotation,
            'lignes' => PortailClient::lignesCotation((int) $cotation['id']),
            'decisionPossible' => PortailClient::decisionPossible($cotation),
            'emetteur' => $this->nomEmetteur($client),
            'base' => '/index.php?r=espace-client/' . $lien['token'],
        ]);
    }

    public function decision(array $params): void
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return;
        }
        [$lien, $client] = $ctx;
        $base = '/index.php?r=espace-client/' . $lien['token'];
        $cotation = PortailClient::cotation((int) $client['id'], (int) ($params['id'] ?? 0));
        if (!$cotation) {
            $this->introuvable();
            return;
        }
        $retour = $base . '/cotations/' . (int) $cotation['id'];
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $this->flashPortail('erreur', 'Page expirée : merci de réessayer.');
            $this->aller($retour);
        }
        if (!PortailClient::decisionPossible($cotation)) {
            $this->flashPortail('erreur', 'Cette cotation ne peut plus être acceptée ou refusée en ligne (déjà traitée ou expirée). Contactez votre interlocuteur.');
            $this->aller($retour);
        }
        $choix = $_POST['decision'] ?? '';
        if (!in_array($choix, ['acceptee', 'refusee'], true)) {
            $this->aller($retour);
        }
        $commentaire = mb_substr(trim((string) ($_POST['commentaire'] ?? '')), 0, 300);

        Cotation::updateStatut((int) $cotation['id'], $choix);
        $details = $choix . ' par le client (espace client)' . ($commentaire !== '' ? ' — ' . $commentaire : '');
        $cotationComplete = Cotation::find((int) $cotation['id']);
        $filialeId = (int) ($cotationComplete['filiale_id'] ?? $client['filiale_id']);
        AuditLog::log($filialeId, null, 'decision_client_cotation', 'cotation', (int) $cotation['id'], $details);

        $dossier = Dossier::find((int) $cotation['dossier_id']);
        $titre = $choix === 'acceptee' ? 'Cotation acceptée par le client' : 'Cotation refusée par le client';
        $message = $client['nom'] . ' a ' . ($choix === 'acceptee' ? 'accepté' : 'refusé') . ' la cotation ' . $cotation['reference']
            . ($commentaire !== '' ? ' — « ' . $commentaire . ' »' : '') . '.';
        $this->notifier($client, $dossier['responsable_id'] ?? null, $filialeId, 'decision_client', $titre, $message, '/index.php?r=cotations/' . (int) $cotation['id'], 'cotation', (int) $cotation['id']);

        $this->flashPortail('succes', $choix === 'acceptee'
            ? 'Merci : votre acceptation a bien été transmise. Votre interlocuteur vous contactera pour la suite.'
            : 'Votre refus a bien été transmis. Votre interlocuteur reviendra vers vous.');
        $this->aller($retour);
    }

    // ------------------------------------------------------------------
    // Facture : vue imprimable
    // ------------------------------------------------------------------

    public function facture(array $params): void
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return;
        }
        [$lien, $client] = $ctx;
        $facture = PortailClient::facture((int) $client['id'], (int) ($params['id'] ?? 0));
        if (!$facture) {
            $this->introuvable();
            return;
        }
        $this->rendre('portail/facture', [
            'lien' => $lien,
            'client' => $client,
            'facture' => $facture,
            'emetteur' => $this->nomEmetteur($client),
            'base' => '/index.php?r=espace-client/' . $lien['token'],
        ]);
    }

    // ------------------------------------------------------------------
    // Nouvelle demande
    // ------------------------------------------------------------------

    public function nouvelleDemande(array $params): void
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return;
        }
        [$lien, $client] = $ctx;
        $base = '/index.php?r=espace-client/' . $lien['token'];
        $retour = $base . '&onglet=demande';

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $this->flashPortail('erreur', 'Page expirée : merci de réessayer.');
            $this->aller($retour);
        }
        // Piège à robots : ce champ reste vide pour une vraie personne.
        if (trim((string) ($_POST['site_web'] ?? '')) !== '') {
            $this->aller($base);
        }
        if ((int) $client['is_active'] !== 1) {
            $this->flashPortail('erreur', 'Votre compte client est actuellement inactif. Merci de contacter directement votre interlocuteur.');
            $this->aller($retour);
        }
        if (PortailClient::demandesRecentes((int) $client['id']) >= PortailClient::DEMANDES_MAX_PAR_HEURE) {
            $this->flashPortail('erreur', 'Vous avez déjà envoyé plusieurs demandes à l’instant. Merci de patienter un peu ou de contacter directement votre interlocuteur.');
            $this->aller($retour);
        }

        $objet = mb_substr(trim((string) ($_POST['objet'] ?? '')), 0, 200);
        $message = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 5000);
        $dateSouhaitee = trim((string) ($_POST['date_souhaitee'] ?? ''));
        $dateOk = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateSouhaitee) && strtotime($dateSouhaitee) !== false ? $dateSouhaitee : null;
        $activite = in_array($_POST['activite'] ?? '', Demande::ACTIVITES, true) ? $_POST['activite'] : '';

        $erreurs = [];
        if ($objet === '') {
            $erreurs['objet'] = 'Indiquez en quelques mots l’objet de votre demande.';
        }
        if ($message === '') {
            $erreurs['message'] = 'Décrivez votre besoin pour que nous puissions vous répondre.';
        }
        if ($erreurs) {
            $this->rendre('portail/espace', [
                'lien' => $lien,
                'client' => $client,
                'onglet' => 'demande',
                'dossiers' => PortailClient::dossiers((int) $client['id']),
                'cotations' => PortailClient::cotations((int) $client['id']),
                'factures' => PortailClient::factures((int) $client['id']),
                'nbAttente' => 0,
                'emetteur' => $this->nomEmetteur($client),
                'base' => $base,
                'erreurs' => $erreurs,
                'old' => $_POST,
            ]);
            return;
        }

        $contact = trim(($client['contact_prenom'] ?? '') . ' ' . ($client['contact_nom'] ?? ''));
        $demandeId = Demande::create([
            'filiale_id' => (int) $client['filiale_id'],
            'objet' => $objet,
            'message' => $message,
            'canal' => 'Espace client',
            'expediteur_nom' => $contact !== '' ? $contact : $client['nom'],
            'expediteur_entreprise' => $client['nom'],
            'expediteur_email' => $client['email'] ?? '',
            'expediteur_telephone' => $client['telephone'] ?? '',
            'activite' => $activite,
            'responsable_id' => !empty($client['responsable_id']) ? (int) $client['responsable_id'] : null,
            'date_souhaitee_client' => $dateOk,
            'echeance' => null,
            'notes_internes' => '',
            'client_id' => (int) $client['id'],
        ]);
        $demande = Demande::find($demandeId);
        AuditLog::log((int) $client['filiale_id'], null, 'creation', 'demande', $demandeId, 'Déposée par le client depuis l’espace client');
        $this->notifier($client, $client['responsable_id'] ?? null, (int) $client['filiale_id'], 'demande_client', 'Nouvelle demande d’un client',
            $client['nom'] . ' a déposé la demande ' . ($demande['reference'] ?? '') . ' : ' . $objet, '/index.php?r=demandes/' . $demandeId, 'demande', $demandeId);

        $this->flashPortail('succes', 'Merci, votre demande a bien été envoyée (référence ' . ($demande['reference'] ?? '') . '). Votre interlocuteur vous répondra rapidement.');
        $this->aller($base . '&onglet=demande');
    }

    // ------------------------------------------------------------------
    // Utilitaires
    // ------------------------------------------------------------------

    /**
     * Charge le lien et le client, ou affiche la page « lien indisponible ».
     * Anti-devinette : au bout de 5 liens invalides en 15 minutes depuis une
     * même adresse IP, l'accès est temporairement refusé.
     */
    private function contexte(array $params): ?array
    {
        $cle = 'portail:' . substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        if (LimiteConnexion::bloque($cle)) {
            http_response_code(429);
            $this->rendre('portail/invalide', ['bloque' => true]);
            return null;
        }
        $lien = PortailClient::findByToken((string) ($params['token'] ?? ''));
        $client = $lien ? Client::find((int) $lien['client_id']) : null;
        if (!$lien || !$client || !PortailClient::estValide($lien)
            || !Filiale::find((int) $client['filiale_id'])
            || !\App\Models\Organisation::estActive((int) Filiale::find((int) $client['filiale_id'])['organisation_id'])) {
            LimiteConnexion::echec($cle);
            http_response_code(410);
            $this->rendre('portail/invalide', ['bloque' => false]);
            return null;
        }
        return [$lien, $client];
    }

    /** Nom de l'entreprise qui propose l'espace (organisation du client). */
    private function nomEmetteur(array $client): string
    {
        $filiale = Filiale::find((int) $client['filiale_id']);
        $org = $filiale ? \App\Models\Organisation::find((int) $filiale['organisation_id']) : null;
        return (string) ($org['nom'] ?? '');
    }

    private function rendre(string $template, array $data): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
        $data['csrfToken'] = Auth::csrfToken();
        $data['flashPortail'] = $_SESSION['flash_portail'] ?? null;
        unset($_SESSION['flash_portail']);
        View::renderPlain($template, $data);
    }

    private function flashPortail(string $type, string $message): void
    {
        $_SESSION['flash_portail'] = ['type' => $type, 'message' => $message];
    }

    private function aller(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    private function introuvable(): void
    {
        http_response_code(404);
        $this->rendre('portail/invalide', ['bloque' => false]);
    }

    /** Notifie le responsable indiqué, sinon les propriétaires actifs de l'entreprise. */
    private function notifier(array $client, $responsableId, int $filialeId, string $type, string $titre, string $message, string $lien, string $entite, int $entiteId): void
    {
        try {
            $filiale = Filiale::find((int) $client['filiale_id']);
            $destinataires = [];
            if ($responsableId) {
                $u = Utilisateur::find((int) $responsableId);
                if ($u && (int) $u['actif'] === 1 && (int) $u['organisation_id'] === (int) $filiale['organisation_id']) {
                    $destinataires[] = $u;
                }
            }
            if (empty($destinataires)) {
                foreach (Utilisateur::allForOrganisation((int) $filiale['organisation_id']) as $u) {
                    if ($u['role'] === 'proprietaire' && (int) $u['actif'] === 1) {
                        $destinataires[] = $u;
                    }
                }
            }
            foreach ($destinataires as $u) {
                Notification::notifier($u, $filialeId, $type, $titre, $message, $lien, $entite, $entiteId);
            }
        } catch (\Throwable $e) {
            // Best-effort : une notification en échec ne doit jamais bloquer la décision du client.
        }
    }
}

<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\LimiteConnexion;
use App\Core\Storage;
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

    /**
     * Le devis du client sous sa vraie forme (mêmes modèle, logo, couleurs, TVA, remises et photos que le document
     * envoyé par l'entreprise), au lieu de l'impression de la page web de l'espace client.
     * Seules les cotations déjà envoyées au client (envoyée / acceptée / refusée) sont accessibles, et que les siennes.
     */
    private function devisDuClient(array $params): ?array
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return null;
        }
        [$lien, $client] = $ctx;
        $visible = PortailClient::cotation((int) $client['id'], (int) ($params['id'] ?? 0));
        $cotation = $visible ? \App\Models\Cotation::findWithDetails((int) $visible['id']) : null;
        if (!$cotation || (int) $cotation['client_id'] !== (int) $client['id']) {
            $this->introuvable();
            return null;
        }
        $filiale = Filiale::find((int) $cotation['filiale_id']) ?: [];
        $info = \App\Models\EntrepriseInfo::forFiliale((int) $cotation['filiale_id']);
        $emetteur = ['nom' => trim((string) ($info['raison_sociale'] ?? '')) ?: (string) ($filiale['nom'] ?? '')] + $info;
        return ['lien' => $lien, 'cotation' => $cotation, 'items' => \App\Models\CotationItem::forCotation((int) $cotation['id']),
                'client' => Client::find((int) $cotation['client_id']) ?: [], 'emetteur' => $emetteur];
    }

    public function devisDocument(array $params): void
    {
        $d = $this->devisDuClient($params);
        if (!$d) {
            return;
        }
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
        $base = '/index.php?r=espace-client/' . $d['lien']['token'];
        View::renderPlain('cotations/apercu', [
            'cotation' => $d['cotation'], 'items' => $d['items'], 'client' => $d['client'], 'emetteur' => $d['emetteur'],
            'totaux' => \App\Services\DevisPdf::totaux($d['cotation']),
            'logoUri' => \App\Models\EntrepriseInfo::logoDataUri($d['emetteur']),
            'photos' => \App\Services\PhotoProduit::urisDevis($d['cotation']),
            'barreClient' => ['pdf' => $base . '/cotations/' . (int) $d['cotation']['id'] . '/pdf', 'retour' => $base . '/cotations/' . (int) $d['cotation']['id']],
        ]);
    }

    public function devisPdf(array $params): void
    {
        $d = $this->devisDuClient($params);
        if (!$d) {
            return;
        }
        header('X-Robots-Tag: noindex, nofollow');
        \App\Services\RecapPdf::sortir(\App\Services\DevisPdf::generer($d['cotation'], $d['items'], $d['emetteur'], $d['client']), \App\Services\DevisPdf::nomFichier($d['cotation']));
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
        if ($choix === 'refusee') {
            Cotation::enregistrerPerte((int) $cotation['id'], (string) ($_POST['motif'] ?? ''), $commentaire);
        }
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

    /** Fichier (PDF, image) joint à une facture par l'équipe : uniquement pour une facture émise/payée de ce client. */
    public function fichierFacture(array $params): void
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return;
        }
        [, $client] = $ctx;
        $f = PortailClient::facture((int) $client['id'], (int) ($params['id'] ?? 0));
        $chemin = $f && !empty($f['fichier']) ? \App\Core\Storage::path('uploads/factures/' . basename((string) $f['fichier'])) : '';
        if (!$f || !is_file($chemin)) {
            $this->introuvable();
            return;
        }
        $ext = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
        header('Content-Type: ' . (['pdf' => 'application/pdf', 'png' => 'image/png'][$ext] ?? 'image/jpeg'));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', basename((string) (($f['fichier_nom'] ?? '') ?: $f['fichier']))) . '"');
        header('Content-Length: ' . filesize($chemin));
        readfile($chemin);
        exit;
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

        // Envoi plus gros que la limite du serveur (post_max_size) : PHP vide alors tout le formulaire.
        $limite = (int) (function_exists('ini_parse_quantity') ? ini_parse_quantity((string) ini_get('post_max_size')) : (int) ini_get('post_max_size') * 1048576);
        if (empty($_POST) && $limite > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $limite) {
            $this->flashPortail('erreur', 'Les fichiers sont trop volumineux. Envoyez-en moins à la fois ou réduisez leur taille (10 Mo maximum par fichier).');
            $this->aller($retour);
        }
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
        $activite = in_array($_POST['activite'] ?? '', \App\Models\Activite::liste((int) (Filiale::find((int) $client['filiale_id'])['organisation_id'] ?? 0)), true) ? $_POST['activite'] : '';

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
        [$nbJoints, $refus] = $this->joindreFichiersDemande($demandeId, (int) $client['filiale_id']);
        $this->notifier($client, $client['responsable_id'] ?? null, (int) $client['filiale_id'], 'demande_client', 'Nouvelle demande d’un client',
            $client['nom'] . ' a déposé la demande ' . ($demande['reference'] ?? '') . ' : ' . $objet . ($nbJoints > 0 ? ' (' . $nbJoints . ' fichier' . ($nbJoints > 1 ? 's' : '') . ' joint' . ($nbJoints > 1 ? 's' : '') . ')' : ''),
            '/index.php?r=demandes/' . $demandeId, 'demande', $demandeId);

        $this->flashPortail('succes', 'Merci, votre demande a bien été envoyée (référence ' . ($demande['reference'] ?? '') . ')'
            . ($nbJoints > 0 ? ', avec ' . $nbJoints . ' fichier' . ($nbJoints > 1 ? 's' : '') . ' joint' . ($nbJoints > 1 ? 's' : '') : '')
            . '. Votre interlocuteur vous répondra rapidement.'
            . ($refus ? ' Non joints : ' . implode(' ; ', $refus) . ' Vous pouvez les renvoyer par message ou à votre interlocuteur.' : ''));
        $this->aller($base . '&onglet=demande');
    }

    /** Extensions acceptées depuis l'espace client (pas d'e-mails .eml/.msg : on reste sur des documents courants). */
    private const JOINTS_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
    private const JOINTS_MAX_FICHIERS = 5;

    /**
     * Pièces jointes d'une demande déposée par le client (champ fichiers[]) : 5 fichiers maximum, 10 Mo chacun,
     * extensions limitées, nom aléatoire hors webroot, type lu sur le fichier (pas celui annoncé par le navigateur).
     * Retourne [nombre joint, liste des refus]. Un refus n'empêche jamais l'envoi de la demande.
     */
    private function joindreFichiersDemande(int $demandeId, int $filialeId): array
    {
        $ok = 0;
        $refus = [];
        $f = $_FILES['fichiers'] ?? null;
        if (!$f || empty($f['name'])) {
            return [0, []];
        }
        $noms = (array) $f['name'];
        $vus = 0;
        foreach ($noms as $i => $nomOriginal) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $nomOriginal = mb_substr(basename((string) $nomOriginal), 0, 200);
            if (++$vus > self::JOINTS_MAX_FICHIERS) {
                $refus[] = '« ' . $nomOriginal . ' » (5 fichiers maximum)';
                continue;
            }
            if ($f['error'][$i] !== UPLOAD_ERR_OK) {
                $refus[] = '« ' . $nomOriginal . ' » (échec de l\'envoi)';
                continue;
            }
            if ($f['size'][$i] > \App\Models\DemandePieceJointe::TAILLE_MAX) {
                $refus[] = '« ' . $nomOriginal . ' » (plus de 10 Mo)';
                continue;
            }
            $ext = strtolower(pathinfo($nomOriginal, PATHINFO_EXTENSION));
            if (!in_array($ext, self::JOINTS_EXTENSIONS, true) || !is_uploaded_file($f['tmp_name'][$i])) {
                $refus[] = '« ' . $nomOriginal . ' » (format non accepté : PDF, images, Word, Excel ou texte)';
                continue;
            }
            $nomFichier = bin2hex(random_bytes(16)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'][$i], Storage::path('uploads/demandes/' . $demandeId . '/' . $nomFichier))) {
                $refus[] = '« ' . $nomOriginal . ' » (enregistrement impossible)';
                continue;
            }
            $mime = function_exists('mime_content_type') ? (string) @mime_content_type(Storage::path('uploads/demandes/' . $demandeId . '/' . $nomFichier)) : '';
            \App\Models\DemandePieceJointe::create([
                'demande_id' => $demandeId,
                'nom_original' => $nomOriginal,
                'nom_fichier' => $nomFichier,
                'taille' => (int) $f['size'][$i],
                'type_mime' => $mime !== '' ? $mime : 'application/octet-stream',
                'uploaded_by' => null,
            ]);
            AuditLog::log($filialeId, null, 'ajout_piece_jointe', 'demande', $demandeId, $nomOriginal . ' (espace client)');
            $ok++;
        }
        return [$ok, $refus];
    }

    // ------------------------------------------------------------------
    // Dossier : suivi (notes de l'équipe) et messages (v72)
    // ------------------------------------------------------------------

    /** Dossier du client (jamais celui d'un autre) : ligne « publique » + dossier complet pour l'enregistrement. */
    private function dossierDuClient(array $client, int $id): ?array
    {
        foreach (PortailClient::dossiers((int) $client['id']) as $d) {
            if ((int) $d['id'] === $id) {
                $complet = Dossier::find($id);
                return $complet ? ['public' => $d, 'complet' => $complet] : null;
            }
        }
        return null;
    }

    public function dossier(array $params): void
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return;
        }
        [$lien, $client] = $ctx;
        $base = '/index.php?r=espace-client/' . $lien['token'];
        $d = \App\Models\Echange::pret() ? $this->dossierDuClient($client, (int) ($params['id'] ?? 0)) : null;
        if (!$d) {
            $this->introuvable();
            return;
        }
        PortailClient::enregistrerAcces((int) $lien['id']);
        $id = (int) $d['public']['id'];
        $vue = in_array($_GET['vue'] ?? '', ['avancement', 'devis', 'documents', 'suivi', 'messages'], true) ? $_GET['vue'] : 'avancement';
        $documents = \App\Models\DossierPieceJointe::pourClient($id);
        $this->rendre('portail/dossier', [
            'documents' => $documents,
            'devis' => array_values(array_filter(PortailClient::cotations((int) $client['id']), fn($c) => (int) $c['dossier_id'] === $id)),
            'factures' => array_values(array_filter(PortailClient::factures((int) $client['id']), fn($f) => (int) ($f['dossier_id'] ?? 0) === $id)),
            'lien' => $lien,
            'client' => $client,
            'dossier' => $d['public'],
            'vue' => $vue,
            'notes' => \App\Models\Echange::notesVisibles($id, \App\Models\Echange::CLIENT),
            'messages' => \App\Models\Echange::messages(\App\Models\Echange::CLIENT, $id),
            'nonLus' => \App\Models\Echange::nonLusExterne(\App\Models\Echange::CLIENT, $id),
            'emetteur' => $this->nomEmetteur($client),
            'base' => $base,
            'erreurs' => [],
        ]);
        if ($vue === 'messages') {
            \App\Models\Echange::marquerLusExterne(\App\Models\Echange::CLIENT, $id);
        }
    }

    /** Téléchargement d'un document « visible client » d'un dossier du client (jamais un autre dossier, jamais une offre fournisseur). */
    public function documentDossier(array $params): void
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return;
        }
        [, $client] = $ctx;
        $d = $this->dossierDuClient($client, (int) ($params['id'] ?? 0));
        $pid = (int) ($params['pid'] ?? 0);
        $piece = null;
        if ($d) {
            foreach (\App\Models\DossierPieceJointe::pourClient((int) $d['public']['id']) as $p) {
                if ((int) $p['id'] === $pid) { $piece = $p; break; }
            }
        }
        $chemin = $piece ? \App\Core\Storage::path('uploads/dossiers/' . (int) $d['public']['id'] . '/' . (\App\Models\DossierPieceJointe::find($pid)['nom_fichier'] ?? '')) : '';
        if (!$piece || !is_file($chemin)) {
            $this->introuvable();
            return;
        }
        $mime = (string) $piece['type_mime'];
        $image = in_array($mime, ['image/jpeg', 'image/png'], true);
        header('Content-Type: ' . ($image || $mime === 'application/pdf' ? $mime : 'application/octet-stream'));
        header('Content-Disposition: ' . (($image && !empty($_GET['apercu'])) ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', basename((string) $piece['nom_original'])) . '"');
        header('Content-Length: ' . filesize($chemin));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($chemin);
        exit;
    }

    public function envoyerMessage(array $params): void
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return;
        }
        [$lien, $client] = $ctx;
        $base = '/index.php?r=espace-client/' . $lien['token'];
        $d = \App\Models\Echange::pret() ? $this->dossierDuClient($client, (int) ($params['id'] ?? 0)) : null;
        if (!$d) {
            $this->introuvable();
            return;
        }
        $id = (int) $d['public']['id'];
        $retour = $base . '/dossiers/' . $id . '&vue=messages';
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $this->flashPortail('erreur', 'Page expirée : merci de réessayer.');
            $this->aller($retour);
        }
        if (trim((string) ($_POST['site_web'] ?? '')) !== '') {
            $this->aller($base);
        }
        if ((int) $client['is_active'] !== 1) {
            $this->flashPortail('erreur', 'Votre compte client est actuellement inactif. Merci de contacter directement votre interlocuteur.');
            $this->aller($retour);
        }
        if (\App\Models\Echange::recentsExternes(\App\Models\Echange::CLIENT, $id, null) >= \App\Models\Echange::MAX_PAR_HEURE) {
            $this->flashPortail('erreur', 'Vous avez envoyé plusieurs messages à l’instant. Merci de patienter un peu avant d’en écrire un autre.');
            $this->aller($retour);
        }
        $contact = \App\Models\Echange::contactDuLien(\App\Models\Echange::CLIENT, $client, $lien);
        [$mid, $erreur] = \App\Models\Echange::envoyer(\App\Models\Echange::CLIENT, $d['complet'], null, 'externe', null, (string) $contact['nom'], (string) ($_POST['texte'] ?? ''), $_FILES['fichier'] ?? null);
        if (!$mid) {
            $this->flashPortail('erreur', (string) $erreur);
            $this->aller($retour);
        }
        $apercu = mb_substr(trim((string) ($_POST['texte'] ?? '')), 0, 300);
        \App\Models\Echange::prevenirEquipe($d['complet'], 'Nouveau message d’un client',
            $client['nom'] . ' a écrit sur le dossier ' . $d['complet']['reference'] . ($apercu !== '' ? ' : « ' . $apercu . ' »' : ' (fichier joint).'),
            '/index.php?r=dossiers/' . $id . '&onglet=messages');
        $this->flashPortail('succes', 'Votre message a bien été envoyé.');
        $this->aller($retour);
    }

    public function fichierMessage(array $params): void
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return;
        }
        [, $client] = $ctx;
        $m = \App\Models\Echange::pret() ? \App\Models\Echange::message((int) ($params['mid'] ?? 0)) : null;
        if (!$m || $m['canal'] !== \App\Models\Echange::CLIENT || !$this->dossierDuClient($client, (int) $m['dossier_id'])) {
            $this->introuvable();
            return;
        }
        \App\Models\Echange::servirFichier($m);
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

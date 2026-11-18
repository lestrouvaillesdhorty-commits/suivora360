<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\LimiteConnexion;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\ConsultationFournisseur;
use App\Models\Dossier;
use App\Models\Filiale;
use App\Models\Fournisseur;
use App\Models\FournisseurLien;
use App\Models\Notification;
use App\Models\Offre;

/**
 * Lien fournisseur (v68) : pages PUBLIQUES (aucune connexion) accessibles par un jeton aléatoire de 192 bits,
 * limité dans le temps et révocable. Le fournisseur voit ses demandes de prix en cours et y répond ;
 * son offre arrive « à vérifier » (hors comparateur) tant que l'entreprise ne l'a pas validée.
 */
class FournisseurLienController
{
    // ------------------------------------------------------------------ Pages publiques

    public function liste(array $params): void
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return;
        }
        [$lien, $f] = $ctx;
        FournisseurLien::enregistrerAcces((int) $lien['id']);
        $this->rendre('fournisseur_lien/liste', [
            'lien' => $lien, 'fournisseur' => $f, 'demandes' => FournisseurLien::consultations((int) $f['id']),
            'emetteur' => $this->emetteur($f), 'base' => $this->base($lien),
        ]);
    }

    public function demande(array $params): void
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return;
        }
        [$lien, $f] = $ctx;
        $c = FournisseurLien::consultation((int) $f['id'], (int) ($params['id'] ?? 0));
        if (!$c) {
            $this->introuvable();
            return;
        }
        FournisseurLien::marquerVue((int) $c['id']);
        $this->afficherDemande($lien, $f, $c, [], []);
    }

    public function repondre(array $params): void
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return;
        }
        [$lien, $f] = $ctx;
        $c = FournisseurLien::consultation((int) $f['id'], (int) ($params['id'] ?? 0));
        if (!$c) {
            $this->introuvable();
            return;
        }
        $base = $this->base($lien);
        $retour = $base . '/demandes/' . (int) $c['id'];
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $this->flash('erreur', 'Page expirée : merci de réessayer.');
            $this->aller($retour);
        }
        if (!FournisseurLien::peutRepondre($c)) {
            $this->flash('erreur', 'Cette demande n\'accepte plus de réponse en ligne. Contactez votre interlocuteur.');
            $this->aller($retour);
        }

        $articles = FournisseurLien::articles($c);
        $erreurs = [];
        $items = [];
        $total = 0.0;
        $prixSaisis = 0;
        foreach ($articles as $a) {
            $brut = trim((string) ($_POST['prix'][(int) $a['id']] ?? ''));
            $prix = $brut === '' ? null : $this->nombre($brut);
            if ($brut !== '' && ($prix === null || $prix < 0)) {
                $erreurs['prix_' . $a['id']] = 'Prix invalide.';
            }
            $q = $a['quantite'] !== null && $a['quantite'] !== '' ? (float) $a['quantite'] : null;
            if ($prix !== null && $prix >= 0) {
                $prixSaisis++;
                $total += ($q ?? 1) * $prix;
            }
            $items[] = [
                'designation' => $a['designation'], 'quantite' => $q, 'unite' => $a['unite'] ?? '', 'prix_unitaire' => $prix,
                'conditionnement' => $a['conditionnement'] ?? '', 'reference' => $a['reference'] ?? '', 'marque' => $a['marque'] ?? '',
                'caracteristiques' => $a['caracteristiques'] ?? '', 'demande_article_id' => (int) $a['id'],
            ];
        }
        if (!$articles) {
            $t = $this->nombre((string) ($_POST['montant_total'] ?? ''));
            if ($t === null || $t <= 0) {
                $erreurs['montant_total'] = 'Indiquez votre prix total.';
            } else {
                $total = $t;
                $prixSaisis = 1;
            }
        } elseif ($prixSaisis === 0) {
            $erreurs['prix'] = 'Indiquez au moins un prix.';
        }
        $transport = trim((string) ($_POST['transport'] ?? '')) === '' ? null : $this->nombre((string) $_POST['transport']);
        if (trim((string) ($_POST['transport'] ?? '')) !== '' && ($transport === null || $transport < 0)) {
            $erreurs['transport'] = 'Montant invalide.';
        }
        $devise = in_array($_POST['devise'] ?? '', FournisseurLien::MONNAIES, true) ? $_POST['devise'] : 'EUR';
        $validite = (string) ($_POST['validite'] ?? '');
        if ($validite !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $validite) || $validite < date('Y-m-d'))) {
            $erreurs['validite'] = 'Choisissez une date à venir.';
        }
        if ($erreurs) {
            $this->afficherDemande($lien, $f, $c, $erreurs, $_POST);
            return;
        }

        $precedente = FournisseurLien::derniereOffreModifiable($c);
        $consultation = ConsultationFournisseur::find((int) $c['id']);
        $offreId = Offre::create($consultation, [
            'montant_total' => round($total, 2), 'devise' => $devise, 'delai_livraison' => mb_substr(trim((string) ($_POST['delai'] ?? '')), 0, 120),
            'validite_offre' => $validite ?: null, 'notes' => mb_substr(trim((string) ($_POST['commentaire'] ?? '')), 0, 1000),
            'transport_montant' => $transport,
        ], $items, null, $precedente);
        $pdo = \App\Core\Database::connection();
        $pdo->prepare("UPDATE offres SET statut = 'a_verifier' WHERE id = ?")->execute([$offreId]);
        if (\App\Core\Schema::colonne('offres', 'saisie_par_lien')) {
            $pdo->prepare('UPDATE offres SET saisie_par_lien = 1 WHERE id = ?')->execute([$offreId]);
        }
        $pdo->prepare("UPDATE consultations_fournisseur SET reponse_fournisseur = NULL, reponse_fournisseur_note = NULL WHERE id = ?")->execute([(int) $c['id']]);

        AuditLog::log((int) $c['filiale_id'], null, $precedente ? 'revision_offre_lien' : 'creation_offre_lien', 'offre', (int) $offreId, 'Saisie par ' . $f['nom'] . ' depuis son lien');
        $this->prevenir($c, $f, (int) $offreId, ($precedente ? 'Offre révisée à vérifier' : 'Offre reçue à vérifier'),
            $f['nom'] . ' a envoyé son prix par son lien (dossier ' . $c['dossier_reference'] . '). Vérifiez et validez l\'offre pour l\'ajouter au comparateur.');
        $this->flash('succes', 'Merci, votre offre a bien été envoyée. Vous pouvez la modifier tant qu\'elle n\'a pas été traitée.');
        $this->aller($retour);
    }

    public function reponseSimple(array $params): void
    {
        $ctx = $this->contexte($params);
        if (!$ctx) {
            return;
        }
        [$lien, $f] = $ctx;
        $c = FournisseurLien::consultation((int) $f['id'], (int) ($params['id'] ?? 0));
        if (!$c) {
            $this->introuvable();
            return;
        }
        $base = $this->base($lien);
        $retour = $base . '/demandes/' . (int) $c['id'];
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $this->flash('erreur', 'Page expirée : merci de réessayer.');
            $this->aller($retour);
        }
        if (!in_array($c['statut'], ['envoyee', 'relance'], true)) {
            $this->flash('erreur', 'Cette demande a déjà reçu une réponse.');
            $this->aller($retour);
        }
        $type = ($_POST['type'] ?? '') === 'delai' ? 'delai' : 'decline';
        $note = trim((string) ($_POST['note'] ?? ''));
        FournisseurLien::enregistrerReponse((int) $c['id'], $type, $note);
        AuditLog::log((int) $c['filiale_id'], null, 'reponse_fournisseur_lien', 'consultation', (int) $c['id'], $f['nom'] . ($type === 'delai' ? ' demande un délai' : ' ne peut pas répondre'));
        $this->prevenir($c, $f, (int) $c['id'], $type === 'delai' ? 'Un fournisseur demande un délai' : 'Un fournisseur ne peut pas répondre',
            $f['nom'] . ($type === 'delai' ? ' demande plus de temps' : ' ne peut pas répondre') . ' pour le dossier ' . $c['dossier_reference'] . ($note !== '' ? ' : ' . mb_substr($note, 0, 200) : '.'), 'consultation');
        $this->flash('succes', $type === 'delai' ? 'Merci, votre demande de délai a été transmise.' : 'Merci, nous avons bien noté que vous ne pouvez pas répondre.');
        $this->aller($base);
    }

    // ------------------------------------------------------------------ Gestion depuis la fiche fournisseur

    public function creer(array $params): void
    {
        $f = $this->fournisseurOu404($params);
        if (!$f) {
            return;
        }
        $id = (int) $f['id'];
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $this->versFiche($id);
        }
        if (!FournisseurLien::schemaPret()) {
            View::flash('erreur', 'Mise à jour de la base requise (migration V68) avant de créer un lien.');
            $this->versFiche($id);
        }
        if ((int) $f['is_active'] !== 1) {
            View::flash('erreur', 'Réactivez d\'abord ce fournisseur.');
            $this->versFiche($id);
        }
        $jours = (int) ($_POST['jours'] ?? FournisseurLien::DUREE_DEFAUT);
        FournisseurLien::creerLien($id, $jours, (int) Auth::user()['id']);
        AuditLog::log((int) $f['filiale_id'], (int) Auth::user()['id'], 'lien_fournisseur_cree', 'fournisseur', $id, $jours . ' jours');
        View::flash('succes', 'Lien créé. Copiez-le ou copiez le message de relance, puis envoyez-le (WhatsApp, e-mail…).');
        $this->versFiche($id);
    }

    public function revoquer(array $params): void
    {
        $f = $this->fournisseurOu404($params);
        if (!$f) {
            return;
        }
        $id = (int) $f['id'];
        if (Auth::verifyCsrf($_POST['csrf_token'] ?? null) && FournisseurLien::lienDuFournisseur($id, (int) ($params['lienId'] ?? 0))) {
            FournisseurLien::revoquer((int) $params['lienId']);
            AuditLog::log((int) $f['filiale_id'], (int) Auth::user()['id'], 'lien_fournisseur_revoque', 'fournisseur', $id, '');
            View::flash('succes', 'Lien révoqué : il ne fonctionne plus.');
        }
        $this->versFiche($id);
    }

    // ------------------------------------------------------------------ Utilitaires

    private function afficherDemande(array $lien, array $f, array $c, array $erreurs, array $old): void
    {
        $dossier = Dossier::find((int) $c['dossier_id']);
        $demande = $dossier ? \App\Models\Demande::find((int) $dossier['demande_id']) : null;
        $this->rendre('fournisseur_lien/demande', [
            'lien' => $lien, 'fournisseur' => $f, 'c' => $c, 'articles' => FournisseurLien::articles($c),
            'livraison' => ConsultationFournisseur::livraison($c, $demande),
            'precedente' => FournisseurLien::derniereOffreModifiable($c),
            'peutRepondre' => FournisseurLien::peutRepondre($c),
            'emetteur' => $this->emetteur($f), 'base' => $this->base($lien), 'erreurs' => $erreurs, 'old' => $old,
        ]);
    }

    private function nombre(string $s): ?float
    {
        $s = str_replace([' ', "\xc2\xa0"], '', trim($s));
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float) $s : null;
    }

    private function prevenir(array $c, array $f, int $entiteId, string $titre, string $message, string $entite = 'offre'): void
    {
        try {
            $dossier = Dossier::find((int) $c['dossier_id']);
            if ($dossier) {
                Notification::notifierTous(
                    Notification::destinatairesDossier($dossier, []), (int) $c['filiale_id'], 'offre_recue', $titre, $message,
                    '/index.php?r=dossiers/' . $dossier['id'], $entite, $entiteId
                );
            }
        } catch (\Throwable $e) {
        }
    }

    private function contexte(array $params): ?array
    {
        $cle = 'fournisseur:' . substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        if (LimiteConnexion::bloque($cle)) {
            http_response_code(429);
            $this->rendre('fournisseur_lien/invalide', ['bloque' => true]);
            return null;
        }
        $lien = FournisseurLien::findByToken((string) ($params['token'] ?? ''));
        $f = $lien ? Fournisseur::find((int) $lien['fournisseur_id']) : null;
        $filiale = $f ? Filiale::find((int) $f['filiale_id']) : null;
        if (!$lien || !$f || !$filiale || !FournisseurLien::estValide($lien) || (int) $f['is_active'] !== 1
            || !\App\Models\Organisation::estActive((int) $filiale['organisation_id'])) {
            LimiteConnexion::echec($cle);
            http_response_code(410);
            $this->rendre('fournisseur_lien/invalide', ['bloque' => false]);
            return null;
        }
        return [$lien, $f];
    }

    private function emetteur(array $f): string
    {
        $info = \App\Models\EntrepriseInfo::forFiliale((int) $f['filiale_id']);
        $filiale = Filiale::find((int) $f['filiale_id']) ?: [];
        return trim((string) ($info['raison_sociale'] ?? '')) ?: (string) ($filiale['nom'] ?? '');
    }

    private function base(array $lien): string
    {
        return '/index.php?r=lien-fournisseur/' . $lien['token'];
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

    private function flash(string $type, string $message): void
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
        $this->rendre('fournisseur_lien/invalide', ['bloque' => false]);
    }

    private function fournisseurOu404(array $params): ?array
    {
        Auth::requireWrite();
        $f = Fournisseur::find((int) ($params['id'] ?? 0));
        if (!$f || !Fournisseur::userCanAccess(Auth::user(), $f)) {
            http_response_code(404);
            View::render('errors/404');
            return null;
        }
        return $f;
    }

    private function versFiche(int $id): void
    {
        header('Location: /index.php?r=fournisseurs/' . $id . '#lien-fournisseur');
        exit;
    }
}

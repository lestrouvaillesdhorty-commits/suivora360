<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\BonCommandeFournisseur;
use App\Models\Dossier;
use App\Models\Filiale;
use App\Models\Fournisseur;
use App\Models\Offre;

/**
 * Bon de commande fournisseur (07/10) : liste, création (depuis une offre retenue ou libre),
 * modification en brouillon, fiche, page imprimable, changements de statut.
 * Voir App\Models\BonCommandeFournisseur pour les règles.
 */
class BonCommandeFournisseurController
{
    private const DEVISES = ['EUR', 'USD', 'XOF', 'XAF', 'GBP', 'CNY'];

    // ------------------------------------------------------------------ garde-fous

    private function voirOu403(): void
    {
        if (!Auth::canVoirFinancesFournisseur()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    private function gererOu403(): void
    {
        Auth::requireWrite();
        if (!Auth::canGererBonCommandeFournisseur()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    private function introuvable(): void
    {
        http_response_code(404);
        View::render('errors/404');
        exit;
    }

    private function chargerBon(array $params): array
    {
        $this->voirOu403();
        $bon = BonCommandeFournisseur::find((int) ($params['id'] ?? 0));
        if (!$bon || !BonCommandeFournisseur::userCanAccess(Auth::user(), $bon)) {
            $this->introuvable();
        }
        return $bon;
    }

    private function chargerFournisseur(array $params): array
    {
        $f = Fournisseur::find((int) ($params['id'] ?? 0));
        if (!$f || !Fournisseur::userCanAccess(Auth::user(), $f)) {
            $this->introuvable();
        }
        return $f;
    }

    private function retour(string $url, string $type, string $message): void
    {
        View::flash($type, $message);
        header('Location: ' . $url);
        exit;
    }

    // ------------------------------------------------------------------ liste

    public function index(): void
    {
        $this->voirOu403();
        $user = Auth::user();
        $statut = (string) ($_GET['statut'] ?? '');
        $q = trim((string) ($_GET['q'] ?? ''));
        View::render('bons_commande/index', [
            'bons' => BonCommandeFournisseur::liste(Filiale::visibleIdsFor($user), $statut, $q),
            'statut' => isset(BonCommandeFournisseur::STATUTS[$statut]) ? $statut : '',
            'q' => $q,
            'schemaPret' => BonCommandeFournisseur::schemaPret(),
            'peutGerer' => Auth::canWrite() && Auth::canGererBonCommandeFournisseur(),
        ]);
    }

    // ------------------------------------------------------------------ création

    public function create(array $params): void
    {
        $this->gererOu403();
        $f = $this->chargerFournisseur($params);
        if (!BonCommandeFournisseur::schemaPret()) {
            $this->retour('/index.php?r=fournisseurs/' . (int) $f['id'] . '&onglet=commandes', 'erreur', 'Mise à jour de la base requise (migration V24) avant de créer un bon de commande.');
        }
        if ((int) $f['is_active'] !== 1) {
            $this->retour('/index.php?r=fournisseurs/' . (int) $f['id'], 'erreur', 'Ce fournisseur est inactif : réactivez-le avant de lui adresser un bon de commande.');
        }

        $offre = null;
        $old = ['date_emission' => date('Y-m-d'), 'devise' => '', 'incoterm' => '', 'lieu_livraison' => '', 'conditions_paiement' => '',
            'reference_offre' => '', 'notes' => '', 'notes_internes' => '', 'date_livraison_souhaitee' => '', 'dossier_id' => 0, 'offre_id' => 0, 'lignes' => []];
        $offreId = (int) ($_GET['offre_id'] ?? 0);
        if ($offreId) {
            $offre = Offre::find($offreId);
            if (!$offre || (int) $offre['fournisseur_id'] !== (int) $f['id'] || $offre['statut'] !== 'retenue') {
                $this->retour('/index.php?r=fournisseurs/' . (int) $f['id'] . '&onglet=commandes', 'erreur', 'Seule l’offre retenue d’un fournisseur peut donner lieu à un bon de commande.');
            }
            $existant = BonCommandeFournisseur::actifPourOffre($offreId);
            if ($existant) {
                $this->retour('/index.php?r=bons-commande/' . (int) $existant['id'], 'erreur', 'Un bon de commande existe déjà pour cette offre : le voici.');
            }
            $old = array_merge($old, BonCommandeFournisseur::propositionDepuisOffre($offre), ['dossier_id' => (int) $offre['dossier_id'], 'offre_id' => $offreId]);
        }
        // Destinataire par défaut : contact principal de la fiche.
        $old['destinataire_nom'] = trim(($f['contact_prenom'] ?? '') . ' ' . ($f['contact_nom'] ?? ''));
        $old['destinataire_email'] = (string) ($f['email'] ?? '');

        $this->afficherFormulaire($f, null, $old, [], $offre);
    }

    private function afficherFormulaire(array $f, ?array $bon, array $old, array $erreurs, ?array $offre = null): void
    {
        $user = Auth::user();
        View::render('bons_commande/form', [
            'fournisseur' => $f,
            'bon' => $bon,
            'old' => $old,
            'erreurs' => $erreurs,
            'offre' => $offre,
            'devises' => self::DEVISES,
            'incoterms' => \App\Models\Demande::INCOTERMS,
            'dossiers' => array_values(array_filter(Dossier::visibleFor($user, ['statut' => 'actif']), fn($d) => (int) $d['filiale_id'] === (int) $f['filiale_id'])),
            'contacts' => Fournisseur::contacts((int) $f['id']),
        ]);
    }

    /** Lit et valide le formulaire. Retourne [données, lignes, total, erreurs]. */
    private function lireFormulaire(array $f, ?array $bon): array
    {
        $p = fn(string $k) => trim((string) ($_POST[$k] ?? ''));
        $erreurs = [];
        $d = [
            'date_emission' => $p('date_emission'),
            'date_livraison_souhaitee' => $p('date_livraison_souhaitee'),
            'devise' => strtoupper($p('devise')),
            'incoterm' => isset(\App\Models\Demande::INCOTERMS[$p('incoterm')]) ? $p('incoterm') : '',
            'lieu_livraison' => mb_substr($p('lieu_livraison'), 0, 255),
            'conditions_paiement' => mb_substr($p('conditions_paiement'), 0, 255),
            'notes' => $p('notes'),
            'notes_internes' => $p('notes_internes'),
            'destinataire_nom' => mb_substr($p('destinataire_nom'), 0, 200),
            'destinataire_email' => $p('destinataire_email'),
            'reference_offre' => $bon['reference_offre'] ?? '',
            'dossier_id' => (int) ($bon['dossier_id'] ?? 0),
            'offre_id' => (int) ($bon['offre_id'] ?? 0),
        ];
        if (!$bon) {
            $d['dossier_id'] = (int) ($_POST['dossier_id'] ?? 0);
            $d['offre_id'] = (int) ($_POST['offre_id'] ?? 0);
            $d['reference_offre'] = mb_substr($p('reference_offre'), 0, 60);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['date_emission'])) {
            $erreurs['date_emission'] = 'Indiquez la date du bon.';
        }
        if ($d['date_livraison_souhaitee'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['date_livraison_souhaitee'])) {
            $erreurs['date_livraison_souhaitee'] = 'Date de livraison non valide.';
        } elseif ($d['date_livraison_souhaitee'] !== '' && $d['date_emission'] !== '' && $d['date_livraison_souhaitee'] < $d['date_emission']) {
            $erreurs['date_livraison_souhaitee'] = 'La livraison souhaitée ne peut pas précéder la date du bon.';
        }
        if ($d['devise'] === '' || !preg_match('/^[A-Z]{2,6}$/', $d['devise'])) {
            $erreurs['devise'] = 'Choisissez la devise du bon (une seule par bon).';
        }
        if ($d['destinataire_email'] !== '' && !filter_var($d['destinataire_email'], FILTER_VALIDATE_EMAIL)) {
            $erreurs['destinataire_email'] = "L'adresse e-mail n'est pas valide.";
        }
        $brutes = [];
        foreach ((array) ($_POST['lignes'] ?? []) as $l) {
            if (is_array($l)) {
                $brutes[] = $l;
            }
        }
        [$lignes, $total] = BonCommandeFournisseur::normaliserLignes($brutes);
        if (empty($lignes)) {
            $erreurs['lignes'] = 'Ajoutez au moins une ligne (désignation, quantité, prix).';
        }
        // Dossier et offre éventuels : mêmes filiale que le fournisseur (isolation).
        if (!$bon && $d['dossier_id']) {
            $dos = Dossier::find($d['dossier_id']);
            if (!$dos || (int) $dos['filiale_id'] !== (int) $f['filiale_id'] || !Dossier::userCanAccess(Auth::user(), $dos)) {
                $d['dossier_id'] = 0;
                $erreurs['dossier_id'] = 'Dossier non valide.';
            }
        }
        if (!$bon && $d['offre_id']) {
            $off = Offre::find($d['offre_id']);
            if (!$off || (int) $off['fournisseur_id'] !== (int) $f['id'] || $off['statut'] !== 'retenue') {
                $erreurs['offre_id'] = 'Seule l’offre retenue de ce fournisseur peut être utilisée.';
            } elseif (BonCommandeFournisseur::actifPourOffre($d['offre_id'])) {
                $erreurs['offre_id'] = 'Un bon de commande existe déjà pour cette offre.';
            } else {
                $d['dossier_id'] = (int) $off['dossier_id'];
            }
        }
        return [$d, $lignes, $total, $erreurs];
    }

    private function postOuRetour(string $retourUrl): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $this->retour($retourUrl, 'erreur', 'Session expirée, merci de réessayer.');
        }
    }

    public function store(array $params): void
    {
        $this->gererOu403();
        $f = $this->chargerFournisseur($params);
        $this->postOuRetour('/index.php?r=fournisseurs/' . (int) $f['id'] . '&onglet=commandes');
        if (!BonCommandeFournisseur::schemaPret() || (int) $f['is_active'] !== 1) {
            $this->retour('/index.php?r=fournisseurs/' . (int) $f['id'], 'erreur', 'Création impossible (migration V24 requise ou fournisseur inactif).');
        }
        [$d, $lignes, $total, $erreurs] = $this->lireFormulaire($f, null);
        if ($erreurs) {
            $old = $d + ['lignes' => (array) ($_POST['lignes'] ?? [])];
            View::flash('erreur', 'Merci de corriger les champs signalés — votre saisie est conservée.');
            $this->afficherFormulaire($f, null, $old, $erreurs);
            return;
        }
        $user = Auth::user();
        $id = BonCommandeFournisseur::creer($f, $d, $lignes, $total, (int) $user['id']);
        AuditLog::log((int) $f['filiale_id'], (int) $user['id'], 'creation_bon_commande', 'bon_commande_fournisseur', $id, number_format($total, 2, ',', ' ') . ' ' . $d['devise']);
        $this->retour('/index.php?r=bons-commande/' . $id, 'succes', 'Bon de commande créé en brouillon. Vérifiez-le, puis marquez-le « envoyé » une fois transmis au fournisseur.');
    }

    // ------------------------------------------------------------------ fiche, modification

    public function show(array $params): void
    {
        $bon = $this->chargerBon($params);
        $f = Fournisseur::find((int) $bon['fournisseur_id']);
        View::render('bons_commande/show', [
            'bon' => $bon,
            'lignes' => BonCommandeFournisseur::lignes((int) $bon['id']),
            'fournisseur' => $f,
            'accordClient' => BonCommandeFournisseur::accordClient($bon['dossier_id'] ? (int) $bon['dossier_id'] : null),
            'peutGerer' => Auth::canWrite() && Auth::canGererBonCommandeFournisseur(),
        ]);
    }

    public function edit(array $params): void
    {
        $this->gererOu403();
        $bon = $this->chargerBon($params);
        if ($bon['statut'] !== 'brouillon') {
            $this->retour('/index.php?r=bons-commande/' . (int) $bon['id'], 'erreur', 'Un bon déjà envoyé ne se modifie plus : annulez-le puis créez-en un nouveau si nécessaire.');
        }
        $f = Fournisseur::find((int) $bon['fournisseur_id']);
        $old = $bon + ['lignes' => BonCommandeFournisseur::lignes((int) $bon['id'])];
        $this->afficherFormulaire($f, $bon, $old, []);
    }

    public function update(array $params): void
    {
        $this->gererOu403();
        $bon = $this->chargerBon($params);
        $url = '/index.php?r=bons-commande/' . (int) $bon['id'];
        $this->postOuRetour($url);
        if ($bon['statut'] !== 'brouillon') {
            $this->retour($url, 'erreur', 'Un bon déjà envoyé ne se modifie plus.');
        }
        $f = Fournisseur::find((int) $bon['fournisseur_id']);
        [$d, $lignes, $total, $erreurs] = $this->lireFormulaire($f, $bon);
        if ($erreurs) {
            $old = $d + $bon + ['lignes' => (array) ($_POST['lignes'] ?? [])];
            View::flash('erreur', 'Merci de corriger les champs signalés — votre saisie est conservée.');
            $this->afficherFormulaire($f, $bon, $old, $erreurs);
            return;
        }
        BonCommandeFournisseur::modifier((int) $bon['id'], $d, $lignes, $total);
        AuditLog::log((int) $bon['filiale_id'], (int) Auth::user()['id'], 'modification_bon_commande', 'bon_commande_fournisseur', (int) $bon['id']);
        $this->retour($url, 'succes', 'Bon de commande mis à jour.');
    }

    // ------------------------------------------------------------------ statut

    public function statut(array $params): void
    {
        $this->gererOu403();
        $bon = $this->chargerBon($params);
        $url = '/index.php?r=bons-commande/' . (int) $bon['id'];
        $this->postOuRetour($url);
        $vers = (string) ($_POST['statut'] ?? '');
        if (!isset(BonCommandeFournisseur::STATUTS[$vers]) || !BonCommandeFournisseur::transitionPossible($bon['statut'], $vers)) {
            $this->retour($url, 'erreur', 'Ce changement de statut n’est pas possible.');
        }
        $motif = mb_substr(trim((string) ($_POST['motif'] ?? '')), 0, 255);
        if ($vers === 'envoye') {
            if (empty(BonCommandeFournisseur::lignes((int) $bon['id']))) {
                $this->retour($url, 'erreur', 'Le bon ne comporte aucune ligne.');
            }
            if (BonCommandeFournisseur::accordClient($bon['dossier_id'] ? (int) $bon['dossier_id'] : null) === false) {
                $this->retour($url, 'erreur', 'Le client n’a pas encore accepté la cotation de ce dossier : le bon de commande ne peut pas être marqué envoyé. La sélection d’une offre fournisseur ne vaut pas accord du client.');
            }
        }
        if ($vers === 'annule' && $motif === '') {
            $this->retour($url, 'erreur', 'Indiquez le motif de l’annulation.');
        }
        BonCommandeFournisseur::changerStatut((int) $bon['id'], $vers, $motif);
        $action = ['envoye' => 'envoi_bon_commande', 'confirme' => 'confirmation_bon_commande', 'annule' => 'annulation_bon_commande'][$vers];
        AuditLog::log((int) $bon['filiale_id'], (int) Auth::user()['id'], $action, 'bon_commande_fournisseur', (int) $bon['id'], $motif);
        $this->retour($url, 'succes', [
            'envoye' => 'Bon marqué envoyé. Suivi : l’envoi réel se fait depuis votre messagerie ; Suivora360 n’envoie rien automatiquement.',
            'confirme' => 'Confirmation du fournisseur enregistrée.',
            'annule' => 'Bon annulé. Il reste consultable dans l’historique.',
        ][$vers]);
    }

    // ------------------------------------------------------------------ impression

    public function imprimable(array $params): void
    {
        $bon = $this->chargerBon($params);
        $filiale = Filiale::find((int) $bon['filiale_id']);
        $org = \App\Models\Organisation::find((int) $filiale['organisation_id']);
        View::renderPlain('bons_commande/imprimable', [
            'bon' => $bon,
            'lignes' => BonCommandeFournisseur::lignes((int) $bon['id']),
            'filiale' => $filiale,
            'organisation' => $org,
            'emetteur' => Auth::user()['nom'] ?? '',
        ]);
    }
}

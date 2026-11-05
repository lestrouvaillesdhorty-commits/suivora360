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
        Dossier::refuserSiAnnule($dossier);

        $demande = Demande::find((int) $dossier['demande_id']);
        $clients = Client::allForFiliale((int) $dossier['filiale_id']);
        $offresRetenues = Offre::retenuesForDossier((int) $dossier['id']);
        $offreRetenue = $offresRetenues[0] ?? null;
        $fraisCommuns = \App\Models\DossierFraisCommuns::forDossier((int) $dossier['id']);
        $calcAchat = \App\Models\DossierFraisCommuns::calculer($offresRetenues, $fraisCommuns);
        // Plusieurs fournisseurs retenus ou frais communs : le coût de chaque article porte sa part des frais.
        $modeGroupe = count($offresRetenues) > 1 || $calcAchat['total_communs'] > 0;

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

        // Nouvelle cotation : articles repris de l'offre retenue (prix d'achat unitaires en repère),
        // sinon des articles de la demande.
        $depuisOffre = false;
        if (!$cotationPrecedente) {
            foreach ($offresRetenues as $oR) {
                if ($modeGroupe && !isset($calcAchat['offres'][(int) $oR['id']])) {
                    continue; // devise différente : non additionnée (signalée à l'écran)
                }
                $lignesOffre = \App\Models\OffreItem::forOffre((int) $oR['id']);
                $sommeMontants = 0.0;
                foreach ($lignesOffre as $oi) {
                    $sommeMontants += (float) ($oi['montant'] ?? 0);
                }
                $extra = $modeGroupe ? (float) $calcAchat['offres'][(int) $oR['id']]['frais_propres'] + (float) $calcAchat['offres'][(int) $oR['id']]['part_communs'] : 0.0;
                foreach ($lignesOffre as $oi) {
                    $cout = $oi['prix_unitaire'];
                    if ($extra > 0.004 && $sommeMontants > 0 && (float) ($oi['montant'] ?? 0) > 0 && (float) $oi['quantite'] > 0) {
                        $cout = round((float) $oi['prix_unitaire'] + $extra * ((float) $oi['montant'] / $sommeMontants) / (float) $oi['quantite'], 4);
                    }
                    $itemsPrecedents[] = ['designation' => $oi['designation'], 'quantite' => $oi['quantite'], 'unite' => (string) ($oi['unite'] ?? ''), 'prix_unitaire' => $oi['prix_unitaire'], 'cout' => $cout,
                        'conditionnement' => $oi['conditionnement'] ?? '', 'reference' => $oi['reference'] ?? '', 'marque' => $oi['marque'] ?? '', 'caracteristiques' => $oi['caracteristiques'] ?? ''];
                }
            }
            if ($itemsPrecedents) {
                $depuisOffre = !empty($itemsPrecedents);
            }
            if (empty($itemsPrecedents) && $demande) {
                foreach (\App\Models\DemandeArticle::forDemande((int) $demande['id']) as $da) {
                    $itemsPrecedents[] = ['designation' => $da['designation'], 'quantite' => $da['quantite'], 'unite' => (string) ($da['unite'] ?? ''), 'prix_unitaire' => null,
                        'conditionnement' => $da['conditionnement'] ?? '', 'reference' => $da['reference'] ?? '', 'marque' => $da['marque'] ?? '', 'caracteristiques' => $da['caracteristiques'] ?? ''];
                }
            }
        }

        // Taux de change proposé (offre → devise du client) à partir des paramètres de la filiale.
        $param = \App\Models\Parametres::forFiliale((int) $dossier['filiale_id']);
        View::render('cotations/create', [
            'tauxEurFcfa' => (float) ($param['taux_eur_fcfa'] ?? 655.957),
            'tvaDefaut' => (float) ($param['tva_defaut_pourcentage'] ?? 20),
            'margeDefaut' => (float) ($param['marge_defaut_pourcentage'] ?? 0),
            'depuisOffre' => $depuisOffre,
            'dossier' => $dossier,
            'demande' => $demande,
            'clients' => $clients,
            'offreRetenue' => $offreRetenue,
            'offresRetenues' => $offresRetenues,
            'calcAchat' => $calcAchat,
            'modeGroupe' => $modeGroupe,
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
        Dossier::refuserSiAnnule($dossier);

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
                'conditionnement' => $_POST['item_conditionnement'][$i] ?? '',
                'reference' => $_POST['item_reference'][$i] ?? '',
                'marque' => $_POST['item_marque'][$i] ?? '',
                'caracteristiques' => $_POST['item_caracteristiques'][$i] ?? '',
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

        // Le coût d'achat est saisi dans la devise de l'offre : on le convertit au taux choisi dans la devise du devis.
        $origine = (string) ($_POST['achat_origine'] ?? '');
        $taux = (float) str_replace(',', '.', (string) ($_POST['taux_change'] ?? ''));
        if ($origine !== '') {
            $_POST['montant_achat'] = round((float) $origine * ($taux > 0 ? $taux : 1), 2);
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
        if ($nouveauStatut === 'acceptee') {
            Dossier::avancerA((int) $cotation['dossier_id'], 'commande'); // parcours guidé : cotation acceptée → étape Commande
        }
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

    /** Charge la cotation et vérifie l'accès ; renvoie null (et écrit le 404) sinon. */
    private function cotationAccessible(array $params): ?array
    {
        $cotation = Cotation::findWithDetails((int) $params['id']);
        if (!$cotation || !Cotation::userCanAccess(Auth::user(), $cotation)) {
            http_response_code(404);
            View::render('errors/404');
            return null;
        }
        return $cotation;
    }

    private function donneesDevis(array $cotation): array
    {
        $client = Client::find((int) $cotation['client_id']) ?: [];
        $filiale = \App\Models\Filiale::find((int) $cotation['filiale_id']) ?: [];
        $info = \App\Models\EntrepriseInfo::forFiliale((int) $cotation['filiale_id']);
        $emetteur = ['nom' => trim((string) ($info['raison_sociale'] ?? '')) ?: (string) ($filiale['nom'] ?? '')] + $info;
        return [CotationItem::forCotation((int) $cotation['id']), $client, $emetteur];
    }

    /** Document « devis » imprimable (même contenu que le PDF). */
    public function apercu(array $params): void
    {
        $cotation = $this->cotationAccessible($params);
        if (!$cotation) {
            return;
        }
        [$items, $client, $emetteur] = $this->donneesDevis($cotation);
        View::renderPlain('cotations/apercu', [
            'cotation' => $cotation, 'items' => $items, 'client' => $client, 'emetteur' => $emetteur,
            'totaux' => \App\Services\DevisPdf::totaux($cotation),
            'logoUri' => \App\Models\EntrepriseInfo::logoDataUri($emetteur),
            'photos' => \App\Services\PhotoProduit::urisDevis($cotation),
        ]);
    }

    public function pdf(array $params): void
    {
        $cotation = $this->cotationAccessible($params);
        if (!$cotation) {
            return;
        }
        [$items, $client, $emetteur] = $this->donneesDevis($cotation);
        \App\Services\RecapPdf::sortir(\App\Services\DevisPdf::generer($cotation, $items, $emetteur, $client), \App\Services\DevisPdf::nomFichier($cotation));
    }

    /** Page « Envoyer le devis » : vérifier, télécharger / partager le PDF, message WhatsApp ou e-mail, puis marquer comme envoyée. */
    public function envoyer(array $params): void
    {
        $cotation = $this->cotationAccessible($params);
        if (!$cotation) {
            return;
        }
        $user = Auth::user();
        [$items, $client, $emetteur] = $this->donneesDevis($cotation);
        $prenom = '';
        $saluts = ['fr' => 'Bonjour', 'en' => 'Hello'];
        $nomClient = trim((string) ($client['nom'] ?? ''));
        $variantes = [];
        foreach (['fr', 'en'] as $lg) {
            $variantes[$lg] = \App\Services\MessageDevis::construire($cotation, $items, $emetteur['nom'], $lg, $saluts[$lg], (string) ($user['nom'] ?? ''));
        }
        $domaineUser = strtolower(substr(strrchr((string) ($user['email'] ?? ''), '@') ?: '', 1));
        $messagerie = 'mailto';
        if (in_array($domaineUser, ['gmail.com', 'googlemail.com'], true)) {
            $messagerie = 'gmail';
        } elseif (in_array($domaineUser, ['outlook.com', 'outlook.fr', 'hotmail.com', 'hotmail.fr', 'live.com', 'live.fr', 'msn.com'], true)) {
            $messagerie = 'outlook';
        }
        View::render('cotations/envoyer', [
            'cotation' => $cotation, 'client' => $client, 'variantes' => $variantes, 'messagerie' => $messagerie,
            'emailClient' => trim((string) ($client['email'] ?? '')),
            'telephoneClient' => preg_replace('/[^0-9]/', '', (string) ($client['telephone'] ?? '')),
            'nomFichier' => \App\Services\DevisPdf::nomFichier($cotation),
        ]);
    }

    public function marquerEnvoyee(array $params): void
    {
        if (!Auth::canGererCotations()) {
            http_response_code(403);
            View::render('errors/403');
            return;
        }
        $cotation = $this->cotationAccessible($params);
        if (!$cotation) {
            return;
        }
        if (Auth::verifyCsrf($_POST['csrf_token'] ?? null) && in_array($cotation['statut'], ['brouillon', 'envoyee'], true)) {
            if ($cotation['statut'] === 'brouillon') {
                Cotation::updateStatut((int) $cotation['id'], 'envoyee');
                AuditLog::log((int) $cotation['filiale_id'], (int) Auth::user()['id'], 'envoi_cotation', 'cotation', (int) $cotation['id'], (string) ($_POST['canal'] ?? ''));
            }
            View::flash('succes', 'Devis ' . $cotation['reference'] . ' marqué comme envoyé.');
        }
        header('Location: /index.php?r=cotations/' . (int) $cotation['id']);
        exit;
    }
}

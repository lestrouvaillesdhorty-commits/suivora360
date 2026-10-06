<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Storage;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Commande;
use App\Models\ConsultationFournisseur;
use App\Models\Cotation;
use App\Models\Demande;
use App\Models\DemandeArticle;
use App\Models\Dossier;
use App\Models\DossierBudget;
use App\Models\DossierCollaborateur;
use App\Models\DossierPieceJointe;
use App\Models\Facture;
use App\Models\Filiale;
use App\Models\Fournisseur;
use App\Models\Notification;
use App\Models\Offre;
use App\Models\Utilisateur;

class DossierController
{
    public function index(): void
    {
        $user = Auth::user();
        $filters = [
            'statut' => $_GET['statut'] ?? null,
            'etape' => $_GET['etape'] ?? null,
            'responsable_id' => $_GET['responsable_id'] ?? null,
            'recherche' => $_GET['q'] ?? null,
            'date_debut' => $_GET['date_debut'] ?? null,
            'date_fin' => $_GET['date_fin'] ?? null,
        ];
        $dossiers = Dossier::visibleFor($user, $filters);
        $utilisateurs = Utilisateur::allForOrganisation((int) $user['organisation_id']);
        View::render('folders/index', [
            'dossiers' => $dossiers,
            'filters' => $filters,
            'utilisateurs' => $utilisateurs,
        ]);
    }

    /**
     * Export CSV de la liste filtrée (mêmes filtres que index()), même
     * pattern que DemandeController::exportCsv() / PilotageController —
     * pas de bibliothèque .xlsx disponible sans Composer.
     */
    public function exportCsv(): void
    {
        $user = Auth::user();
        $filters = [
            'statut' => $_GET['statut'] ?? null,
            'etape' => $_GET['etape'] ?? null,
            'responsable_id' => $_GET['responsable_id'] ?? null,
            'recherche' => $_GET['q'] ?? null,
            'date_debut' => $_GET['date_debut'] ?? null,
            'date_fin' => $_GET['date_fin'] ?? null,
        ];
        $dossiers = Dossier::visibleFor($user, $filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="dossiers_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['Dossiers — Suivora360'], ';', '"', '\\');
        fputcsv($out, ['Exporté le', date('d/m/Y H:i')], ';', '"', '\\');
        fputcsv($out, ['Nombre de résultats', count($dossiers)], ';', '"', '\\');
        fputcsv($out, [], ';', '"', '\\');
        fputcsv($out, ['Référence', 'Objet', 'Filiale', 'Étape', 'Responsable', 'Priorité', 'Échéance', 'Statut'], ';', '"', '\\');
        foreach ($dossiers as $d) {
            fputcsv($out, [
                $d['reference'],
                $d['objet'],
                $d['filiale_nom'],
                Dossier::ETAPES_LABELS[$d['etape']] ?? $d['etape'],
                Utilisateur::nameOf($d['responsable_id']),
                Demande::PRIORITES[$d['priorite']] ?? $d['priorite'],
                $d['echeance'] ? date('d/m/Y', strtotime($d['echeance'])) : '',
                ucfirst($d['statut']),
            ], ';', '"', '\\');
        }
        fclose($out);
        exit;
    }

    /**
     * Formulaire d'import CSV des Dossiers. Un dossier ne pouvant exister
     * sans demande d'origine (demande_id NOT NULL UNIQUE en base), chaque
     * ligne importée crée une demande, la qualifie automatiquement par la
     * voie "Reprise hors Suivora" (déjà utilisée pour les dossiers démarrés
     * avant Suivora360), puis crée le dossier — même enchaînement que
     * DemandeController::qualifierReprise() + creerDossier(), en un seul
     * import en masse plutôt que 2 écrans par dossier.
     */
    public function importForm(): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        View::render('folders/import', [
            'filiales' => Filiale::visibleFor($user),
        ]);
    }

    public function importModele(): void
    {
        Auth::requireWrite();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="modele_import_dossiers.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['objet', 'activite', 'filiale', 'takeover_stage', 'original_started_at', 'external_source', 'external_reference', 'responsable', 'priorite', 'echeance', 'notes'], ';', '"', '\\');
        fputcsv($out, ['Transport routier Douala-Yaoundé (en cours)', 'Transport et logistique', 'Siège', 'suivi_operationnel', date('d/m/Y', strtotime('-30 days')), 'Suivi Excel', 'REF-ANCIEN-0123', '', 'normale', '', 'Repris depuis l\'ancien suivi'], ';', '"', '\\');
        fclose($out);
        exit;
    }

    /**
     * Traite le fichier CSV : chaque ligne valide devient demande + reprise
     * hors Suivora + dossier, dans une transaction par ligne (toute erreur
     * sur l'une des 3 étapes annule la ligne entière plutôt que de laisser
     * une demande qualifiée sans dossier).
     */
    public function importStore(): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=dossiers/importer');
            exit;
        }
        $user = Auth::user();

        if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
            View::flash('erreur', 'Merci de sélectionner un fichier CSV.');
            header('Location: /index.php?r=dossiers/importer');
            exit;
        }

        $handle = fopen($_FILES['fichier']['tmp_name'], 'r');
        if (!$handle) {
            View::flash('erreur', 'Impossible de lire le fichier.');
            header('Location: /index.php?r=dossiers/importer');
            exit;
        }

        $filialesAccessibles = Filiale::visibleFor($user);
        $filialeParNom = [];
        foreach ($filialesAccessibles as $f) {
            $filialeParNom[mb_strtolower(trim($f['nom']))] = $f;
        }
        $filialeParDefaut = count($filialesAccessibles) === 1 ? $filialesAccessibles[0] : null;

        $activitesValides = [];
        foreach (Demande::ACTIVITES as $a) {
            $activitesValides[mb_strtolower($a)] = $a;
        }

        $utilisateursOrga = Utilisateur::allForOrganisation((int) $user['organisation_id']);
        $utilisateurParNom = [];
        foreach ($utilisateursOrga as $u) {
            $utilisateurParNom[mb_strtolower(trim($u['nom']))] = $u;
        }

        $etapesValides = [];
        foreach (Demande::TAKEOVER_STAGES as $code => $label) {
            $etapesValides[mb_strtolower($code)] = $code;
            $etapesValides[mb_strtolower($label)] = $code;
        }

        $premiereLigne = fgets($handle);
        rewind($handle);
        $delimiteur = substr_count((string) $premiereLigne, ';') >= substr_count((string) $premiereLigne, ',') ? ';' : ',';

        $entetes = fgetcsv($handle, 0, $delimiteur);
        if ($entetes === false) {
            fclose($handle);
            View::flash('erreur', 'Fichier vide ou illisible.');
            header('Location: /index.php?r=dossiers/importer');
            exit;
        }
        $entetes = array_map(static function ($h) {
            return mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $h)));
        }, $entetes);

        $alias = [
            'objet' => ['objet'],
            'activite' => ['activite', 'activité'],
            'filiale' => ['filiale'],
            'takeover_stage' => ['takeover_stage', 'etape actuelle', 'étape actuelle', 'etape'],
            'original_started_at' => ['original_started_at', 'date reelle de debut', 'date réelle de début'],
            'external_source' => ['external_source', 'origine externe'],
            'external_reference' => ['external_reference', 'reference externe', 'référence externe'],
            'responsable' => ['responsable'],
            'priorite' => ['priorite', 'priorité'],
            'echeance' => ['echeance', 'échéance'],
            'notes' => ['notes'],
        ];
        $index = [];
        foreach ($alias as $champ => $noms) {
            foreach ($noms as $n) {
                $pos = array_search($n, $entetes, true);
                if ($pos !== false) {
                    $index[$champ] = $pos;
                    break;
                }
            }
        }

        if (!isset($index['objet'])) {
            fclose($handle);
            View::flash('erreur', "La colonne \"objet\" est obligatoire dans le fichier (voir le modèle à télécharger).");
            header('Location: /index.php?r=dossiers/importer');
            exit;
        }

        $resultats = ['crees' => 0, 'erreurs' => []];
        $numeroLigne = 1;
        while (($row = fgetcsv($handle, 0, $delimiteur)) !== false) {
            $numeroLigne++;
            if (count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $val = static function (string $champ) use ($index, $row): string {
                return isset($index[$champ], $row[$index[$champ]]) ? trim((string) $row[$index[$champ]]) : '';
            };

            $objet = $val('objet');
            if ($objet === '') {
                $resultats['erreurs'][] = "Ligne $numeroLigne : objet manquant — ligne ignorée.";
                continue;
            }

            $nomFiliale = $val('filiale');
            $filialeId = null;
            if ($nomFiliale !== '' && isset($filialeParNom[mb_strtolower($nomFiliale)])) {
                $filialeId = (int) $filialeParNom[mb_strtolower($nomFiliale)]['id'];
            } elseif ($nomFiliale === '' && $filialeParDefaut) {
                $filialeId = (int) $filialeParDefaut['id'];
            }
            if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
                $resultats['erreurs'][] = "Ligne $numeroLigne : filiale "
                    . ($nomFiliale !== '' ? "\"$nomFiliale\"" : '(non précisée)')
                    . ' introuvable ou inaccessible — ligne ignorée.';
                continue;
            }

            $activiteBrute = $val('activite');
            $activite = $activitesValides[mb_strtolower($activiteBrute)] ?? '';
            if ($activite === '') {
                $resultats['erreurs'][] = "Ligne $numeroLigne : activité "
                    . ($activiteBrute !== '' ? "\"$activiteBrute\" non reconnue" : 'manquante')
                    . ' — ligne ignorée.';
                continue;
            }

            $responsableId = null;
            $nomResponsable = $val('responsable');
            if ($nomResponsable !== '' && isset($utilisateurParNom[mb_strtolower($nomResponsable)])) {
                $responsableId = (int) $utilisateurParNom[mb_strtolower($nomResponsable)]['id'];
            }

            $takeoverStageBrut = $val('takeover_stage');
            $takeoverStage = $etapesValides[mb_strtolower($takeoverStageBrut)] ?? '';

            $priorite = in_array($val('priorite'), ['normale', 'haute', 'critique'], true) ? $val('priorite') : 'normale';
            $echeance = $this->parseDateImportDossier($val('echeance'));
            $originalStartedAt = $this->parseDateImportDossier($val('original_started_at'));

            // Pas de transaction englobante : Dossier::createFromDemande()
            // ouvre déjà sa propre transaction (PDO ne supporte pas
            // l'imbrication). Même tolérance que le parcours manuel
            // existant (qualifierReprise() puis creerDossier() sur 2 écrans
            // séparés) : si la création du dossier échoue après coup, la
            // demande reste qualifiée et peut être rattachée manuellement
            // depuis sa fiche (bouton "Créer le dossier").
            $demandeId = null;
            try {
                $demandeId = Demande::create([
                    'filiale_id' => $filialeId,
                    'objet' => $objet,
                    'canal' => 'Import (reprise)',
                    'activite' => $activite,
                    'responsable_id' => $responsableId,
                    'priorite' => $priorite,
                    'echeance' => $echeance,
                ]);

                Demande::qualifyTakeover($demandeId, [
                    'responsable_id' => $responsableId,
                    'priorite' => $priorite,
                    'echeance' => $echeance,
                    'notes' => $val('notes'),
                    'original_started_at' => $originalStartedAt,
                    'external_source' => $val('external_source'),
                    'external_reference' => $val('external_reference'),
                    'takeover_stage' => $takeoverStage,
                ], (int) $user['id']);
                AuditLog::log($filialeId, (int) $user['id'], 'qualification_reprise', 'demande', $demandeId, 'Voie : Reprise hors Suivora (import CSV)');

                $dossierId = Dossier::createFromDemande($demandeId, [
                    'responsable_id' => $responsableId,
                    'priorite' => $priorite,
                    'echeance' => $echeance,
                ]);
                AuditLog::log($filialeId, (int) $user['id'], 'creation_dossier', 'dossier', $dossierId, 'Depuis demande #' . $demandeId . ' (import CSV)');

                $resultats['crees']++;
            } catch (\Throwable $e) {
                $suffixe = $demandeId
                    ? " (demande #$demandeId créée et qualifiée — le dossier peut être créé manuellement depuis sa fiche)"
                    : '';
                $resultats['erreurs'][] = "Ligne $numeroLigne : erreur lors de la création — " . $e->getMessage() . $suffixe;
            }
        }
        fclose($handle);

        View::render('folders/import_resultat', ['resultats' => $resultats]);
    }

    private function parseDateImportDossier(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date instanceof \DateTime) {
                return $date->format('Y-m-d');
            }
        }
        return null;
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

        $demande = Demande::find((int) $dossier['demande_id']);
        $articles = DemandeArticle::forDemande($dossier['demande_id']);
        $filiale = Filiale::find((int) $dossier['filiale_id']);

        $consultations = ConsultationFournisseur::forDossier((int) $dossier['id']);
        $offres = Offre::forDossier((int) $dossier['id']);
        $offreRetenue = Offre::retenueForDossier((int) $dossier['id']);
        $cotation = Cotation::latestForDossier((int) $dossier['id']);
        $cotationVersions = Cotation::versionsForDossier((int) $dossier['id']);
        $commande = Commande::findByDossier((int) $dossier['id']);
        $factures = Facture::forDossier((int) $dossier['id']);
        $historique = AuditLog::forDossier((int) $dossier['id']);
        $piecesJointes = DossierPieceJointe::forDossier((int) $dossier['id']);
        $collaborateurs = DossierCollaborateur::forDossier((int) $dossier['id']);
        // Candidats à l'assignation : utilisateurs actifs de l'organisation
        // ayant déjà accès à la filiale du dossier (sinon ils ne pourraient
        // pas ouvrir le dossier une fois assignés).
        $collaborateursPossibles = array_values(array_filter(
            Utilisateur::allForOrganisation((int) $user['organisation_id']),
            fn($u) => (bool) $u['actif'] && Filiale::userCanAccess($u, (int) $dossier['filiale_id'])
        ));
        // Liste complète des fournisseurs de la filiale (pas seulement ceux
        // déjà consultés sur ce dossier) : un collaborateur peut être assigné
        // à un fournisseur qu'il va lui-même consulter, pas seulement suivre
        // une consultation déjà en cours (décision Marie Laure, 02/10).
        $fournisseursFiliale = Fournisseur::allForFiliale((int) $dossier['filiale_id']);

        // [ajouté 06/10, report de la maquette Dossiers] Fiche à onglets
        // (Synthèse / Besoin / Achats et offres / Cotations client /
        // Exécution / Documents / Équipe et historique) au lieu d'une page
        // unique — onglet choisi par ?onglet=..., "synthese" par défaut.
        // Toutes les données ci-dessus restent chargées en une seule requête
        // par entité (comme avant), seul l'affichage se répartit par onglet.
        $onglet = $_GET['onglet'] ?? 'synthese';
        if (!in_array($onglet, Dossier::ONGLETS, true)) {
            $onglet = 'synthese';
        }

        // [ajouté 06/10, étape 2 du découpage] L'onglet "Achats et offres"
        // se scinde lui-même en 3 sous-onglets (Consultations / Offres
        // reçues / Comparaison), conforme à la maquette — même mécanisme
        // que $onglet, mais à un niveau en dessous et seulement pertinent
        // pour cet onglet précis.
        $sousOngletsAchats = ['consultations', 'offres'];
        $sousOngletAchats = $_GET['sous'] ?? 'consultations';
        if (!in_array($sousOngletAchats, $sousOngletsAchats, true)) {
            $sousOngletAchats = 'consultations';
        }

        View::render('folders/show', [
            'dossier' => $dossier,
            'onglet' => $onglet,
            'sousOngletAchats' => $sousOngletAchats,
            'demande' => $demande,
            'articles' => $articles,
            'filiale' => $filiale,
            'consultations' => $consultations,
            'offres' => $offres,
            'offreRetenue' => $offreRetenue,
            'cotation' => $cotation,
            'cotationVersions' => $cotationVersions,
            'commande' => $commande,
            'factures' => $factures,
            'historique' => $historique,
            'piecesJointes' => $piecesJointes,
            'collaborateurs' => $collaborateurs,
            'collaborateursPossibles' => $collaborateursPossibles,
            'fournisseursFiliale' => $fournisseursFiliale,
            'budgetLignes' => DossierBudget::forDossier((int) $dossier['id']),
        ]);
    }

    public function updateEtape(array $params): void
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

        Dossier::updateEtape((int) $dossier['id'], $_POST['etape'] ?? '');
        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'maj_etape_dossier', 'dossier', (int) $dossier['id'], $_POST['etape'] ?? '');
        View::flash('succes', 'Étape mise à jour.');
        header('Location: /index.php?r=dossiers/' . $dossier['id']);
        exit;
    }

    /**
     * Change le type de dossier (section 9 de la feuille de route) :
     * déduit automatiquement de l'Activité à la création, mais reste
     * modifiable à tout moment — pas de restriction de rôle particulière.
     * Toute modification est tracée en audit (ancien → nouveau type), même
     * esprit que "Revenir sur une décision" au Comparateur.
     */
    public function changerType(array $params): void
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

        $nouveauType = $_POST['type_dossier'] ?? '';
        if (!in_array($nouveauType, Dossier::TYPES, true)) {
            View::flash('erreur', 'Type de dossier invalide.');
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }

        $ancienType = $dossier['type_dossier'] ?? 'autre';
        if ($nouveauType !== $ancienType) {
            Dossier::updateType((int) $dossier['id'], $nouveauType);
            $detail = (Dossier::TYPES_LABELS[$ancienType] ?? $ancienType) . ' → ' . (Dossier::TYPES_LABELS[$nouveauType] ?? $nouveauType);
            AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'maj_type_dossier', 'dossier', (int) $dossier['id'], $detail);
            View::flash('succes', 'Type de dossier mis à jour.');
        }
        header('Location: /index.php?r=dossiers/' . $dossier['id']);
        exit;
    }

    /**
     * [ajouté 06/10, report de la maquette Dossiers] Onglet Exécution,
     * déclinaison Prestation entreprise — type de prestation, case "visite
     * terrain nécessaire" et les 2 champs de mesure adaptatifs (voir
     * Dossier::TYPES_PRESTATION_CHAMPS). N'a d'effet que sur un dossier de
     * type "prestation_entreprise" ; appelée sur un autre type elle ne
     * fait rien de visible (les champs n'existent pas sur ces écrans) mais
     * n'est pas bloquée non plus, par cohérence avec le reste du
     * contrôleur qui ne verrouille pas ces champs côté serveur.
     */
    public function updatePrestation(array $params): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=dossiers/' . $params['id'] . '&onglet=execution');
            exit;
        }

        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $typePrestation = $_POST['type_prestation'] ?? '';
        $typePrestation = $typePrestation === '' ? null : $typePrestation;
        if ($typePrestation !== null && !in_array($typePrestation, Dossier::TYPES_PRESTATION, true)) {
            View::flash('erreur', 'Type de prestation invalide.');
            header('Location: /index.php?r=dossiers/' . $dossier['id'] . '&onglet=execution');
            exit;
        }
        $visiteTerrainNecessaire = !empty($_POST['visite_terrain_necessaire']);
        $mesure1 = trim((string) ($_POST['prestation_mesure_1'] ?? ''));
        $mesure2 = trim((string) ($_POST['prestation_mesure_2'] ?? ''));

        // [ajouté 06/10, migrate_v16.php] Champs de la carte "Évaluation du
        // besoin" de la maquette — report fidèle demandé par Marie Laure.
        $champsEvaluation = ['site', 'technicien', 'delai_estime', 'constat', 'contraintes'];
        $evaluation = [];
        foreach ($champsEvaluation as $champ) {
            $valeur = trim((string) ($_POST['prestation_' . $champ] ?? ''));
            $evaluation[$champ] = $valeur !== '' ? $valeur : null;
        }

        Dossier::updatePrestation(
            (int) $dossier['id'],
            $typePrestation,
            $visiteTerrainNecessaire,
            $mesure1 !== '' ? $mesure1 : null,
            $mesure2 !== '' ? $mesure2 : null,
            $evaluation
        );
        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'maj_prestation_dossier', 'dossier', (int) $dossier['id'], Dossier::TYPES_PRESTATION_LABELS[$typePrestation] ?? '—');
        View::flash('succes', 'Évaluation de la prestation mise à jour.');
        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '&onglet=execution');
        exit;
    }

    /**
     * [ajouté 06/10, étape 5, migrate_v20.php] Enregistre la carte "Budget —
     * prévisionnel vs réalisé" de l'onglet Exécution (6 catégories d'un coup).
     * Montants : espaces et virgule décimale acceptés ; vide = 0 pour le
     * prévisionnel, "pas encore renseigné" pour le réalisé.
     */
    public function updateBudget(array $params): void
    {
        Auth::requireWrite();
        $retour = '/index.php?r=dossiers/' . (int) $params['id'] . '&onglet=execution';
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: ' . $retour);
            exit;
        }

        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $devise = $_POST['devise'] ?? 'FCFA';
        if (!in_array($devise, DossierBudget::DEVISES, true)) {
            $devise = 'FCFA';
        }
        $parse = function ($v) { // null = vide, false = invalide, sinon float
            $v = str_replace([' ', "\u{00A0}"], '', trim((string) $v));
            $v = str_replace(',', '.', $v);
            if ($v === '') {
                return null;
            }
            if (!is_numeric($v) || (float) $v < 0 || (float) $v > 999999999999) {
                return false;
            }
            return round((float) $v, 2);
        };
        $data = [];
        foreach (array_keys(DossierBudget::CATEGORIES) as $cat) {
            $prev = $parse($_POST['previsionnel'][$cat] ?? '');
            $real = $parse($_POST['realise'][$cat] ?? '');
            if ($prev === false || $real === false) {
                View::flash('erreur', 'Montant invalide : saisissez un nombre positif (ex. 180000).');
                header('Location: ' . $retour);
                exit;
            }
            $detail = trim((string) ($_POST['detail'][$cat] ?? ''));
            $data[$cat] = [
                'previsionnel' => $prev ?? 0.0,
                'realise' => $real,
                'detail' => $detail !== '' ? mb_substr($detail, 0, 255) : null,
            ];
        }

        try {
            DossierBudget::enregistrer((int) $dossier['id'], (int) $dossier['filiale_id'], $devise, $data, (int) $user['id']);
        } catch (\Throwable $e) {
            View::flash('erreur', "Le budget n'a pas pu être enregistré (migration v20 lancée ?).");
            header('Location: ' . $retour);
            exit;
        }
        $tot = DossierBudget::totaux(array_map(fn($d) => ['montant_previsionnel' => $d['previsionnel'], 'montant_realise' => $d['realise']], $data));
        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'maj_budget_dossier', 'dossier', (int) $dossier['id'], number_format($tot['previsionnel'], 0, ',', ' ') . ' ' . $devise . ' prévus');
        View::flash('succes', 'Budget enregistré.');
        header('Location: ' . $retour);
        exit;
    }

    public function addArticle(array $params): void
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

        if (trim($_POST['designation'] ?? '') !== '') {
            // [corrigé 06/10] 'conditionnement' (colonne ajoutée en migrate_v14
            // pour le module Demandes) n'était jamais transmis depuis ce
            // formulaire-ci alors que la colonne existe déjà — corrigé au
            // passage en ajoutant aussi 'caracteristiques' (migrate_v16,
            // report de la maquette Besoin.dc.html).
            DemandeArticle::create((int) $dossier['demande_id'], [
                'designation' => trim($_POST['designation']),
                'quantite' => $_POST['quantite'] ?? null,
                'unite' => trim($_POST['unite'] ?? ''),
                'conditionnement' => trim($_POST['conditionnement'] ?? ''),
                'reference' => trim($_POST['reference'] ?? ''),
                'marque' => trim($_POST['marque'] ?? ''),
                'caracteristiques' => trim($_POST['caracteristiques'] ?? ''),
            ]);
            View::flash('succes', 'Article ajouté.');
        }

        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '&onglet=besoin');
        exit;
    }

    public function updateNotes(array $params): void
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

        Dossier::updateNotes((int) $dossier['id'], trim($_POST['notes'] ?? ''));
        View::flash('succes', 'Notes enregistrées.');
        header('Location: /index.php?r=dossiers/' . $dossier['id']);
        exit;
    }

    /**
     * Pièces jointes du dossier (factures pro forma, bons de commande,
     * connaissements...). Même mécanique que pour les demandes : fichier
     * stocké hors du webroot, accès uniquement via ce contrôleur.
     */
    public function uploadPiece(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        // [corrigé 06/10] Manquait partout ci-dessous : la redirection ne
        // reprécisait jamais l'onglet, donc tout retombait sur "Synthèse"
        // après un envoi — gênant depuis que ce même formulaire d'upload
        // est aussi réutilisé sur l'onglet Exécution (photos de visite
        // terrain). $ongletRetour, soumis en champ caché par le formulaire
        // d'origine, ramène sur le bon onglet ("documents" par défaut).
        $ongletsValides = Dossier::ONGLETS;
        $ongletRetour = $_POST['onglet_retour'] ?? 'documents';
        if (!in_array($ongletRetour, $ongletsValides, true)) {
            $ongletRetour = 'documents';
        }
        $retour = '/index.php?r=dossiers/' . $dossier['id'] . '&onglet=' . $ongletRetour;

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: ' . $retour);
            exit;
        }

        if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] === UPLOAD_ERR_NO_FILE) {
            View::flash('erreur', 'Aucun fichier sélectionné.');
            header('Location: ' . $retour);
            exit;
        }

        $fichier = $_FILES['fichier'];
        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            View::flash('erreur', "Échec de l'envoi du fichier.");
            header('Location: ' . $retour);
            exit;
        }
        if ($fichier['size'] > DossierPieceJointe::TAILLE_MAX) {
            View::flash('erreur', 'Le fichier dépasse la taille maximale autorisée (10 Mo).');
            header('Location: ' . $retour);
            exit;
        }

        $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, DossierPieceJointe::EXTENSIONS_AUTORISEES, true)) {
            View::flash('erreur', 'Type de fichier non autorisé (formats acceptés : ' . implode(', ', DossierPieceJointe::EXTENSIONS_AUTORISEES) . ').');
            header('Location: ' . $retour);
            exit;
        }

        $nomFichier = bin2hex(random_bytes(16)) . '.' . $extension;
        $chemin = Storage::path('uploads/dossiers/' . $dossier['id'] . '/' . $nomFichier);
        if (!move_uploaded_file($fichier['tmp_name'], $chemin)) {
            View::flash('erreur', "Impossible d'enregistrer le fichier.");
            header('Location: ' . $retour);
            exit;
        }

        DossierPieceJointe::create([
            'dossier_id' => $dossier['id'],
            'nom_original' => $fichier['name'],
            'nom_fichier' => $nomFichier,
            'taille' => $fichier['size'],
            'type_mime' => $fichier['type'] ?: 'application/octet-stream',
            'uploaded_by' => (int) $user['id'],
            'categorie' => $_POST['categorie'] ?? 'autre',
            'visibilite' => $_POST['visibilite'] ?? 'interne',
        ]);
        AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'ajout_piece_jointe', 'dossier', (int) $dossier['id'], $fichier['name']);

        View::flash('succes', 'Pièce jointe ajoutée.');
        header('Location: ' . $retour);
        exit;
    }

    public function telechargerPiece(array $params): void
    {
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $piece = DossierPieceJointe::find((int) $params['pieceId']);
        if (!$piece || (int) $piece['dossier_id'] !== (int) $dossier['id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $chemin = Storage::path('uploads/dossiers/' . $dossier['id'] . '/' . $piece['nom_fichier']);
        if (!is_file($chemin)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $disposition = (!empty($_GET['apercu']) && Storage::estPrevisualisable($piece['type_mime'])) ? 'inline' : 'attachment';
        header('Content-Type: ' . $piece['type_mime']);
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($piece['nom_original']) . '"');
        header('Content-Length: ' . filesize($chemin));
        readfile($chemin);
        exit;
    }

    public function supprimerPiece(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=dossiers/' . $dossier['id']);
            exit;
        }

        $piece = DossierPieceJointe::find((int) ($params['pieceId'] ?? 0));
        if ($piece && (int) $piece['dossier_id'] === (int) $dossier['id']) {
            $chemin = Storage::path('uploads/dossiers/' . $dossier['id'] . '/' . $piece['nom_fichier']);
            if (is_file($chemin)) {
                unlink($chemin);
            }
            DossierPieceJointe::delete($piece['id']);
            AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'suppression_piece_jointe', 'dossier', (int) $dossier['id'], $piece['nom_original']);
            View::flash('succes', 'Pièce jointe supprimée.');
        }

        // [corrigé 06/10] Renvoie sur l'onglet Documents (retombait sur Synthèse).
        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '&onglet=documents');
        exit;
    }

    /**
     * [ajouté 06/10, étape 4] Reclasse un document déjà ajouté (catégorie +
     * visibilité Interne / Visible client) — utile pour les pièces jointes
     * antérieures à la migration v19, qui prennent "Autres documents / Interne".
     */
    public function classerPiece(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $dossier = Dossier::find((int) $params['id']);
        if (!$dossier || !Dossier::userCanAccess($user, $dossier)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $retour = '/index.php?r=dossiers/' . $dossier['id'] . '&onglet=documents';
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: ' . $retour);
            exit;
        }
        $piece = DossierPieceJointe::find((int) ($params['pieceId'] ?? 0));
        if ($piece && (int) $piece['dossier_id'] === (int) $dossier['id']) {
            $categorie = DossierPieceJointe::categorieValide($_POST['categorie'] ?? null);
            $visibilite = DossierPieceJointe::visibiliteValide($_POST['visibilite'] ?? null);
            DossierPieceJointe::classer((int) $piece['id'], $categorie, $visibilite);
            AuditLog::log((int) $dossier['filiale_id'], (int) $user['id'], 'classement_piece_jointe', 'dossier', (int) $dossier['id'], $piece['nom_original'] . ' : ' . $categorie . ' / ' . $visibilite);
            View::flash('succes', 'Document classé.');
        }
        header('Location: ' . $retour);
        exit;
    }

    /**
     * Associe un collaborateur additionnel au dossier, dédié à un
     * fournisseur précis de la filiale — pas forcément déjà consulté sur ce
     * dossier : le collaborateur peut être la personne qui va elle-même
     * consulter ce fournisseur (décision Marie Laure, 02/10 : l'assignation
     * doit pouvoir se faire dès le début du dossier, avant toute
     * consultation, pas seulement pour suivre une consultation en cours).
     * Notifie la personne assignée (cloche + email best-effort).
     */
    public function assignerCollaborateur(array $params): void
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

        $fournisseurId = (int) ($_POST['fournisseur_id'] ?? 0);
        $utilisateurId = (int) ($_POST['utilisateur_id'] ?? 0);

        $fournisseur = $fournisseurId > 0 ? Fournisseur::find($fournisseurId) : null;
        if (!$fournisseur || (int) $fournisseur['filiale_id'] !== (int) $dossier['filiale_id']) {
            View::flash('erreur', 'Fournisseur invalide : il doit appartenir à la filiale de ce dossier.');
            header('Location: /index.php?r=dossiers/' . $dossier['id'] . '&onglet=equipe');
            exit;
        }

        $collaborateur = Utilisateur::find($utilisateurId);
        if (
            !$collaborateur
            || (int) $collaborateur['organisation_id'] !== (int) $user['organisation_id']
            || !$collaborateur['actif']
        ) {
            View::flash('erreur', 'Utilisateur invalide.');
            header('Location: /index.php?r=dossiers/' . $dossier['id'] . '&onglet=equipe');
            exit;
        }
        if (!Filiale::userCanAccess($collaborateur, (int) $dossier['filiale_id'])) {
            View::flash('erreur', "Cette personne n'a pas accès à la filiale de ce dossier — donnez-lui d'abord accès depuis Paramètres > Utilisateurs.");
            header('Location: /index.php?r=dossiers/' . $dossier['id'] . '&onglet=equipe');
            exit;
        }

        DossierCollaborateur::assigner(
            (int) $dossier['id'],
            (int) $dossier['filiale_id'],
            $fournisseurId,
            $utilisateurId,
            (int) $user['id'],
            (string) ($_POST['role'] ?? '')
        );
        AuditLog::log(
            (int) $dossier['filiale_id'],
            (int) $user['id'],
            'collaborateur_assigne',
            'dossier',
            (int) $dossier['id'],
            $collaborateur['nom']
        );

        Notification::notifier(
            $collaborateur,
            (int) $dossier['filiale_id'],
            'collaborateur_assigne',
            'Nouveau dossier à suivre',
            'Vous avez été ajouté au suivi du dossier ' . $dossier['reference'] . ' (' . $dossier['objet'] . ')'
                . ' pour le fournisseur ' . ($fournisseur['nom'] ?? '—') . '.',
            '/index.php?r=dossiers/' . $dossier['id'],
            'dossier',
            (int) $dossier['id']
        );

        View::flash('succes', 'Collaborateur assigné et notifié.');
        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '&onglet=equipe');
        exit;
    }

    public function retirerCollaborateur(array $params): void
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

        $collaboration = DossierCollaborateur::find((int) ($params['collaborateurId'] ?? 0));
        if ($collaboration && (int) $collaboration['dossier_id'] === (int) $dossier['id']) {
            DossierCollaborateur::retirer((int) $collaboration['id']);
            AuditLog::log(
                (int) $dossier['filiale_id'],
                (int) $user['id'],
                'collaborateur_retire',
                'dossier',
                (int) $dossier['id'],
                Utilisateur::nameOf((int) $collaboration['utilisateur_id'])
            );
            View::flash('succes', 'Collaborateur retiré du dossier.');
        }

        header('Location: /index.php?r=dossiers/' . $dossier['id'] . '&onglet=equipe');
        exit;
    }
}

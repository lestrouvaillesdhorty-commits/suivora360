<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Storage;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Demande;
use App\Models\DemandeArticle;
use App\Models\DemandePieceJointe;
use App\Models\Dossier;
use App\Models\Filiale;
use App\Models\Utilisateur;
use App\Services\AiExtracteur;

class DemandeController
{
    /**
     * [réécrit 04/10, refonte du module Demandes demandée par Marie Laure]
     * Filtres combinables (filiale, activité, statut, responsable, priorité,
     * canal, période) + "vues rapides" (Toutes/À qualifier/Mes demandes/En
     * retard/Archivées) — ces vues ne sont qu'une manière pré-remplie
     * d'arriver sur les MÊMES filtres (statut=a_qualifier, responsable_id=
     * l'utilisateur courant, statut=en_retard, statut=archivee), donc
     * librement combinables avec les filtres détaillés plutôt que de
     * dupliquer la logique de requête. Les filtres choisis sont mémorisés en
     * session pour que "Retour aux demandes" (fiche, formulaire) y revienne
     * exactement comme laissé, sans dépendre du bouton précédent du
     * navigateur.
     */
    public function index(): void
    {
        $user = Auth::user();

        $filters = [
            'filiale_id' => $_GET['filiale_id'] ?? null,
            'activite' => $_GET['activite'] ?? null,
            'statut' => $_GET['statut'] ?? null,
            'responsable_id' => $_GET['responsable_id'] ?? null,
            'priorite' => $_GET['priorite'] ?? null,
            'canal' => $_GET['canal'] ?? null,
            'recherche' => trim($_GET['q'] ?? ''),
            'date_debut' => $_GET['date_debut'] ?? null,
            'date_fin' => $_GET['date_fin'] ?? null,
            'tri' => $_GET['tri'] ?? 'created_at',
            'sens' => $_GET['sens'] ?? 'desc',
        ];
        // 'me' est résolu ici (jamais stocké tel quel) pour que le lien "Mes
        // demandes" reste valable quel que soit l'utilisateur qui clique.
        if (($filters['responsable_id'] ?? null) === 'me') {
            $filters['responsable_id'] = (string) $user['id'];
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));

        $total = Demande::countVisibleFor($user, $filters);
        $totalPages = max(1, (int) ceil($total / Demande::PAR_PAGE));
        $page = min($page, $totalPages);

        $demandes = Demande::visibleFor($user, $filters, ['page' => $page]);
        $utilisateurs = Utilisateur::allForOrganisation((int) $user['organisation_id']);
        $filiales = Filiale::visibleFor($user);
        $canaux = Demande::distinctCanaux(Filiale::visibleIdsFor($user));

        // "Vue" active, déduite des filtres déjà posés (voir docblock) — pour
        // surligner le bon onglet sans dupliquer la logique de filtrage.
        $vueActive = 'toutes';
        if (($filters['statut'] ?? null) === 'a_qualifier') {
            $vueActive = 'a_qualifier';
        } elseif (($filters['responsable_id'] ?? null) === (string) $user['id'] && empty($filters['statut'])) {
            $vueActive = 'mes_demandes';
        } elseif (($filters['statut'] ?? null) === 'en_retard') {
            $vueActive = 'en_retard';
        } elseif (($filters['statut'] ?? null) === 'archivee') {
            $vueActive = 'archivees';
        }

        // Mémorise la requête de liste courante pour que "Retour aux
        // demandes" depuis une fiche/formulaire y revienne telle quelle.
        Auth::start();
        $_SESSION['demandes_liste_query'] = http_build_query(array_filter([
            'r' => 'demandes',
            'filiale_id' => $filters['filiale_id'],
            'activite' => $filters['activite'],
            'statut' => $filters['statut'],
            'responsable_id' => $_GET['responsable_id'] ?? null,
            'priorite' => $filters['priorite'],
            'canal' => $filters['canal'],
            'q' => $filters['recherche'],
            'date_debut' => $filters['date_debut'],
            'date_fin' => $filters['date_fin'],
            'tri' => $filters['tri'],
            'sens' => $filters['sens'],
            'page' => $page > 1 ? $page : null,
        ], fn($v) => $v !== null && $v !== ''));

        View::render('requests/index', [
            'demandes' => $demandes,
            'filters' => $filters,
            'utilisateurs' => $utilisateurs,
            'filiales' => $filiales,
            'canaux' => $canaux,
            'vueActive' => $vueActive,
            'compteursVues' => Demande::compteursVuesRapides($user),
            'kpis' => Demande::kpisListe($user, $filters['filiale_id'] ? (int) $filters['filiale_id'] : null),
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    /**
     * Export CSV de la liste filtrée (mêmes filtres que index()). Pas de
     * bibliothèque .xlsx disponible sans Composer (hébergement mutualisé
     * IONOS) : fichier CSV ouvrable directement dans Excel/LibreOffice,
     * même pattern que PilotageController::exportCsv().
     */
    public function exportCsv(): void
    {
        $user = Auth::user();
        $filters = [
            'filiale_id' => $_GET['filiale_id'] ?? null,
            'activite' => $_GET['activite'] ?? null,
            'statut' => $_GET['statut'] ?? null,
            'responsable_id' => $_GET['responsable_id'] ?? null,
            'priorite' => $_GET['priorite'] ?? null,
            'canal' => $_GET['canal'] ?? null,
            'recherche' => $_GET['q'] ?? null,
            'date_debut' => $_GET['date_debut'] ?? null,
            'date_fin' => $_GET['date_fin'] ?? null,
        ];
        if (($filters['responsable_id'] ?? null) === 'me') {
            $filters['responsable_id'] = (string) $user['id'];
        }
        $demandes = Demande::visibleFor($user, $filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="demandes_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel

        fputcsv($out, ['Demandes — Suivora360'], ';', '"', '\\');
        fputcsv($out, ['Exporté le', date('d/m/Y H:i')], ';', '"', '\\');
        fputcsv($out, ['Nombre de résultats', count($demandes)], ';', '"', '\\');
        fputcsv($out, [], ';', '"', '\\');
        fputcsv($out, ['Référence', 'Objet', 'Expéditeur', 'Entreprise', 'Email', 'Téléphone', 'Canal', 'Reçue le', 'Activité', 'Filiale', 'Responsable', 'Priorité', 'Statut', 'Échéance'], ';', '"', '\\');
        foreach ($demandes as $d) {
            fputcsv($out, [
                $d['reference'],
                $d['objet'],
                $d['expediteur_nom'],
                $d['expediteur_entreprise'],
                $d['expediteur_email'],
                $d['expediteur_telephone'],
                $d['canal'],
                $d['recue_le'] ? date('d/m/Y', strtotime($d['recue_le'])) : '',
                $d['activite'],
                $d['filiale_nom'],
                Utilisateur::nameOf($d['responsable_id']),
                Demande::PRIORITES[$d['priorite']] ?? $d['priorite'],
                $d['statut'],
                $d['echeance'] ? date('d/m/Y', strtotime($d['echeance'])) : '',
            ], ';', '"', '\\');
        }
        fclose($out);
        exit;
    }

    /**
     * Formulaire d'import CSV (création en masse de demandes brutes,
     * statut "à qualifier" — elles suivent ensuite le circuit de
     * qualification normal comme n'importe quelle demande).
     */
    public function importForm(): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        View::render('requests/import', [
            'filiales' => Filiale::visibleFor($user),
        ]);
    }

    /**
     * Modèle CSV téléchargeable (en-têtes + une ligne d'exemple) pour
     * cadrer le format attendu par importStore().
     */
    public function importModele(): void
    {
        Auth::requireWrite();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="modele_import_demandes.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['objet', 'activite', 'filiale', 'expediteur_nom', 'expediteur_entreprise', 'expediteur_email', 'expediteur_telephone', 'canal', 'recue_le', 'priorite', 'echeance', 'message'], ';', '"', '\\');
        fputcsv($out, ['Demande de cotation transport Douala-Paris', 'Transport et logistique', 'Siège', 'Jean Dupont', 'ACME SARL', 'jean.dupont@acme.com', '+237600000000', 'Email', date('d/m/Y'), 'normale', '', 'Message ou détails complémentaires'], ';', '"', '\\');
        fclose($out);
        exit;
    }

    /**
     * Traite le fichier CSV importé : une demande brute créée par ligne
     * valide (statut "à qualifier", comme à la création manuelle), les
     * lignes en erreur étant ignorées et listées plutôt que de bloquer
     * tout l'import.
     */
    public function importStore(): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/importer');
            exit;
        }
        $user = Auth::user();

        if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
            View::flash('erreur', 'Merci de sélectionner un fichier CSV.');
            header('Location: /index.php?r=demandes/importer');
            exit;
        }

        $handle = fopen($_FILES['fichier']['tmp_name'], 'r');
        if (!$handle) {
            View::flash('erreur', 'Impossible de lire le fichier.');
            header('Location: /index.php?r=demandes/importer');
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

        // Détection du séparateur (point-virgule par défaut — convention Excel
        // FR déjà utilisée pour les exports — virgule acceptée aussi).
        $premiereLigne = fgets($handle);
        rewind($handle);
        $delimiteur = substr_count((string) $premiereLigne, ';') >= substr_count((string) $premiereLigne, ',') ? ';' : ',';

        $entetes = fgetcsv($handle, 0, $delimiteur);
        if ($entetes === false) {
            fclose($handle);
            View::flash('erreur', 'Fichier vide ou illisible.');
            header('Location: /index.php?r=demandes/importer');
            exit;
        }
        $entetes = array_map(static function ($h) {
            return mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $h)));
        }, $entetes);

        $alias = [
            'objet' => ['objet'],
            'message' => ['message'],
            'canal' => ['canal'],
            'expediteur_nom' => ['expediteur_nom', 'expéditeur', 'expediteur'],
            'expediteur_entreprise' => ['expediteur_entreprise', 'entreprise'],
            'expediteur_email' => ['expediteur_email', 'email'],
            'expediteur_telephone' => ['expediteur_telephone', 'telephone', 'téléphone'],
            'recue_le' => ['recue_le', 'reçue le', 'recu_le'],
            'activite' => ['activite', 'activité'],
            'priorite' => ['priorite', 'priorité'],
            'echeance' => ['echeance', 'échéance'],
            'filiale' => ['filiale'],
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
            header('Location: /index.php?r=demandes/importer');
            exit;
        }

        $resultats = ['crees' => 0, 'erreurs' => []];
        $numeroLigne = 1; // ligne 1 = en-têtes
        while (($row = fgetcsv($handle, 0, $delimiteur)) !== false) {
            $numeroLigne++;
            if (count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // ligne vide
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

            $priorite = in_array($val('priorite'), ['basse', 'normale', 'haute', 'critique'], true) ? $val('priorite') : 'normale';
            $recueLe = $this->parseDateImport($val('recue_le')) ?? date('Y-m-d');
            $echeance = $this->parseDateImport($val('echeance'));

            try {
                $demandeId = Demande::create([
                    'filiale_id' => $filialeId,
                    'objet' => $objet,
                    'message' => $val('message'),
                    'canal' => $val('canal') ?: 'Import',
                    'expediteur_nom' => $val('expediteur_nom'),
                    'expediteur_entreprise' => $val('expediteur_entreprise'),
                    'expediteur_email' => $val('expediteur_email'),
                    'expediteur_telephone' => $val('expediteur_telephone'),
                    'recue_le' => $recueLe,
                    'activite' => $activite,
                    'priorite' => $priorite,
                    'echeance' => $echeance,
                ]);
                AuditLog::log($filialeId, (int) $user['id'], 'import', 'demande', $demandeId, 'Demande créée par import CSV');
                $resultats['crees']++;
            } catch (\Throwable $e) {
                $resultats['erreurs'][] = "Ligne $numeroLigne : erreur lors de la création — " . $e->getMessage();
            }
        }
        fclose($handle);

        View::render('requests/import_resultat', ['resultats' => $resultats]);
    }

    private function parseDateImport(string $value): ?string
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

    /**
     * Données communes au formulaire de création ET de modification (écran
     * 2, section "6. Cohérence visuelle" — un seul formulaire partagé,
     * jamais deux versions à maintenir séparément).
     */
    private function donneesFormulaire(array $user, ?array $demande = null): array
    {
        $filiales = Filiale::visibleFor($user);
        return [
            'filiales' => $filiales,
            'utilisateurs' => Utilisateur::allForOrganisation((int) $user['organisation_id']),
            'clients' => Client::visibleFor($user),
            'articles' => $demande ? DemandeArticle::forDemande((int) $demande['id']) : [],
            'piecesJointes' => $demande ? DemandePieceJointe::forDemande((int) $demande['id']) : [],
            'dossierExistant' => $demande ? Dossier::findByDemande((int) $demande['id']) : null,
            'demande' => $demande,
            'retourListeQuery' => $_SESSION['demandes_liste_query'] ?? 'r=demandes',
        ];
    }

    public function create(): void
    {
        $user = Auth::user();
        View::render('requests/create', $this->donneesFormulaire($user));
    }

    /**
     * Valide et renvoie les champs communs création/modification. Retourne
     * null (et flashe l'erreur + redirige) si la validation échoue — permet
     * à store()/update() de s'arrêter d'un simple `if (!$data) return;`.
     */
    private function validerChampsCommuns(array $user, string $redirectEnErreur): ?array
    {
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);
        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId)) {
            View::flash('erreur', "Vous n'avez pas accès à cette filiale.");
            header('Location: ' . $redirectEnErreur);
            exit;
        }
        if (trim($_POST['objet'] ?? '') === '') {
            View::flash('erreur', "L'objet de la demande est obligatoire.");
            header('Location: ' . $redirectEnErreur);
            exit;
        }
        $activite = trim($_POST['activite'] ?? '');
        if (!in_array($activite, Demande::ACTIVITES, true)) {
            View::flash('erreur', "L'activité est obligatoire.");
            header('Location: ' . $redirectEnErreur);
            exit;
        }

        $clientId = (int) ($_POST['client_id'] ?? 0);
        if ($clientId && !Filiale::userCanAccess($user, $filialeId)) {
            $clientId = 0;
        }

        return [
            'filiale_id' => $filialeId,
            'objet' => trim($_POST['objet']),
            'message' => trim($_POST['message'] ?? ''),
            'canal' => $_POST['canal'] ?? 'Formulaire',
            'client_id' => $clientId ?: null,
            'expediteur_nom' => trim($_POST['expediteur_nom'] ?? ''),
            'expediteur_entreprise' => trim($_POST['expediteur_entreprise'] ?? ''),
            'expediteur_email' => trim($_POST['expediteur_email'] ?? ''),
            'expediteur_telephone' => trim($_POST['expediteur_telephone'] ?? ''),
            'recue_le' => $_POST['recue_le'] ?? date('Y-m-d'),
            'activite' => $activite,
            'responsable_id' => $_POST['responsable_id'] ?? null,
            'priorite' => $_POST['priorite'] ?? 'normale',
            'echeance' => $_POST['echeance'] ?? null,
            'date_souhaitee_client' => $_POST['date_souhaitee_client'] ?? null,
            'notes_internes' => trim($_POST['notes_internes'] ?? ''),
        ];
    }

    public function store(): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/nouvelle');
            exit;
        }

        $user = Auth::user();
        $data = $this->validerChampsCommuns($user, '/index.php?r=demandes/nouvelle');
        if (!$data) {
            return;
        }

        $demandeId = Demande::create($data);
        AuditLog::log($data['filiale_id'], (int) $user['id'], 'creation', 'demande', $demandeId, 'Demande créée');

        $this->enregistrerArticlesSoumis($demandeId, $_POST);
        $erreursFichiers = $this->enregistrerPiecesJointes($demandeId, $data['filiale_id'], $user);

        if (!empty($erreursFichiers)) {
            View::flash('erreur', 'Demande créée, mais certaines pièces jointes n\'ont pas pu être ajoutées : ' . implode(' ', $erreursFichiers));
        } else {
            View::flash('succes', 'Demande créée avec succès.');
        }
        header('Location: /index.php?r=demandes/' . $demandeId);
        exit;
    }

    /**
     * [ajouté 04/10] Écran 2, voie "modification" — jusqu'ici inexistante :
     * seule la création était possible, aucune route ne permettait de
     * corriger une demande après coup. Reste ouvert tant que la demande
     * n'est pas rejetée/archivée (il faut d'abord la désarchiver), pour
     * éviter de corriger silencieusement une affaire classée.
     */
    public function edit(array $params): void
    {
        $user = Auth::user();
        $demande = $this->loadDemandeOr404($user, $params);
        if (!$demande) {
            return;
        }
        if (in_array($demande['statut'], ['rejetee', 'archivee'], true)) {
            View::flash('erreur', 'Cette demande est ' . ($demande['statut'] === 'archivee' ? 'archivée' : 'rejetée') . ' — impossible de la modifier en l\'état.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }
        View::render('requests/create', $this->donneesFormulaire($user, $demande));
    }

    public function update(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        $redirect = '/index.php?r=demandes/' . $demande['id'] . '/modifier';
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: ' . $redirect);
            exit;
        }
        if (in_array($demande['statut'], ['rejetee', 'archivee'], true)) {
            View::flash('erreur', 'Cette demande ne peut plus être modifiée dans son état actuel.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        $data = $this->validerChampsCommuns($user, $redirect);
        if (!$data) {
            return;
        }
        // La filiale n'est jamais modifiable après coup (les permissions, les
        // compteurs de référence et l'isolation en dépendent) — on garde
        // celle d'origine quoi qu'il arrive dans le POST.
        $data['filiale_id'] = (int) $demande['filiale_id'];

        Demande::update((int) $demande['id'], $data);
        AuditLog::log($data['filiale_id'], (int) $user['id'], 'modification', 'demande', (int) $demande['id'], 'Demande modifiée');

        $erreursFichiers = $this->enregistrerPiecesJointes((int) $demande['id'], $data['filiale_id'], $user);
        if (!empty($erreursFichiers)) {
            View::flash('erreur', 'Demande mise à jour, mais certaines pièces jointes n\'ont pas pu être ajoutées : ' . implode(' ', $erreursFichiers));
        } else {
            View::flash('succes', 'Demande mise à jour.');
        }
        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    /**
     * Lignes d'articles soumises en même temps que le formulaire (création
     * OU modification) — champs parallèles designation[]/quantite[]/...,
     * une ligne vide (désignation vide) est simplement ignorée plutôt que
     * de bloquer tout l'enregistrement.
     */
    private function enregistrerArticlesSoumis(int $demandeId, array $post): void
    {
        $designations = $post['art_designation'] ?? [];
        foreach ($designations as $i => $designation) {
            $designation = trim((string) $designation);
            if ($designation === '') {
                continue;
            }
            DemandeArticle::create($demandeId, [
                'designation' => $designation,
                'quantite' => $post['art_quantite'][$i] ?? null,
                'unite' => trim((string) ($post['art_unite'][$i] ?? '')),
                'conditionnement' => trim((string) ($post['art_conditionnement'][$i] ?? '')),
                'reference' => trim((string) ($post['art_reference'][$i] ?? '')),
                'marque' => trim((string) ($post['art_marque'][$i] ?? '')),
            ]);
        }
    }

    /**
     * Enregistre les fichiers soumis via le champ multi-fichiers
     * "fichiers[]" du formulaire de création/modification (mêmes règles que
     * uploadPiece() : extension/taille, stockage hors webroot — seule
     * différence, plusieurs fichiers en une fois). Retourne la liste des
     * messages d'erreur rencontrés fichier par fichier ; un tableau vide
     * signifie que tout s'est bien passé, y compris quand aucun fichier
     * n'a été fourni (champ facultatif).
     */
    private function enregistrerPiecesJointes(int $demandeId, int $filialeId, array $user): array
    {
        $erreurs = [];
        if (empty($_FILES['fichiers']) || empty($_FILES['fichiers']['name'])) {
            return $erreurs;
        }

        $fichiers = $_FILES['fichiers'];
        $nombre = count((array) $fichiers['name']);
        for ($i = 0; $i < $nombre; $i++) {
            if ($fichiers['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue; // emplacement laissé vide (sélection multiple)
            }
            $nomOriginal = $fichiers['name'][$i];
            if ($fichiers['error'][$i] !== UPLOAD_ERR_OK) {
                $erreurs[] = "« $nomOriginal » : échec de l'envoi.";
                continue;
            }
            if ($fichiers['size'][$i] > DemandePieceJointe::TAILLE_MAX) {
                $erreurs[] = "« $nomOriginal » : dépasse la taille maximale autorisée (10 Mo).";
                continue;
            }
            $extension = strtolower(pathinfo($nomOriginal, PATHINFO_EXTENSION));
            if (!in_array($extension, DemandePieceJointe::EXTENSIONS_AUTORISEES, true)) {
                $erreurs[] = "« $nomOriginal » : type de fichier non autorisé.";
                continue;
            }

            $nomFichier = bin2hex(random_bytes(16)) . '.' . $extension;
            $chemin = Storage::path('uploads/demandes/' . $demandeId . '/' . $nomFichier);
            if (!move_uploaded_file($fichiers['tmp_name'][$i], $chemin)) {
                $erreurs[] = "« $nomOriginal » : impossible d'enregistrer le fichier.";
                continue;
            }

            DemandePieceJointe::create([
                'demande_id' => $demandeId,
                'nom_original' => $nomOriginal,
                'nom_fichier' => $nomFichier,
                'taille' => $fichiers['size'][$i],
                'type_mime' => $fichiers['type'][$i] ?: 'application/octet-stream',
                'uploaded_by' => (int) $user['id'],
            ]);
            AuditLog::log($filialeId, (int) $user['id'], 'ajout_piece_jointe', 'demande', $demandeId, $nomOriginal);
        }

        return $erreurs;
    }

    public function show(array $params): void
    {
        $user = Auth::user();
        $demande = Demande::find((int) $params['id']);

        if (!$demande || !Filiale::userCanAccess($user, (int) $demande['filiale_id'])) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $articles = DemandeArticle::forDemande($demande['id']);
        $dossier = Dossier::findByDemande($demande['id']);
        $filiale = \App\Models\Filiale::find((int) $demande['filiale_id']);
        $client = !empty($demande['client_id']) ? Client::find((int) $demande['client_id']) : null;
        $linkedDemande = !empty($demande['linked_request_id']) ? Demande::find((int) $demande['linked_request_id']) : null;
        $linkedDossier = !empty($demande['linked_dossier_id']) ? Dossier::find((int) $demande['linked_dossier_id']) : null;
        $historique = AuditLog::forEntity('demande', (int) $demande['id']);
        $piecesJointes = DemandePieceJointe::forDemande((int) $demande['id']);

        View::render('requests/show', [
            'demande' => $demande,
            'articles' => $articles,
            'dossier' => $dossier,
            'filiale' => $filiale,
            'client' => $client,
            'linkedDemande' => $linkedDemande,
            'linkedDossier' => $linkedDossier,
            'historique' => $historique,
            'piecesJointes' => $piecesJointes,
            'retourListeQuery' => $_SESSION['demandes_liste_query'] ?? 'r=demandes',
        ]);
    }

    /**
     * [ajouté 04/10] Ajout manuel d'une ligne d'article depuis la fiche de
     * consultation (jusqu'ici, seule l'extraction IA pouvait créer des
     * lignes après la création initiale).
     */
    public function ajouterArticle(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }
        $designation = trim($_POST['designation'] ?? '');
        if ($designation === '') {
            View::flash('erreur', "La désignation de l'article est obligatoire.");
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }
        DemandeArticle::create((int) $demande['id'], [
            'designation' => $designation,
            'quantite' => $_POST['quantite'] ?? null,
            'unite' => trim($_POST['unite'] ?? ''),
            'conditionnement' => trim($_POST['conditionnement'] ?? ''),
            'reference' => trim($_POST['reference'] ?? ''),
            'marque' => trim($_POST['marque'] ?? ''),
        ]);
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'ajout_article', 'demande', (int) $demande['id'], $designation);
        View::flash('succes', 'Article ajouté.');
        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    public function modifierArticle(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }
        $article = DemandeArticle::find((int) ($params['articleId'] ?? 0));
        if (!$article || (int) $article['demande_id'] !== (int) $demande['id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $designation = trim($_POST['designation'] ?? '');
        if ($designation === '') {
            View::flash('erreur', "La désignation de l'article est obligatoire.");
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }
        DemandeArticle::update((int) $article['id'], [
            'designation' => $designation,
            'quantite' => $_POST['quantite'] ?? null,
            'unite' => trim($_POST['unite'] ?? ''),
            'conditionnement' => trim($_POST['conditionnement'] ?? ''),
            'reference' => trim($_POST['reference'] ?? ''),
            'marque' => trim($_POST['marque'] ?? ''),
        ]);
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'modification_article', 'demande', (int) $demande['id'], $designation);
        View::flash('succes', 'Article modifié.');
        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    public function supprimerArticle(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }
        $article = DemandeArticle::find((int) ($params['articleId'] ?? 0));
        if ($article && (int) $article['demande_id'] === (int) $demande['id']) {
            DemandeArticle::delete((int) $article['id']);
            AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'suppression_article', 'demande', (int) $demande['id'], $article['designation']);
            View::flash('succes', 'Article retiré.');
        }
        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    /**
     * Affiche le choix des 3 voies de qualification, en choix exclusif
     * (section 5 du cahier des charges — remplace l'empilement des 3
     * formulaires). Jamais de nature attribuée automatiquement : la
     * personne choisit explicitement.
     */
    public function qualifierForm(array $params): void
    {
        $user = Auth::user();
        $demande = $this->loadDemandeOr404($user, $params);
        if (!$demande) {
            return;
        }

        $filiale = Filiale::find((int) $demande['filiale_id']);
        $clients = Client::allForFiliale((int) $demande['filiale_id']);
        $utilisateurs = Utilisateur::allForOrganisation((int) $filiale['organisation_id']);

        $termeRecherche = trim($_GET['q'] ?? '');
        $resultats = [];
        if ($termeRecherche !== '') {
            $resultats = Demande::searchForAddition(Filiale::visibleIdsFor($user), $termeRecherche, (int) $demande['id']);
        }

        View::render('requests/qualifier', [
            'demande' => $demande,
            'clients' => $clients,
            'utilisateurs' => $utilisateurs,
            'termeRecherche' => $termeRecherche,
            'resultats' => $resultats,
            'voieDemandee' => $_GET['voie'] ?? 'nouvelle',
        ]);
    }

    /**
     * Voie "Nouveau dossier" — [fusionné 04/10, décision confirmée avec
     * Marie Laure] qualifie ET crée le dossier en une seule soumission
     * (bouton "Qualifier et créer le dossier"), au lieu des deux étapes
     * séparées d'avant. Dossier::createFromDemande() reste idempotente
     * (elle renvoie le dossier déjà existant plutôt que d'en recréer un) :
     * un double clic/double soumission ne crée donc jamais deux dossiers.
     *
     * Les champs spécifiques au type de dossier qui n'ont pas de colonne
     * dédiée (nature du service, site, visite nécessaire pour une
     * prestation ; lieu d'enlèvement, marchandises, poids/volume estimés
     * pour un transport) sont ajoutés en texte structuré à la suite des
     * notes de qualification plutôt que d'ajouter une dizaine de colonnes
     * rarement utiles isolément — le budget détaillé et la réalisation
     * restent du ressort du dossier, pas de cette étape (cahier des
     * charges, section 5.A).
     */
    public function qualifierNouvelle(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id'] . '/qualifier');
            exit;
        }

        $typeDossier = $_POST['type_dossier'] ?? '';
        if (!in_array($typeDossier, Dossier::TYPES, true)) {
            $typeDossier = Dossier::deduireType($demande['activite'] ?? null);
        }

        $post = $_POST;
        $post['notes'] = $this->composerNotesQualification($post, $typeDossier);
        if ($typeDossier !== 'achat_sourcing') {
            // Incoterm/livraison ne s'appliquent pas aux prestations/transport
            // tels que décrits en section 5.A — on évite de les enregistrer
            // comme "souhaités" alors qu'ils n'ont jamais été demandés.
            $post['incoterm_souhaite'] = '';
        }

        Demande::qualifyNew((int) $demande['id'], $post, (int) $user['id']);
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'qualification', 'demande', (int) $demande['id'], 'Voie : Nouvelle demande');

        try {
            $dossierId = Dossier::createFromDemande((int) $demande['id'], [
                'responsable_id' => $post['responsable_id'] ?? null,
                'priorite' => $post['priorite'] ?? null,
                'echeance' => $post['echeance'] ?? null,
                'type_dossier' => $typeDossier,
            ]);
            AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'creation_dossier', 'dossier', $dossierId, 'Depuis demande #' . $demande['id']);
            View::flash('succes', 'Demande qualifiée et dossier créé.');
            header('Location: /index.php?r=dossiers/' . $dossierId);
        } catch (\Throwable $e) {
            // La qualification, elle, a déjà réussi : on prévient sans faire
            // perdre cette étape (comportement identique à l'ancien flux en
            // 2 étapes si la création du dossier échouait déjà à ce moment).
            View::flash('erreur', 'Demande qualifiée, mais le dossier n\'a pas pu être créé : ' . $e->getMessage());
            header('Location: /index.php?r=demandes/' . $demande['id']);
        }
        exit;
    }

    private function composerNotesQualification(array $post, string $typeDossier): string
    {
        $lignes = [];
        $notesLibres = trim($post['notes'] ?? '');
        if ($notesLibres !== '') {
            $lignes[] = $notesLibres;
        }
        if ($typeDossier === 'prestation_entreprise') {
            if (trim($post['nature_service'] ?? '') !== '') {
                $lignes[] = 'Nature du service : ' . trim($post['nature_service']);
            }
            if (trim($post['site_intervention'] ?? '') !== '') {
                $lignes[] = "Site d'intervention : " . trim($post['site_intervention']);
            }
            $lignes[] = 'Visite préalable nécessaire : ' . (!empty($post['visite_necessaire']) ? 'Oui' : 'Non');
        } elseif ($typeDossier === 'transport_logistique') {
            if (trim($post['lieu_enlevement'] ?? '') !== '') {
                $lignes[] = "Lieu d'enlèvement : " . trim($post['lieu_enlevement']);
            }
            if (trim($post['marchandises'] ?? '') !== '') {
                $lignes[] = 'Marchandises : ' . trim($post['marchandises']);
            }
            if (trim($post['poids_estime'] ?? '') !== '') {
                $lignes[] = 'Poids estimé : ' . trim($post['poids_estime']) . ' kg';
            }
            if (trim($post['volume_estime'] ?? '') !== '') {
                $lignes[] = 'Volume estimé : ' . trim($post['volume_estime']) . ' m³';
            }
        }
        return implode("\n", $lignes);
    }

    public function qualifierComplement(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id'] . '/qualifier');
            exit;
        }

        $linkedType = $_POST['type'] ?? '';
        $linkedId = (int) ($_POST['id'] ?? 0);

        if (!in_array($linkedType, ['demande', 'dossier'], true) || !$linkedId) {
            View::flash('erreur', 'Sélection invalide.');
            header('Location: /index.php?r=demandes/' . $demande['id'] . '/qualifier');
            exit;
        }

        // Vérifie que l'élément rattaché est bien visible par l'utilisateur (même isolation multi-filiale)
        if ($linkedType === 'dossier') {
            $cible = Dossier::find($linkedId);
            $accessOk = $cible && Dossier::userCanAccess($user, $cible);
            if ($cible && $cible['statut'] === 'cloture') {
                View::flash('erreur', "Ce dossier est clôturé — le rattachement reste possible mais vérifiez qu'il ne faut pas plutôt créer une nouvelle demande.");
            }
        } else {
            $cible = Demande::find($linkedId);
            $accessOk = $cible && Filiale::userCanAccess($user, (int) $cible['filiale_id']);
        }
        if (!$accessOk) {
            View::flash('erreur', "L'élément sélectionné est introuvable ou hors de votre périmètre.");
            header('Location: /index.php?r=demandes/' . $demande['id'] . '/qualifier');
            exit;
        }

        Demande::qualifyAddition((int) $demande['id'], $linkedType, $linkedId, $_POST['notes'] ?? '', (int) $user['id']);
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'qualification_rattachement', 'demande', (int) $demande['id'], "Rattachée à $linkedType #$linkedId");

        View::flash('succes', 'Demande rattachée avec succès. Aucun nouveau dossier créé.');
        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    public function qualifierReprise(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id'] . '/qualifier');
            exit;
        }

        // [réécrit 05/10, report de la maquette] Fusionne désormais
        // qualification + création du dossier en une seule soumission,
        // comme la voie 1 (qualifierNouvelle() ci-dessus) — jusqu'ici la
        // création du dossier restait une étape séparée, déclenchée sur la
        // fiche après coup.
        $typeDossier = $_POST['type_dossier'] ?? '';
        if (!in_array($typeDossier, Dossier::TYPES, true)) {
            $typeDossier = Dossier::deduireType($demande['activite'] ?? null);
        }

        Demande::qualifyTakeover((int) $demande['id'], $_POST, (int) $user['id']);
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'qualification_reprise', 'demande', (int) $demande['id'], 'Voie : Reprise hors Suivora');

        try {
            $dossierId = Dossier::createFromDemande((int) $demande['id'], [
                'responsable_id' => $_POST['responsable_id'] ?? null,
                'priorite' => $_POST['priorite'] ?? null,
                'echeance' => $_POST['echeance'] ?? null,
                'type_dossier' => $typeDossier,
            ]);
            AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'creation_dossier', 'dossier', $dossierId, 'Depuis demande #' . $demande['id']);
            View::flash('succes', 'Reprise enregistrée et dossier créé.');
            header('Location: /index.php?r=dossiers/' . $dossierId);
        } catch (\Throwable $e) {
            // La qualification a déjà réussi : on prévient sans faire perdre
            // cette étape (le bouton de secours générique sur la fiche —
            // voir $peutCreerDossier dans show.php — permet de retenter).
            View::flash('erreur', 'Reprise enregistrée, mais le dossier n\'a pas pu être créé : ' . $e->getMessage());
            header('Location: /index.php?r=demandes/' . $demande['id']);
        }
        exit;
    }

    /**
     * Crée le dossier une fois la demande qualifiée — ne reste utile que
     * pour la voie "Reprise hors Suivora" (la voie "Nouveau dossier" crée
     * désormais le dossier directement dans qualifierNouvelle() ci-dessus).
     */
    public function creerDossier(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        try {
            $dossierId = Dossier::createFromDemande((int) $demande['id'], [
                'responsable_id' => $_POST['responsable_id'] ?? null,
                'priorite' => $_POST['priorite'] ?? null,
                'echeance' => $_POST['echeance'] ?? null,
                'type_dossier' => $_POST['type_dossier'] ?? null,
            ]);
            AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'creation_dossier', 'dossier', $dossierId, 'Depuis demande #' . $demande['id']);
            View::flash('succes', 'Dossier créé avec succès.');
            header('Location: /index.php?r=dossiers/' . $dossierId);
        } catch (\Throwable $e) {
            View::flash('erreur', $e->getMessage());
            header('Location: /index.php?r=demandes/' . $demande['id']);
        }
        exit;
    }

    /**
     * [ajouté 04/10] Archiver / désarchiver — statut prévu depuis l'origine
     * dans les filtres de la liste mais jamais appliqué nulle part.
     */
    public function archiver(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }
        Demande::archiver((int) $demande['id']);
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'archivage_demande', 'demande', (int) $demande['id']);
        View::flash('succes', 'Demande archivée.');
        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    public function desarchiver(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }
        Demande::desarchiver((int) $demande['id']);
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'desarchivage_demande', 'demande', (int) $demande['id']);
        View::flash('succes', 'Demande désarchivée.');
        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    /**
     * [ajouté 04/10] Suppression — absente jusqu'ici. Bloquée (décision
     * confirmée avec Marie Laure) si un dossier a déjà été créé à partir de
     * cette demande, ou si une autre demande y est rattachée (voie
     * "Complément") : aucune contrainte de clé étrangère n'existe en base
     * dans cette application, donc seule cette vérification applicative
     * empêche de laisser un dossier ou un rattachement pointer vers une
     * ligne supprimée. Les pièces jointes physiques sont supprimées du
     * disque avant les lignes en base.
     */
    public function supprimer(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        $raisons = Demande::raisonsBlocantesSuppression((int) $demande['id']);
        if (!empty($raisons)) {
            View::flash('erreur', 'Impossible de supprimer cette demande : ' . implode(' ', $raisons));
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        foreach (DemandePieceJointe::forDemande((int) $demande['id']) as $piece) {
            $chemin = Storage::path('uploads/demandes/' . $demande['id'] . '/' . $piece['nom_fichier']);
            if (is_file($chemin)) {
                unlink($chemin);
            }
        }

        $reference = $demande['reference'];
        $objet = $demande['objet'];
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'suppression_demande', 'demande', null, "$reference — $objet");
        Demande::supprimer((int) $demande['id']);

        View::flash('succes', "Demande $reference supprimée.");
        header('Location: /index.php?r=demandes');
        exit;
    }

    /**
     * Ajoute une pièce jointe à une demande (message original, devis reçu,
     * capture WhatsApp...). Le fichier est stocké hors du webroot ; tout
     * accès repasse obligatoirement par telechargerPiece() ci-dessous.
     */
    public function uploadPiece(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] === UPLOAD_ERR_NO_FILE) {
            View::flash('erreur', 'Aucun fichier sélectionné.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        $fichier = $_FILES['fichier'];
        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            View::flash('erreur', "Échec de l'envoi du fichier.");
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }
        if ($fichier['size'] > DemandePieceJointe::TAILLE_MAX) {
            View::flash('erreur', 'Le fichier dépasse la taille maximale autorisée (10 Mo).');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, DemandePieceJointe::EXTENSIONS_AUTORISEES, true)) {
            View::flash('erreur', 'Type de fichier non autorisé (formats acceptés : ' . implode(', ', DemandePieceJointe::EXTENSIONS_AUTORISEES) . ').');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        $nomFichier = bin2hex(random_bytes(16)) . '.' . $extension;
        $chemin = Storage::path('uploads/demandes/' . $demande['id'] . '/' . $nomFichier);
        if (!move_uploaded_file($fichier['tmp_name'], $chemin)) {
            View::flash('erreur', "Impossible d'enregistrer le fichier.");
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        DemandePieceJointe::create([
            'demande_id' => $demande['id'],
            'nom_original' => $fichier['name'],
            'nom_fichier' => $nomFichier,
            'taille' => $fichier['size'],
            'type_mime' => $fichier['type'] ?: 'application/octet-stream',
            'uploaded_by' => (int) $user['id'],
        ]);
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'ajout_piece_jointe', 'demande', (int) $demande['id'], $fichier['name']);

        View::flash('succes', 'Pièce jointe ajoutée.');
        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    public function telechargerPiece(array $params): void
    {
        $user = Auth::user();
        $demande = $this->loadDemandeOr404($user, $params);
        if (!$demande) {
            return;
        }
        $piece = DemandePieceJointe::find((int) $params['pieceId']);
        if (!$piece || (int) $piece['demande_id'] !== (int) $demande['id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $chemin = Storage::path('uploads/demandes/' . $demande['id'] . '/' . $piece['nom_fichier']);
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
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        $piece = DemandePieceJointe::find((int) ($params['pieceId'] ?? 0));
        if ($piece && (int) $piece['demande_id'] === (int) $demande['id']) {
            $chemin = Storage::path('uploads/demandes/' . $demande['id'] . '/' . $piece['nom_fichier']);
            if (is_file($chemin)) {
                unlink($chemin);
            }
            DemandePieceJointe::delete($piece['id']);
            AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'suppression_piece_jointe', 'demande', (int) $demande['id'], $piece['nom_original']);
            View::flash('succes', 'Pièce jointe supprimée.');
        }

        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    /**
     * Lance l'extraction IA des articles depuis le message brut de la
     * demande, puis affiche une page de relecture (rien n'est enregistré
     * tant que confirmerExtractionIa() n'a pas été appelé). Les suggestions
     * dont la désignation correspond déjà à un article existant sont
     * signalées (et décochées par défaut) pour ne pas dupliquer une ligne
     * lors d'une nouvelle extraction (cahier des charges, section 3).
     */
    public function extraireIa(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        try {
            $suggestions = AiExtracteur::extraireArticles($demande['message'] ?? '');
        } catch (\Throwable $e) {
            View::flash('erreur', $e->getMessage());
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        if (empty($suggestions)) {
            View::flash('erreur', "L'IA n'a identifié aucun article dans le message de cette demande.");
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        $designationsExistantes = array_map(
            static fn($a) => mb_strtolower(trim($a['designation'])),
            DemandeArticle::forDemande((int) $demande['id'])
        );
        foreach ($suggestions as &$s) {
            $s['deja_existant'] = in_array(mb_strtolower(trim($s['designation'])), $designationsExistantes, true);
        }
        unset($s);

        View::render('requests/extraction_ia', [
            'demande' => $demande,
            'suggestions' => $suggestions,
        ]);
    }

    public function confirmerExtractionIa(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        $designations = $_POST['designation'] ?? [];
        $quantites = $_POST['quantite'] ?? [];
        $unites = $_POST['unite'] ?? [];
        $conditionnements = $_POST['conditionnement'] ?? [];
        $references = $_POST['reference'] ?? [];
        $marques = $_POST['marque'] ?? [];
        $inclure = $_POST['inclure'] ?? [];

        $ajoutes = 0;
        foreach ($designations as $i => $designation) {
            if (!in_array((string) $i, $inclure, true)) {
                continue;
            }
            $designation = trim((string) $designation);
            if ($designation === '') {
                continue;
            }
            DemandeArticle::create((int) $demande['id'], [
                'designation' => $designation,
                'quantite' => $quantites[$i] ?? null,
                'unite' => trim((string) ($unites[$i] ?? '')),
                'conditionnement' => trim((string) ($conditionnements[$i] ?? '')),
                'reference' => trim((string) ($references[$i] ?? '')),
                'marque' => trim((string) ($marques[$i] ?? '')),
            ]);
            $ajoutes++;
        }

        if ($ajoutes > 0) {
            AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'extraction_ia_articles', 'demande', (int) $demande['id'], "$ajoutes article(s) ajouté(s) via IA");
            View::flash('succes', "$ajoutes article(s) ajouté(s).");
        } else {
            View::flash('erreur', 'Aucun article sélectionné.');
        }

        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    public function rejeter(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $demande = $this->loadDemandeOrRedirect($user, $params);
        if (!$demande) {
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        $motif = trim($_POST['motif'] ?? '');
        if ($motif === '') {
            View::flash('erreur', 'Le motif de rejet est obligatoire.');
            header('Location: /index.php?r=demandes/' . $demande['id']);
            exit;
        }

        Demande::reject((int) $demande['id'], $motif);
        AuditLog::log((int) $demande['filiale_id'], (int) $user['id'], 'rejet_demande', 'demande', (int) $demande['id'], $motif);

        View::flash('succes', 'Demande rejetée.');
        header('Location: /index.php?r=demandes/' . $demande['id']);
        exit;
    }

    private function loadDemandeOr404(array $user, array $params): ?array
    {
        $demande = Demande::find((int) $params['id']);
        if (!$demande || !Filiale::userCanAccess($user, (int) $demande['filiale_id'])) {
            http_response_code(404);
            View::render('errors/404');
            return null;
        }
        return $demande;
    }

    private function loadDemandeOrRedirect(array $user, array $params): ?array
    {
        $demande = Demande::find((int) $params['id']);
        if (!$demande || !Filiale::userCanAccess($user, (int) $demande['filiale_id'])) {
            http_response_code(404);
            View::render('errors/404');
            return null;
        }
        return $demande;
    }
}

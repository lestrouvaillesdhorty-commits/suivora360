<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Storage;
use App\Core\Tenant;
use App\Core\View;
use App\Models\Activite;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Demande;
use App\Models\DemandeArticle;
use App\Models\DemandePieceJointe;
use App\Models\EntrantDemande;
use App\Models\Filiale;
use App\Services\AiExtracteur;
use App\Services\IaQuota;

/** Boîte « À valider » : dépôt, lecture par l'IA, vérification puis création de la demande. */
class EntrantController
{
    private function retour(): string
    {
        return '/index.php?r=a-valider';
    }

    private function charger(array $params, bool $ecriture = false): ?array
    {
        $user = Auth::user();
        $e = EntrantDemande::find((int) ($params['id'] ?? 0));
        if (!$e || !Filiale::userCanAccess($user, (int) $e['filiale_id'])) {
            http_response_code(404);
            View::render('errors/404');
            return null;
        }
        if ($ecriture && !Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: ' . $this->retour());
            exit;
        }
        return $e;
    }

    public function index(): void
    {
        $user = Auth::user();
        $ids = Filiale::visibleIdsFor($user);
        View::render('entrants/index', [
            'disponible' => EntrantDemande::disponible(),
            'entrants' => EntrantDemande::disponible() ? EntrantDemande::enAttente($ids) : [],
            'iaDispo' => AiExtracteur::estDisponible(),
        ]);
    }

    /** Dépôt depuis la page (fichiers glissés/choisis et/ou texte collé). */
    public function deposer(): void
    {
        Auth::requireWrite();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: ' . $this->retour());
            exit;
        }
        $this->enregistrer(trim((string) ($_POST['texte'] ?? '')), $_FILES['fichiers'] ?? null);
    }

    /** Cible du « Partager vers Suivora360 » du téléphone (manifest share_target). */
    public function partage(): void
    {
        Auth::requireLogin();
        Auth::requireWrite();
        $texte = trim(implode("\n", array_filter([
            trim((string) ($_POST['title'] ?? '')),
            trim((string) ($_POST['text'] ?? '')),
            trim((string) ($_POST['url'] ?? '')),
        ])));
        $this->enregistrer($texte, $_FILES['fichiers'] ?? null);
    }

    private function enregistrer(string $texte, ?array $fichiers): void
    {
        $user = Auth::user();
        $filiales = Filiale::visibleFor($user);
        if (!EntrantDemande::disponible() || !$filiales) {
            View::flash('erreur', "La boîte « À valider » demande une mise à jour de la base : lancez migrate_v57.php.");
            header('Location: ' . $this->retour());
            exit;
        }
        $filialeId = (int) $filiales[0]['id'];
        $crees = [];
        $erreurs = [];

        if ($fichiers && !empty($fichiers['name'])) {
            $noms = (array) $fichiers['name'];
            foreach ($noms as $i => $nom) {
                if (($fichiers['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $nom = (string) $nom;
                if ($fichiers['error'][$i] !== UPLOAD_ERR_OK) { $erreurs[] = "« $nom » : échec de l'envoi."; continue; }
                if ($fichiers['size'][$i] > EntrantDemande::TAILLE_MAX) { $erreurs[] = "« $nom » : dépasse 10 Mo."; continue; }
                $ext = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
                if (!in_array($ext, DemandePieceJointe::EXTENSIONS_AUTORISEES, true)) { $erreurs[] = "« $nom » : type de fichier non autorisé."; continue; }
                $id = EntrantDemande::creer([
                    'filiale_id' => $filialeId, 'utilisateur_id' => (int) $user['id'],
                    'source' => EntrantDemande::sourceDe($nom), 'texte' => $texte !== '' && !$crees ? $texte : null,
                    'nom_original' => mb_substr($nom, 0, 255), 'taille' => (int) $fichiers['size'][$i], 'type_mime' => (string) ($fichiers['type'][$i] ?: 'application/octet-stream'),
                ]);
                $stocke = bin2hex(random_bytes(16)) . '.' . $ext;
                if (!move_uploaded_file($fichiers['tmp_name'][$i], Storage::path('uploads/entrants/' . $id . '/' . $stocke))) {
                    EntrantDemande::marquer($id, 'ignore');
                    $erreurs[] = "« $nom » : impossible d'enregistrer le fichier.";
                    continue;
                }
                \App\Core\Database::connection()->prepare('UPDATE demandes_entrantes SET nom_fichier = ? WHERE id = ?')->execute([$stocke, $id]);
                $crees[] = $id;
            }
        }
        if ($texte !== '' && !$crees) {
            $crees[] = EntrantDemande::creer(['filiale_id' => $filialeId, 'utilisateur_id' => (int) $user['id'], 'source' => 'texte', 'texte' => mb_substr($texte, 0, 60000)]);
        }
        foreach ($crees as $id) {
            AuditLog::log($filialeId, (int) $user['id'], 'entrant_recu', 'demande_entrante', $id, 'Reçu dans À valider');
        }
        if ($erreurs) {
            View::flash('erreur', implode(' ', $erreurs));
        }
        if ($crees) {
            View::flash('succes', count($crees) === 1 ? 'Reçu : 1 élément à valider.' : 'Reçu : ' . count($crees) . ' éléments à valider.');
        } elseif (!$erreurs) {
            View::flash('erreur', 'Rien à envoyer : déposez un fichier ou collez un texte.');
        }
        header('Location: ' . (count($crees) === 1 ? '/index.php?r=a-valider/' . $crees[0] : $this->retour()));
        exit;
    }

    public function show(array $params): void
    {
        $user = Auth::user();
        $e = $this->charger($params);
        if (!$e) {
            return;
        }
        $infoIa = '';
        $articles = $e['lecture'] ? (json_decode((string) $e['lecture'], true) ?: []) : null;
        if ($articles === null && in_array($e['statut'], ['nouveau', 'lu'], true)) {
            $articles = [];
            if (AiExtracteur::estDisponible()) {
                try {
                    $fichiers = [];
                    if ($e['nom_fichier'] && AiExtracteur::estLisible((string) $e['nom_original'])) {
                        $fichiers[] = AiExtracteur::preparerFichier(Storage::path('uploads/entrants/' . $e['id'] . '/' . $e['nom_fichier']), (string) $e['nom_original']);
                    }
                    if (trim((string) $e['texte']) !== '' || $fichiers) {
                        $exec = IaQuota::executer($user, (int) $e['filiale_id'], 'articles', (string) $e['texte'] . '|' . md5(serialize($fichiers)),
                            fn() => AiExtracteur::extraireArticles((string) $e['texte'], $fichiers));
                        $articles = $exec['resultat'];
                        $infoIa = IaQuota::message($exec['statut']);
                    }
                    EntrantDemande::enregistrerLecture((int) $e['id'], $articles);
                } catch (\Throwable $ex) {
                    $infoIa = 'Lecture automatique impossible : ' . $ex->getMessage() . ' Vous pouvez saisir les articles à la main.';
                }
            } else {
                $infoIa = "Lecture automatique non disponible (clé IA non configurée). Saisissez les articles à la main.";
            }
        }
        $filiale = Filiale::find((int) $e['filiale_id']);
        $designation = $articles[0]['designation'] ?? '';
        $objet = $designation !== '' ? 'Demande : ' . $designation . (count($articles) > 1 ? ' et ' . (count($articles) - 1) . ' autre(s)' : '') : '';
        View::render('entrants/show', [
            'e' => $e,
            'articles' => $articles ?: [],
            'infoIa' => $infoIa,
            'objet' => $objet,
            'clients' => Client::allForFiliale((int) $e['filiale_id']),
            'activites' => Activite::liste((int) ($filiale['organisation_id'] ?? 0)),
        ]);
    }

    public function fichier(array $params): void
    {
        $e = $this->charger($params);
        if (!$e || !$e['nom_fichier']) {
            return;
        }
        $chemin = Storage::path('uploads/entrants/' . $e['id'] . '/' . $e['nom_fichier']);
        if (!is_file($chemin)) {
            http_response_code(404);
            return;
        }
        header('Content-Type: ' . ($e['type_mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . rawurlencode((string) $e['nom_original']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($chemin);
        exit;
    }

    public function valider(array $params): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $e = $this->charger($params, true);
        if (!$e) {
            return;
        }
        $retour = '/index.php?r=a-valider/' . $e['id'];
        if (!in_array($e['statut'], ['nouveau', 'lu'], true)) {
            View::flash('erreur', 'Cet élément a déjà été traité.');
            header('Location: ' . $this->retour());
            exit;
        }
        $objet = trim((string) ($_POST['objet'] ?? ''));
        $activite = trim((string) ($_POST['activite'] ?? ''));
        if ($objet === '' || !Activite::connue($activite)) {
            View::flash('erreur', "L'objet et l'activité sont obligatoires.");
            header('Location: ' . $retour);
            exit;
        }
        $filialeId = (int) $e['filiale_id'];
        $clientId = (int) Tenant::clientVisible($_POST['client_id'] ?? 0, Filiale::visibleIdsFor($user));
        $demandeId = Demande::create([
            'filiale_id' => $filialeId,
            'objet' => $objet,
            'message' => trim((string) ($_POST['message'] ?? '')),
            'canal' => ['mail' => 'E-mail', 'photo' => 'Partage', 'pdf' => 'Partage', 'texte' => 'Partage', 'fichier' => 'Partage'][$e['source']] ?? 'Partage',
            'client_id' => $clientId ?: null,
            'expediteur_nom' => '', 'expediteur_entreprise' => '', 'expediteur_email' => '', 'expediteur_telephone' => '',
            'recue_le' => substr((string) $e['created_at'], 0, 10),
            'activite' => $activite, 'responsable_id' => null, 'priorite' => 'normale', 'echeance' => null,
            'date_souhaitee_client' => null, 'notes_internes' => '',
        ]);
        foreach ((array) ($_POST['art_designation'] ?? []) as $i => $des) {
            $des = trim((string) $des);
            if ($des === '') { continue; }
            DemandeArticle::create($demandeId, [
                'designation' => $des,
                'quantite' => $_POST['art_quantite'][$i] ?? null,
                'unite' => trim((string) ($_POST['art_unite'][$i] ?? '')),
                'conditionnement' => trim((string) ($_POST['art_conditionnement'][$i] ?? '')),
                'reference' => trim((string) ($_POST['art_reference'][$i] ?? '')),
                'marque' => trim((string) ($_POST['art_marque'][$i] ?? '')),
            ]);
        }
        if ($e['nom_fichier']) {
            $src = Storage::path('uploads/entrants/' . $e['id'] . '/' . $e['nom_fichier']);
            $dst = Storage::path('uploads/demandes/' . $demandeId . '/' . $e['nom_fichier']);
            if (is_file($src) && @rename($src, $dst)) {
                DemandePieceJointe::create([
                    'demande_id' => $demandeId, 'nom_original' => $e['nom_original'], 'nom_fichier' => $e['nom_fichier'],
                    'taille' => (int) $e['taille'], 'type_mime' => $e['type_mime'] ?: 'application/octet-stream', 'uploaded_by' => (int) $user['id'],
                ]);
            }
        }
        EntrantDemande::marquer((int) $e['id'], 'valide', $demandeId);
        AuditLog::log($filialeId, (int) $user['id'], 'creation', 'demande', $demandeId, 'Demande créée depuis À valider');
        View::flash('succes', 'Demande créée. Le message d\'origine est joint à la demande.');
        header('Location: /index.php?r=demandes/' . $demandeId);
        exit;
    }

    public function ignorer(array $params): void
    {
        Auth::requireWrite();
        $e = $this->charger($params, true);
        if (!$e) {
            return;
        }
        EntrantDemande::marquer((int) $e['id'], 'ignore');
        AuditLog::log((int) $e['filiale_id'], (int) Auth::user()['id'], 'entrant_ignore', 'demande_entrante', (int) $e['id'], 'Mis de côté');
        View::flash('succes', 'Mis de côté. Rien n\'a été créé.');
        header('Location: ' . $this->retour());
        exit;
    }
}

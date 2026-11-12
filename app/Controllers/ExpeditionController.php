<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Dossier;
use App\Models\Expedition;
use App\Models\Filiale;

/** Expéditions (groupage / conteneur complet) et réservations des dossiers clients. */
class ExpeditionController
{
    private const LISTE = '/index.php?r=expeditions';

    private function numerique($v): ?float
    {
        $v = str_replace([' ', ','], ['', '.'], trim((string) $v));
        return $v === '' || !is_numeric($v) ? null : (float) $v;
    }

    private function entier($v): ?int
    {
        $n = $this->numerique($v);
        return $n === null ? null : (int) round($n);
    }

    private function charger(array $params, bool $ecriture = false): ?array
    {
        $user = Auth::user();
        $exp = Expedition::find((int) ($params['id'] ?? 0));
        if (!$exp || !Filiale::userCanAccess($user, (int) $exp['filiale_id'])) {
            http_response_code(404);
            View::render('errors/404');
            return null;
        }
        if ($ecriture) {
            Auth::requireWrite();
            if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
                View::flash('erreur', 'Session expirée, merci de réessayer.');
                header('Location: /index.php?r=expeditions/' . $exp['id']);
                exit;
            }
        }
        return $exp;
    }

    public function index(): void
    {
        $user = Auth::user();
        $statut = (string) ($_GET['statut'] ?? 'en_cours');
        View::render('expeditions/index', [
            'disponible' => Expedition::disponible(),
            'expeditions' => Expedition::disponible() ? Expedition::visibleFor($user, $statut) : [],
            'statut' => $statut,
            'voitMarges' => \App\Core\Permissions::canSeeMarges($user['role']),
        ]);
    }

    public function create(): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        View::render('expeditions/form', ['exp' => null, 'filiales' => Filiale::visibleFor($user), 'erreur' => null]);
    }

    private function lireFormulaire(): array
    {
        return [
            'type' => isset(Expedition::TYPES[$_POST['type'] ?? '']) ? $_POST['type'] : 'groupage',
            'mode' => isset(Expedition::MODES[$_POST['mode'] ?? '']) ? $_POST['mode'] : 'maritime',
            'origine' => mb_substr(trim((string) ($_POST['origine'] ?? '')), 0, 120),
            'destination' => mb_substr(trim((string) ($_POST['destination'] ?? '')), 0, 120),
            'partenaire' => mb_substr(trim((string) ($_POST['partenaire'] ?? '')), 0, 150),
            'date_depart' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['date_depart'] ?? '')) ? $_POST['date_depart'] : '',
            'date_arrivee' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['date_arrivee'] ?? '')) ? $_POST['date_arrivee'] : '',
            'cloture_reservations' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['cloture_reservations'] ?? '')) ? $_POST['cloture_reservations'] : '',
            'capacite_volume' => ($v = $this->numerique($_POST['capacite_volume'] ?? '')) !== null ? $v : '',
            'capacite_poids' => ($v = $this->numerique($_POST['capacite_poids'] ?? '')) !== null ? $v : '',
            'cout_achat' => max(0, $this->numerique($_POST['cout_achat'] ?? '') ?? 0),
            'devise' => mb_substr(trim((string) ($_POST['devise'] ?? 'EUR')) ?: 'EUR', 0, 10),
            'numero_suivi' => mb_substr(trim((string) ($_POST['numero_suivi'] ?? '')), 0, 80),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];
    }

    public function store(): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: ' . self::LISTE);
            exit;
        }
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);
        $d = $this->lireFormulaire();
        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId) || !Expedition::disponible()) {
            View::flash('erreur', "Création impossible (accès à la filiale ou migrate_v58.php manquant).");
            header('Location: ' . self::LISTE);
            exit;
        }
        if ($d['origine'] === '' || $d['destination'] === '') {
            View::flash('erreur', "L'origine et la destination sont obligatoires.");
            header('Location: /index.php?r=expeditions/nouvelle');
            exit;
        }
        $d['filiale_id'] = $filialeId;
        $d['created_by'] = (int) $user['id'];
        $id = Expedition::create($d);
        AuditLog::log($filialeId, (int) $user['id'], 'creation', 'expedition', $id, $d['origine'] . ' → ' . $d['destination']);
        View::flash('succes', 'Expédition créée.');
        header('Location: /index.php?r=expeditions/' . $id);
        exit;
    }

    public function show(array $params): void
    {
        $user = Auth::user();
        $exp = $this->charger($params);
        if (!$exp) {
            return;
        }
        $res = Expedition::reservations((int) $exp['id']);
        $dossiers = [];
        try {
            $dossiers = array_slice(Dossier::visibleFor($user, ['statut' => 'actif', 'filiale_id' => (int) $exp['filiale_id']]), 0, 200);
        } catch (\Throwable $e) {
        }
        View::render('expeditions/show', [
            'exp' => $exp,
            'reservations' => $res,
            'bilan' => Expedition::bilan($exp, $res),
            'dossiers' => $dossiers,
            'voitMarges' => \App\Core\Permissions::canSeeMarges($user['role']),
            'peutEcrire' => Auth::canWrite(),
        ]);
    }

    public function edit(array $params): void
    {
        Auth::requireWrite();
        $exp = $this->charger($params);
        if ($exp) {
            View::render('expeditions/form', ['exp' => $exp, 'filiales' => [], 'erreur' => null]);
        }
    }

    public function update(array $params): void
    {
        $exp = $this->charger($params, true);
        if (!$exp) {
            return;
        }
        $d = $this->lireFormulaire();
        if ($d['origine'] === '' || $d['destination'] === '') {
            View::flash('erreur', "L'origine et la destination sont obligatoires.");
            header('Location: /index.php?r=expeditions/' . $exp['id'] . '/modifier');
            exit;
        }
        Expedition::modifier((int) $exp['id'], $d);
        AuditLog::log((int) $exp['filiale_id'], (int) Auth::user()['id'], 'modification', 'expedition', (int) $exp['id'], 'Expédition modifiée');
        View::flash('succes', 'Expédition enregistrée. Les prix déjà fixés des réservations ne changent pas.');
        header('Location: /index.php?r=expeditions/' . $exp['id']);
        exit;
    }

    public function statut(array $params): void
    {
        $exp = $this->charger($params, true);
        if (!$exp) {
            return;
        }
        $s = (string) ($_POST['statut'] ?? '');
        if (isset(Expedition::STATUTS[$s])) {
            Expedition::changerStatut((int) $exp['id'], $s);
            AuditLog::log((int) $exp['filiale_id'], (int) Auth::user()['id'], 'statut_expedition', 'expedition', (int) $exp['id'], $exp['statut'] . ' → ' . $s);
            View::flash('succes', 'Statut : ' . Expedition::STATUTS[$s] . '.');
        }
        header('Location: /index.php?r=expeditions/' . $exp['id']);
        exit;
    }

    /** Ajoute une réservation, depuis la fiche expédition ou depuis un dossier (champ dossier_id). */
    public function reserver(array $params): void
    {
        $exp = $this->charger($params, true);
        if (!$exp) {
            return;
        }
        $user = Auth::user();
        $retour = !empty($_POST['retour_dossier']) ? '/index.php?r=dossiers/' . (int) $_POST['retour_dossier'] . '&onglet=execution' : '/index.php?r=expeditions/' . $exp['id'];
        if ($exp['statut'] !== 'ouverte') {
            View::flash('erreur', "Les réservations sont closes pour cette expédition.");
            header('Location: ' . $retour);
            exit;
        }
        if ($exp['type'] === 'conteneur_complet' && Expedition::reservations((int) $exp['id'])) {
            View::flash('erreur', "Un conteneur complet n'a qu'une seule réservation.");
            header('Location: ' . $retour);
            exit;
        }
        $dossierId = 0;
        $clientId = 0;
        $clientNom = trim((string) ($_POST['client_nom'] ?? ''));
        if (!empty($_POST['dossier_id'])) {
            $dos = Dossier::find((int) $_POST['dossier_id']);
            if ($dos && Dossier::userCanAccess($user, $dos) && (int) $dos['filiale_id'] === (int) $exp['filiale_id']) {
                $dossierId = (int) $dos['id'];
                $dm = \App\Models\Demande::find((int) $dos['demande_id']);
                $clientId = (int) ($dm['client_id'] ?? 0);
                if ($clientNom === '' && $clientId) {
                    $clientNom = (string) (\App\Models\Client::find($clientId)['nom'] ?? '');
                }
            }
        }
        if ($dossierId === 0 && $clientNom === '') {
            View::flash('erreur', 'Indiquez un dossier ou le nom du client.');
            header('Location: ' . $retour);
            exit;
        }
        $tarif = isset(Expedition::TARIFICATIONS[$_POST['tarification'] ?? '']) ? $_POST['tarification'] : 'forfait';
        $d = [
            'dossier_id' => $dossierId, 'client_id' => $clientId, 'client_nom' => mb_substr($clientNom, 0, 150),
            'type_marchandise' => isset(Expedition::MARCHANDISES[$_POST['type_marchandise'] ?? '']) ? $_POST['type_marchandise'] : 'non_alimentaire',
            'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 255),
            'volume' => $this->numerique($_POST['volume'] ?? ''), 'poids' => $this->numerique($_POST['poids'] ?? ''),
            'colis' => $this->entier($_POST['colis'] ?? ''), 'palettes' => $this->entier($_POST['palettes'] ?? ''),
            'tarification' => $tarif, 'tarif_unitaire' => max(0, $this->numerique($_POST['tarif_unitaire'] ?? '') ?? 0),
            'created_by' => (int) $user['id'],
        ];
        $id = Expedition::ajouterReservation($exp, $d);
        AuditLog::log((int) $exp['filiale_id'], (int) $user['id'], 'reservation_expedition', 'expedition', (int) $exp['id'], ($clientNom ?: 'Dossier ' . $dossierId));
        $bilan = Expedition::bilan($exp);
        View::flash($bilan['surcharge'] ? 'erreur' : 'succes', $bilan['surcharge'] ? "Réservation ajoutée, mais la capacité de l'expédition est dépassée." : 'Réservation ajoutée.');
        header('Location: ' . $retour);
        exit;
    }

    public function supprimerReservation(array $params): void
    {
        $exp = $this->charger($params, true);
        if (!$exp) {
            return;
        }
        $r = Expedition::findReservation((int) ($_POST['reservation_id'] ?? 0));
        if ($r && (int) $r['expedition_id'] === (int) $exp['id']) {
            Expedition::supprimerReservation((int) $r['id']);
            AuditLog::log((int) $exp['filiale_id'], (int) Auth::user()['id'], 'reservation_supprimee', 'expedition', (int) $exp['id'], (string) ($r['client_nom'] ?: 'Dossier ' . $r['dossier_id']));
            View::flash('succes', 'Réservation retirée.');
        }
        $retour = !empty($_POST['retour_dossier']) ? '/index.php?r=dossiers/' . (int) $_POST['retour_dossier'] . '&onglet=execution' : '/index.php?r=expeditions/' . $exp['id'];
        header('Location: ' . $retour);
        exit;
    }
}

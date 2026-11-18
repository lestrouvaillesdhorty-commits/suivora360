<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\DepartsPublics;
use App\Models\EntrepriseInfo;
use App\Models\Expedition;
use App\Models\Filiale;

/** Page publique des départs : réglages (connecté) et page en lecture seule (sans connexion, via un lien aléatoire). */
class DepartsPublicsController
{
    private const RETOUR = '/index.php?r=expeditions/page-publique';

    private static function urlPublique(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/index.php?r=departs/' . $token;
    }

    private function filialeCourante(array $user): int
    {
        $filiales = Filiale::visibleFor($user);
        $id = (int) ($_GET['filiale_id'] ?? $_POST['filiale_id'] ?? ($filiales[0]['id'] ?? 0));
        return ($id && Filiale::userCanAccess($user, $id)) ? $id : (int) ($filiales[0]['id'] ?? 0);
    }

    /** Écran de réglages : activer, numéro WhatsApp, lien, QR code. */
    public function reglages(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $filialeId = $this->filialeCourante($user);
        $disponible = DepartsPublics::disponible() && $filialeId;
        $reg = $disponible ? DepartsPublics::reglages($filialeId) : ['actif' => 0, 'token' => '', 'whatsapp' => ''];
        $expeditions = [];
        if ($disponible && Expedition::disponible()) {
            $st = \App\Core\Database::connection()->prepare("SELECT * FROM expeditions WHERE filiale_id = ? AND statut = 'ouverte' ORDER BY date_depart IS NULL, date_depart, id");
            $st->execute([$filialeId]);
            $expeditions = $st->fetchAll();
        }
        View::render('expeditions/page_publique', [
            'disponible' => $disponible, 'filiales' => Filiale::visibleFor($user), 'filialeId' => $filialeId,
            'hebdo' => DepartsPublics::hebdoListe($filialeId), 'reg' => $reg, 'url' => $reg['token'] !== '' ? self::urlPublique($reg['token']) : '', 'expeditions' => $expeditions,
            'peutEcrire' => Auth::canWrite(),
        ]);
    }

    public function enregistrer(): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null) || !$filialeId || !Filiale::userCanAccess($user, $filialeId) || !DepartsPublics::disponible()) {
            View::flash('erreur', 'Enregistrement impossible (session expirée ou migrate_v66.php manquant).');
            header('Location: ' . self::RETOUR);
            exit;
        }
        DepartsPublics::enregistrer($filialeId, !empty($_POST['actif']), (string) ($_POST['whatsapp'] ?? ''), !empty($_POST['nouveau_lien']), isset($_POST['interdits']) ? (string) $_POST['interdits'] : null);
        View::flash('succes', !empty($_POST['nouveau_lien']) ? "Nouveau lien créé : l'ancien ne fonctionne plus." : 'Réglages enregistrés.');
        header('Location: ' . self::RETOUR . '&filiale_id=' . $filialeId);
        exit;
    }

    /** Départs hebdomadaires : créer, modifier, supprimer, suspendre une semaine. */
    public function hebdo(): void
    {
        Auth::requireWrite();
        $user = Auth::user();
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);
        $retour = self::RETOUR . '&filiale_id=' . $filialeId;
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null) || !$filialeId || !Filiale::userCanAccess($user, $filialeId) || !DepartsPublics::hebdoDisponible()) {
            View::flash('erreur', 'Enregistrement impossible (session expirée ou migrate_v67.php manquant).');
            header('Location: ' . self::RETOUR);
            exit;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $action = (string) ($_POST['action'] ?? 'enregistrer');
        if ($action === 'supprimer') {
            DepartsPublics::hebdoSupprimer($filialeId, $id);
            View::flash('succes', 'Départ hebdomadaire supprimé.');
        } elseif ($action === 'suspendre' || $action === 'reprendre') {
            DepartsPublics::hebdoSuspendre($filialeId, $id, $action === 'suspendre');
            View::flash('succes', $action === 'suspendre' ? 'Prochain départ suspendu : il disparaît de la page jusqu\'à la semaine suivante.' : 'Départ réactivé.');
        } else {
            if (trim((string) ($_POST['origine'] ?? '')) === '' || trim((string) ($_POST['destination'] ?? '')) === '') {
                View::flash('erreur', "L'origine et la destination sont obligatoires.");
                header('Location: ' . $retour);
                exit;
            }
            DepartsPublics::hebdoEnregistrer($filialeId, $_POST, $id);
            View::flash('succes', 'Départ hebdomadaire enregistré.');
        }
        header('Location: ' . $retour . '#hebdo');
        exit;
    }

    /** Page publique : aucune authentification, aucune donnée client. */
    public function show(array $params): void
    {
        $info = DepartsPublics::findByToken((string) ($params['token'] ?? ''));
        if (!$info) {
            http_response_code(404);
            header('X-Robots-Tag: noindex');
            echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Page introuvable</title></head><body style="font-family:sans-serif;text-align:center;padding:60px 20px"><h1 style="font-size:20px">Cette page n\'est pas disponible.</h1><p>Le lien est peut-être ancien. Contactez-nous pour obtenir le bon lien.</p></body></html>';
            return;
        }
        $filialeId = (int) $info['filiale_id'];
        $filiale = Filiale::find($filialeId) ?: [];
        $emetteur = ['nom' => trim((string) ($info['raison_sociale'] ?? '')) ?: (string) ($filiale['nom'] ?? '')] + $info;
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        View::renderPlain('expeditions/departs_public', [
            'emetteur' => $emetteur, 'logoUri' => EntrepriseInfo::logoDataUri($info),
            'departs' => DepartsPublics::departs($filialeId), 'whatsapp' => (string) ($info['depart_whatsapp'] ?? ''), 'interdits' => (string) ($info['depart_interdits'] ?? ''),
        ]);
    }
}

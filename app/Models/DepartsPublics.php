<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Schema;

/**
 * Page publique des départs : liste en lecture seule des expéditions que l'entreprise choisit d'afficher.
 * N'expose que trajet, mode, dates, places restantes et tarif affiché : jamais de client, de réservation ni de coût d'achat.
 */
class DepartsPublics
{
    public const UNITES = ['m3' => 'm³', 'kg' => 'kg', 'colis' => 'colis', 'palette' => 'palette'];
    private const CACHE_SECONDES = 30;

    /** Devises proposées pour les tarifs (XAF et XOF s'affichent « FCFA »). */
    public const DEVISES = ['EUR', 'XAF', 'XOF', 'USD', 'GBP'];
    public const ORGANISATIONS = ['capacite' => 'Capacité fixe : places restantes en kg', 'reservations' => 'Départ confirmé selon les réservations', 'confirme' => 'Départ confirmé (date certaine)'];
    public const TRANSPORTS = ['fret' => 'Fret aérien', 'voyageur' => 'Par voyageur', 'maritime' => 'Maritime', 'routier' => 'Routier'];
    public const PRODUITS = ['alimentaire' => 'Alimentaire', 'non_alimentaire' => 'Non alimentaire'];
    public const JOURS = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

    public static function v67(): bool
    {
        return Schema::colonne('expeditions', 'public_organisation');
    }

    public static function hebdoDisponible(): bool
    {
        return Schema::colonne('departs_hebdo', 'jour_semaine');
    }

    public static function disponible(): bool
    {
        return Schema::colonne('entreprise_infos', 'depart_token') && Schema::colonne('expeditions', 'public_affiche');
    }

    /** Réglages de l'entreprise (filiale) : actif, token, numéro WhatsApp. */
    public static function reglages(int $filialeId): array
    {
        $vide = ['actif' => 0, 'token' => '', 'whatsapp' => '', 'interdits' => ''];
        if (!self::disponible()) {
            return $vide;
        }
        $inter = Schema::colonne('entreprise_infos', 'depart_interdits') ? ', depart_interdits' : ", '' AS depart_interdits";
        $st = Database::connection()->prepare('SELECT depart_actif, depart_token, depart_whatsapp' . $inter . ' FROM entreprise_infos WHERE filiale_id = ? LIMIT 1');
        $st->execute([$filialeId]);
        $r = $st->fetch();
        return $r ? ['actif' => (int) $r['depart_actif'], 'token' => (string) $r['depart_token'], 'whatsapp' => (string) $r['depart_whatsapp'], 'interdits' => (string) $r['depart_interdits']] : $vide;
    }

    public static function enregistrer(int $filialeId, bool $actif, string $whatsapp, bool $nouveauLien = false, ?string $interdits = null): void
    {
        if (!self::disponible()) {
            return;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT id, depart_token FROM entreprise_infos WHERE filiale_id = ? LIMIT 1');
        $st->execute([$filialeId]);
        $ligne = $st->fetch();
        if (!$ligne) {
            $pdo->prepare('INSERT INTO entreprise_infos (filiale_id, updated_at) VALUES (?, ?)')->execute([$filialeId, date('Y-m-d H:i:s')]);
            $ligne = ['depart_token' => ''];
        }
        $token = (string) $ligne['depart_token'];
        if ($token === '' || $nouveauLien) {
            $token = bin2hex(random_bytes(10));
        }
        $tel = preg_replace('/[^0-9+]/', '', $whatsapp);
        $pdo->prepare('UPDATE entreprise_infos SET depart_actif = ?, depart_token = ?, depart_whatsapp = ? WHERE filiale_id = ?')
            ->execute([$actif ? 1 : 0, $token, mb_substr((string) $tel, 0, 30), $filialeId]);
        if ($interdits !== null && Schema::colonne('entreprise_infos', 'depart_interdits')) {
            $pdo->prepare('UPDATE entreprise_infos SET depart_interdits = ? WHERE filiale_id = ?')->execute([mb_substr(trim($interdits), 0, 3000), $filialeId]);
        }
        self::viderCache($filialeId);
    }

    /** Réglages d'affichage d'une expédition. */
    public static function enregistrerExpedition(int $expeditionId, array $d): void
    {
        if (!Schema::colonne('expeditions', 'public_affiche')) {
            return;
        }
        $num = function ($v) {
            $v = str_replace([' ', ','], ['', '.'], trim((string) $v));
            return $v !== '' && is_numeric($v) && (float) $v >= 0 ? round((float) $v, 2) : null;
        };
        Database::connection()->prepare('UPDATE expeditions SET public_affiche = ?, public_tarif_alim = ?, public_tarif_non_alim = ?, public_tarif_unite = ? WHERE id = ?')
            ->execute([!empty($d['public_affiche']) ? 1 : 0, $num($d['public_tarif_alim'] ?? ''), $num($d['public_tarif_non_alim'] ?? ''), 'kg', $expeditionId]);
        if (self::v67()) {
            $org = isset(self::ORGANISATIONS[$d['public_organisation'] ?? '']) ? $d['public_organisation'] : 'capacite';
            $type = ($d['public_objectif_type'] ?? '') === 'reservations' ? 'reservations' : 'kg';
            Database::connection()->prepare('UPDATE expeditions SET public_organisation = ?, public_objectif_type = ?, public_objectif = ?, public_confirme = ?, public_chiffres = ? WHERE id = ?')
                ->execute([$org, $type, $num($d['public_objectif'] ?? ''), !empty($d['public_confirme']) ? 1 : 0, !empty($d['public_chiffres']) ? 1 : 0, $expeditionId]);
        }
        $exp = Expedition::find($expeditionId);
        if ($exp) {
            self::viderCache((int) $exp['filiale_id']);
        }
    }

    public static function findByToken(string $token): ?array
    {
        if (!self::disponible() || !preg_match('/^[a-f0-9]{20}$/', $token)) {
            return null;
        }
        $st = Database::connection()->prepare('SELECT * FROM entreprise_infos WHERE depart_token = ? AND depart_actif = 1 LIMIT 1');
        $st->execute([$token]);
        return $st->fetch() ?: null;
    }

    private static function fichierCache(int $filialeId): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/departs_' . $filialeId . '.json';
    }

    /** Vide tous les caches de la page publique (changement de réservation, de statut…). */
    public static function viderTout(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/storage/cache/departs_*.json') ?: [] as $f) { @unlink($f); }
    }

    public static function viderCache(int $filialeId): void
    {
        $f = self::fichierCache($filialeId);
        if (is_file($f)) {
            @unlink($f);
        }
    }

    /** Départs affichables pour la page publique : expéditions cochées « afficher » + départs hebdomadaires. Mis en cache quelques minutes. */
    public static function departs(int $filialeId): array
    {
        $f = self::fichierCache($filialeId);
        if (is_file($f) && time() - filemtime($f) < self::CACHE_SECONDES) {
            $c = json_decode((string) file_get_contents($f), true);
            if (is_array($c) && ($c['jour'] ?? '') === date('Y-m-d')) {
                return $c['departs'];
            }
        }
        $liste = array_merge(self::departsExpeditions($filialeId), self::departsHebdo($filialeId));
        usort($liste, fn($a, $b) => strcmp($a['depart'] ?: '9999', $b['depart'] ?: '9999'));
        $dir = dirname($f);
        if (is_dir($dir) || @mkdir($dir, 0775, true)) {
            @file_put_contents($f, json_encode(['jour' => date('Y-m-d'), 'departs' => $liste], JSON_UNESCAPED_UNICODE));
        }
        return $liste;
    }

    private static function departsExpeditions(int $filialeId): array
    {
        $st = Database::connection()->prepare("SELECT * FROM expeditions WHERE filiale_id = ? AND statut = 'ouverte' AND public_affiche = 1 ORDER BY date_depart IS NULL, date_depart, id");
        $st->execute([$filialeId]);
        $aujourdhui = date('Y-m-d');
        $v67 = self::v67();
        $liste = [];
        foreach ($st->fetchAll() as $e) {
            $cloture = (string) ($e['cloture_reservations'] ?? '');
            if ($cloture !== '' && $cloture < $aujourdhui) {
                continue;
            }
            $depart = (string) ($e['date_depart'] ?? '');
            if ($depart !== '' && $depart < $aujourdhui) {
                continue;
            }
            $org = $v67 ? (string) ($e['public_organisation'] ?? 'capacite') : 'capacite';
            $b = Expedition::bilan($e);
            $capP = (float) ($e['capacite_poids'] ?? 0);
            $d = [
                'genre' => 'expedition', 'origine' => (string) $e['origine'], 'destination' => (string) $e['destination'],
                'transport' => (string) $e['mode'], 'transport_lib' => Expedition::MODES[$e['mode']] ?? (string) $e['mode'],
                'produit' => '', 'depart' => $depart, 'cloture' => $cloture, 'confirme' => true, 'prevu' => false,
                'etat' => 'sur_demande', 'places_kg' => null, 'progres' => null, 'depot' => '', 'regle' => '',
                'tarif_alim' => $e['public_tarif_alim'] !== null && $e['public_tarif_alim'] !== '' ? (float) $e['public_tarif_alim'] : null,
                'tarif_non_alim' => $e['public_tarif_non_alim'] !== null && $e['public_tarif_non_alim'] !== '' ? (float) $e['public_tarif_non_alim'] : null,
                'devise' => (string) ($e['devise'] ?: 'EUR'),
            ];
            if ($org === 'reservations') {
                $objType = ($e['public_objectif_type'] ?? 'kg') === 'reservations' ? 'reservations' : 'kg';
                $objectif = (float) ($e['public_objectif'] ?? 0);
                $actuel = $objType === 'kg' ? (float) $b['poids'] : (float) $b['nb'];
                $atteint = $objectif > 0 && $actuel >= $objectif;
                $confirme = $atteint || !empty($e['public_confirme']);
                $d['confirme'] = $confirme;
                $d['prevu'] = !$confirme;
                $d['etat'] = $confirme ? 'confirme' : 'remplissage';
                if ($objectif > 0) {
                    $d['progres'] = ['type' => $objType, 'actuel' => round($actuel, 1), 'objectif' => $objectif, 'pct' => (int) min(100, round($actuel / $objectif * 100)), 'chiffres' => !$v67 || !empty($e['public_chiffres'])];
                }
            } elseif ($org === 'confirme') {
                $d['etat'] = 'confirme';
            } elseif ($capP > 0) {
                $libre = max(0, round($capP - (float) $b['poids'], 0));
                $d['places_kg'] = $libre;
                $d['etat'] = $libre <= 0 ? 'complet' : ($libre <= max(50, $capP * 0.2) ? 'peu' : 'ok');
                if ($v67 && empty($e['public_chiffres'])) {
                    $d['places_kg'] = null;
                }
            }
            $liste[] = $d;
        }
        return $liste;
    }

    /** Prochaine date d'un départ hebdomadaire : respecte la règle de dépôt, l'heure limite et la suspension. */
    public static function prochaineDate(array $h, ?int $maintenant = null): ?string
    {
        $maintenant ??= time();
        $jour = (int) $h['jour_semaine'];
        $avant = max(0, (int) ($h['depot_jours_avant'] ?? 0));
        $heure = preg_match('/^\d{1,2}:\d{2}$/', (string) ($h['heure_limite'] ?? '')) ? $h['heure_limite'] : '23:59';
        $susp = (string) ($h['suspendu_jusqua'] ?? '');
        for ($i = 0; $i < 60; $i++) {
            $ts = strtotime(date('Y-m-d', $maintenant) . " +$i day");
            if ((int) date('N', $ts) !== $jour) {
                continue;
            }
            $date = date('Y-m-d', $ts);
            if ($susp !== '' && $date <= $susp) {
                continue;
            }
            $limite = strtotime(date('Y-m-d', strtotime($date . " -$avant day")) . ' ' . $heure);
            if ($limite >= $maintenant) {
                return $date;
            }
        }
        return null;
    }

    private static function departsHebdo(int $filialeId): array
    {
        $liste = [];
        foreach (self::hebdoListe($filialeId) as $h) {
            if (!(int) $h['actif'] || !($date = self::prochaineDate($h))) {
                continue;
            }
            $avant = (int) $h['depot_jours_avant'];
            $heure = trim((string) ($h['heure_limite'] ?? ''));
            $regle = $avant > 0
                ? 'Déposez votre colis au moins ' . $avant . ' jour' . ($avant > 1 ? 's' : '') . ' avant le départ, au plus tard le ' . self::jourDate(date('Y-m-d', strtotime($date . " -$avant day"))) . ($heure !== '' ? ' avant ' . $heure : '')
                : 'Déposez votre colis le jour même du départ' . ($heure !== '' ? ', avant ' . $heure : '') . '.';
            $regle = rtrim($regle, '.') . '.';
            $liste[] = [
                'genre' => 'hebdo', 'origine' => (string) $h['origine'], 'destination' => (string) $h['destination'],
                'transport' => (string) $h['transport'], 'transport_lib' => self::TRANSPORTS[$h['transport']] ?? (string) $h['transport'],
                'produit' => (string) $h['produit'], 'depart' => $date, 'cloture' => '', 'confirme' => true, 'prevu' => false,
                'etat' => 'depot', 'places_kg' => null, 'progres' => null, 'depot' => (string) ($h['lieu_depot'] ?? ''), 'regle' => $regle,
                'tarif_alim' => $h['produit'] === 'alimentaire' && $h['tarif_kg'] !== null && $h['tarif_kg'] !== '' ? (float) $h['tarif_kg'] : null,
                'tarif_non_alim' => $h['produit'] !== 'alimentaire' && $h['tarif_kg'] !== null && $h['tarif_kg'] !== '' ? (float) $h['tarif_kg'] : null,
                'devise' => (string) (($h['devise'] ?? '') ?: 'EUR'),
            ];
        }
        return $liste;
    }

    public static function jourDate(string $iso): string
    {
        $t = strtotime($iso);
        $mois = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        return self::JOURS[(int) date('N', $t)] . ' ' . (int) date('j', $t) . ' ' . $mois[(int) date('n', $t)];
    }

    // --- Départs hebdomadaires (réglages) ---

    public static function hebdoListe(int $filialeId): array
    {
        if (!self::hebdoDisponible()) {
            return [];
        }
        $st = Database::connection()->prepare('SELECT * FROM departs_hebdo WHERE filiale_id = ? ORDER BY jour_semaine, id');
        $st->execute([$filialeId]);
        return $st->fetchAll();
    }

    public static function hebdoEnregistrer(int $filialeId, array $d, int $id = 0): void
    {
        if (!self::hebdoDisponible()) {
            return;
        }
        $num = function ($v) {
            $v = str_replace([' ', ','], ['', '.'], trim((string) $v));
            return $v !== '' && is_numeric($v) && (float) $v >= 0 ? round((float) $v, 2) : null;
        };
        $vals = [
            max(1, min(7, (int) ($d['jour_semaine'] ?? 1))),
            mb_substr(trim((string) ($d['origine'] ?? '')), 0, 120), mb_substr(trim((string) ($d['destination'] ?? '')), 0, 120),
            isset(self::TRANSPORTS[$d['transport'] ?? '']) ? $d['transport'] : 'fret',
            isset(self::PRODUITS[$d['produit'] ?? '']) ? $d['produit'] : 'alimentaire',
            max(0, min(30, (int) ($d['depot_jours_avant'] ?? 0))),
            preg_match('/^\d{1,2}:\d{2}$/', (string) ($d['heure_limite'] ?? '')) ? $d['heure_limite'] : null,
            $num($d['tarif_kg'] ?? ''),
            mb_substr(trim((string) ($d['lieu_depot'] ?? '')), 0, 1000),
            preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['suspendu_jusqua'] ?? '')) ? $d['suspendu_jusqua'] : null,
            !empty($d['actif']) ? 1 : 0,
        ];
        $pdo = Database::connection();
        $devise = in_array($d['devise'] ?? '', self::DEVISES, true) ? $d['devise'] : 'EUR';
        $avecDevise = Schema::colonne('departs_hebdo', 'devise');
        if ($id > 0) {
            $pdo->prepare('UPDATE departs_hebdo SET jour_semaine=?, origine=?, destination=?, transport=?, produit=?, depot_jours_avant=?, heure_limite=?, tarif_kg=?, lieu_depot=?, suspendu_jusqua=?, actif=? WHERE id = ? AND filiale_id = ?')
                ->execute(array_merge($vals, [$id, $filialeId]));
        } else {
            $pdo->prepare('INSERT INTO departs_hebdo (jour_semaine, origine, destination, transport, produit, depot_jours_avant, heure_limite, tarif_kg, lieu_depot, suspendu_jusqua, actif, filiale_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute(array_merge($vals, [$filialeId, date('Y-m-d H:i:s')]));
        }
        if ($avecDevise) {
            if ($id > 0) {
                $pdo->prepare('UPDATE departs_hebdo SET devise = ? WHERE id = ? AND filiale_id = ?')->execute([$devise, $id, $filialeId]);
            } else {
                $pdo->prepare('UPDATE departs_hebdo SET devise = ? WHERE id = ? AND filiale_id = ?')->execute([$devise, (int) $pdo->lastInsertId(), $filialeId]);
            }
        }
        self::viderCache($filialeId);
    }

    public static function hebdoSupprimer(int $filialeId, int $id): void
    {
        if (self::hebdoDisponible()) {
            Database::connection()->prepare('DELETE FROM departs_hebdo WHERE id = ? AND filiale_id = ?')->execute([$id, $filialeId]);
            self::viderCache($filialeId);
        }
    }

    /** Suspend le départ jusqu'à (et y compris) sa prochaine date, ou le réactive. */
    public static function hebdoSuspendre(int $filialeId, int $id, bool $suspendre): void
    {
        if (!self::hebdoDisponible()) {
            return;
        }
        $st = Database::connection()->prepare('SELECT * FROM departs_hebdo WHERE id = ? AND filiale_id = ?');
        $st->execute([$id, $filialeId]);
        $h = $st->fetch();
        if (!$h) {
            return;
        }
        $jusqua = null;
        if ($suspendre) {
            $h['suspendu_jusqua'] = null;
            $jusqua = self::prochaineDate($h);
        }
        Database::connection()->prepare('UPDATE departs_hebdo SET suspendu_jusqua = ? WHERE id = ?')->execute([$jusqua, $id]);
        self::viderCache($filialeId);
    }

    // ------------------------------------------------------------------
    // [v70] Apparence, informations importantes, lieux de dépôt, contact
    // ------------------------------------------------------------------

    /** Ambiances douces : fond du bandeau, texte, accent, accent clair, bordure, libellé. */
    public const AMBIANCES = [
        'menthe' => ['linear-gradient(135deg,#E3F2F1,#F3FAF9)', '#0F3F46', '#0B7C8A', '#E3F2F1', '#CFE6E6', 'Menthe'],
        'sable' => ['linear-gradient(135deg,#F6EEE3,#FBF7F1)', '#4A3524', '#A8643A', '#F6EBDD', '#E8D9C4', 'Sable'],
        'ciel' => ['linear-gradient(135deg,#E5EEF9,#F5F9FE)', '#16325C', '#2F5FA8', '#E5EEF9', '#D2E0F3', 'Ciel'],
    ];

    public static function v70(): bool
    {
        return Schema::colonne('entreprise_infos', 'depart_ambiance') && Schema::colonne('depart_infos', 'texte') && Schema::colonne('depart_lieux', 'nom');
    }

    public static function aspect(int $filialeId): array
    {
        $vide = ['titre' => '', 'soustitre' => '', 'ambiance' => 'menthe', 'photo' => '', 'tel' => '', 'email' => '', 'etapes' => 1, 'conv' => 1, 'apercu' => 1];
        if (!self::v70()) {
            return $vide;
        }
        $st = Database::connection()->prepare('SELECT depart_titre, depart_soustitre, depart_ambiance, depart_photo, depart_tel, depart_email, depart_etapes, depart_conv, depart_apercu FROM entreprise_infos WHERE filiale_id = ? LIMIT 1');
        $st->execute([$filialeId]);
        $r = $st->fetch();
        if (!$r) {
            return $vide;
        }
        return [
            'titre' => (string) $r['depart_titre'], 'soustitre' => (string) $r['depart_soustitre'],
            'ambiance' => isset(self::AMBIANCES[$r['depart_ambiance']]) ? $r['depart_ambiance'] : 'menthe',
            'photo' => (string) $r['depart_photo'], 'tel' => (string) $r['depart_tel'], 'email' => (string) $r['depart_email'],
            'etapes' => (int) $r['depart_etapes'], 'conv' => (int) $r['depart_conv'], 'apercu' => (int) $r['depart_apercu'],
        ];
    }

    public static function enregistrerAspect(int $filialeId, array $d): void
    {
        if (!self::v70()) {
            return;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT id FROM entreprise_infos WHERE filiale_id = ? LIMIT 1');
        $st->execute([$filialeId]);
        if (!$st->fetch()) {
            $pdo->prepare('INSERT INTO entreprise_infos (filiale_id, updated_at) VALUES (?, ?)')->execute([$filialeId, date('Y-m-d H:i:s')]);
        }
        $amb = isset(self::AMBIANCES[$d['ambiance'] ?? '']) ? $d['ambiance'] : 'menthe';
        $email = trim((string) ($d['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }
        $pdo->prepare('UPDATE entreprise_infos SET depart_titre = ?, depart_soustitre = ?, depart_ambiance = ?, depart_tel = ?, depart_email = ?, depart_etapes = ?, depart_conv = ?, depart_apercu = ? WHERE filiale_id = ?')
            ->execute([
                mb_substr(trim((string) ($d['titre'] ?? '')), 0, 160), mb_substr(trim((string) ($d['soustitre'] ?? '')), 0, 255), $amb,
                mb_substr(preg_replace('/[^0-9+ ().-]/', '', (string) ($d['tel'] ?? '')), 0, 40), mb_substr($email, 0, 120),
                !empty($d['etapes']) ? 1 : 0, !empty($d['conv']) ? 1 : 0, !empty($d['apercu']) ? 1 : 0, $filialeId,
            ]);
        self::viderCache($filialeId);
    }

    /** Enregistre la photo du bandeau (réduite à 1600 px de large, JPEG). Retourne un message d'erreur ou null. */
    public static function enregistrerPhoto(int $filialeId, array $fichier): ?string
    {
        if (!self::v70() || ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($fichier['error'] ?? 0) !== UPLOAD_ERR_OK || ($fichier['size'] ?? 0) > 6 * 1024 * 1024) {
            return 'Photo non enregistrée : fichier trop lourd (6 Mo maximum).';
        }
        $info = @getimagesize($fichier['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || !function_exists('imagecreatetruecolor')) {
            return 'Photo non enregistrée : utilisez une image JPEG, PNG ou WebP.';
        }
        $src = match ($info[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($fichier['tmp_name']), IMAGETYPE_PNG => @imagecreatefrompng($fichier['tmp_name']), default => @imagecreatefromwebp($fichier['tmp_name']) };
        if (!$src) {
            return 'Photo non enregistrée : image illisible.';
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $largeur = min(1600, $w);
        $hauteur = (int) round($h * $largeur / $w);
        $dst = imagecreatetruecolor($largeur, $hauteur);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $largeur, $hauteur, $w, $h);
        $nom = 'depart_' . $filialeId . '_' . time() . '.jpg';
        $dossier = \App\Core\Storage::path('uploads/departs');
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }
        if (!imagejpeg($dst, \App\Core\Storage::path('uploads/departs/' . $nom), 82)) {
            return "Photo non enregistrée : impossible d'écrire sur le serveur.";
        }
        self::supprimerPhoto($filialeId);
        Database::connection()->prepare('UPDATE entreprise_infos SET depart_photo = ? WHERE filiale_id = ?')->execute([$nom, $filialeId]);
        return null;
    }

    public static function supprimerPhoto(int $filialeId): void
    {
        if (!self::v70()) {
            return;
        }
        $a = self::aspect($filialeId);
        if ($a['photo'] !== '') {
            @unlink(\App\Core\Storage::path('uploads/departs/' . basename($a['photo'])));
            Database::connection()->prepare("UPDATE entreprise_infos SET depart_photo = '' WHERE filiale_id = ?")->execute([$filialeId]);
        }
    }

    public static function photoChemin(array $aspect): ?string
    {
        if (($aspect['photo'] ?? '') === '') {
            return null;
        }
        $c = \App\Core\Storage::path('uploads/departs/' . basename($aspect['photo']));
        return is_file($c) ? $c : null;
    }

    // --- Informations importantes

    public static function infosListe(int $filialeId, bool $visiblesSeulement = false): array
    {
        if (!self::v70()) {
            return [];
        }
        $st = Database::connection()->prepare('SELECT * FROM depart_infos WHERE filiale_id = ? ORDER BY id DESC');
        $st->execute([$filialeId]);
        $liste = $st->fetchAll();
        if ($visiblesSeulement) {
            $auj = date('Y-m-d');
            $liste = array_values(array_filter($liste, fn($i) => (int) $i['actif'] === 1 && ($i['fin'] === null || $i['fin'] === '' || $i['fin'] >= $auj)));
        }
        return $liste;
    }

    public static function infoAjouter(int $filialeId, string $texte, string $niveau, string $fin): void
    {
        $texte = trim($texte);
        if (!self::v70() || $texte === '') {
            return;
        }
        Database::connection()->prepare('INSERT INTO depart_infos (filiale_id, texte, niveau, fin, actif, created_at) VALUES (?,?,?,?,1,?)')
            ->execute([$filialeId, mb_substr($texte, 0, 600), $niveau === 'alerte' ? 'alerte' : 'info', preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin) ? $fin : null, date('Y-m-d H:i:s')]);
    }

    public static function infoSupprimer(int $filialeId, int $id): void
    {
        if (self::v70()) {
            Database::connection()->prepare('DELETE FROM depart_infos WHERE id = ? AND filiale_id = ?')->execute([$id, $filialeId]);
        }
    }

    // --- Lieux de dépôt

    public static function lieuxListe(int $filialeId): array
    {
        if (!self::v70()) {
            return [];
        }
        $st = Database::connection()->prepare('SELECT * FROM depart_lieux WHERE filiale_id = ? ORDER BY ordre, id');
        $st->execute([$filialeId]);
        return $st->fetchAll();
    }

    public static function lieuAjouter(int $filialeId, array $d): void
    {
        $nom = trim((string) ($d['nom'] ?? ''));
        if (!self::v70() || $nom === '') {
            return;
        }
        $url = trim((string) ($d['carte_url'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = '';
        }
        Database::connection()->prepare('INSERT INTO depart_lieux (filiale_id, nom, adresse, horaires, carte_url, ordre, created_at) VALUES (?,?,?,?,?,?,?)')
            ->execute([$filialeId, mb_substr($nom, 0, 120), mb_substr(trim((string) ($d['adresse'] ?? '')), 0, 500), mb_substr(trim((string) ($d['horaires'] ?? '')), 0, 500), mb_substr($url, 0, 300), count(self::lieuxListe($filialeId)), date('Y-m-d H:i:s')]);
    }

    public static function lieuSupprimer(int $filialeId, int $id): void
    {
        if (self::v70()) {
            Database::connection()->prepare('DELETE FROM depart_lieux WHERE id = ? AND filiale_id = ?')->execute([$id, $filialeId]);
        }
    }

    /** Texte court des prochains départs (aperçu du lien WhatsApp). */
    public static function resumeApercu(array $departs, int $max = 3): string
    {
        $mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        $jours = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
        $avecDate = array_values(array_filter($departs, fn($d) => !empty($d['depart'])));
        usort($avecDate, fn($a, $b) => strcmp($a['depart'], $b['depart']));
        $lignes = [];
        foreach (array_slice($avecDate, 0, $max) as $d) {
            $t = strtotime($d['depart']);
            $tarif = $d['tarif_alim'] ?? $d['tarif_non_alim'];
            $dev = in_array($d['devise'], ['XAF', 'XOF'], true) ? 'FCFA' : ($d['devise'] === 'EUR' ? '€' : $d['devise']);
            $lignes[] = $jours[(int) date('w', $t)] . ' ' . (int) date('j', $t) . ' ' . $mois[(int) date('n', $t) - 1] . ' ' . $d['origine'] . ' → ' . $d['destination']
                . ($tarif !== null ? ' · ' . rtrim(rtrim(number_format((float) $tarif, 2, ',', ' '), '0'), ',') . ' ' . $dev . '/kg' : '');
        }
        return implode(' | ', $lignes);
    }

    /** Message WhatsApp prérempli pour un départ. */
    public static function messageWhatsapp(array $d, string $produit): string
    {
        $modes = Expedition::MODES;
        $date = $d['depart'] !== '' ? date('d/m/Y', strtotime($d['depart'])) : 'date à confirmer';
        return "Bonjour, je voudrais réserver sur le départ du $date : " . $d['origine'] . ' → ' . $d['destination']
            . ' (' . strtolower($modes[$d['mode']] ?? $d['mode']) . ").\nType de produit : $produit\nQuantité estimée : \nMon nom : ";
    }
}

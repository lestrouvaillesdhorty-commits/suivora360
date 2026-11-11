<?php

namespace App\Models;

use App\Core\AccesModules;
use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

/**
 * Activités visibles par rôle et par personne (décidé par le Propriétaire de l'entreprise).
 *
 * Trois niveaux par activité : complet, lecture (voir sans modifier), caché (n'apparaît nulle part).
 * Lecture dans l'ordre : liste de la personne (si elle en a une) > liste du rôle > tout en « complet ».
 * Propriétaire, Admin d'organisation et administrateur Suivora voient toujours tout.
 * Stockage : tables acces_modules / acces_modules_user (migrate_v52), module « act_<empreinte du nom> » :
 * aucune migration supplémentaire. Sans migrate_v52, aucune restriction (comportement d'avant).
 */
class ActiviteAcces
{
    private const PREFIXE = 'act_';
    private const TOUT = ['proprietaire', 'admin_organisation'];

    /** Cache par utilisateur : [userId => [nom => niveau]]. */
    private static array $cache = [];

    public static function cle(string $nom): string
    {
        return self::PREFIXE . substr(md5(mb_strtolower(trim($nom))), 0, 12);
    }

    private static function exempte(?array $user): bool
    {
        return !$user || !AccesModules::disponible() || !empty($user['is_super_admin']) || in_array($user['role'], self::TOUT, true);
    }

    /** Niveaux effectifs d'une personne : [nom d'activité => niveau]. Vide = aucune restriction. */
    public static function niveaux(?array $user = null): array
    {
        $user = $user ?? Auth::user();
        if (self::exempte($user)) {
            return [];
        }
        $uid = (int) $user['id'];
        if (isset(self::$cache[$uid])) {
            return self::$cache[$uid];
        }
        $orgId = (int) $user['organisation_id'];
        $pdo = Database::connection();
        $noms = array_column(Activite::toutes($orgId), 'nom');
        $parNom = [];
        foreach ($noms as $n) {
            $parNom[self::cle($n)] = $n;
        }
        $res = [];
        // Liste de la personne
        $st = $pdo->prepare("SELECT module, niveau FROM acces_modules_user WHERE utilisateur_id = ? AND module LIKE 'act_%'");
        $st->execute([$uid]);
        $lignes = $st->fetchAll();
        if (!$lignes) {
            $st = $pdo->prepare("SELECT module, niveau FROM acces_modules WHERE organisation_id = ? AND role = ? AND module LIKE 'act_%'");
            $st->execute([$orgId, AccesModules::cleDe($user)]);
            $lignes = $st->fetchAll();
        }
        foreach ($lignes as $l) {
            if (isset($parNom[$l['module']]) && isset(AccesModules::NIVEAUX[$l['niveau']]) && $l['niveau'] !== AccesModules::COMPLET) {
                $res[$parNom[$l['module']]] = $l['niveau'];
            }
        }
        return self::$cache[$uid] = $res;
    }

    public static function restreint(?array $user = null): bool
    {
        return self::niveaux($user) !== [];
    }

    public static function niveau(?string $activite, ?array $user = null): string
    {
        if ($activite === null || $activite === '') {
            return AccesModules::COMPLET;
        }
        return self::niveaux($user)[$activite] ?? AccesModules::COMPLET;
    }

    /** Noms des activités cachées pour la personne connectée. */
    public static function cachees(?array $user = null): array
    {
        return array_keys(array_filter(self::niveaux($user), fn ($n) => $n === AccesModules::CACHE));
    }

    private static function liste(array $noms): string
    {
        $pdo = Database::connection();
        return implode(',', array_map(fn ($n) => $pdo->quote($n), $noms));
    }

    /** Fragment SQL « AND … » : exclut les activités cachées. $col = colonne portant le nom d'activité (ex. dm.activite). */
    public static function sqlActivite(string $col): string
    {
        $c = self::cachees();
        return $c ? " AND ($col IS NULL OR $col NOT IN (" . self::liste($c) . '))' : '';
    }

    /** Idem pour une colonne contenant un id de demande. */
    public static function sqlDemande(string $col): string
    {
        $c = self::cachees();
        return $c ? " AND NOT EXISTS (SELECT 1 FROM demandes zg_m WHERE zg_m.id = $col AND zg_m.activite IN (" . self::liste($c) . '))' : '';
    }

    /** Idem pour une colonne contenant un id de dossier. */
    public static function sqlDossier(string $col): string
    {
        $c = self::cachees();
        return $c ? " AND NOT EXISTS (SELECT 1 FROM dossiers zg_d INNER JOIN demandes zg_m ON zg_m.id = zg_d.demande_id WHERE zg_d.id = $col AND zg_m.activite IN (" . self::liste($c) . '))' : '';
    }

    /** Activités affichables (retire les cachées) : filtres, formulaires, tableau de bord. */
    public static function retirerCachees(array $noms): array
    {
        $c = self::cachees();
        return $c ? array_values(array_diff($noms, $c)) : $noms;
    }

    private static bool $enCours = false;

    /**
     * Réécrit une requête de lecture pour en retirer les enregistrements d'une activité cachée :
     * jointures vers dossiers / demandes / cotations, et lectures directes de ces tables (clause WHERE).
     * Aucune modification pour les administrateurs, ni si rien n'est caché, ni pour les écritures.
     */
    public static function garde(string $sql): string
    {
        if (self::$enCours || !preg_match('/^\s*(SELECT|WITH)\b/i', $sql) || stripos($sql, 'demandes') === false && stripos($sql, 'dossiers') === false && stripos($sql, 'cotations') === false
            && stripos($sql, 'commandes') === false && stripos($sql, 'offres') === false && stripos($sql, 'factures') === false && stripos($sql, 'consultations_fournisseur') === false && stripos($sql, 'bons_commande_fournisseur') === false) {
            return $sql;
        }
        self::$enCours = true;
        try {
            $c = self::cachees();
            if (!$c) {
                return $sql;
            }
            $in = self::liste($c);
            $viaDossier = fn (string $col) => "NOT EXISTS (SELECT 1 FROM dossiers zg_d INNER JOIN demandes zg_m ON zg_m.id = zg_d.demande_id WHERE zg_d.id = $col AND zg_m.activite IN ($in))";
            // 1. INNER JOIN demandes x ON x.id = d.demande_id
            $sql = preg_replace_callback('/INNER JOIN demandes (?!zg_)(\w+) ON \1\.id = (\w+)\.demande_id/i',
                fn ($m) => $m[0] . " AND ({$m[1]}.activite IS NULL OR {$m[1]}.activite NOT IN ($in))", $sql);
            // 2. INNER JOIN dossiers d ON d.id = x.dossier_id
            $sql = preg_replace_callback('/INNER JOIN dossiers (?!zg_)(\w+) ON \1\.id = (\w+)\.dossier_id/i',
                fn ($m) => $m[0] . ' AND ' . "NOT EXISTS (SELECT 1 FROM demandes zg_m WHERE zg_m.id = {$m[1]}.demande_id AND zg_m.activite IN ($in))", $sql);
            // 3. INNER JOIN cotations c ON c.id = x.cotation_id
            $sql = preg_replace_callback('/INNER JOIN cotations (?!zg_)(\w+) ON \1\.id = (\w+)\.cotation_id/i',
                fn ($m) => $m[0] . ' AND ' . $viaDossier($m[1] . '.dossier_id'), $sql);
            // 4. Lectures directes : FROM <table> [alias] WHERE …
            $kw = '(?!WHERE\b|INNER\b|LEFT\b|JOIN\b|ORDER\b|GROUP\b|zg_)';
            $sql = preg_replace_callback('/\bFROM (cotations|commandes|offres|factures|consultations_fournisseur|bons_commande_fournisseur)(?: ' . $kw . '(\w+))? WHERE /i',
                fn ($m) => $m[0] . $viaDossier((($m[2] ?? '') !== '' ? $m[2] : $m[1]) . '.dossier_id') . ' AND ', $sql);
            $sql = preg_replace_callback('/\bFROM dossiers(?: ' . $kw . '(\w+))? WHERE /i',
                fn ($m) => $m[0] . "NOT EXISTS (SELECT 1 FROM demandes zg_m WHERE zg_m.id = " . (($m[1] ?? '') !== '' ? $m[1] : 'dossiers') . ".demande_id AND zg_m.activite IN ($in)) AND ", $sql);
            $sql = preg_replace_callback('/\bFROM demandes(?: ' . $kw . '(\w+))? WHERE /i',
                function ($m) use ($in) {
                    $col = (($m[1] ?? '') !== '' ? $m[1] . '.' : '') . 'activite';
                    return $m[0] . "($col IS NULL OR $col NOT IN ($in)) AND ";
                }, $sql);
            return $sql;
        } finally {
            self::$enCours = false;
        }
    }

    // ----- Activité d'un enregistrement -----

    private const ENTITES = [
        'demandes' => 'demande', 'dossiers' => 'dossier', 'cotations' => 'cotation', 'commandes' => 'commande',
        'offres' => 'offre', 'factures' => 'facture', 'consultations' => 'consultation', 'bons-commande' => 'bon',
    ];
    private const TABLES = [
        'cotation' => 'cotations', 'commande' => 'commandes', 'offre' => 'offres', 'facture' => 'factures',
        'consultation' => 'consultations_fournisseur', 'bon' => 'bons_commande_fournisseur',
    ];

    public static function activiteDe(string $entite, int $id): ?string
    {
        $pdo = Database::connection();
        $avant = self::$enCours;
        self::$enCours = true; // on lit l'activité réelle : le filtre ne doit pas s'appliquer à cette lecture
        try {
            if ($entite === 'demande') {
                $st = $pdo->prepare('SELECT activite FROM demandes WHERE id = ?');
            } elseif ($entite === 'dossier') {
                $st = $pdo->prepare('SELECT dm.activite FROM dossiers d INNER JOIN demandes dm ON dm.id = d.demande_id WHERE d.id = ?');
            } elseif (isset(self::TABLES[$entite])) {
                $st = $pdo->prepare('SELECT dm.activite FROM ' . self::TABLES[$entite] . ' x INNER JOIN dossiers d ON d.id = x.dossier_id INNER JOIN demandes dm ON dm.id = d.demande_id WHERE x.id = ?');
            } else {
                return null;
            }
            $st->execute([$id]);
            $v = $st->fetchColumn();
            return $v === false || $v === '' ? null : (string) $v;
        } catch (\Throwable $e) {
            return null;
        } finally {
            self::$enCours = $avant;
        }
    }

    public static function peutVoir(string $entite, int $id, ?array $user = null): bool
    {
        return !self::restreint($user) || self::niveau(self::activiteDe($entite, $id), $user) !== AccesModules::CACHE;
    }

    public static function peutModifier(?string $activite, ?array $user = null): bool
    {
        return self::niveau($activite, $user) === AccesModules::COMPLET;
    }

    /** Une notification (entite_type / entite_id) est-elle visible ? */
    public static function notificationVisible(array $n, ?array $user = null): bool
    {
        if (!self::restreint($user)) {
            return true;
        }
        $type = (string) ($n['entite_type'] ?? '');
        $id = (int) ($n['entite_id'] ?? 0);
        $entite = ['demande' => 'demande', 'dossier' => 'dossier', 'cotation' => 'cotation', 'commande' => 'commande',
            'offre' => 'offre', 'facture' => 'facture', 'consultation' => 'consultation', 'consultation_fournisseur' => 'consultation',
            'bon_commande' => 'bon'][$type] ?? null;
        return $entite === null || !$id || self::peutVoir($entite, $id, $user);
    }

    /** Appelée par le routeur : refuse une activité cachée, et toute modification d'une activité en lecture. */
    public static function controler(string $methode, string $path): void
    {
        $user = Auth::user();
        if (!$user || !self::restreint($user)) {
            return;
        }
        $seg = explode('/', trim($path, '/'));
        $activites = [];
        $entite = self::ENTITES[$seg[0] ?? ''] ?? null;
        if ($entite !== null && isset($seg[1]) && ctype_digit($seg[1])) {
            $activites[] = self::activiteDe($entite, (int) $seg[1]);
        }
        foreach (['dossier_id' => 'dossier', 'demande_id' => 'demande'] as $k => $e) {
            $v = $_POST[$k] ?? $_GET[$k] ?? null;
            if (is_scalar($v) && ctype_digit((string) $v) && (int) $v > 0) {
                $activites[] = self::activiteDe($e, (int) $v);
            }
        }
        // Création / changement d'activité d'une demande
        if ($methode !== 'GET' && isset($_POST['activite']) && is_string($_POST['activite']) && $_POST['activite'] !== '') {
            $activites[] = $_POST['activite'];
        }
        foreach ($activites as $a) {
            $n = self::niveau($a, $user);
            if ($n === AccesModules::CACHE) {
                http_response_code(403);
                View::render('errors/403');
                exit;
            }
            if ($n === AccesModules::LECTURE && $methode !== 'GET') {
                View::flash('erreur', "L'activité « $a » est en lecture seule pour vous : modification impossible.");
                $retour = $_SERVER['HTTP_REFERER'] ?? '/index.php';
                $h = parse_url($retour, PHP_URL_HOST);
                header('Location: ' . ($h === null || $h === ($_SERVER['HTTP_HOST'] ?? '') ? $retour : '/index.php'));
                exit;
            }
        }
    }

    // ----- Réglage (Propriétaire) -----

    /** Niveaux réglés pour un rôle : [nom => niveau] pour toutes les activités de l'entreprise (défaut complet). */
    public static function niveauxRole(int $orgId, string $cle): array
    {
        $st = Database::connection()->prepare("SELECT module, niveau FROM acces_modules WHERE organisation_id = ? AND role = ? AND module LIKE 'act_%'");
        $st->execute([$orgId, $cle]);
        $reg = [];
        foreach ($st->fetchAll() as $l) {
            $reg[$l['module']] = $l['niveau'];
        }
        $out = [];
        foreach (Activite::toutes($orgId) as $a) {
            $n = $reg[self::cle($a['nom'])] ?? AccesModules::COMPLET;
            $out[$a['nom']] = isset(AccesModules::NIVEAUX[$n]) ? $n : AccesModules::COMPLET;
        }
        return $out;
    }

    public static function enregistrerRole(int $orgId, string $cle, string $nom, string $niveau): void
    {
        if (!AccesModules::cleValide($orgId, $cle) || !isset(AccesModules::NIVEAUX[$niveau]) || !self::activiteConnue($orgId, $nom)) {
            return;
        }
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM acces_modules WHERE organisation_id = ? AND role = ? AND module = ?')->execute([$orgId, $cle, self::cle($nom)]);
        if ($niveau !== AccesModules::COMPLET) {
            $pdo->prepare('INSERT INTO acces_modules (organisation_id, role, module, niveau) VALUES (?,?,?,?)')->execute([$orgId, $cle, self::cle($nom), $niveau]);
        }
        self::$cache = [];
    }

    private static function activiteConnue(int $orgId, string $nom): bool
    {
        foreach (Activite::toutes($orgId) as $a) {
            if ($a['nom'] === $nom) {
                return true;
            }
        }
        return false;
    }

    /** Liste propre à une personne : [nom => niveau] ou null si elle suit son rôle. */
    public static function listePersonne(int $userId, int $orgId): ?array
    {
        $st = Database::connection()->prepare("SELECT module, niveau FROM acces_modules_user WHERE utilisateur_id = ? AND module LIKE 'act_%'");
        $st->execute([$userId]);
        $lignes = $st->fetchAll();
        if (!$lignes) {
            return null;
        }
        $reg = array_column($lignes, 'niveau', 'module');
        $out = [];
        foreach (Activite::toutes($orgId) as $a) {
            $n = $reg[self::cle($a['nom'])] ?? AccesModules::COMPLET;
            $out[$a['nom']] = isset(AccesModules::NIVEAUX[$n]) ? $n : AccesModules::COMPLET;
        }
        return $out;
    }

    /** $niveaux = null : la personne suit son rôle. Sinon liste complète choisie pour elle (une ligne par activité). */
    public static function definirPersonne(int $orgId, int $userId, ?array $niveaux): bool
    {
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT role FROM utilisateurs WHERE id = ? AND organisation_id = ?');
        $st->execute([$userId, $orgId]);
        $role = $st->fetchColumn();
        if (!$role || in_array($role, self::TOUT, true)) {
            return false;
        }
        $pdo->prepare("DELETE FROM acces_modules_user WHERE utilisateur_id = ? AND module LIKE 'act_%'")->execute([$userId]);
        if ($niveaux !== null) {
            foreach (Activite::toutes($orgId) as $a) {
                $n = $niveaux[$a['nom']] ?? AccesModules::COMPLET;
                if (!isset(AccesModules::NIVEAUX[$n])) {
                    $n = AccesModules::COMPLET;
                }
                // Une ligne par activité, même en « complet » : c'est ce qui distingue « liste de la personne » de « suit le rôle ».
                $pdo->prepare('INSERT INTO acces_modules_user (utilisateur_id, module, niveau) VALUES (?,?,?)')->execute([$userId, self::cle($a['nom']), $n]);
            }
        }
        self::$cache = [];
        return true;
    }

    /** Recopie la liste d'activités d'un rôle vers un autre (création d'un rôle personnalisé). */
    public static function copierRole(int $orgId, string $source, string $cible): void
    {
        foreach (self::niveauxRole($orgId, $source) as $nom => $n) {
            self::enregistrerRole($orgId, $cible, $nom, $n);
        }
    }

    /** Efface les réglages d'activités d'un rôle (suppression du rôle personnalisé, ou « revenir aux réglages de départ »). */
    public static function effacerRole(int $orgId, ?string $cle = null): void
    {
        $pdo = Database::connection();
        if ($cle === null) {
            $pdo->prepare("DELETE FROM acces_modules WHERE organisation_id = ? AND module LIKE 'act_%'")->execute([$orgId]);
        } else {
            $pdo->prepare("DELETE FROM acces_modules WHERE organisation_id = ? AND role = ? AND module LIKE 'act_%'")->execute([$orgId, $cle]);
        }
        self::$cache = [];
    }
}

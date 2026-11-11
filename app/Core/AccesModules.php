<?php

namespace App\Core;

/**
 * Accès aux modules par rôle (et par personne), à trois niveaux :
 *   complet = voir et modifier · lecture = voir seulement · cache = absent du menu, adresse refusée.
 *
 * Résolution : exception de la personne > réglage de l'entreprise pour son rôle > réglage de départ (DEPART).
 * Propriétaire, Admin d'organisation et administrateur Suivora ont toujours « complet ».
 * Les pages Filiales, Utilisateurs, Sécurité, Administration Suivora restent régies par le rôle (Permissions).
 * Tant que migrate_v52.php n'a pas été lancée, aucune restriction n'est appliquée (comportement d'avant).
 */
class AccesModules
{
    public const COMPLET = 'complet';
    public const LECTURE = 'lecture';
    public const CACHE = 'cache';
    public const NIVEAUX = [self::COMPLET => 'Complet', self::LECTURE => 'Lecture', self::CACHE => 'Caché'];

    /** Groupes du menu et modules réglables. */
    public const GROUPES = [
        'Suivi' => ['demandes' => 'Demandes', 'dossiers' => 'Dossiers'],
        'Achats' => ['fournisseurs' => 'Fournisseurs', 'offres' => 'Offres', 'comparateur' => 'Comparateur', 'simulateur' => 'Simulateur de prix'],
        'Ventes' => ['clients' => 'Clients', 'cotations' => 'Cotations', 'commandes' => 'Commandes', 'livraisons' => 'Livraisons', 'factures' => 'Factures'],
        'Administration' => ['pilotage' => 'Pilotage (chiffres)', 'parametres' => 'Paramètres'],
    ];

    /** Rôles réglables (les administrateurs ont toujours tout). */
    public const ROLES_REGLABLES = ['achats', 'commercial', 'finance', 'lecture_seule'];

    private const ROLES_TOUT = ['proprietaire', 'admin_organisation'];

    /** Réglages de départ : [module => [achats, commercial, finance, lecture_seule]]. c = complet, l = lecture, h = caché. */
    private const DEPART = [
        'demandes' => 'lclL',
        'dossiers' => 'cccl',
        'fournisseurs' => 'ccll',
        'offres' => 'chhl',
        'comparateur' => 'chhl',
        'simulateur' => 'chhl',
        'clients' => 'lccl',
        'cotations' => 'cccl',
        'commandes' => 'llcl',
        'livraisons' => 'lllL',
        'factures' => 'hccl',
        'pilotage' => 'hhch',
        'parametres' => 'hhch',
    ];

    private static ?array $reglagesOrg = null;
    private static ?array $exceptions = null;

    public static function disponible(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::connection()->query('SELECT 1 FROM acces_modules LIMIT 1');
                $ok = true;
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    private static function lettre(string $l): string
    {
        return ['c' => self::COMPLET, 'C' => self::COMPLET, 'l' => self::LECTURE, 'L' => self::LECTURE, 'h' => self::CACHE][$l] ?? self::COMPLET;
    }

    /** Niveau de départ d'un rôle pour un module. */
    public static function depart(string $role, string $module): string
    {
        if (in_array($role, self::ROLES_TOUT, true)) {
            return self::COMPLET;
        }
        $i = array_search($role, self::ROLES_REGLABLES, true);
        $d = self::DEPART[$module] ?? null;
        if ($i === false || $d === null) {
            return self::COMPLET;
        }
        return self::lettre($d[$i]);
    }

    private static function charger(int $orgId, int $userId): void
    {
        if (self::$reglagesOrg !== null) {
            return;
        }
        self::$reglagesOrg = [];
        self::$exceptions = [];
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT role, module, niveau FROM acces_modules WHERE organisation_id = ?');
        $st->execute([$orgId]);
        foreach ($st->fetchAll() as $r) {
            self::$reglagesOrg[$r['role']][$r['module']] = $r['niveau'];
        }
        $st = $pdo->prepare('SELECT module, niveau FROM acces_modules_user WHERE utilisateur_id = ?');
        $st->execute([$userId]);
        foreach ($st->fetchAll() as $r) {
            self::$exceptions[$r['module']] = $r['niveau'];
        }
    }

    /** Niveau effectif de l'utilisateur connecté (ou donné) pour un module. */
    public static function niveau(string $module, ?array $user = null): string
    {
        $user = $user ?? Auth::user();
        if (!$user || !self::disponible() || !isset(self::DEPART[$module])) {
            return self::COMPLET;
        }
        if (!empty($user['is_super_admin']) || in_array($user['role'], self::ROLES_TOUT, true)) {
            return self::COMPLET;
        }
        self::charger((int) $user['organisation_id'], (int) $user['id']);
        $n = self::$exceptions[$module] ?? self::$reglagesOrg[$user['role']][$module] ?? self::depart($user['role'], $module);
        return isset(self::NIVEAUX[$n]) ? $n : self::COMPLET;
    }

    public static function visible(string $module, ?array $user = null): bool
    {
        return self::niveau($module, $user) !== self::CACHE;
    }

    /** Module d'une route (premier segment, avec les cas particuliers). Null = non réglable. */
    public static function moduleDeRoute(string $methode, string $path): ?string
    {
        $p = trim($path, '/');
        $seg = explode('/', $p);
        $s0 = $seg[0] ?? '';
        if ($s0 === 'commandes' && $methode !== 'GET' && isset($seg[2]) && in_array($seg[2], ['etapes', 'suivi', 'logistique', 'livrables'], true)) {
            return 'livraisons';
        }
        $map = [
            'demandes' => 'demandes', 'dossiers' => 'dossiers', 'fournisseurs' => 'fournisseurs',
            'offres' => 'offres', 'consultations' => 'offres', 'bons-commande' => 'offres',
            'comparateur' => 'comparateur', 'simulateur' => 'simulateur',
            'clients' => 'clients', 'cotations' => 'cotations', 'commandes' => 'commandes', 'factures' => 'factures',
            'pilotage' => 'pilotage', 'parametres' => 'parametres',
        ];
        return $map[$s0] ?? null;
    }

    /** Appelée par le routeur avant chaque page : refuse l'accès caché, et les modifications en lecture seule. */
    public static function controler(string $methode, string $path): void
    {
        $module = self::moduleDeRoute($methode, $path);
        if ($module === null) {
            return;
        }
        $user = Auth::user();
        if (!$user) {
            return; // la page demandera elle-même la connexion
        }
        $n = self::niveau($module, $user);
        if ($n === self::CACHE) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
        if ($n === self::LECTURE && $methode !== 'GET') {
            View::flash('erreur', 'Ce module est en lecture seule pour vous : modification impossible.');
            $retour = $_SERVER['HTTP_REFERER'] ?? '/index.php';
            $h = parse_url($retour, PHP_URL_HOST);
            header('Location: ' . ($h === null || $h === ($_SERVER['HTTP_HOST'] ?? '') ? $retour : '/index.php'));
            exit;
        }
    }

    // ----- Réglage par l'administrateur -----

    /** Niveau réglé pour un rôle dans l'entreprise (réglage ou départ). */
    public static function niveauRole(int $orgId, string $role, string $module): string
    {
        $st = Database::connection()->prepare('SELECT niveau FROM acces_modules WHERE organisation_id = ? AND role = ? AND module = ?');
        $st->execute([$orgId, $role, $module]);
        $n = $st->fetchColumn();
        return $n && isset(self::NIVEAUX[$n]) ? $n : self::depart($role, $module);
    }

    public static function enregistrerRole(int $orgId, string $role, string $module, string $niveau): void
    {
        if (!in_array($role, self::ROLES_REGLABLES, true) || !isset(self::DEPART[$module]) || !isset(self::NIVEAUX[$niveau])) {
            return;
        }
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM acces_modules WHERE organisation_id = ? AND role = ? AND module = ?')->execute([$orgId, $role, $module]);
        if ($niveau !== self::depart($role, $module)) {
            $pdo->prepare('INSERT INTO acces_modules (organisation_id, role, module, niveau) VALUES (?,?,?,?)')->execute([$orgId, $role, $module, $niveau]);
        }
    }

    public static function reinitialiser(int $orgId): void
    {
        Database::connection()->prepare('DELETE FROM acces_modules WHERE organisation_id = ?')->execute([$orgId]);
    }

    public static function exceptionsOrganisation(int $orgId): array
    {
        $st = Database::connection()->prepare('SELECT x.id, x.utilisateur_id, x.module, x.niveau, u.nom, u.role FROM acces_modules_user x INNER JOIN utilisateurs u ON u.id = x.utilisateur_id WHERE u.organisation_id = ? ORDER BY u.nom, x.module');
        $st->execute([$orgId]);
        return $st->fetchAll();
    }

    public static function ajouterException(int $orgId, int $userId, string $module, string $niveau): bool
    {
        if (!isset(self::DEPART[$module]) || !isset(self::NIVEAUX[$niveau])) {
            return false;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT role FROM utilisateurs WHERE id = ? AND organisation_id = ?');
        $st->execute([$userId, $orgId]);
        $role = $st->fetchColumn();
        if (!$role || in_array($role, self::ROLES_TOUT, true)) {
            return false;
        }
        $pdo->prepare('DELETE FROM acces_modules_user WHERE utilisateur_id = ? AND module = ?')->execute([$userId, $module]);
        $pdo->prepare('INSERT INTO acces_modules_user (utilisateur_id, module, niveau) VALUES (?,?,?)')->execute([$userId, $module, $niveau]);
        return true;
    }

    public static function supprimerException(int $orgId, int $id): void
    {
        Database::connection()->prepare('DELETE FROM acces_modules_user WHERE id = ? AND utilisateur_id IN (SELECT id FROM utilisateurs WHERE organisation_id = ?)')->execute([$id, $orgId]);
    }
}

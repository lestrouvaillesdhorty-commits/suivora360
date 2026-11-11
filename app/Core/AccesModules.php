<?php

namespace App\Core;

use App\Models\RolePerso;

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

    /** Actions sensibles réglables par rôle (case à cocher dans la grille). [code => [libellé, rôles par défaut]] */
    public const PERMISSIONS = [
        'qualifier_fournisseur' => ['Qualifier un fournisseur', ['achats']],
        'bon_commande_fournisseur' => ['Établir et envoyer un bon de commande fournisseur', ['achats']],
        'valider_offres' => ['Retenir une offre au Comparateur', ['achats']],
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

    /** Clé de grille d'un compte : son rôle personnalisé (p3) s'il en a un, sinon son rôle. */
    public static function cleDe(array $user): string
    {
        $id = (int) ($user['role_perso_id'] ?? 0);
        return $id > 0 ? RolePerso::cle($id) : (string) $user['role'];
    }

    /** Clé réglable pour cette entreprise : rôle fourni réglable, ou rôle personnalisé de l'entreprise. */
    public static function cleValide(int $orgId, string $cle): bool
    {
        return in_array($cle, self::ROLES_REGLABLES, true) || RolePerso::appartient($orgId, $cle);
    }

    /** Copie les réglages (niveaux et actions sensibles) d'une clé vers une autre. */
    public static function copierReglages(int $orgId, string $source, string $cible): void
    {
        foreach (self::GROUPES as $mods) {
            foreach (array_keys($mods) as $mod) {
                self::enregistrerRole($orgId, $cible, $mod, self::niveauRole($orgId, $source, $mod));
            }
        }
        foreach (array_keys(self::PERMISSIONS) as $code) {
            self::enregistrerPermission($orgId, $cible, $code, self::permissionRole($orgId, $source, $code));
        }
        \App\Models\ActiviteAcces::copierRole($orgId, $source, $cible);
    }

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

    /**
     * Une action sensible est-elle permise à ce rôle ? Réglage de l'entreprise (module « perm_… », niveau oui/non),
     * sinon règle de départ. Propriétaire et Admin d'organisation : toujours oui. Le réglage ne s'applique qu'au
     * rôle de la personne connectée (les contrôles de rôle ne concernent qu'elle).
     */
    public static function permission(string $code, string $role, array $rolesDepart): bool
    {
        if (in_array($role, self::ROLES_TOUT, true)) {
            return true;
        }
        $defaut = in_array($role, $rolesDepart, true);
        $user = Auth::user();
        if (!$user || $user['role'] !== $role || !self::disponible() || !isset(self::PERMISSIONS[$code]) || !in_array($role, self::ROLES_REGLABLES, true)) {
            return $defaut;
        }
        self::charger((int) $user['organisation_id'], (int) $user['id']);
        $v = self::$reglagesOrg[self::cleDe($user)]['perm_' . $code] ?? null;
        return $v === 'oui' ? true : ($v === 'non' ? false : $defaut);
    }

    public static function permissionRole(int $orgId, string $role, string $code): bool
    {
        $st = Database::connection()->prepare('SELECT niveau FROM acces_modules WHERE organisation_id = ? AND role = ? AND module = ?');
        $st->execute([$orgId, $role, 'perm_' . $code]);
        $v = $st->fetchColumn();
        return $v === 'oui' ? true : ($v === 'non' ? false : in_array(RolePerso::baseDeCle($role), self::PERMISSIONS[$code][1], true));
    }

    public static function enregistrerPermission(int $orgId, string $role, string $code, bool $oui): void
    {
        if (!self::cleValide($orgId, $role) || !isset(self::PERMISSIONS[$code])) {
            return;
        }
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM acces_modules WHERE organisation_id = ? AND role = ? AND module = ?')->execute([$orgId, $role, 'perm_' . $code]);
        if ($oui !== in_array(RolePerso::baseDeCle($role), self::PERMISSIONS[$code][1], true)) {
            $pdo->prepare('INSERT INTO acces_modules (organisation_id, role, module, niveau) VALUES (?,?,?,?)')->execute([$orgId, $role, 'perm_' . $code, $oui ? 'oui' : 'non']);
        }
    }

    /** Préréglage : « petite » = tout le monde fait tout (hors Pilotage/Paramètres réservés à la Finance) ; « structuree » = réglages de départ. */
    public static function appliquerPrereglage(int $orgId, string $nom): void
    {
        self::reinitialiser($orgId);
        if ($nom !== 'petite') {
            return;
        }
        $roles = [];
        foreach (RolePerso::clesReglables($orgId) as $cle) {
            if (in_array(RolePerso::baseDeCle($cle), ['achats', 'commercial', 'finance'], true)) {
                $roles[] = $cle;
            }
        }
        foreach (self::GROUPES as $mods) {
            foreach (array_keys($mods) as $mod) {
                foreach ($roles as $role) {
                    $n = in_array($mod, ['pilotage', 'parametres'], true) ? (RolePerso::baseDeCle($role) === 'finance' ? self::COMPLET : self::CACHE) : self::COMPLET;
                    self::enregistrerRole($orgId, $role, $mod, $n);
                }
            }
        }
        foreach (array_keys(self::PERMISSIONS) as $code) {
            foreach ($roles as $role) {
                self::enregistrerPermission($orgId, $role, $code, true);
            }
        }
    }

    private static function lettre(string $l): string
    {
        return ['c' => self::COMPLET, 'C' => self::COMPLET, 'l' => self::LECTURE, 'L' => self::LECTURE, 'h' => self::CACHE][$l] ?? self::COMPLET;
    }

    /** Niveau de départ d'un rôle pour un module. */
    public static function depart(string $role, string $module): string
    {
        $role = RolePerso::baseDeCle($role);
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
        $cle = self::cleDe($user);
        $n = self::$exceptions[$module] ?? self::$reglagesOrg[$cle][$module] ?? self::depart($cle, $module);
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
        if (!self::cleValide($orgId, $role) || !isset(self::DEPART[$module]) || !isset(self::NIVEAUX[$niveau])) {
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
        Database::connection()->prepare("DELETE FROM acces_modules WHERE organisation_id = ? AND module NOT LIKE 'act_%'")->execute([$orgId]);
    }

    public static function exceptionsOrganisation(int $orgId): array
    {
        foreach (['u.role, u.role_perso_id', 'u.role'] as $cols) {
            try {
                $st = Database::connection()->prepare('SELECT x.id, x.utilisateur_id, x.module, x.niveau, u.nom, ' . $cols . ' FROM acces_modules_user x INNER JOIN utilisateurs u ON u.id = x.utilisateur_id WHERE u.organisation_id = ? AND x.module NOT LIKE \'act_%\' ORDER BY u.nom, x.module');
                $st->execute([$orgId]);
                return $st->fetchAll();
            } catch (\Throwable $e) {
            }
        }
        return [];
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

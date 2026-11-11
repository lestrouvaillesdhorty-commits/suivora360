<?php

namespace App\Models;

use App\Core\AccesModules;
use App\Core\Database;
use App\Core\Permissions;

/**
 * Rôles personnalisés d'une entreprise : un nom choisi par l'entreprise + un rôle de base
 * (« fonctionne comme ») qui décide des règles internes (marge visible, filiales…).
 * Le compte garde role = rôle de base (tout le code existant continue de marcher) et
 * utilisateurs.role_perso_id = le rôle personnalisé, qui sert aux accès aux modules et à l'affichage.
 * Clé dans la grille d'accès : « p » + identifiant (ex. p3). Sûr avant migrate_v55 : fonction inactive.
 */
class RolePerso
{
    public const BASES = ['achats', 'commercial', 'finance', 'lecture_seule'];

    public static function disponible(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::connection()->query('SELECT 1 FROM roles_perso LIMIT 1');
                $ok = true;
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    public static function cle(int $id): string
    {
        return 'p' . $id;
    }

    public static function idDeCle(string $cle): ?int
    {
        return preg_match('/^p(\d+)$/', $cle, $m) ? (int) $m[1] : null;
    }

    public static function find(int $id): ?array
    {
        if (!self::disponible()) {
            return null;
        }
        $st = Database::connection()->prepare('SELECT * FROM roles_perso WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Rôles de l'entreprise, avec le nombre de personnes qui les ont. */
    public static function allForOrganisation(int $orgId): array
    {
        if (!self::disponible()) {
            return [];
        }
        $st = Database::connection()->prepare('SELECT r.*, (SELECT COUNT(*) FROM utilisateurs u WHERE u.role_perso_id = r.id) AS nb FROM roles_perso r WHERE r.organisation_id = ? ORDER BY r.nom');
        $st->execute([$orgId]);
        return $st->fetchAll();
    }

    /** Base d'une clé de grille (« p3 » → rôle de base ; « achats » → achats). */
    public static function baseDeCle(string $cle): string
    {
        $id = self::idDeCle($cle);
        if ($id === null) {
            return $cle;
        }
        static $cache = [];
        if (!isset($cache[$id])) {
            $r = self::find($id);
            $cache[$id] = $r['base_role'] ?? 'lecture_seule';
        }
        return $cache[$id];
    }

    /** La clé désigne-t-elle un rôle personnalisé de cette entreprise ? */
    public static function appartient(int $orgId, string $cle): bool
    {
        $id = self::idDeCle($cle);
        if ($id === null) {
            return false;
        }
        $r = self::find($id);
        return $r && (int) $r['organisation_id'] === $orgId;
    }

    /** Valeur d'un menu de rôle (« achats » ou « p3 ») → [rôle de base, id du rôle perso ou null], ou null si invalide. */
    public static function resoudre(int $orgId, string $valeur): ?array
    {
        $id = self::idDeCle($valeur);
        if ($id !== null) {
            $r = self::find($id);
            if (!$r || (int) $r['organisation_id'] !== $orgId) {
                return null;
            }
            return [$r['base_role'], (int) $r['id']];
        }
        return Permissions::isValidRole($valeur) ? [$valeur, null] : null;
    }

    /** Nom affiché du rôle d'un compte : le rôle personnalisé s'il y en a un, sinon le rôle fourni. */
    public static function libelle(array $user): string
    {
        $id = (int) ($user['role_perso_id'] ?? 0);
        if ($id > 0) {
            $r = self::find($id);
            if ($r) {
                return $r['nom'];
            }
        }
        return Permissions::label($user['role']);
    }

    public static function libelleCle(string $cle): string
    {
        $id = self::idDeCle($cle);
        if ($id !== null) {
            return self::find($id)['nom'] ?? $cle;
        }
        return Permissions::label($cle);
    }

    /** Clés de grille de l'entreprise : rôles fournis réglables puis rôles personnalisés. */
    public static function clesReglables(int $orgId): array
    {
        $cles = AccesModules::ROLES_REGLABLES;
        foreach (self::allForOrganisation($orgId) as $r) {
            $cles[] = self::cle((int) $r['id']);
        }
        return $cles;
    }

    /** @return string|null message d'erreur, null si créé */
    public static function creer(int $orgId, string $nom, string $base, ?string $copieDe): ?string
    {
        $nom = trim($nom);
        if (!self::disponible()) {
            return "Fonction indisponible : lancez migrate_v55.php.";
        }
        if (mb_strlen($nom) < 2 || mb_strlen($nom) > 60) {
            return 'Le nom du rôle doit faire entre 2 et 60 caractères.';
        }
        if (!in_array($base, self::BASES, true)) {
            return 'Choisissez le rôle de base (« Fonctionne comme »).';
        }
        $err = self::erreurNom($orgId, $nom, 0);
        if ($err) {
            return $err;
        }
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO roles_perso (organisation_id, nom, base_role, created_at) VALUES (?, ?, ?, ?)')->execute([$orgId, $nom, $base, date('Y-m-d H:i:s')]);
        $id = (int) $pdo->lastInsertId();
        if ($copieDe !== null && $copieDe !== '' && (in_array($copieDe, AccesModules::ROLES_REGLABLES, true) || self::appartient($orgId, $copieDe))) {
            AccesModules::copierReglages($orgId, $copieDe, self::cle($id));
        }
        return null;
    }

    public static function erreurNom(int $orgId, string $nom, int $sauf): ?string
    {
        foreach (Permissions::ROLES as $code => $label) {
            if (mb_strtolower($label) === mb_strtolower($nom)) {
                return 'Ce nom est déjà celui d\'un rôle fourni : choisissez-en un autre.';
            }
        }
        foreach (self::allForOrganisation($orgId) as $r) {
            if ((int) $r['id'] !== $sauf && mb_strtolower($r['nom']) === mb_strtolower($nom)) {
                return 'Ce rôle existe déjà.';
            }
        }
        return null;
    }

    public static function renommer(int $orgId, int $id, string $nom): ?string
    {
        $nom = trim($nom);
        if (!self::appartient($orgId, self::cle($id))) {
            return 'Rôle introuvable.';
        }
        if (mb_strlen($nom) < 2 || mb_strlen($nom) > 60) {
            return 'Le nom du rôle doit faire entre 2 et 60 caractères.';
        }
        $err = self::erreurNom($orgId, $nom, $id);
        if ($err) {
            return $err;
        }
        Database::connection()->prepare('UPDATE roles_perso SET nom = ? WHERE id = ?')->execute([$nom, $id]);
        return null;
    }

    public static function supprimer(int $orgId, int $id): ?string
    {
        if (!self::appartient($orgId, self::cle($id))) {
            return 'Rôle introuvable.';
        }
        $st = Database::connection()->prepare('SELECT COUNT(*) FROM utilisateurs WHERE role_perso_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            return 'Ce rôle est encore attribué : passez d\'abord ces personnes à un autre rôle.';
        }
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM roles_perso WHERE id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM acces_modules WHERE organisation_id = ? AND role = ?')->execute([$orgId, self::cle($id)]);
        return null;
    }
}

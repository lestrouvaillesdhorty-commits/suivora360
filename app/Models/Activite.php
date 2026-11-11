<?php

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

/**
 * Activités d'une entreprise cliente (réglées par l'administrateur Suivora uniquement).
 * Les 3 activités fournies (Demande::ACTIVITES) sont actives pour tout le monde tant qu'aucun réglage n'existe.
 * Une activité ajoutée « fonctionne comme » l'une des 3 (déroulé du dossier, vocabulaire).
 * Sûr avant migrate_v56 : la liste reste celle des 3 activités fournies.
 */
class Activite
{
    public static function disponible(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::connection()->query('SELECT 1 FROM activites_org LIMIT 1');
                $ok = true;
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    private static function orgCourante(): ?int
    {
        $u = Auth::user();
        return $u ? (int) $u['organisation_id'] : null;
    }

    /** Lignes de réglage d'une entreprise (nom => ligne). */
    private static function lignes(int $orgId): array
    {
        static $cache = [];
        if (!isset($cache[$orgId])) {
            $cache[$orgId] = [];
            if (self::disponible()) {
                $st = Database::connection()->prepare('SELECT * FROM activites_org WHERE organisation_id = ? ORDER BY id');
                $st->execute([$orgId]);
                foreach ($st->fetchAll() as $r) {
                    $cache[$orgId][$r['nom']] = $r;
                }
            }
        }
        return $cache[$orgId];
    }

    /** Toutes les activités de l'entreprise (actives ou non) : [[nom, comme, actif, fournie]]. */
    public static function toutes(?int $orgId = null): array
    {
        $orgId = $orgId ?? self::orgCourante();
        $lignes = $orgId ? self::lignes($orgId) : [];
        $out = [];
        foreach (Demande::ACTIVITES as $nom) {
            $l = $lignes[$nom] ?? null;
            $out[] = ['nom' => $nom, 'comme' => $nom, 'actif' => $l ? (int) $l['actif'] === 1 : true, 'fournie' => true, 'id' => $l['id'] ?? null];
        }
        foreach ($lignes as $nom => $l) {
            if (!(int) $l['fournie']) {
                $out[] = ['nom' => $nom, 'comme' => $l['comme'], 'actif' => (int) $l['actif'] === 1, 'fournie' => false, 'id' => (int) $l['id']];
            }
        }
        return $out;
    }

    /** Noms des activités actives (listes, filtres, formulaires). */
    public static function liste(?int $orgId = null): array
    {
        $noms = [];
        foreach (self::toutes($orgId) as $a) {
            if ($a['actif']) {
                $noms[] = $a['nom'];
            }
        }
        $noms = $noms ?: Demande::ACTIVITES;
        $u = Auth::user();
        return ($u && ($orgId === null || $orgId === (int) $u['organisation_id'])) ? ActiviteAcces::retirerCachees($noms) : $noms;
    }

    /** Une activité connue de l'entreprise (même désactivée) : sert à ne pas bloquer la modification d'une demande existante. */
    public static function connue(string $nom, ?int $orgId = null): bool
    {
        foreach (self::toutes($orgId) as $a) {
            if ($a['nom'] === $nom) {
                return true;
            }
        }
        return false;
    }

    /** L'une des 3 activités fournies dont celle-ci reprend le fonctionnement. */
    public static function comme(?string $nom, ?int $orgId = null): ?string
    {
        if ($nom === null || $nom === '') {
            return null;
        }
        if (in_array($nom, Demande::ACTIVITES, true)) {
            return $nom;
        }
        foreach (self::toutes($orgId) as $a) {
            if ($a['nom'] === $nom) {
                return $a['comme'];
            }
        }
        if (self::disponible() && $orgId === null && self::orgCourante() === null) {
            $st = Database::connection()->prepare('SELECT comme FROM activites_org WHERE nom = ? AND fournie = 0 LIMIT 1');
            $st->execute([$nom]);
            $c = $st->fetchColumn();
            return $c ?: null;
        }
        return null;
    }

    public static function nbDemandes(int $orgId, string $nom): int
    {
        $st = Database::connection()->prepare('SELECT COUNT(*) FROM demandes d INNER JOIN filiales f ON f.id = d.filiale_id WHERE f.organisation_id = ? AND d.activite = ?');
        $st->execute([$orgId, $nom]);
        return (int) $st->fetchColumn();
    }

    // ----- Réglage (administrateur Suivora) -----

    public static function ajouter(int $orgId, string $nom, string $comme): ?string
    {
        $nom = trim($nom);
        if (!self::disponible()) {
            return 'Fonction indisponible : lancez migrate_v56.php.';
        }
        if (mb_strlen($nom) < 2 || mb_strlen($nom) > 60) {
            return "Le nom de l'activité doit faire entre 2 et 60 caractères.";
        }
        if (!in_array($comme, Demande::ACTIVITES, true)) {
            return 'Choisissez « Fonctionne comme ».';
        }
        foreach (self::toutes($orgId) as $a) {
            if (mb_strtolower($a['nom']) === mb_strtolower($nom)) {
                return 'Cette activité existe déjà pour cette entreprise.';
            }
        }
        Database::connection()->prepare('INSERT INTO activites_org (organisation_id, nom, comme, actif, fournie, created_at) VALUES (?, ?, ?, 1, 0, ?)')
            ->execute([$orgId, $nom, $comme, date('Y-m-d H:i:s')]);
        return null;
    }

    public static function basculer(int $orgId, string $nom): ?string
    {
        $courante = null;
        $nbActives = 0;
        foreach (self::toutes($orgId) as $a) {
            if ($a['actif']) {
                $nbActives++;
            }
            if ($a['nom'] === $nom) {
                $courante = $a;
            }
        }
        if (!$courante) {
            return 'Activité introuvable.';
        }
        if ($courante['actif'] && $nbActives <= 1) {
            return 'Au moins une activité doit rester active.';
        }
        $pdo = Database::connection();
        $nouveau = $courante['actif'] ? 0 : 1;
        $st = $pdo->prepare('SELECT id FROM activites_org WHERE organisation_id = ? AND nom = ?');
        $st->execute([$orgId, $nom]);
        $id = $st->fetchColumn();
        if ($id) {
            $pdo->prepare('UPDATE activites_org SET actif = ? WHERE id = ?')->execute([$nouveau, $id]);
        } else {
            $pdo->prepare('INSERT INTO activites_org (organisation_id, nom, comme, actif, fournie, created_at) VALUES (?, ?, ?, ?, 1, ?)')
                ->execute([$orgId, $nom, $nom, $nouveau, date('Y-m-d H:i:s')]);
        }
        return null;
    }

    public static function renommer(int $orgId, string $ancien, string $nouveau): ?string
    {
        $nouveau = trim($nouveau);
        $cible = null;
        foreach (self::toutes($orgId) as $a) {
            if ($a['nom'] === $ancien && !$a['fournie']) {
                $cible = $a;
            }
        }
        if (!$cible) {
            return 'Seules les activités ajoutées se renomment.';
        }
        if (mb_strlen($nouveau) < 2 || mb_strlen($nouveau) > 60) {
            return "Le nom de l'activité doit faire entre 2 et 60 caractères.";
        }
        foreach (self::toutes($orgId) as $a) {
            if ($a['nom'] !== $ancien && mb_strtolower($a['nom']) === mb_strtolower($nouveau)) {
                return 'Cette activité existe déjà pour cette entreprise.';
            }
        }
        $pdo = Database::connection();
        $pdo->prepare('UPDATE activites_org SET nom = ? WHERE organisation_id = ? AND nom = ?')->execute([$nouveau, $orgId, $ancien]);
        // Les demandes déjà saisies suivent le nouveau nom.
        $pdo->prepare('UPDATE demandes SET activite = ? WHERE activite = ? AND filiale_id IN (SELECT id FROM filiales WHERE organisation_id = ?)')->execute([$nouveau, $ancien, $orgId]);
        return null;
    }

    public static function supprimer(int $orgId, string $nom): ?string
    {
        foreach (self::toutes($orgId) as $a) {
            if ($a['nom'] === $nom) {
                if ($a['fournie']) {
                    return 'Une activité fournie ne se supprime pas : désactivez-la.';
                }
                if (self::nbDemandes($orgId, $nom) > 0) {
                    return 'Cette activité a des demandes : désactivez-la plutôt que la supprimer.';
                }
                Database::connection()->prepare('DELETE FROM activites_org WHERE organisation_id = ? AND nom = ?')->execute([$orgId, $nom]);
                return null;
            }
        }
        return 'Activité introuvable.';
    }
}

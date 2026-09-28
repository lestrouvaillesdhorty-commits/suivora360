<?php

namespace App\Core;

/**
 * Rôles fins (Phase 4) — remplace le modèle binaire dirigeant/employé.
 * ---------------------------------------------------------------------
 * 6 rôles, comme dans la V1 de référence : Propriétaire, Admin
 * d'organisation, Achats, Commercial, Finance, Lecture seule.
 *
 * Ce fichier centralise les règles pour que tout contrôleur (et toute vue)
 * pose la même question de la même façon : Permissions::can($user, ...).
 * Rien ici ne touche à l'isolation par filiale (Filiale::userCanAccess) —
 * c'est un axe séparé et orthogonal : un rôle donne des droits sur des
 * *actions*, la filiale donne accès à un *périmètre* de données.
 */
class Permissions
{
    public const ROLES = [
        'proprietaire' => 'Propriétaire',
        'admin_organisation' => "Admin d'organisation",
        'achats' => 'Achats',
        'commercial' => 'Commercial',
        'finance' => 'Finance',
        'lecture_seule' => 'Lecture seule',
    ];

    /** Rôles qui voient toutes les filiales de leur organisation (comme l'ancien "dirigeant"). */
    private const ROLES_TOUTES_FILIALES = ['proprietaire', 'admin_organisation'];

    /** Filiales/Utilisateurs : administration de l'organisation elle-même. */
    private const ROLES_ADMIN = ['proprietaire', 'admin_organisation'];

    /** Paramètres de calcul (taux, marge/TVA par défaut) : administratif + finance. */
    private const ROLES_PARAMETRES = ['proprietaire', 'admin_organisation', 'finance'];

    /** Marge (marge_pourcentage / marge_montant sur une cotation) : donnée la plus sensible. */
    private const ROLES_MARGES = ['proprietaire', 'admin_organisation', 'finance'];

    /** Décision au Comparateur ("Retenir cette offre" / "Revenir sur une décision"). */
    private const ROLES_VALIDATION_OFFRES = ['proprietaire', 'achats'];

    /** Création/modification d'une Cotation (prix client) — pas l'équipe achats. */
    private const ROLES_GESTION_COTATIONS = ['proprietaire', 'admin_organisation', 'commercial', 'finance'];

    /**
     * Module Pilotage (analyse par période/activité/responsable) : expose la
     * valeur active et la marge prévisionnelle, données au même niveau de
     * sensibilité que la marge sur une Cotation — mêmes rôles que
     * ROLES_MARGES/ROLES_PARAMETRES.
     */
    private const ROLES_PILOTAGE = ['proprietaire', 'admin_organisation', 'finance'];

    public static function label(string $role): string
    {
        return self::ROLES[$role] ?? $role;
    }

    public static function isValidRole(string $role): bool
    {
        return array_key_exists($role, self::ROLES);
    }

    public static function seesAllFiliales(string $role): bool
    {
        return in_array($role, self::ROLES_TOUTES_FILIALES, true);
    }

    public static function isAdmin(string $role): bool
    {
        return in_array($role, self::ROLES_ADMIN, true);
    }

    public static function canManageParametres(string $role): bool
    {
        return in_array($role, self::ROLES_PARAMETRES, true);
    }

    public static function canSeeMarges(string $role): bool
    {
        return in_array($role, self::ROLES_MARGES, true);
    }

    public static function canValiderOffres(string $role): bool
    {
        return in_array($role, self::ROLES_VALIDATION_OFFRES, true);
    }

    public static function canGererCotations(string $role): bool
    {
        return in_array($role, self::ROLES_GESTION_COTATIONS, true);
    }

    public static function canVoirPilotage(string $role): bool
    {
        return in_array($role, self::ROLES_PILOTAGE, true);
    }

    /**
     * Lecture seule : jamais de création/modification/suppression/validation,
     * nulle part dans l'application — seule règle transversale à tous les
     * domaines qui n'ont pas de restriction plus spécifique ci-dessus.
     */
    public static function canWrite(string $role): bool
    {
        return $role !== 'lecture_seule';
    }

    /**
     * Seul un Propriétaire peut créer ou promouvoir un autre Propriétaire —
     * empêche un Admin d'organisation de s'auto-élever au rôle le plus haut.
     */
    public static function canAssignRole(string $assignerRole, string $targetRole): bool
    {
        if ($targetRole === 'proprietaire') {
            return $assignerRole === 'proprietaire';
        }
        return self::isAdmin($assignerRole);
    }
}

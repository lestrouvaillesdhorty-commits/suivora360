<?php

namespace App\Core;

/**
 * Couche d'isolation multi-entreprises : contrôle d'appartenance des
 * identifiants reçus en POST.
 *
 * Contexte : chaque ligne métier porte `filiale_id`, et une filiale appartient
 * à UNE organisation (entreprise cliente). Les contrôles d'accès par page
 * (« cet utilisateur peut-il ouvrir ce dossier ? ») existent partout ; ce qui
 * manquait, c'est de vérifier qu'un identifiant d'un AUTRE objet envoyé dans
 * un formulaire (client_id, fournisseur_id, offre_id, cotation_id...)
 * appartient bien à la même filiale / au même dossier que l'objet en cours.
 * Sans cela, un utilisateur d'une entreprise pouvait rattacher à ses données
 * un identifiant (séquentiel, donc énumérable) d'une autre entreprise, et lire
 * ensuite le nom/coordonnées correspondants.
 *
 * Règle commune de toutes les méthodes : renvoyer l'identifiant (int) s'il est
 * valide dans le périmètre demandé, sinon null. Jamais d'exception ni de
 * message : l'appelant décide (ignorer, refuser, afficher une erreur).
 */
class Tenant
{
    /** Client appartenant à la filiale donnée. */
    public static function clientDeFiliale($id, int $filialeId): ?int
    {
        return self::existe('clients', 'filiale_id', $id, $filialeId);
    }

    /**
     * Client appartenant à l'une des filiales visibles de l'utilisateur
     * (utilisé quand un formulaire propose les clients de toutes les filiales
     * auxquelles l'utilisateur a accès, ex. création d'une demande).
     */
    public static function clientVisible($id, array $filialeIds): ?int
    {
        $id = (int) $id;
        $filialeIds = array_values(array_map('intval', $filialeIds));
        if ($id <= 0 || empty($filialeIds)) {
            return null;
        }
        $ph = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare("SELECT id FROM clients WHERE id = ? AND filiale_id IN ($ph)");
        $stmt->execute(array_merge([$id], $filialeIds));
        return $stmt->fetchColumn() === false ? null : $id;
    }

    /** Client appartenant à l'organisation (entreprise cliente) donnée, quelle que soit sa filiale. */
    public static function clientDOrganisation($id, int $organisationId): ?int
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT c.id FROM clients c INNER JOIN filiales f ON f.id = c.filiale_id WHERE c.id = ? AND f.organisation_id = ?'
        );
        $stmt->execute([$id, $organisationId]);
        return $stmt->fetchColumn() === false ? null : $id;
    }

    /** Fournisseur appartenant à la filiale donnée. */
    public static function fournisseurDeFiliale($id, int $filialeId): ?int
    {
        return self::existe('fournisseurs', 'filiale_id', $id, $filialeId);
    }

    /** Offre appartenant au dossier donné. */
    public static function offreDuDossier($id, int $dossierId): ?int
    {
        return self::existe('offres', 'dossier_id', $id, $dossierId);
    }

    /** Cotation appartenant au dossier donné. */
    public static function cotationDuDossier($id, int $dossierId): ?int
    {
        return self::existe('cotations', 'dossier_id', $id, $dossierId);
    }

    /** Commande appartenant au dossier donné. */
    public static function commandeDuDossier($id, int $dossierId): ?int
    {
        return self::existe('commandes', 'dossier_id', $id, $dossierId);
    }

    /**
     * Utilisateur (responsable assigné) appartenant à l'organisation donnée.
     * Les comptes désactivés restent acceptés (un historique peut les citer) ;
     * seule l'appartenance à l'organisation compte ici.
     */
    public static function utilisateurDOrganisation($id, int $organisationId): ?int
    {
        return self::existe('utilisateurs', 'organisation_id', $id, $organisationId);
    }

    /** Organisation d'une filiale (null si la filiale n'existe pas). */
    public static function organisationDeFiliale(int $filialeId): ?int
    {
        $stmt = Database::connection()->prepare('SELECT organisation_id FROM filiales WHERE id = ?');
        $stmt->execute([$filialeId]);
        $v = $stmt->fetchColumn();
        return $v === false || $v === null ? null : (int) $v;
    }

    /**
     * Responsable valide pour un objet d'une filiale : l'utilisateur doit
     * appartenir à l'organisation de cette filiale.
     */
    public static function responsableDeFiliale($id, int $filialeId): ?int
    {
        $org = self::organisationDeFiliale($filialeId);
        return $org === null ? null : self::utilisateurDOrganisation($id, $org);
    }

    private static function existe(string $table, string $colonne, $id, int $valeur): ?int
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        // $table et $colonne sont des constantes internes (jamais issues d'une saisie).
        $stmt = Database::connection()->prepare("SELECT id FROM $table WHERE id = ? AND $colonne = ?");
        $stmt->execute([$id, $valeur]);
        return $stmt->fetchColumn() === false ? null : $id;
    }
}

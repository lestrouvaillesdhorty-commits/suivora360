<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Permissions;

class Filiale
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM filiales WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function allForOrganisation(int $organisationId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM filiales WHERE organisation_id = ? ORDER BY nom');
        $stmt->execute([$organisationId]);
        return $stmt->fetchAll();
    }

    /**
     * Filiales visibles par un utilisateur donné :
     * - Propriétaire / Admin d'organisation : toutes les filiales de l'organisation
     * - autres rôles : uniquement celles qui leur sont assignées
     *
     * [corrigé 03/10] Bug d'isolation multi-organisations trouvé en revoyant
     * le module Utilisateurs & rôles : cette jointure ne vérifiait jamais que
     * la filiale assignée dans `utilisateur_filiales` appartient bien à
     * l'organisation de l'utilisateur. Une ligne erronée pointant vers la
     * filiale d'une AUTRE organisation cliente aurait donné accès à ses
     * dossiers/clients/demandes. Ajout de `f.organisation_id = ?` pour
     * fermer cette faille — en défense en profondeur, au cas où une ligne
     * incohérente existerait déjà (voir aussi UtilisateurController::store()/
     * updateAcces(), corrigés pour ne plus jamais écrire une telle ligne).
     */
    public static function visibleFor(array $user): array
    {
        if (Permissions::seesAllFiliales($user['role'])) {
            return self::allForOrganisation((int) $user['organisation_id']);
        }

        $stmt = Database::connection()->prepare(
            'SELECT f.* FROM filiales f
             INNER JOIN utilisateur_filiales uf ON uf.filiale_id = f.id
             WHERE uf.utilisateur_id = ? AND f.organisation_id = ?
             ORDER BY f.nom'
        );
        $stmt->execute([$user['id'], $user['organisation_id']]);
        return $stmt->fetchAll();
    }

    public static function visibleIdsFor(array $user): array
    {
        return array_map(fn($f) => (int) $f['id'], self::visibleFor($user));
    }

    public static function userCanAccess(array $user, int $filialeId): bool
    {
        if (Permissions::seesAllFiliales($user['role'])) {
            $filiale = self::find($filialeId);
            return $filiale && (int) $filiale['organisation_id'] === (int) $user['organisation_id'];
        }
        return in_array($filialeId, self::visibleIdsFor($user), true);
    }

    public static function create(int $organisationId, string $nom): int
    {
        $stmt = Database::connection()->prepare('INSERT INTO filiales (organisation_id, nom, created_at) VALUES (?, ?, ?)');
        $stmt->execute([$organisationId, $nom, date('Y-m-d H:i:s')]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function countForOrganisation(int $organisationId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM filiales WHERE organisation_id = ?');
        $stmt->execute([$organisationId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * [ajouté 03/10, demande explicite de Marie Laure] Renommer une filiale
     * existante — jusqu'ici une filiale créée ne pouvait ni être renommée
     * ni supprimée.
     */
    public static function rename(int $filialeId, string $nom): void
    {
        $stmt = Database::connection()->prepare('UPDATE filiales SET nom = ? WHERE id = ?');
        $stmt->execute([$nom, $filialeId]);
    }

    /**
     * Ce qui empêche de supprimer une filiale sans risque : toute donnée
     * métier réelle déjà créée dessus (une fois qu'il y a une Demande, un
     * Client ou un Fournisseur, tout le reste — Dossier, Offre, Cotation,
     * Commande, Facture — en découle, donc vérifier ces trois tables suffit
     * à détecter un usage réel). Tableau vide = suppression possible.
     */
    public static function usagesBloquants(int $filialeId): array
    {
        $pdo = Database::connection();
        $aVerifier = ['Demandes' => 'demandes', 'Clients' => 'clients', 'Fournisseurs' => 'fournisseurs'];
        $usages = [];
        foreach ($aVerifier as $label => $table) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE filiale_id = ?");
            $stmt->execute([$filialeId]);
            $n = (int) $stmt->fetchColumn();
            if ($n > 0) {
                $usages[$label] = $n;
            }
        }
        return $usages;
    }

    /**
     * Supprime une filiale vide (voir usagesBloquants — à vérifier avant
     * d'appeler cette méthode) ainsi que ce qui lui est rattaché sans
     * constituer une donnée métier en soi : les accès utilisateurs
     * (`utilisateur_filiales`) et ses Paramètres de calcul.
     */
    public static function delete(int $filialeId): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM utilisateur_filiales WHERE filiale_id = ?')->execute([$filialeId]);
        $pdo->prepare('DELETE FROM parametres WHERE filiale_id = ?')->execute([$filialeId]);
        $pdo->prepare('DELETE FROM filiales WHERE id = ?')->execute([$filialeId]);
    }

    /**
     * Filtre une liste de filiale_id soumise (ex. formulaire d'assignation
     * d'un utilisateur) pour n'en garder que celles qui appartiennent
     * réellement à l'organisation donnée — défense en profondeur pour ne
     * jamais écrire, dans `utilisateur_filiales`, une ligne pointant vers la
     * filiale d'une autre organisation (voir le bug corrigé sur visibleFor()
     * ci-dessus).
     */
    public static function filterIdsForOrganisation(array $filialeIds, int $organisationId): array
    {
        $valides = array_map(fn($f) => (int) $f['id'], self::allForOrganisation($organisationId));
        return array_values(array_intersect(array_map('intval', $filialeIds), $valides));
    }

    /**
     * Résout la ou les filiale_id à utiliser pour une requête filtrable
     * (switcher Tableau de bord) : si l'utilisateur a explicitement
     * sélectionné une filiale et qu'il y a bien accès, on ne garde que
     * celle-ci ; sinon on retombe sur toutes ses filiales visibles (comportement
     * actuel, inchangé). Ne fait jamais confiance à `$filialeIdDemande` sans
     * vérifier l'accès — sinon un utilisateur pourrait se faire passer la
     * filiale d'un autre simplement en changeant l'URL.
     */
    public static function resoudreFiltreIds(array $user, ?int $filialeIdDemande): array
    {
        $visibles = self::visibleIdsFor($user);
        if ($filialeIdDemande && in_array($filialeIdDemande, $visibles, true)) {
            return [$filialeIdDemande];
        }
        return $visibles;
    }
}

<?php

namespace App\Models;

use App\Core\Database;

class Organisation
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM organisations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM organisations ORDER BY nom')->fetchAll();
    }

    /**
     * Une entreprise cliente est « active » tant qu'elle n'a pas été suspendue
     * par l'administrateur Suivora (colonne organisations.actif, ajoutée par
     * migrate_v21). Tant que la colonne n'existe pas encore (code déployé avant
     * la migration), toutes les organisations sont considérées actives : le site
     * ne doit jamais tomber à cause d'une migration pas encore lancée.
     */
    public static function estActive(int $id): bool
    {
        static $cache = [];
        if (!array_key_exists($id, $cache)) {
            try {
                $stmt = Database::connection()->prepare('SELECT actif FROM organisations WHERE id = ?');
                $stmt->execute([$id]);
                $v = $stmt->fetchColumn();
                $cache[$id] = ($v === false || $v === null) ? true : ((int) $v === 1);
            } catch (\Throwable $e) {
                $cache[$id] = true;
            }
        }
        return $cache[$id];
    }

    /**
     * Liste des entreprises clientes avec des compteurs d'ensemble (nombre de
     * filiales, d'utilisateurs actifs, de demandes et de dossiers) — jamais le
     * contenu métier lui-même : l'administrateur Suivora voit l'espace, pas les
     * données des entreprises.
     */
    public static function toutesAvecStatistiques(): array
    {
        $sql = "SELECT o.id, o.nom, o.created_at, o.actif,
                  o.abonnement_offre, o.abonnement_prix, o.abonnement_devise, o.abonnement_echeance, o.abonnement_notes,
                  (SELECT COUNT(*) FROM filiales f WHERE f.organisation_id = o.id) AS nb_filiales,
                  (SELECT COUNT(*) FROM utilisateurs u WHERE u.organisation_id = o.id AND u.actif = 1) AS nb_utilisateurs,
                  (SELECT COUNT(*) FROM demandes d INNER JOIN filiales f ON f.id = d.filiale_id WHERE f.organisation_id = o.id) AS nb_demandes,
                  (SELECT COUNT(*) FROM dossiers d INNER JOIN filiales f ON f.id = d.filiale_id WHERE f.organisation_id = o.id) AS nb_dossiers,
                  (SELECT MAX(a.created_at) FROM audit_logs a INNER JOIN filiales f ON f.id = a.filiale_id WHERE f.organisation_id = o.id) AS derniere_activite
                FROM organisations o ORDER BY o.nom";
        return Database::connection()->query($sql)->fetchAll();
    }

    /** Crée l'entreprise, sa première filiale et son premier compte Propriétaire, en une seule transaction. */
    public static function creerAvecProprietaire(string $nomOrganisation, string $nomFiliale, string $nomProprietaire, string $emailProprietaire, string $motDePasse): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO organisations (nom, created_at) VALUES (?, ?)')->execute([$nomOrganisation, $now]);
            $orgId = (int) $pdo->lastInsertId();
            $filialeId = Filiale::create($orgId, $nomFiliale);
            $userId = Utilisateur::create([
                'organisation_id' => $orgId,
                'nom' => $nomProprietaire,
                'email' => $emailProprietaire,
                'mot_de_passe' => $motDePasse,
                'role' => 'proprietaire',
                'doit_changer_mdp' => 1,
            ]);
            $pdo->commit();
            return ['organisation_id' => $orgId, 'filiale_id' => $filialeId, 'utilisateur_id' => $userId];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public const OFFRES = ['pilote' => 'Pilote (gratuit)', 'essentiel' => 'Essentiel', 'pro' => 'Pro', 'groupe' => 'Groupe'];
    public const DEVISES_ABONNEMENT = ['XAF' => 'FCFA', 'EUR' => '€'];

    /** Informations d'abonnement d'une entreprise (suivi manuel, aucun paiement en ligne). */
    public static function definirAbonnement(int $id, ?string $offre, ?float $prix, ?string $devise, ?string $echeance, ?string $notes): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE organisations SET abonnement_offre = ?, abonnement_prix = ?, abonnement_devise = ?, abonnement_echeance = ?, abonnement_notes = ? WHERE id = ?'
        );
        $stmt->execute([$offre, $prix, $devise, $echeance, $notes, $id]);
    }

    public static function definirActive(int $id, bool $actif): void
    {
        $stmt = Database::connection()->prepare('UPDATE organisations SET actif = ? WHERE id = ?');
        $stmt->execute([$actif ? 1 : 0, $id]);
    }
}

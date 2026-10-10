<?php

namespace App\Models;

use App\Core\Database;

/**
 * Espace client externe (07/10) : liens privés, à durée limitée et révocables,
 * donnant au client un accès en LECTURE à ses propres dossiers, cotations
 * envoyées et factures. Toutes les lectures sont filtrées par client_id du
 * lien : un lien ne peut jamais montrer les données d'un autre client.
 *
 * Jamais exposé : marges, prix d'achat, offres fournisseurs, notes internes,
 * fournisseurs, collaborateurs.
 */
class PortailClient
{
    public const DUREES_JOURS = [7, 30, 90];
    public const DUREE_DEFAUT = 30;
    public const DEMANDES_MAX_PAR_HEURE = 5;

    public static function schemaPret(): bool
    {
        static $pret = null;
        if ($pret === null) {
            try {
                $pdo = Database::connection();
                if (Database::driver() === 'sqlite') {
                    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
                } else {
                    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
                }
                $stmt->execute(['client_portail_liens']);
                $pret = (bool) $stmt->fetch();
            } catch (\Throwable $e) {
                $pret = false;
            }
        }
        return $pret;
    }

    // ------------------------------------------------------------------
    // Liens
    // ------------------------------------------------------------------

    public static function creerLien(int $clientId, int $jours, ?int $userId): string
    {
        if (!in_array($jours, self::DUREES_JOURS, true)) {
            $jours = self::DUREE_DEFAUT;
        }
        $token = bin2hex(random_bytes(24)); // 192 bits
        Database::connection()->prepare(
            'INSERT INTO client_portail_liens (client_id, token, expire_le, cree_par, created_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $clientId, $token, date('Y-m-d H:i:s', time() + $jours * 86400), $userId, date('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    public static function liensDuClient(int $clientId): array
    {
        if (!self::schemaPret()) {
            return [];
        }
        $stmt = Database::connection()->prepare('SELECT * FROM client_portail_liens WHERE client_id = ? ORDER BY created_at DESC, id DESC LIMIT 10');
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public static function lienDuClient(int $clientId, int $lienId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM client_portail_liens WHERE id = ? AND client_id = ?');
        $stmt->execute([$lienId, $clientId]);
        return $stmt->fetch() ?: null;
    }

    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token) || !self::schemaPret()) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM client_portail_liens WHERE token = ?');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public static function estValide(array $lien): bool
    {
        return empty($lien['revoque_le']) && strtotime((string) $lien['expire_le']) > time();
    }

    public static function revoquer(int $lienId): void
    {
        Database::connection()->prepare('UPDATE client_portail_liens SET revoque_le = ? WHERE id = ? AND revoque_le IS NULL')
            ->execute([date('Y-m-d H:i:s'), $lienId]);
    }

    public static function enregistrerAcces(int $lienId): void
    {
        Database::connection()->prepare('UPDATE client_portail_liens SET nb_acces = nb_acces + 1, dernier_acces = ? WHERE id = ?')
            ->execute([date('Y-m-d H:i:s'), $lienId]);
    }

    // ------------------------------------------------------------------
    // Données visibles par le client (toutes filtrées par client_id)
    // ------------------------------------------------------------------

    /** Dossiers du client (via la demande d'origine) : référence, objet, étape, statut, échéance. Rien d'interne. */
    public static function dossiers(int $clientId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT d.id, d.reference, d.objet, d.etape, d.statut, d.echeance, d.created_at
             FROM dossiers d INNER JOIN demandes de ON de.id = d.demande_id
             WHERE de.client_id = ? ORDER BY d.created_at DESC'
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    /**
     * Cotations visibles : envoyées, acceptées ou refusées (jamais brouillon ni
     * version remplacée). Colonnes limitées : aucune marge, aucun prix d'achat.
     */
    public static function cotations(int $clientId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT co.id, co.reference, co.version, co.montant_total, co.devise, co.statut, co.validite_devis,
                    co.mode_paiement_negocie, co.incoterm_client, co.created_at, d.id AS dossier_id, d.reference AS dossier_reference, d.objet AS dossier_objet
             FROM cotations co INNER JOIN dossiers d ON d.id = co.dossier_id
             WHERE co.client_id = ? AND co.statut IN ('envoyee', 'acceptee', 'refusee')
             ORDER BY (CASE WHEN co.statut = 'envoyee' THEN 0 ELSE 1 END), co.created_at DESC"
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public static function cotation(int $clientId, int $cotationId): ?array
    {
        foreach (self::cotations($clientId) as $c) {
            if ((int) $c['id'] === $cotationId) {
                return $c;
            }
        }
        return null;
    }

    public static function lignesCotation(int $cotationId): array
    {
        $stmt = Database::connection()->prepare('SELECT designation, quantite, unite, prix_unitaire, montant FROM cotation_items WHERE cotation_id = ? ORDER BY id');
        $stmt->execute([$cotationId]);
        return $stmt->fetchAll();
    }

    /** Une cotation peut être acceptée/refusée par le client seulement si elle est « envoyée » et encore valable. */
    public static function decisionPossible(array $cotation): bool
    {
        if ($cotation['statut'] !== 'envoyee') {
            return false;
        }
        return empty($cotation['validite_devis']) || $cotation['validite_devis'] >= date('Y-m-d');
    }

    public static function factures(int $clientId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT fa.id, fa.reference, fa.montant, fa.devise, fa.statut, fa.date_emission, fa.date_echeance, d.reference AS dossier_reference
             FROM factures fa
             INNER JOIN dossiers d ON d.id = fa.dossier_id
             INNER JOIN demandes de ON de.id = d.demande_id
             LEFT JOIN cotations co ON co.id = fa.cotation_id
             WHERE (co.client_id = ? OR (co.id IS NULL AND de.client_id = ?)) AND fa.statut IN ('emise', 'payee')
             ORDER BY fa.date_emission DESC, fa.id DESC"
        );
        $stmt->execute([$clientId, $clientId]);
        return $stmt->fetchAll();
    }

    public static function facture(int $clientId, int $factureId): ?array
    {
        foreach (self::factures($clientId) as $f) {
            if ((int) $f['id'] === $factureId) {
                return $f;
            }
        }
        return null;
    }

    /** Nombre de demandes déposées depuis l'espace client par ce client dans la dernière heure (anti-abus). */
    public static function demandesRecentes(int $clientId): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM demandes WHERE client_id = ? AND canal = 'Espace client' AND created_at >= ?");
        $stmt->execute([$clientId, date('Y-m-d H:i:s', time() - 3600)]);
        return (int) $stmt->fetchColumn();
    }
}

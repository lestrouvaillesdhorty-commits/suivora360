<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Schema;

/**
 * Lien fournisseur : lien privé (sans compte ni mot de passe), à durée limitée et révocable,
 * qui montre à UN fournisseur ses demandes de prix en cours et lui permet de répondre.
 * Toutes les lectures sont filtrées par fournisseur_id du lien. Jamais exposé : client final,
 * marges, offres des autres fournisseurs, notes internes.
 */
class FournisseurLien
{
    public const DUREES_JOURS = [7, 30, 90, 180, 365];
    public const DUREE_DEFAUT = 30;
    public const MONNAIES = ['EUR', 'USD', 'XAF', 'XOF', 'GBP', 'CNY'];

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
                $stmt->execute(['fournisseur_liens']);
                $pret = (bool) $stmt->fetch() && Schema::colonne('consultations_fournisseur', 'vue_fournisseur_le');
            } catch (\Throwable $e) {
                $pret = false;
            }
        }
        return $pret;
    }

    // ------------------------------------------------------------------ Liens

    public static function creerLien(int $fournisseurId, int $jours, ?int $userId, ?int $contactId = null): string
    {
        if (!in_array($jours, self::DUREES_JOURS, true)) {
            $jours = self::DUREE_DEFAUT;
        }
        $token = bin2hex(random_bytes(24));
        Database::connection()->prepare(
            'INSERT INTO fournisseur_liens (fournisseur_id, token, expire_le, cree_par, created_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([$fournisseurId, $token, date('Y-m-d H:i:s', time() + $jours * 86400), $userId, date('Y-m-d H:i:s')]);
        if ($contactId && \App\Core\Schema::colonne('fournisseur_liens', 'contact_id')) {
            Database::connection()->prepare('UPDATE fournisseur_liens SET contact_id = ? WHERE token = ?')->execute([$contactId, $token]);
        }
        return $token;
    }

    public static function liensDuFournisseur(int $fournisseurId): array
    {
        if (!self::schemaPret()) {
            return [];
        }
        $stmt = Database::connection()->prepare('SELECT * FROM fournisseur_liens WHERE fournisseur_id = ? ORDER BY created_at DESC, id DESC LIMIT 10');
        $stmt->execute([$fournisseurId]);
        return $stmt->fetchAll();
    }

    public static function lienDuFournisseur(int $fournisseurId, int $lienId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM fournisseur_liens WHERE id = ? AND fournisseur_id = ?');
        $stmt->execute([$lienId, $fournisseurId]);
        return $stmt->fetch() ?: null;
    }

    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token) || !self::schemaPret()) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM fournisseur_liens WHERE token = ?');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public static function estValide(array $lien): bool
    {
        return empty($lien['revoque_le']) && strtotime((string) $lien['expire_le']) > time();
    }

    /** Repousse l'échéance d'un lien non révoqué (client régulier) : nouvelle échéance = aujourd'hui + durée. */
    public static function prolonger(int $lienId, int $jours): void
    {
        if (!in_array($jours, self::DUREES_JOURS, true)) {
            $jours = self::DUREE_DEFAUT;
        }
        Database::connection()->prepare('UPDATE fournisseur_liens SET expire_le = ? WHERE id = ? AND revoque_le IS NULL')
            ->execute([date('Y-m-d H:i:s', time() + $jours * 86400), $lienId]);
    }

    public static function revoquer(int $lienId): void
    {
        Database::connection()->prepare('UPDATE fournisseur_liens SET revoque_le = ? WHERE id = ? AND revoque_le IS NULL')
            ->execute([date('Y-m-d H:i:s'), $lienId]);
    }

    public static function enregistrerAcces(int $lienId): void
    {
        Database::connection()->prepare('UPDATE fournisseur_liens SET nb_acces = nb_acces + 1, dernier_acces = ? WHERE id = ?')
            ->execute([date('Y-m-d H:i:s'), $lienId]);
    }

    // ------------------------------------------------------------------ Demandes visibles par le fournisseur

    /**
     * Demandes de prix de CE fournisseur : envoyées ou relancées (à répondre), et celles auxquelles il a
     * répondu ces 60 derniers jours (pour confirmation). Dossiers annulés/perdus exclus.
     */
    public static function consultations(int $fournisseurId): array
    {
        $depuis = date('Y-m-d H:i:s', time() - 60 * 86400);
        $stmt = Database::connection()->prepare(
            "SELECT cf.*, d.reference AS dossier_reference, d.objet AS dossier_objet,
                    (SELECT cp.echeance_reponse FROM consultation_partages cp WHERE cp.consultation_id = cf.id AND cp.echeance_reponse IS NOT NULL ORDER BY cp.created_at DESC LIMIT 1) AS echeance_reponse,
                    (SELECT COUNT(*) FROM offres o WHERE o.consultation_id = cf.id AND o.statut != 'remplacee') AS nb_offres
             FROM consultations_fournisseur cf
             INNER JOIN dossiers d ON d.id = cf.dossier_id
             WHERE cf.fournisseur_id = ? AND d.statut = 'actif'
               AND (cf.statut IN ('envoyee', 'relance') OR (cf.statut = 'reponse_recue' AND cf.created_at >= ?))
             ORDER BY (CASE WHEN cf.statut IN ('envoyee', 'relance') THEN 0 ELSE 1 END), cf.created_at DESC"
        );
        $stmt->execute([$fournisseurId, $depuis]);
        return $stmt->fetchAll();
    }

    public static function consultation(int $fournisseurId, int $id): ?array
    {
        foreach (self::consultations($fournisseurId) as $c) {
            if ((int) $c['id'] === $id) {
                return $c;
            }
        }
        return null;
    }

    public static function peutRepondre(array $c): bool
    {
        return in_array($c['statut'], ['envoyee', 'relance'], true)
            || ($c['statut'] === 'reponse_recue' && self::derniereOffreModifiable($c) !== null);
    }

    /** Dernière offre de cette consultation, si elle n'a pas encore été retenue/écartée par l'entreprise. */
    public static function derniereOffreModifiable(array $c): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM offres WHERE consultation_id = ? AND statut != 'remplacee' ORDER BY id DESC LIMIT 1");
        $stmt->execute([(int) $c['id']]);
        $o = $stmt->fetch();
        return ($o && in_array($o['statut'], ['a_verifier', 'recue'], true)) ? $o : null;
    }

    public static function marquerVue(int $consultationId): void
    {
        Database::connection()->prepare('UPDATE consultations_fournisseur SET vue_fournisseur_le = ? WHERE id = ? AND vue_fournisseur_le IS NULL')
            ->execute([date('Y-m-d H:i:s'), $consultationId]);
    }

    public static function enregistrerReponse(int $consultationId, string $type, string $note): void
    {
        if (!in_array($type, ['decline', 'delai'], true)) {
            return;
        }
        Database::connection()->prepare('UPDATE consultations_fournisseur SET reponse_fournisseur = ?, reponse_fournisseur_note = ?, reponse_fournisseur_le = ?, updated_at = ? WHERE id = ?')
            ->execute([$type, mb_substr(trim($note), 0, 500), date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $consultationId]);
    }

    /** Articles demandés (aucune donnée client). */
    public static function articles(array $c): array
    {
        $dossier = Dossier::find((int) $c['dossier_id']);
        $demande = $dossier ? Demande::find((int) $dossier['demande_id']) : null;
        return $demande ? ConsultationFournisseur::filtrerArticles($c, DemandeArticle::forDemande((int) $demande['id'])) : [];
    }

    // ------------------------------------------------------------------ Suivi côté entreprise

    /** État lisible d'une consultation pour l'entreprise (pastille). */
    public static function etat(array $c): array
    {
        if (($c['reponse_fournisseur'] ?? '') === 'decline') {
            return ['A décliné', 'gray'];
        }
        if (($c['statut'] ?? '') === 'reponse_recue') {
            return ['Offre reçue', 'green'];
        }
        if (($c['reponse_fournisseur'] ?? '') === 'delai') {
            return ['Délai demandé', 'yellow'];
        }
        if (!empty($c['vue_fournisseur_le'])) {
            return ['En attente, vu le ' . date('d/m H:i', strtotime((string) $c['vue_fournisseur_le'])), 'yellow'];
        }
        return ['Lien pas encore ouvert', 'red'];
    }

    /** Consultations à relancer (envoyées ou relancées, sans offre), pour le message de relance. */
    public static function aRelancer(int $fournisseurId): array
    {
        return array_values(array_filter(self::consultations($fournisseurId), fn($c) => in_array($c['statut'], ['envoyee', 'relance'], true) && ($c['reponse_fournisseur'] ?? '') !== 'decline'));
    }

    public static function messageRelance(array $aRelancer, string $url): string
    {
        $n = count($aRelancer);
        if ($n === 0) {
            return '';
        }
        $echeances = array_filter(array_map(fn($c) => $c['echeance_reponse'] ?? null, $aRelancer));
        $txt = 'Bonjour, nous attendons encore votre prix pour ' . $n . ' demande' . ($n > 1 ? 's' : '');
        if ($echeances) {
            $txt .= ' (réponse souhaitée avant le ' . date('d/m/Y', strtotime(min($echeances))) . ')';
        }
        return $txt . '. Vous pouvez répondre directement ici : ' . $url;
    }
}

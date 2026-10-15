<?php

namespace App\Models;

use App\Core\Database;

class ConsultationFournisseur
{
    public const STATUTS = [
        'a_envoyer' => 'À envoyer',
        'envoyee' => 'Envoyée',
        'relance' => 'Relancée',
        'reponse_recue' => 'Réponse reçue',
        'sans_reponse' => 'Sans réponse',
    ];

    /** Canaux d'envoi possibles (choisis par l'utilisateur, jamais devinés). */
    public const CANAUX = [
        'whatsapp' => 'WhatsApp',
        'email' => 'E-mail',
        'autre' => 'Autre (téléphone, Alibaba, plateforme…)',
    ];

    /** La colonne canal_envoi existe-t-elle (migrate_v30 passée) ? */
    public static function colonneCanal(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::connection()->query('SELECT canal_envoi FROM consultations_fournisseur LIMIT 1');
                $ok = true;
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * L'envoi a réellement eu lieu : la consultation passe de « À envoyer » à
     * « Envoyée », avec la date du jour et le canal choisi. Une consultation
     * déjà relancée ou répondue n'est pas ramenée en arrière.
     */
    public static function marquerEnvoyee(int $id, string $canal): void
    {
        $canal = array_key_exists($canal, self::CANAUX) ? $canal : 'autre';
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');
        $pdo->prepare("UPDATE consultations_fournisseur SET statut = 'envoyee', date_envoi = ?, updated_at = ? WHERE id = ? AND statut = 'a_envoyer'")
            ->execute([date('Y-m-d'), $now, $id]);
        if (self::colonneCanal()) {
            $pdo->prepare('UPDATE consultations_fournisseur SET canal_envoi = ?, updated_at = ? WHERE id = ?')->execute([$canal, $now, $id]);
        }
    }

    /** La colonne articles_ids existe-t-elle (migrate_v29 passée) ? */
    public static function colonneArticles(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::connection()->query('SELECT articles_ids FROM consultations_fournisseur LIMIT 1');
                $ok = true;
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * Articles de la demande concernés par cette consultation : null = tous, sinon la liste des ids.
     * @return int[]|null
     */
    public static function articlesIds(array $consultation): ?array
    {
        $brut = trim((string) ($consultation['articles_ids'] ?? ''));
        if ($brut === '') {
            return null;
        }
        return array_values(array_filter(array_map('intval', explode(',', $brut))));
    }

    /** Filtre une liste d'articles de demande selon le choix fait pour cette consultation. */
    public static function filtrerArticles(array $consultation, array $articles): array
    {
        $ids = self::articlesIds($consultation);
        if ($ids === null) {
            return $articles;
        }
        return array_values(array_filter($articles, static fn($a) => in_array((int) $a['id'], $ids, true)));
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM consultations_fournisseur WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findWithDetails(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT cf.*, fo.nom AS fournisseur_nom, fo.email AS fournisseur_email, d.reference AS dossier_reference, d.type_dossier
             FROM consultations_fournisseur cf
             INNER JOIN fournisseurs fo ON fo.id = cf.fournisseur_id
             INNER JOIN dossiers d ON d.id = cf.dossier_id
             WHERE cf.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forDossier(int $dossierId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT cf.*, fo.nom AS fournisseur_nom,
                    (SELECT COUNT(*) FROM offres o WHERE o.consultation_id = cf.id AND o.statut != 'remplacee') AS nb_offres,
                    (SELECT cp.id FROM consultation_partages cp WHERE cp.consultation_id = cf.id ORDER BY cp.created_at DESC LIMIT 1) AS dernier_partage_id,
                    (SELECT cp.echeance_reponse FROM consultation_partages cp WHERE cp.consultation_id = cf.id ORDER BY cp.created_at DESC LIMIT 1) AS dernier_partage_echeance,
                    (SELECT cp.pieces_ids FROM consultation_partages cp WHERE cp.consultation_id = cf.id ORDER BY cp.created_at DESC LIMIT 1) AS dernier_partage_pieces_ids
             FROM consultations_fournisseur cf
             INNER JOIN fournisseurs fo ON fo.id = cf.fournisseur_id
             WHERE cf.dossier_id = ?
             ORDER BY cf.created_at DESC"
        );
        $stmt->execute([$dossierId]);
        return $stmt->fetchAll();
    }

    /**
     * [ajouté 06/10, étape 2 du découpage Dossiers] Vrai la réponse est
     * en retard seulement si une échéance a été fixée (via un lien de
     * partage "Demander une offre" — voir ConsultationPartage), qu'elle
     * est dépassée, et qu'aucune offre n'a encore été reçue ni la
     * consultation classée "Sans réponse".
     */
    public static function estEnRetard(array $consultation): bool
    {
        $echeance = $consultation['dernier_partage_echeance'] ?? null;
        if (empty($echeance)) {
            return false;
        }
        if (in_array($consultation['statut'], ['reponse_recue', 'sans_reponse'], true)) {
            return false;
        }
        return $echeance < date('Y-m-d');
    }

    /**
     * Historique des consultations envoyées à un fournisseur donné, tous
     * dossiers confondus — pour la fiche fournisseur.
     */
    public static function forFournisseur(int $fournisseurId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT cf.*, d.reference AS dossier_reference,
                    (SELECT COUNT(*) FROM offres o WHERE o.consultation_id = cf.id) AS nb_offres
             FROM consultations_fournisseur cf
             INNER JOIN dossiers d ON d.id = cf.dossier_id
             WHERE cf.fournisseur_id = ?
             ORDER BY cf.created_at DESC'
        );
        $stmt->execute([$fournisseurId]);
        return $stmt->fetchAll();
    }

    public static function create(int $dossierId, int $filialeId, array $data, int $userId): int
    {
        $pdo = Database::connection();
        $filiale = Filiale::find($filialeId);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'consultation');
        $reference = Compteur::formatReference('CONS', $numero);
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT INTO consultations_fournisseur
             (dossier_id, filiale_id, fournisseur_id, reference, articles_demandes, statut, date_envoi, date_relance, notes, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $dossierId,
            $filialeId,
            $data['fournisseur_id'],
            $reference,
            trim($data['articles_demandes'] ?? ''),
            'a_envoyer',
            null,
            null,
            trim($data['notes'] ?? ''),
            $userId,
            $now,
            $now,
        ]);
        $id = (int) $pdo->lastInsertId();
        // [13/10] Articles choisis pour ce fournisseur (absent = tous les articles de la demande).
        if (!empty($data['articles_ids']) && self::colonneArticles()) {
            $ids = implode(',', array_map('intval', (array) $data['articles_ids']));
            $pdo->prepare('UPDATE consultations_fournisseur SET articles_ids = ? WHERE id = ?')->execute([$ids, $id]);
        }
        return $id;
    }

    public static function updateStatut(int $id, string $statut, ?string $dateRelance = null, ?string $canal = null): void
    {
        if (!array_key_exists($statut, self::STATUTS)) {
            throw new \InvalidArgumentException('Statut de consultation invalide.');
        }
        if ($statut === 'envoyee') {
            // Passage manuel à « Envoyée » : on date l'envoi et on retient le canal indiqué.
            $pdo = Database::connection();
            $pdo->prepare('UPDATE consultations_fournisseur SET date_envoi = ? WHERE id = ? AND date_envoi IS NULL')->execute([date('Y-m-d'), $id]);
            if ($canal !== null && $canal !== '' && array_key_exists($canal, self::CANAUX) && self::colonneCanal()) {
                $pdo->prepare('UPDATE consultations_fournisseur SET canal_envoi = ? WHERE id = ?')->execute([$canal, $id]);
            }
        }
        $stmt = Database::connection()->prepare(
            'UPDATE consultations_fournisseur SET statut = ?, date_relance = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([$statut, $dateRelance ?: null, date('Y-m-d H:i:s'), $id]);
    }

    /**
     * Modifie une consultation PAS ENCORE ENVOYÉE (ce que le fournisseur verra ne change plus une fois envoyée).
     * @param int[]|null $articlesIds null = tous les articles de la demande
     */
    public static function modifier(int $id, int $fournisseurId, string $precisions, string $notes, ?array $articlesIds): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("UPDATE consultations_fournisseur SET fournisseur_id = ?, articles_demandes = ?, notes = ?, updated_at = ? WHERE id = ? AND statut = 'a_envoyer'");
        $stmt->execute([$fournisseurId, trim($precisions), trim($notes), date('Y-m-d H:i:s'), $id]);
        if (self::colonneArticles()) {
            $val = $articlesIds ? implode(',', array_map('intval', $articlesIds)) : null;
            $pdo->prepare("UPDATE consultations_fournisseur SET articles_ids = ? WHERE id = ? AND statut = 'a_envoyer'")->execute([$val, $id]);
        }
        return true;
    }

    /** Nombre d'offres enregistrées pour cette consultation (toutes versions). */
    public static function nbOffres(int $id): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM offres WHERE consultation_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Suppression définitive d'une consultation SANS offre reçue (une consultation
     * qui a donné lieu à des offres fait partie de l'historique du dossier : elle
     * se garde, en « Sans réponse » si besoin). Les liens de partage associés sont supprimés avec elle.
     */
    public static function supprimer(int $id): bool
    {
        if (self::nbOffres($id) > 0) {
            return false;
        }
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM consultation_partages WHERE consultation_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM consultations_fournisseur WHERE id = ?')->execute([$id]);
        return true;
    }

    public static function userCanAccess(array $user, array $consultation): bool
    {
        return Filiale::userCanAccess($user, (int) $consultation['filiale_id']);
    }
}

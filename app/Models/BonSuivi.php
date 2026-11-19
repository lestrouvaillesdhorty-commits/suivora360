<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Schema;
use App\Core\Storage;

/**
 * Suivi d'un bon de commande par le fournisseur, depuis son lien (v71) : confirmation, expédition, documents.
 * Le fournisseur ne voit que ses bons « envoyé » ou « confirmé » : jamais de brouillon, de client ni de prix de vente.
 */
class BonSuivi
{
    public const TYPES = ['facture' => 'Facture', 'bon_livraison' => 'Bon de livraison', 'autre' => 'Autre'];
    public const TAILLE_MAX = 10485760;
    public const MAX_PAR_BON = 20;
    private const EXT = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function pret(): bool
    {
        return BonCommandeFournisseur::schemaPret() && Schema::colonne('bons_commande_fournisseur', 'suivi_etat') && Schema::colonne('bon_commande_documents', 'fichier');
    }

    /** Bons visibles par le fournisseur (envoyés ou confirmés, jamais annulés ni brouillons). À confirmer d'abord. */
    public static function liste(int $fournisseurId): array
    {
        if (!self::pret()) {
            return [];
        }
        $st = Database::connection()->prepare(
            "SELECT b.*, d.reference AS dossier_reference, d.objet AS dossier_objet,
                    (SELECT COUNT(*) FROM bon_commande_fournisseur_lignes l WHERE l.bon_id = b.id) AS nb_lignes
             FROM bons_commande_fournisseur b LEFT JOIN dossiers d ON d.id = b.dossier_id
             WHERE b.fournisseur_id = ? AND b.statut IN ('envoye', 'confirme') AND b.refuse_le IS NULL
             ORDER BY (CASE WHEN b.statut = 'envoye' THEN 0 ELSE 1 END), (CASE WHEN b.suivi_etat = 'livre' THEN 1 ELSE 0 END), b.date_emission DESC, b.id DESC"
        );
        $st->execute([$fournisseurId]);
        return $st->fetchAll();
    }

    /** Nombre de bons à confirmer (pastille de l'onglet). */
    public static function aConfirmer(int $fournisseurId): int
    {
        if (!self::pret()) {
            return 0;
        }
        $st = Database::connection()->prepare("SELECT COUNT(*) FROM bons_commande_fournisseur WHERE fournisseur_id = ? AND statut = 'envoye' AND refuse_le IS NULL");
        $st->execute([$fournisseurId]);
        return (int) $st->fetchColumn();
    }

    public static function bon(int $fournisseurId, int $id): ?array
    {
        foreach (self::liste($fournisseurId) as $b) {
            if ((int) $b['id'] === $id) {
                return $b;
            }
        }
        return null;
    }

    /** Étape atteinte, de 1 (reçue) à 5 (livrée). */
    public static function etape(array $b): int
    {
        if (($b['suivi_etat'] ?? '') === 'livre') {
            return 5;
        }
        if (($b['suivi_etat'] ?? '') === 'expedie') {
            return 4;
        }
        if ($b['statut'] === 'confirme') {
            return !empty($b['prepare_le']) ? 3 : 2;
        }
        return 1;
    }

    public const ETAPES = ['Reçue', 'Confirmée', 'Préparée', 'Expédiée', 'Livrée'];

    public static function confirmer(array $b, string $prepareLe): void
    {
        BonCommandeFournisseur::changerStatut((int) $b['id'], 'confirme');
        Database::connection()->prepare('UPDATE bons_commande_fournisseur SET prepare_le = ? WHERE id = ?')
            ->execute([preg_match('/^\d{4}-\d{2}-\d{2}$/', $prepareLe) ? $prepareLe : null, (int) $b['id']]);
    }

    public static function refuser(array $b, string $motif): void
    {
        Database::connection()->prepare('UPDATE bons_commande_fournisseur SET refuse_le = ?, refus_motif = ?, updated_at = ? WHERE id = ?')
            ->execute([date('Y-m-d H:i:s'), mb_substr($motif, 0, 255), date('Y-m-d H:i:s'), (int) $b['id']]);
    }

    /** Enregistre l'expédition ; reporte l'arrivée prévue et le suivi sur la commande du dossier (si elle existe). */
    public static function expedier(array $b, array $d): void
    {
        $date = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : null;
        $pdo = Database::connection();
        $pdo->prepare("UPDATE bons_commande_fournisseur SET suivi_etat = 'expedie', expedie_le = ?, transporteur = ?, suivi_numero = ?, arrivee_prevue = ?, updated_at = ? WHERE id = ?")
            ->execute([$date($d['expedie_le'] ?? '') ?: date('Y-m-d'), mb_substr(trim((string) ($d['transporteur'] ?? '')), 0, 80), mb_substr(trim((string) ($d['suivi_numero'] ?? '')), 0, 80), $date($d['arrivee_prevue'] ?? ''), date('Y-m-d H:i:s'), (int) $b['id']]);
        try {
            if (!empty($b['dossier_id']) && Schema::colonne('commandes', 'date_arrivee_prevue')) {
                $pdo->prepare("UPDATE commandes SET date_arrivee_prevue = COALESCE(?, date_arrivee_prevue), tracking_numero = COALESCE(?, tracking_numero), updated_at = ? WHERE dossier_id = ? AND statut = 'en_cours'")
                    ->execute([$date($d['arrivee_prevue'] ?? ''), trim((string) ($d['suivi_numero'] ?? '')) ?: null, date('Y-m-d H:i:s'), (int) $b['dossier_id']]);
            }
        } catch (\Throwable $e) {
        }
    }

    public static function marquerLivree(int $bonId): void
    {
        Database::connection()->prepare("UPDATE bons_commande_fournisseur SET suivi_etat = 'livre', livre_le = ?, updated_at = ? WHERE id = ?")
            ->execute([date('Y-m-d'), date('Y-m-d H:i:s'), $bonId]);
    }

    // --- Documents

    public static function documents(int $bonId): array
    {
        if (!self::pret()) {
            return [];
        }
        $st = Database::connection()->prepare('SELECT * FROM bon_commande_documents WHERE bon_id = ? ORDER BY id DESC');
        $st->execute([$bonId]);
        return $st->fetchAll();
    }

    public static function documentsFournisseur(int $fournisseurId): array
    {
        if (!self::pret()) {
            return [];
        }
        $st = Database::connection()->prepare(
            'SELECT x.*, b.reference AS bon_reference FROM bon_commande_documents x INNER JOIN bons_commande_fournisseur b ON b.id = x.bon_id
             WHERE x.fournisseur_id = ? ORDER BY x.id DESC LIMIT 100'
        );
        $st->execute([$fournisseurId]);
        return $st->fetchAll();
    }

    public static function document(int $bonId, int $docId): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM bon_commande_documents WHERE id = ? AND bon_id = ?');
        $st->execute([$docId, $bonId]);
        return $st->fetch() ?: null;
    }

    public static function cheminDocument(array $doc): string
    {
        return Storage::path('uploads/bons/' . (int) $doc['bon_id'] . '/' . basename((string) $doc['fichier']));
    }

    /** Enregistre un fichier reçu (PDF ou image). Retourne un message d'erreur, ou null si tout va bien. */
    public static function ajouterDocument(array $b, array $f, string $type, array $fichier): ?string
    {
        if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'Choisissez un fichier.';
        }
        if (($fichier['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file($fichier['tmp_name'] ?? '') || ($fichier['size'] ?? 0) > self::TAILLE_MAX || ($fichier['size'] ?? 0) <= 0) {
            return 'Fichier non reçu : 10 Mo maximum.';
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']);
        if (!isset(self::EXT[$mime])) {
            return 'Format refusé : envoyez un PDF ou une image (JPG, PNG).';
        }
        if (count(self::documents((int) $b['id'])) >= self::MAX_PAR_BON) {
            return 'Nombre maximum de documents atteint pour ce bon.';
        }
        $nom = bin2hex(random_bytes(12)) . '.' . self::EXT[$mime];
        $chemin = Storage::path('uploads/bons/' . (int) $b['id'] . '/' . $nom);
        if (!move_uploaded_file($fichier['tmp_name'], $chemin)) {
            return "Le fichier n'a pas pu être enregistré. Réessayez.";
        }
        $original = preg_replace('/[^\p{L}\p{N} ._()-]/u', '', basename((string) ($fichier['name'] ?? 'document')));
        Database::connection()->prepare('INSERT INTO bon_commande_documents (bon_id, filiale_id, fournisseur_id, type, nom_original, fichier, taille, created_at) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([(int) $b['id'], (int) $b['filiale_id'], (int) $f['id'], isset(self::TYPES[$type]) ? $type : 'autre', mb_substr($original ?: 'document', 0, 200), $nom, (int) $fichier['size'], date('Y-m-d H:i:s')]);
        return null;
    }
}

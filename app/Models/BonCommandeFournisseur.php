<?php

namespace App\Models;

use App\Core\Database;

/**
 * Bon de commande fournisseur (07/10).
 *
 * Document d'achat adressé à UN fournisseur, en UNE devise, avec ses lignes (désignation,
 * quantité, unité, prix unitaire). Il peut venir d'une offre retenue (lignes reprises de l'offre,
 * modifiables tant que le bon est en brouillon) ou être établi librement depuis la fiche fournisseur.
 *
 * Règles :
 *  - le nom, l'adresse et le destinataire du fournisseur sont COPIÉS à la création : une fiche
 *    modifiée plus tard ne change pas un bon déjà émis ;
 *  - modifiable seulement en brouillon ; ensuite on ne fait qu'avancer le statut ou annuler (jamais supprimer) ;
 *  - un seul bon non annulé par offre ;
 *  - marqué « envoyé » seulement si, pour un bon lié à un dossier, le client a accepté la cotation
 *    (la sélection d'une offre fournisseur ne vaut pas accord du client) ;
 *  - aucune marge, aucun prix client : seulement des données d'achat.
 */
class BonCommandeFournisseur
{
    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'envoye' => 'Envoyé',
        'confirme' => 'Confirmé par le fournisseur',
        'annule' => 'Annulé',
    ];
    public const BADGES = [
        'brouillon' => 'badge-gray',
        'envoye' => 'badge-blue',
        'confirme' => 'badge-green',
        'annule' => 'badge-red',
    ];

    private static ?bool $pret = null;

    public static function schemaPret(): bool
    {
        if (self::$pret === null) {
            self::$pret = Fournisseur::tableExiste('bons_commande_fournisseur') && Fournisseur::tableExiste('bon_commande_fournisseur_lignes');
        }
        return self::$pret;
    }

    public static function find(int $id): ?array
    {
        if (!self::schemaPret()) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT b.*, d.reference AS dossier_reference, d.objet AS dossier_objet, u.nom AS createur_nom
             FROM bons_commande_fournisseur b
             LEFT JOIN dossiers d ON d.id = b.dossier_id
             LEFT JOIN utilisateurs u ON u.id = b.created_by
             WHERE b.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function userCanAccess(array $user, array $bon): bool
    {
        return Filiale::userCanAccess($user, (int) $bon['filiale_id']);
    }

    public static function lignes(int $bonId): array
    {
        if (!self::schemaPret()) {
            return [];
        }
        $stmt = Database::connection()->prepare('SELECT * FROM bon_commande_fournisseur_lignes WHERE bon_id = ? ORDER BY ordre, id');
        $stmt->execute([$bonId]);
        return $stmt->fetchAll();
    }

    public static function pourFournisseur(int $fournisseurId): array
    {
        if (!self::schemaPret()) {
            return [];
        }
        $stmt = Database::connection()->prepare(
            'SELECT b.*, d.reference AS dossier_reference FROM bons_commande_fournisseur b
             LEFT JOIN dossiers d ON d.id = b.dossier_id
             WHERE b.fournisseur_id = ? ORDER BY b.date_emission DESC, b.id DESC'
        );
        $stmt->execute([$fournisseurId]);
        return $stmt->fetchAll();
    }

    /** Bon non annulé d'une offre (au plus un). */
    public static function actifPourOffre(int $offreId): ?array
    {
        if (!self::schemaPret()) {
            return null;
        }
        $stmt = Database::connection()->prepare("SELECT * FROM bons_commande_fournisseur WHERE offre_id = ? AND statut != 'annule' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$offreId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Liste d'ensemble, limitée aux filiales visibles. */
    public static function liste(array $filialeIds, string $statut = '', string $q = ''): array
    {
        if (!self::schemaPret() || empty($filialeIds)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT b.*, d.reference AS dossier_reference FROM bons_commande_fournisseur b
                LEFT JOIN dossiers d ON d.id = b.dossier_id WHERE b.filiale_id IN ($ph)";
        $params = $filialeIds;
        if (isset(self::STATUTS[$statut])) {
            $sql .= ' AND b.statut = ?';
            $params[] = $statut;
        }
        if ($q !== '') {
            $sql .= ' AND (b.reference LIKE ? OR b.fournisseur_nom LIKE ? OR d.reference LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        $sql .= ' ORDER BY b.date_emission DESC, b.id DESC LIMIT 300';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Le client a-t-il accepté la cotation courante du dossier ? (parcours : estimation, cotation, accord client, puis achats) */
    public static function accordClient(?int $dossierId): ?bool
    {
        if (!$dossierId) {
            return null; // bon sans dossier : la règle ne s'applique pas
        }
        $cotation = Cotation::latestForDossier($dossierId);
        return $cotation !== null && $cotation['statut'] === 'acceptee';
    }

    /** Valeurs proposées pour un nouveau bon, reprises de l'offre retenue. */
    public static function propositionDepuisOffre(array $offre): array
    {
        $lignes = [];
        foreach (OffreItem::forOffre((int) $offre['id']) as $it) {
            $lignes[] = [
                'designation' => $it['designation'],
                'quantite' => $it['quantite'],
                'unite' => $it['unite'],
                'prix_unitaire' => $it['prix_unitaire'],
            ];
        }
        if (empty($lignes) && (float) $offre['montant_total'] > 0) {
            $lignes[] = ['designation' => 'Selon offre ' . $offre['reference'], 'quantite' => 1, 'unite' => '', 'prix_unitaire' => (float) $offre['montant_total']];
        }
        return [
            'devise' => (string) ($offre['devise'] ?? ''),
            'incoterm' => (string) ($offre['incoterm_negocie'] ?? ''),
            'conditions_paiement' => (string) (Client::CONDITIONS_PAIEMENT[$offre['conditions_paiement'] ?? ''] ?? ($offre['conditions_paiement'] ?? '')),
            'lieu_livraison' => (string) ($offre['lieu_depart'] ?? ''),
            'reference_offre' => (string) $offre['reference'] . ' (v' . (int) $offre['version'] . ')',
            'lignes' => $lignes,
        ];
    }

    private static function nombre($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $v = str_replace([' ', ','], ['', '.'], (string) $v);
        return is_numeric($v) ? (float) $v : null;
    }

    /** Lignes nettoyées (lignes vides ignorées), montant de chaque ligne, total. */
    public static function normaliserLignes(array $brutes): array
    {
        $lignes = [];
        $total = 0.0;
        $ordre = 0;
        foreach ($brutes as $l) {
            $des = trim((string) ($l['designation'] ?? ''));
            if ($des === '') {
                continue;
            }
            $q = self::nombre($l['quantite'] ?? null);
            $pu = self::nombre($l['prix_unitaire'] ?? null);
            $montant = ($q !== null && $pu !== null) ? round($q * $pu, 2) : null;
            if ($montant !== null) {
                $total += $montant;
            }
            $lignes[] = ['designation' => mb_substr($des, 0, 255), 'quantite' => $q, 'unite' => mb_substr(trim((string) ($l['unite'] ?? '')), 0, 20),
                'prix_unitaire' => $pu, 'montant' => $montant, 'ordre' => $ordre++];
        }
        return [$lignes, round($total, 2)];
    }

    private static function enregistrerLignes(int $bonId, array $lignes): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM bon_commande_fournisseur_lignes WHERE bon_id = ?')->execute([$bonId]);
        $ins = $pdo->prepare('INSERT INTO bon_commande_fournisseur_lignes (bon_id, designation, quantite, unite, prix_unitaire, montant, ordre) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($lignes as $l) {
            $ins->execute([$bonId, $l['designation'], $l['quantite'], $l['unite'], $l['prix_unitaire'], $l['montant'], $l['ordre']]);
        }
    }

    private static function adresseTexte(array $fournisseur): string
    {
        foreach (Fournisseur::adresses((int) $fournisseur['id']) as $a) {
            if ((int) $a['actif'] === 1 && (int) $a['par_defaut'] === 1) {
                return trim(implode(', ', array_filter([$a['adresse'], trim($a['code_postal'] . ' ' . $a['ville']), $a['pays']])));
            }
        }
        return trim(implode(', ', array_filter([$fournisseur['adresse'] ?? '', trim(($fournisseur['code_postal'] ?? '') . ' ' . ($fournisseur['ville'] ?? '')), $fournisseur['pays'] ?? ''])));
    }

    public static function creer(array $fournisseur, array $d, array $lignes, float $total, int $userId): int
    {
        $pdo = Database::connection();
        $filiale = Filiale::find((int) $fournisseur['filiale_id']);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'bon_cmd_fournisseur');
        $reference = Compteur::formatReference('BCF', $numero);
        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO bons_commande_fournisseur
                 (filiale_id, fournisseur_id, dossier_id, offre_id, reference, statut, date_emission, date_livraison_souhaitee, devise, incoterm,
                  lieu_livraison, conditions_paiement, reference_offre, notes, notes_internes, fournisseur_nom, fournisseur_adresse,
                  destinataire_nom, destinataire_email, montant_total, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, \'brouillon\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                (int) $fournisseur['filiale_id'], (int) $fournisseur['id'], $d['dossier_id'] ?: null, $d['offre_id'] ?: null, $reference,
                $d['date_emission'], $d['date_livraison_souhaitee'] ?: null, $d['devise'], $d['incoterm'],
                $d['lieu_livraison'], $d['conditions_paiement'], $d['reference_offre'], $d['notes'], $d['notes_internes'],
                $fournisseur['nom'], self::adresseTexte($fournisseur),
                $d['destinataire_nom'], $d['destinataire_email'], $total, $userId, $now, $now,
            ]);
            $id = (int) $pdo->lastInsertId();
            self::enregistrerLignes($id, $lignes);
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function modifier(int $id, array $d, array $lignes, float $total): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE bons_commande_fournisseur SET date_emission = ?, date_livraison_souhaitee = ?, devise = ?, incoterm = ?, lieu_livraison = ?,
                 conditions_paiement = ?, notes = ?, notes_internes = ?, destinataire_nom = ?, destinataire_email = ?, montant_total = ?, updated_at = ?
                 WHERE id = ? AND statut = \'brouillon\''
            )->execute([
                $d['date_emission'], $d['date_livraison_souhaitee'] ?: null, $d['devise'], $d['incoterm'], $d['lieu_livraison'],
                $d['conditions_paiement'], $d['notes'], $d['notes_internes'], $d['destinataire_nom'], $d['destinataire_email'], $total,
                date('Y-m-d H:i:s'), $id,
            ]);
            self::enregistrerLignes($id, $lignes);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Transitions : brouillon → envoyé → confirmé ; annulation possible tant que ce n'est pas déjà annulé. */
    public static function transitionPossible(string $de, string $vers): bool
    {
        return match ($vers) {
            'envoye' => $de === 'brouillon',
            'confirme' => $de === 'envoye',
            'annule' => $de !== 'annule',
            default => false,
        };
    }

    public static function changerStatut(int $id, string $vers, string $motif = ''): void
    {
        $now = date('Y-m-d H:i:s');
        $col = ['envoye' => 'envoye_le', 'confirme' => 'confirme_le', 'annule' => 'annule_le'][$vers];
        $sql = "UPDATE bons_commande_fournisseur SET statut = ?, $col = ?, updated_at = ?" . ($vers === 'annule' ? ', annule_motif = ?' : '') . ' WHERE id = ?';
        $params = $vers === 'annule' ? [$vers, $now, $now, $motif, $id] : [$vers, $now, $now, $id];
        Database::connection()->prepare($sql)->execute($params);
    }
}

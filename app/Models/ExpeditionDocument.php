<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Schema;

/**
 * Documents à fournir pour une expédition (certificats, factures…).
 * - La liste des documents attendus se règle par filiale (Paramètres > Listes), selon le type
 *   de marchandise ou le cas particulier. Une liste d'origine est copiée à la première utilisation.
 * - Rien n'est demandé quand « Formalités gérées par le transitaire » est activé sur l'expédition (défaut).
 * - Aucun blocage : seulement un compteur « documents manquants ».
 */
class ExpeditionDocument
{
    public const STATUTS = ['a_fournir' => 'À fournir', 'recu' => 'Reçu', 'na' => 'Non applicable'];
    public const CIBLES = [
        'tous' => 'Toutes les marchandises',
        'alimentaire' => 'Alimentaire',
        'non_alimentaire' => 'Non alimentaire',
        'hors_format' => 'Hors format',
        'alcool' => 'Alcool',
        'dangereux' => 'Produit dangereux',
        'autre' => 'Autre cas particulier',
    ];
    private const DEFAUT = [
        ['Facture commerciale', 'tous'],
        ['Liste de colisage', 'tous'],
        ['Certificat sanitaire ou phytosanitaire', 'alimentaire'],
        ['Certificat d\'origine', 'alimentaire'],
        ['Autorisation ou licence pour l\'alcool', 'alcool'],
        ['Photos et dimensions précises', 'hors_format'],
        ['Accord du transitaire sur le hors format', 'hors_format'],
        ['Fiche de données de sécurité', 'dangereux'],
    ];

    /** Cibles proposées pour un document : toutes, une famille, ou un type de produit de la filiale. */
    public static function cibles(int $filialeId): array
    {
        if (!TypeProduit::disponible()) {
            return self::CIBLES;
        }
        $c = ['tous' => 'Toutes les marchandises', 'alimentaire' => 'Famille Alimentaire', 'non_alimentaire' => 'Famille Non alimentaire'];
        foreach (TypeProduit::lignes($filialeId) as $t) {
            $c['tp' . $t['id']] = $t['libelle'];
        }
        return $c;
    }

    /** Valeur à présélectionner : les anciens codes (hors_format…) pointent vers le type de même code. */
    public static function cibleAffichee(int $filialeId, string $appliqueA): string
    {
        foreach (TypeProduit::lignes($filialeId) as $t) {
            if ($t['code'] !== '' && $t['code'] === $appliqueA) {
                return 'tp' . $t['id'];
            }
        }
        return $appliqueA;
    }

    public static function disponible(): bool
    {
        return Schema::colonne('expeditions', 'formalites_transitaire') && Schema::colonne('expedition_docs', 'statut') && Schema::colonne('expedition_doc_types', 'libelle');
    }

    public static function formalitesTransitaire(array $exp): bool
    {
        return (int) ($exp['formalites_transitaire'] ?? 1) === 1;
    }

    public static function definirFormalites(int $expeditionId, bool $transitaire): void
    {
        if (Schema::colonne('expeditions', 'formalites_transitaire')) {
            Database::connection()->prepare('UPDATE expeditions SET formalites_transitaire = ? WHERE id = ?')->execute([$transitaire ? 1 : 0, $expeditionId]);
        }
    }

    public static function assurer(int $filialeId): void
    {
        if (!$filialeId || !self::disponible()) {
            return;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT COUNT(*) FROM expedition_doc_types WHERE filiale_id = ?');
        $st->execute([$filialeId]);
        if ((int) $st->fetchColumn() > 0) {
            return;
        }
        $ins = $pdo->prepare('INSERT INTO expedition_doc_types (filiale_id, libelle, applique_a, ordre, actif, created_at) VALUES (?,?,?,?,1,?)');
        foreach (self::DEFAUT as $i => [$lib, $cible]) {
            $ins->execute([$filialeId, $lib, $cible, $i + 1, date('Y-m-d H:i:s')]);
        }
    }

    public static function types(int $filialeId): array
    {
        if (!self::disponible()) {
            return [];
        }
        self::assurer($filialeId);
        $st = Database::connection()->prepare('SELECT * FROM expedition_doc_types WHERE filiale_id = ? ORDER BY ordre, id');
        $st->execute([$filialeId]);
        return $st->fetchAll();
    }

    public static function ajouterType(int $filialeId, string $libelle, string $cible): bool
    {
        $libelle = trim(mb_substr($libelle, 0, 150));
        if ($libelle === '' || !self::disponible() || !isset(self::cibles($filialeId)[$cible])) {
            return false;
        }
        self::assurer($filialeId);
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT COALESCE(MAX(ordre), 0) + 1 FROM expedition_doc_types WHERE filiale_id = ?');
        $st->execute([$filialeId]);
        $pdo->prepare('INSERT INTO expedition_doc_types (filiale_id, libelle, applique_a, ordre, actif, created_at) VALUES (?,?,?,?,1,?)')
            ->execute([$filialeId, $libelle, $cible, (int) $st->fetchColumn(), date('Y-m-d H:i:s')]);
        return true;
    }

    public static function modifierType(int $filialeId, int $id, string $libelle, string $cible): bool
    {
        $libelle = trim(mb_substr($libelle, 0, 150));
        if ($libelle === '' || !self::disponible() || !isset(self::cibles($filialeId)[$cible])) {
            return false;
        }
        Database::connection()->prepare('UPDATE expedition_doc_types SET libelle = ?, applique_a = ? WHERE id = ? AND filiale_id = ?')->execute([$libelle, $cible, $id, $filialeId]);
        return true;
    }

    public static function basculerType(int $filialeId, int $id): void
    {
        if (self::disponible()) {
            Database::connection()->prepare('UPDATE expedition_doc_types SET actif = 1 - actif WHERE id = ? AND filiale_id = ?')->execute([$id, $filialeId]);
        }
    }

    /** Crée les documents attendus d'une réservation (ceux qui manquent seulement). */
    public static function synchroniser(array $exp, array $reservations): void
    {
        if (!self::disponible() || self::formalitesTransitaire($exp)) {
            return;
        }
        $types = array_filter(self::types((int) $exp['filiale_id']), fn($t) => (int) $t['actif'] === 1);
        $pdo = Database::connection();
        $ex = $pdo->prepare('SELECT type_id FROM expedition_docs WHERE reservation_id = ? AND type_id IS NOT NULL');
        $ins = $pdo->prepare('INSERT INTO expedition_docs (reservation_id, type_id, libelle, statut, created_at) VALUES (?,?,?,?,?)');
        foreach ($reservations as $r) {
            $ex->execute([(int) $r['id']]);
            $deja = array_map('intval', array_column($ex->fetchAll(), 'type_id'));
            $cibles = ['tous', (string) ($r['type_marchandise'] ?? '')];
            if (!empty($r['type_produit_id'])) {
                $cibles[] = 'tp' . (int) $r['type_produit_id'];
            }
            if (!empty($r['cas_special'])) {
                $cibles[] = (string) $r['cas_special'];
            }
            foreach ($types as $t) {
                if (in_array($t['applique_a'], $cibles, true) && !in_array((int) $t['id'], $deja, true)) {
                    $ins->execute([(int) $r['id'], (int) $t['id'], $t['libelle'], 'a_fournir', date('Y-m-d H:i:s')]);
                }
            }
        }
    }

    /** Documents par réservation : [reservation_id => [lignes]]. */
    public static function parReservation(array $reservations): array
    {
        $out = [];
        if (!self::disponible() || !$reservations) {
            return $out;
        }
        $st = Database::connection()->prepare('SELECT * FROM expedition_docs WHERE reservation_id = ? ORDER BY id');
        foreach ($reservations as $r) {
            $st->execute([(int) $r['id']]);
            $out[(int) $r['id']] = $st->fetchAll();
        }
        return $out;
    }

    public static function nbManquants(array $docsParReservation): int
    {
        $n = 0;
        foreach ($docsParReservation as $lignes) {
            foreach ($lignes as $d) {
                if ($d['statut'] === 'a_fournir') {
                    $n++;
                }
            }
        }
        return $n;
    }

    public static function ajouterLibre(int $reservationId, string $libelle): void
    {
        $libelle = trim(mb_substr($libelle, 0, 150));
        if ($libelle !== '' && self::disponible()) {
            Database::connection()->prepare('INSERT INTO expedition_docs (reservation_id, type_id, libelle, statut, created_at) VALUES (?,NULL,?,?,?)')
                ->execute([$reservationId, $libelle, 'a_fournir', date('Y-m-d H:i:s')]);
        }
    }

    public static function maj(int $docId, int $reservationId, string $statut, string $note): void
    {
        if (!self::disponible() || !isset(self::STATUTS[$statut])) {
            return;
        }
        Database::connection()->prepare('UPDATE expedition_docs SET statut = ?, recu_le = ?, note = ? WHERE id = ? AND reservation_id = ?')
            ->execute([$statut, $statut === 'recu' ? date('Y-m-d') : null, mb_substr(trim($note), 0, 200), $docId, $reservationId]);
    }
}

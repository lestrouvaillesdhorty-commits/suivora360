<?php

namespace App\Models;

use App\Core\Database;

/**
 * Génère des références séquentielles du type DEM-2026-00001 / DOS-2026-00001,
 * remises à zéro chaque année, par organisation.
 */
class Compteur
{
    /**
     * [corrigé 03/10] Avant ce correctif, cette méthode lisait `valeur`
     * puis la réécrivait en deux requêtes séparées sans transaction ni
     * verrou : deux créations arrivant presque au même instant (deux
     * utilisateurs qui créent chacun une demande/un dossier/etc. en même
     * temps, cas normal dès que plusieurs personnes utilisent l'app
     * simultanément) pouvaient lire la même valeur avant que l'une des
     * deux ne la réincrémente — résultat, deux enregistrements avec
     * exactement la même référence métier (ex. deux "DEM-2026-00015"),
     * sans qu'aucune erreur ne le signale. Reproduit et corrigé le 03/10.
     *
     * Corrigé en rendant la lecture + l'écriture atomiques via une
     * transaction avec verrou de ligne (`SELECT ... FOR UPDATE` sous
     * MySQL — l'hébergement de production ; SQLite n'a pas cette syntaxe
     * mais sérialise de toute façon les écritures dans une transaction,
     * donc le verrou explicite n'est ajouté que pour MySQL).
     *
     * Cette méthode est parfois appelée alors qu'une transaction est déjà
     * ouverte par l'appelant (`Dossier::createFromDemande()`) : dans ce
     * cas on ne démarre/valide pas notre propre transaction (PDO ne
     * supporte pas les transactions imbriquées) — on profite simplement
     * du verrou de ligne qui tient jusqu'à la fin de la transaction
     * englobante, ce qui protège tout aussi bien.
     *
     * Reste une fenêtre de course distincte, beaucoup plus rare : la toute
     * première utilisation d'un compteur donné (nouvelle organisation, ou
     * premier document d'un type sur une nouvelle année) passe par un
     * INSERT plutôt qu'un SELECT ... FOR UPDATE — `FOR UPDATE` ne peut
     * verrouiller une ligne qui n'existe pas encore. La migration
     * `public/migrate_v13.php` ajoute une contrainte UNIQUE
     * (organisation_id, type, annee) sur `compteurs` : si deux créations
     * arrivent malgré tout exactement en même temps sur un compteur encore
     * inexistant, la base rejette la seconde INSERT au lieu d'accepter
     * deux lignes "valeur = 1" — on relit alors la ligne créée par la
     * première et on repart correctement de là (voir le catch ci-dessous).
     * Sans cette migration (si elle n'a pas encore été exécutée en ligne),
     * le comportement reste celui d'avant ce correctif sur ce cas précis
     * uniquement — mais le cas courant (compteur déjà existant, 99 % des
     * créations) est protégé dès ce correctif, migration ou non.
     */
    public static function next(int $organisationId, string $type): int
    {
        $pdo = Database::connection();
        $driver = Database::driver();
        $annee = (int) date('Y');

        $gereSaPropreTransaction = !$pdo->inTransaction();
        if ($gereSaPropreTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $forUpdate = $driver === 'mysql' ? ' FOR UPDATE' : '';
            $stmt = $pdo->prepare('SELECT id, valeur FROM compteurs WHERE organisation_id = ? AND type = ? AND annee = ?' . $forUpdate);
            $stmt->execute([$organisationId, $type, $annee]);
            $row = $stmt->fetch();

            if ($row) {
                $nouvelleValeur = (int) $row['valeur'] + 1;
                $update = $pdo->prepare('UPDATE compteurs SET valeur = ? WHERE id = ?');
                $update->execute([$nouvelleValeur, $row['id']]);
            } else {
                try {
                    $insert = $pdo->prepare('INSERT INTO compteurs (organisation_id, type, annee, valeur) VALUES (?, ?, ?, 1)');
                    $insert->execute([$organisationId, $type, $annee]);
                    $nouvelleValeur = 1;
                } catch (\PDOException $e) {
                    // Un autre processus a créé la ligne entre notre SELECT
                    // et notre INSERT (voir le commentaire de tête de
                    // méthode) — ne remonte cette erreur que si la
                    // contrainte UNIQUE de migrate_v13.php n'est pas (ou
                    // pas encore) en place : dans ce cas la ligne qu'on
                    // s'attend à retrouver n'existe pas, et l'erreur est
                    // réellement anormale.
                    $retry = $pdo->prepare('SELECT id, valeur FROM compteurs WHERE organisation_id = ? AND type = ? AND annee = ?' . $forUpdate);
                    $retry->execute([$organisationId, $type, $annee]);
                    $row = $retry->fetch();
                    if (!$row) {
                        throw $e;
                    }
                    $nouvelleValeur = (int) $row['valeur'] + 1;
                    $update = $pdo->prepare('UPDATE compteurs SET valeur = ? WHERE id = ?');
                    $update->execute([$nouvelleValeur, $row['id']]);
                }
            }

            if ($gereSaPropreTransaction) {
                $pdo->commit();
            }
            return $nouvelleValeur;
        } catch (\Throwable $e) {
            if ($gereSaPropreTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function formatReference(string $prefix, int $numero): string
    {
        return sprintf('%s-%d-%05d', $prefix, (int) date('Y'), $numero);
    }
}

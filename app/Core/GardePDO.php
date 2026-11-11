<?php

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Connexion PDO qui applique le réglage « activités visibles » (voir ActiviteAcces::garde) à toute requête de lecture
 * de la personne connectée. Sans effet pour les administrateurs ni tant qu'aucune activité n'est cachée.
 */
class GardePDO extends PDO
{
    #[\ReturnTypeWillChange]
    public function prepare(string $query, array $options = [])
    {
        return parent::prepare(\App\Models\ActiviteAcces::garde($query), $options);
    }

    #[\ReturnTypeWillChange]
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs)
    {
        $query = \App\Models\ActiviteAcces::garde($query);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

<?php

namespace App\Core;

/**
 * Utilitaires d'export CSV.
 *
 * Neutralisation des « formules » : un texte commençant par = + - @ (ou une
 * tabulation / retour chariot) est interprété comme une formule par Excel à
 * l'ouverture du fichier. Comme certains champs viennent de personnes
 * extérieures (objet ou expéditeur d'une demande reçue par e-mail, par
 * exemple), on préfixe ces textes d'une apostrophe pour qu'ils restent du
 * texte. Les nombres ne sont jamais modifiés.
 */
class Csv
{
    public static function cell($v)
    {
        if (is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) {
            return "'" . $v;
        }
        return $v;
    }

    public static function row(array $cells): array
    {
        return array_map([self::class, 'cell'], $cells);
    }
}

<?php

namespace App\Core;

/**
 * Petit utilitaire pour stocker des fichiers (pièces jointes, etc.) en
 * dehors du webroot (public/), donc jamais accessibles directement par
 * une URL — tout téléchargement doit obligatoirement passer par un
 * contrôleur qui vérifie les droits d'accès.
 */
class Storage
{
    public static function basePath(): string
    {
        return dirname(__DIR__, 2) . '/storage';
    }

    /**
     * Chemin absolu vers un fichier sous storage/, en créant le dossier
     * parent si besoin.
     */
    public static function path(string $relative): string
    {
        $relative = ltrim($relative, '/');
        $full = self::basePath() . '/' . $relative;
        $dir = dirname($full);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $full;
    }
}

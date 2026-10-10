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

    /**
     * Types de fichiers que le navigateur peut afficher directement (aperçu
     * inline) plutôt que de forcer un téléchargement.
     */
    public static function estPrevisualisable(string $typeMime): bool
    {
        return $typeMime === 'application/pdf' || str_starts_with($typeMime, 'image/');
    }

    /**
     * Attributs HTML à poser sur le lien « Aperçu » d'une pièce : si c'est une
     * image, la galerie (galerie.js) l'ajoute aux miniatures et à la visionneuse.
     */
    public static function attrsPhoto(array $piece): string
    {
        if (!str_starts_with((string) ($piece['type_mime'] ?? ''), 'image/')) {
            return '';
        }
        return ' data-galerie="1" data-nom="' . htmlspecialchars((string) ($piece['nom_original'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
    }
}

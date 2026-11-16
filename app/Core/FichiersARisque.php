<?php

namespace App\Core;

/**
 * Fichiers d'installation / de migration qui ne doivent plus rester accessibles dans public/ une fois utilisés.
 * Liste fermée (install.php, reinit_mdp.php, migrate_vNN.php) : rien d'autre ne peut être listé ni supprimé.
 */
class FichiersARisque
{
    private static function dossier(): string
    {
        return realpath(__DIR__ . '/../../public') ?: (__DIR__ . '/../../public');
    }

    public static function lister(): array
    {
        $noms = [];
        foreach (@scandir(self::dossier()) ?: [] as $f) {
            if ($f === 'install.php' || $f === 'reinit_mdp.php' || preg_match('/^migrate_v\d+[a-z]?\.php$/', $f)) {
                $noms[] = $f;
            }
        }
        natsort($noms);
        return array_values($noms);
    }

    /** Supprime les fichiers demandés s'ils figurent bien dans la liste fermée. Retourne [supprimés, échecs]. */
    public static function supprimer(array $demandes): array
    {
        $ok = $ko = [];
        $permis = self::lister();
        foreach ($demandes as $n) {
            $n = basename((string) $n);
            if (!in_array($n, $permis, true)) {
                continue;
            }
            if (@unlink(self::dossier() . '/' . $n)) {
                $ok[] = $n;
            } else {
                $ko[] = $n;
            }
        }
        return [$ok, $ko];
    }
}

<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Env;

/**
 * Mise à jour en une étape : on dépose le zip, les fichiers sont copiés (les anciens sont sauvegardés),
 * puis les migrations en attente sont lancées à la suite. Jamais touchés : .env, storage/, fichiers .htaccess, install.php.
 */
class MiseAJour
{
    /** Migrations déjà passées avant la mise en place de cet outil (jusqu'à migrate_v70 incluse). */
    public const BASE_MIGRATIONS = 70;
    private const EXTENSIONS = ['php', 'js', 'css', 'html', 'json', 'md', 'sql', 'png', 'jpg', 'jpeg', 'svg', 'ico', 'webmanifest', 'txt', 'woff', 'woff2', 'example'];
    private const DOSSIERS = ['app', 'views', 'public', 'database'];

    public static function racine(): string
    {
        return realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2);
    }

    /** Dossier web : la racine du domaine si elle contient index.php, sinon <projet>/public. */
    public static function dossierPublic(): string
    {
        $doc = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($doc && is_file($doc . '/index.php')) {
            return $doc;
        }
        return self::racine() . '/public';
    }

    private static function table(): void
    {
        $pdo = Database::connection();
        $auto = Database::driver() === 'sqlite' ? 'INTEGER PRIMARY KEY' : 'INT PRIMARY KEY';
        $pdo->exec("CREATE TABLE IF NOT EXISTS maj_migrations (numero $auto, applique_le VARCHAR(19) NOT NULL)");
        if ((int) $pdo->query('SELECT COUNT(*) FROM maj_migrations')->fetchColumn() === 0) {
            $st = $pdo->prepare('INSERT INTO maj_migrations (numero, applique_le) VALUES (?, ?)');
            $now = date('Y-m-d H:i:s');
            for ($i = 1; $i <= self::BASE_MIGRATIONS; $i++) {
                $st->execute([$i, $now]);
            }
        }
    }

    public static function appliquees(): array
    {
        self::table();
        return array_map('intval', Database::connection()->query('SELECT numero FROM maj_migrations')->fetchAll(\PDO::FETCH_COLUMN));
    }

    public static function sauvegardes(): array
    {
        $d = self::racine() . '/storage/maj';
        $liste = is_dir($d) ? array_filter(scandir($d) ?: [], fn($x) => $x !== '.' && $x !== '..') : [];
        rsort($liste);
        return array_values($liste);
    }

    /** @return array{ok:bool, lignes:array<int,string>, erreur?:string} */
    public static function installer(string $zipChemin): array
    {
        $lignes = [];
        if (!class_exists(\ZipArchive::class)) {
            return ['ok' => false, 'lignes' => [], 'erreur' => "L'extension Zip n'est pas disponible sur ce serveur : copiez le zip à la main."];
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipChemin) !== true) {
            return ['ok' => false, 'lignes' => [], 'erreur' => "Ce fichier n'est pas un zip lisible."];
        }
        // Le zip peut contenir un dossier racine unique.
        $prefixe = '';
        if ($zip->locateName('app/autoload.php') === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = (string) $zip->getNameIndex($i);
                if (preg_match('#^([^/]+)/app/autoload\.php$#', $n, $m)) { $prefixe = $m[1] . '/'; break; }
            }
        }
        if ($zip->locateName($prefixe . 'app/autoload.php') === false || $zip->locateName($prefixe . 'public/index.php') === false) {
            $zip->close();
            return ['ok' => false, 'lignes' => [], 'erreur' => "Ce zip ne ressemble pas à une mise à jour Suivora360 (app/ et public/ introuvables)."];
        }
        $token = (string) Env::get('INSTALL_TOKEN', '');
        if ($token === '' || $token === 'change-moi-avant-mise-en-ligne') {
            $zip->close();
            return ['ok' => false, 'lignes' => [], 'erreur' => "INSTALL_TOKEN n'est pas défini dans .env : nécessaire pour lancer les migrations."];
        }
        $appliquees = self::appliquees();
        $racine = self::racine();
        $public = self::dossierPublic();
        $sauv = $racine . '/storage/maj/' . date('Ymd-His');
        $nouveaux = $modifies = $inchanges = $ignores = 0;
        $migrations = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nom = (string) $zip->getNameIndex($i);
            if ($prefixe !== '') {
                if (!str_starts_with($nom, $prefixe)) { continue; }
                $nom = substr($nom, strlen($prefixe));
            }
            if ($nom === '' || str_ends_with($nom, '/')) { continue; }
            if (str_contains($nom, '..') || str_contains($nom, "\\") || str_contains($nom, "\0") || $nom[0] === '/') { $ignores++; continue; }
            $parts = explode('/', $nom);
            $ext = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
            $base = basename($nom);
            // Fichiers racine : documentation seulement.
            if (count($parts) === 1) {
                if (!in_array($ext, ['md'], true) && $base !== '.env.example') { $ignores++; continue; }
                $cible = $racine . '/' . $nom;
            } else {
                if (!in_array($parts[0], self::DOSSIERS, true) || $base[0] === '.' || !in_array($ext, self::EXTENSIONS, true)) { $ignores++; continue; }
                if ($parts[0] === 'database' && $ext !== 'sql') { $ignores++; continue; }
                if ($parts[0] === 'public' && $base === 'install.php') { $ignores++; continue; }
                if ($parts[0] === 'public') {
                    if (preg_match('/^migrate_v(\d+)\.php$/', $base, $mm)) {
                        $num = (int) $mm[1];
                        if (in_array($num, $appliquees, true)) { $ignores++; continue; }
                        $migrations[$num] = $base;
                    }
                    $cible = $public . '/' . implode('/', array_slice($parts, 1));
                } else {
                    $cible = $racine . '/' . $nom;
                }
            }
            $contenu = $zip->getFromIndex($i);
            if ($contenu === false) { $lignes[] = "Illisible dans le zip : $nom"; continue; }
            if (is_file($cible)) {
                if (md5_file($cible) === md5($contenu)) { $inchanges++; continue; }
                $copie = $sauv . '/' . $nom;
                if (!is_dir(dirname($copie))) { @mkdir(dirname($copie), 0775, true); }
                @copy($cible, $copie);
                $modifies++;
            } else {
                $nouveaux++;
            }
            if (!is_dir(dirname($cible)) && !@mkdir(dirname($cible), 0775, true)) {
                $lignes[] = "ERREUR : dossier non créé pour $nom"; continue;
            }
            $tmp = $cible . '.maj-tmp';
            if (file_put_contents($tmp, $contenu) === false || !@rename($tmp, $cible)) {
                @unlink($tmp);
                $lignes[] = "ERREUR : écriture impossible pour $nom (droits du dossier ?)";
            }
        }
        $zip->close();
        if (function_exists('opcache_reset')) { @opcache_reset(); }
        $lignes[] = "Fichiers : $modifies modifié(s), $nouveaux nouveau(x), $inchanges déjà à jour.";
        if ($modifies > 0) { $lignes[] = 'Anciennes versions gardées dans storage/maj/' . basename($sauv) . '.'; }
        self::purgerSauvegardes();

        // Migrations en attente, dans l'ordre.
        ksort($migrations);
        if (!$migrations) {
            $lignes[] = 'Aucune migration en attente.';
        }
        $okGlobal = true;
        foreach ($migrations as $num => $fichier) {
            [$ok, $msg] = self::lancerMigration($fichier, $token, $public);
            if ($ok) {
                Database::connection()->prepare('INSERT INTO maj_migrations (numero, applique_le) VALUES (?, ?)')->execute([$num, date('Y-m-d H:i:s')]);
                @unlink($public . '/' . $fichier);
                $lignes[] = "Migration $num : OK.";
            } else {
                $okGlobal = false;
                $lignes[] = "Migration $num : $msg";
                break;
            }
        }
        return ['ok' => $okGlobal, 'lignes' => $lignes];
    }

    /** Lance une migration par un appel interne à la page (même serveur) ; [succès, message]. */
    private static function lancerMigration(string $fichier, string $token, string $public): array
    {
        if (!function_exists('curl_init')) {
            return [false, "curl indisponible : ouvrez /$fichier?token=… à la main."];
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $hote = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($hote === '' || !preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $hote)) {
            return [false, "adresse du site inconnue : ouvrez /$fichier?token=… à la main."];
        }
        $ch = curl_init(($https ? 'https://' : 'http://') . $hote . '/' . $fichier);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_POSTFIELDS => http_build_query(['action' => 'migrer', 'token' => $token]),
        ]);
        $rep = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($rep === false) { return [false, "appel impossible ($err) : ouvrez /$fichier?token=… à la main."]; }
        if ($code !== 200 || !str_contains((string) $rep, 'Migration terminée')) {
            $detail = preg_match('/<li class="err">(.*?)<\/li>|<p class="err">(.*?)<\/p>/s', (string) $rep, $m) ? trim(strip_tags($m[1] ?: $m[2])) : "code $code";
            return [false, "échec ($detail). Réessayez ou ouvrez /$fichier?token=… à la main."];
        }
        if (preg_match('/<li class="err">/', (string) $rep)) {
            return [false, 'terminée avec une erreur, voir la page de la migration.'];
        }
        return [true, ''];
    }

    private static function purgerSauvegardes(): void
    {
        $d = self::racine() . '/storage/maj';
        foreach (array_slice(self::sauvegardes(), 5) as $ancien) {
            $chemin = $d . '/' . $ancien;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($chemin, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
            @rmdir($chemin);
        }
    }
}

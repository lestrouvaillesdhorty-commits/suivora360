<?php

namespace App\Services;

use App\Core\Env;
use App\Core\Telephone;
use App\Models\Client;
use App\Models\Demande;
use App\Models\Fournisseur;

/**
 * [13/10] Recherche d'informations sur un fournisseur pour préremplir sa fiche.
 *  - si un site web est connu : lecture des pages publiques du site (accueil, contact, mentions légales)
 *    puis extraction par l'IA ;
 *  - sinon : l'IA cherche sur le web (outil de recherche web de l'API Anthropic, à activer dans la Console).
 * Rien n'est enregistré ici : la personne relit et corrige le formulaire. Seules les informations
 * publiées sont reprises, jamais déduites.
 */
class FournisseurRecherche
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-sonnet-4-5-20250929';
    private const MAX_TEXTE_PAGE = 9000;

    /**
     * @return array{champs: array<string,mixed>, sources: string[], mode: string, remarque: string}
     * @throws \RuntimeException
     */
    public static function rechercher(string $nom, string $site = '', string $pays = '', string $cible = 'fournisseur'): array
    {
        $apiKey = Env::get('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            throw new \RuntimeException("La clé API IA n'est pas configurée (ANTHROPIC_API_KEY manquante dans .env).");
        }
        $nom = trim($nom);
        $site = trim($site);
        if ($nom === '' && $site === '') {
            throw new \RuntimeException("Saisissez d'abord la raison sociale ou le site internet du fournisseur.");
        }

        $pages = [];
        if ($site !== '') {
            $pages = self::lirePagesSite($site);
        }
        $mode = $pages ? 'site' : 'web';

        $consigne = $cible === 'client' ? self::consigneClient($nom, $site, $pays) : self::consigne($nom, $site, $pays);
        if ($pages) {
            $texte = '';
            foreach ($pages as $url => $contenu) {
                $texte .= "\n\n=== Page : $url ===\n" . $contenu;
            }
            $contenuMsg = [['type' => 'text', 'text' => "Contenu des pages publiques du site du fournisseur :" . $texte . "\n\n" . $consigne]];
            $payload = ['model' => self::MODEL, 'max_tokens' => 2048, 'messages' => [['role' => 'user', 'content' => $contenuMsg]]];
        } else {
            $payload = [
                'model' => self::MODEL,
                'max_tokens' => 4096,
                'tools' => [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 4]],
                'messages' => [['role' => 'user', 'content' => $consigne]],
            ];
        }

        $reponse = self::appeler($payload, $apiKey, $mode === 'web' ? 120 : 60);
        $sources = array_keys($pages);
        $texteFinal = '';
        foreach ((array) ($reponse['content'] ?? []) as $bloc) {
            if (($bloc['type'] ?? '') === 'text') {
                $texteFinal .= ($bloc['text'] ?? '');
                foreach ((array) ($bloc['citations'] ?? []) as $c) {
                    if (!empty($c['url']) && preg_match('#^https?://#i', $c['url'])) {
                        $sources[] = $c['url'];
                    }
                }
            }
        }
        $json = self::extraireJson($texteFinal);
        if ($json === null) {
            throw new \RuntimeException("Réponse IA illisible : réessayez, ou renseignez le site internet du fournisseur pour une lecture directe.");
        }
        foreach ((array) ($json['sources'] ?? []) as $u) {
            if (is_string($u) && preg_match('#^https?://#i', $u)) {
                $sources[] = $u;
            }
        }
        return [
            'champs' => self::nettoyer($json, $cible),
            'sources' => array_slice(array_values(array_unique($sources)), 0, 6),
            'mode' => $mode,
            'remarque' => isset($json['remarque']) && is_string($json['remarque']) ? mb_substr(trim($json['remarque']), 0, 300) : '',
        ];
    }

    private static function consigne(string $nom, string $site, string $pays): string
    {
        $activites = implode(' | ', Demande::ACTIVITES);
        $types = implode(', ', array_keys(Fournisseur::TYPES));
        $cond = implode(', ', array_keys(Client::CONDITIONS_PAIEMENT));
        $ident = 'Société : « ' . ($nom !== '' ? $nom : '(non précisée)') . ' »'
            . ($site !== '' ? ' — site : ' . $site : '')
            . ($pays !== '' ? ' — pays indiqué : ' . $pays : '') . '.';
        return <<<TXT
Tu aides une société de sourcing / import-export à créer la fiche d'un fournisseur. $ident
Retrouve ses informations PUBLIQUES (site officiel, pages contact et mentions légales, registre des sociétés, annuaires professionnels sérieux) et réponds par UN objet JSON strict, sans texte autour, sans balises de code.

Clés possibles (OMETS toute clé dont tu n'es pas certain) :
- "nom": raison sociale officielle ; "nom_commercial"
- "types": tableau parmi [$types]
- "specialites": texte court (produits / services, séparés par des virgules)
- "activites": tableau parmi [$activites]
- "pays", "ville", "adresse", "code_postal", "site_web"
- "siret": numéro d'immatriculation (SIRET, RCCM, NIU… tel que publié) ; "tva": identifiant fiscal / TVA
- "email" et "telephone" (au format international +XX…) : coordonnées générales de la société
- "contact_prenom", "contact_nom", "fonction_contact" : UNIQUEMENT si une personne de contact est nommée publiquement
- "devises_proposees": tableau parmi [XAF, XOF, EUR, USD, GBP, CNY] si publié
- "conditions_paiement": un code parmi [$cond] si publié ; "quantite_min": texte (MOQ) si publié
- "pays_desservis": texte ; "marques": texte
- "description": une à deux phrases décrivant l'activité
- "sources": tableau des URL réellement consultées
- "remarque": une phrase si plusieurs sociétés pourraient correspondre, si les informations sont anciennes ou incertaines

Règles strictes : n'invente RIEN et ne complète jamais une information manquante (surtout numéros d'immatriculation, TVA, e-mails, téléphones, noms de personnes). S'il y a plusieurs sociétés possibles avec ce nom, ne renvoie que {"remarque": "…"} en expliquant l'ambiguïté. Si tu ne trouves rien de fiable, réponds {}.
TXT;
    }

    private static function consigneClient(string $nom, string $site, string $pays): string
    {
        $types = implode(', ', array_keys(Client::TYPES));
        $ident = 'Société : « ' . ($nom !== '' ? $nom : '(non précisée)') . ' »'
            . ($site !== '' ? ' — site : ' . $site : '')
            . ($pays !== '' ? ' — pays indiqué : ' . $pays : '') . '.';
        return <<<TXT
Tu aides une société de sourcing / import-export à créer la fiche d'un CLIENT (une entreprise à qui elle vend). $ident
Retrouve ses informations PUBLIQUES (site officiel, pages contact et mentions légales, registre des sociétés, annuaires professionnels sérieux) et réponds par UN objet JSON strict, sans texte autour, sans balises de code.

Clés possibles (OMETS toute clé dont tu n'es pas certain) :
- "nom": raison sociale ou enseigne officielle
- "type": un code parmi [$types]
- "secteur": secteur d'activité (texte court)
- "pays", "ville", "adresse", "code_postal"
- "siret": numéro d'immatriculation (SIRET, RCCM, NIU… tel que publié) ; "tva": identifiant fiscal / TVA
- "email" et "telephone" (format international +XX…) : coordonnées générales de la société
- "contact_prenom", "contact_nom", "fonction_contact" : UNIQUEMENT si une personne de contact est nommée publiquement
- "description": une à deux phrases décrivant l'activité
- "sources": tableau des URL réellement consultées
- "remarque": une phrase si plusieurs sociétés pourraient correspondre, ou si les informations sont anciennes ou incertaines

Règles strictes : n'invente RIEN et ne complète jamais une information manquante (surtout numéros d'immatriculation, TVA, e-mails, téléphones, noms de personnes). S'il y a plusieurs sociétés possibles avec ce nom, ne renvoie que {"remarque": "…"} en expliquant l'ambiguïté. Si tu ne trouves rien de fiable, réponds {}.
TXT;
    }

    private static function appeler(array $payload, string $apiKey, int $timeout): array
    {
        $ch = curl_init((string) Env::get('ANTHROPIC_API_URL', self::API_URL));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01'],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $reponse = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($reponse === false || $reponse === '') {
            throw new \RuntimeException('Impossible de contacter le service IA' . ($err ? " : $err" : '.'));
        }
        $data = json_decode($reponse, true);
        if ($http !== 200) {
            $detail = is_array($data) ? (string) ($data['error']['message'] ?? '') : '';
            if (isset($payload['tools']) && $http === 400 && stripos($detail, 'web_search') !== false) {
                throw new \RuntimeException("La recherche web n'est pas activée sur votre compte Anthropic (Console → Paramètres → recherche web). En attendant, renseignez le site internet du fournisseur : l'IA lira ses pages.");
            }
            throw new \RuntimeException("Le service IA a répondu une erreur (HTTP $http).");
        }
        return is_array($data) ? $data : [];
    }

    private static function extraireJson(string $texte): ?array
    {
        $texte = trim(preg_replace(['/^```(json)?/im', '/```$/m'], '', $texte));
        $debut = strpos($texte, '{');
        $fin = strrpos($texte, '}');
        if ($debut === false || $fin === false || $fin < $debut) {
            return null;
        }
        $o = json_decode(substr($texte, $debut, $fin - $debut + 1), true);
        return is_array($o) ? $o : null;
    }

    /** Ne garde que des valeurs sûres, aux formats attendus par le formulaire. */
    private static function nettoyer(array $o, string $cible = 'fournisseur'): array
    {
        $res = [];
        foreach (['nom', 'nom_commercial', 'specialites', 'pays', 'ville', 'adresse', 'code_postal', 'siret', 'tva', 'contact_prenom', 'contact_nom', 'fonction_contact', 'quantite_min', 'pays_desservis', 'marques', 'secteur', 'adresse_livraison', 'description'] as $k) {
            if (isset($o[$k]) && is_string($o[$k]) && trim($o[$k]) !== '') {
                $res[$k] = mb_substr(trim($o[$k]), 0, 300);
            }
        }
        if (!empty($o['site_web']) && is_string($o['site_web']) && preg_match('#^(https?://)?[\w.-]+\.[a-z]{2,}#i', $o['site_web'])) {
            $res['site_web'] = mb_substr(trim($o['site_web']), 0, 200);
        }
        if (!empty($o['email']) && is_string($o['email']) && filter_var(trim($o['email']), FILTER_VALIDATE_EMAIL)) {
            $res['email'] = trim($o['email']);
        }
        if (!empty($o['telephone']) && is_string($o['telephone'])) {
            [$ind, $num] = Telephone::decouper($o['telephone']);
            if ($num !== '') {
                $res['tel_indicatif'] = $ind;
                $res['tel_numero'] = $num;
            }
        }
        if ($cible === 'client') {
            unset($res['site_web'], $res['quantite_min'], $res['pays_desservis'], $res['marques']);
            if (isset($o['type']) && array_key_exists($o['type'], Client::TYPES)) {
                $res['type'] = $o['type'];
            }
            return $res;
        }
        $listes = [
            'types' => array_keys(Fournisseur::TYPES),
            'activites' => Demande::ACTIVITES,
            'devises_proposees' => Fournisseur::DEVISES,
        ];
        foreach ($listes as $k => $autorise) {
            $vals = array_values(array_intersect((array) ($o[$k] ?? []), $autorise));
            if ($vals) {
                $res[$k] = $vals;
            }
        }
        if (isset($o['conditions_paiement']) && array_key_exists($o['conditions_paiement'], Client::CONDITIONS_PAIEMENT)) {
            $res['conditions_paiement'] = $o['conditions_paiement'];
        }
        return $res;
    }

    // ------------------------------------------------------------------ lecture du site

    /** @return array<string,string> url => texte (accueil + jusqu'à 2 pages contact / mentions légales) */
    private static function lirePagesSite(string $site): array
    {
        if (!preg_match('#^https?://#i', $site)) {
            $site = 'https://' . $site;
        }
        $accueil = self::telecharger($site);
        if ($accueil === null) {
            return [];
        }
        $pages = [$site => self::enTexte($accueil)];
        $hote = strtolower((string) parse_url($site, PHP_URL_HOST));
        $candidats = [];
        if (preg_match_all('#<a\s[^>]*href=["\']([^"\'\#]+)["\']#i', $accueil, $m)) {
            foreach ($m[1] as $href) {
                if (!preg_match('#contact|about|a-propos|apropos|qui-sommes|societe|company|mentions|legal|nous-connaitre#i', $href)) {
                    continue;
                }
                $abs = self::absolutiser($href, $site);
                if ($abs && strtolower((string) parse_url($abs, PHP_URL_HOST)) === $hote && !isset($pages[$abs]) && !in_array($abs, $candidats, true)) {
                    $candidats[] = $abs;
                }
            }
        }
        foreach (array_slice($candidats, 0, 2) as $url) {
            $html = self::telecharger($url);
            if ($html !== null) {
                $pages[$url] = self::enTexte($html);
            }
        }
        return $pages;
    }

    private static function absolutiser(string $href, string $base): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if (preg_match('#^(mailto|tel|javascript):#i', $href)) {
            return null;
        }
        $p = parse_url($base);
        if (!$p || empty($p['host'])) {
            return null;
        }
        $racine = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($href, '//')) {
            return $p['scheme'] . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $racine . $href;
        }
        return rtrim($racine . (isset($p['path']) ? preg_replace('#/[^/]*$#', '/', $p['path']) : '/'), '/') . '/' . $href;
    }

    /** Téléchargement prudent : http(s) seulement, adresses publiques uniquement, 1,5 Mo max. */
    private static function telecharger(string $url): ?string
    {
        $p = parse_url($url);
        if (!$p || empty($p['host']) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }
        $ip = gethostbyname($p['host']);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) && !Env::get('FOURNISSEUR_RECHERCHE_ALLOW_LOCAL')) {
            return null;
        }
        $corps = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false, // pas de redirection suivie aveuglément (SSRF) : géré ci-dessous
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Suivora360/1.0)',
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$corps) {
                $corps .= $data;
                return strlen($corps) > 1500000 ? 0 : strlen($data);
            },
        ]);
        curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redir = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);
        static $profondeur = 0;
        if ($http >= 300 && $http < 400 && $redir && $profondeur < 3) {
            $profondeur++;
            $suite = self::telecharger($redir);
            $profondeur--;
            return $suite;
        }
        return ($http === 200 && $corps !== '') ? $corps : null;
    }

    private static function enTexte(string $html): string
    {
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
        }
        $html = preg_replace('#<(script|style|noscript|svg)\b.*?</\1>#is', ' ', $html);
        $html = preg_replace('#<(br|/p|/div|/li|/tr|/h[1-6])\b[^>]*>#i', "\n", $html);
        $texte = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texte = preg_replace("/[ \t]+/", ' ', $texte);
        $texte = trim(preg_replace("/\n\s*\n+/", "\n", $texte));
        return mb_substr($texte, 0, self::MAX_TEXTE_PAGE);
    }
}

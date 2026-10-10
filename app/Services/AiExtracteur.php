<?php

namespace App\Services;

use App\Core\Env;

/**
 * Appelle l'API Claude (Anthropic) pour extraire une liste d'articles
 * structurée à partir du message brut d'une demande (texte libre reçu
 * par email/WhatsApp). Reste volontairement simple : un seul appel,
 * aucune conversation ni contexte conservé — la personne valide et
 * corrige toujours le résultat avant tout enregistrement (voir
 * DemandeController::confirmerExtractionIa).
 */
class AiExtracteur
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-sonnet-4-5-20250929';

    /**
     * [ajouté 05/10, report de la maquette] Vérification légère, sans appel
     * réseau, pour afficher un état de repli discret (bouton "Extraire avec
     * l'IA" remplacé par un message) plutôt que de laisser l'utilisateur
     * cliquer dans le vide puis découvrir l'échec après coup — le vrai appel
     * (extraireArticles()) continue de son côté à vérifier la clé lui-même,
     * cette méthode n'est qu'un raccourci d'affichage.
     */
    public static function estDisponible(): bool
    {
        return (bool) Env::get('ANTHROPIC_API_KEY');
    }

    /**
     * @return array<int, array{designation:string, quantite:?float, unite:string, conditionnement:string, reference:string, marque:string, ambigu:bool, note_ambiguite:string}>
     * @throws \RuntimeException si la clé API est absente ou si l'appel échoue
     */
    public static function extraireArticles(string $messageBrut, array $fichiers = []): array
    {
        $apiKey = Env::get('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            throw new \RuntimeException("La clé API IA n'est pas configurée (ANTHROPIC_API_KEY manquante dans .env).");
        }
        if (trim($messageBrut) === '' && empty($fichiers)) {
            return [];
        }

        // [complété 04/10, refonte du module Demandes] Sépare désormais
        // aussi référence/marque/conditionnement (au lieu de désignation/
        // quantité/unité seulement), et signale les informations ambiguës
        // plutôt que de les deviner — ex. "5 palettes de riz parfumé de
        // 50 kg" : la quantité de riz (combien de kg au total) n'est PAS
        // déductible du nombre de palettes sans connaître leur gabarit, donc
        // le poids ne doit jamais être recalculé/inventé ici.
        $prompt = <<<PROMPT
Voici le message brut envoyé par un client à une société de sourcing/import-export, accompagné éventuellement de documents joints (bon de commande, liste de produits, tableur, photo, PDF). Lis le message ET les documents.
Extrais la liste des articles/produits demandés sous forme d'un tableau JSON strict, sans aucun texte autour, sans balises de code.

Chaque élément : {"designation": string, "quantite": nombre ou null, "unite": string ou "", "conditionnement": string ou "", "reference": string ou "", "marque": string ou "", "ambigu": booléen, "note_ambiguite": string ou ""}.

Règles :
- "designation" : le produit lui-même (ex. "Biscuits"), jamais la quantité ni le conditionnement.
- "unite" : l'unité de comptage (ex. "Carton", "Palette", "Sac"), jamais un poids/volume total.
- "conditionnement" : la précision de conditionnement si elle est donnée (ex. "sacs de 50 kg", "carton de 12") — ne jamais recalculer un total à partir du nombre d'unités, juste rapporter ce que le client a écrit.
- "reference" et "marque" : uniquement si explicitement mentionnées.
- "ambigu" = true dès qu'une information nécessaire ne peut pas être déduite sans supposer un fait non donné (ex. poids total inconnu sans gabarit de palette) ; dans ce cas, laisse le champ concerné à null/"" plutôt que de l'inventer, et explique brièvement dans "note_ambiguite" ce qui reste à clarifier.
- Si aucun article n'est identifiable, réponds [].
- Ne rapporte que ce qui est écrit dans le message ou les documents : ne complète jamais une information manquante (prix, quantité, référence).

Exemple — message "20 cartons de biscuits réf BX100 marque Fatima" :
[{"designation": "Biscuits", "quantite": 20, "unite": "Carton", "conditionnement": "", "reference": "BX100", "marque": "Fatima", "ambigu": false, "note_ambiguite": ""}]

Exemple — message "5 palettes de riz parfumé de 50 kg" (poids total non déductible du nombre de palettes) :
[{"designation": "Riz parfumé", "quantite": 5, "unite": "Palette", "conditionnement": "sacs de 50 kg", "reference": "", "marque": "", "ambigu": true, "note_ambiguite": "Poids total non précisé (dépend du nombre de sacs par palette)."}]

Message :
"""
{$messageBrut}
"""
PROMPT;

        // Contenu envoyé à l'IA : documents/images d'abord, puis texte des tableurs/Word, puis la consigne.
        $contenu = [];
        foreach ($fichiers as $f) {
            if ($f['type'] === 'image') {
                $contenu[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $f['mime'], 'data' => $f['base64']]];
            } elseif ($f['type'] === 'pdf') {
                $contenu[] = ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $f['base64']]];
            } else {
                $contenu[] = ['type' => 'text', 'text' => "Contenu du document joint « " . $f['nom'] . " » :\n" . $f['texte']];
            }
        }
        $contenu[] = ['type' => 'text', 'text' => $prompt];

        $payload = json_encode([
            'model' => self::MODEL,
            'max_tokens' => 4096,
            'messages' => [
                ['role' => 'user', 'content' => $contenu],
            ],
        ]);

        $ch = curl_init((string) Env::get('ANTHROPIC_API_URL', self::API_URL));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => empty($fichiers) ? 30 : 90,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => $payload,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erreurCurl = curl_error($ch);
        curl_close($ch);

        if ($response === false || $response === '') {
            throw new \RuntimeException("Impossible de contacter le service IA" . ($erreurCurl ? " : $erreurCurl" : '.'));
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException("Le service IA a répondu une erreur (HTTP $httpCode).");
        }

        $data = json_decode($response, true);
        $texte = $data['content'][0]['text'] ?? '';
        // Le modèle peut entourer le JSON de ```json ... ``` malgré la consigne : on nettoie avant de parser.
        $texte = trim($texte);
        $texte = preg_replace('/^```(json)?/i', '', $texte);
        $texte = preg_replace('/```$/', '', $texte);
        $texte = trim($texte);

        $articles = json_decode($texte, true);
        if (!is_array($articles)) {
            throw new \RuntimeException('Réponse IA illisible, réessayez ou saisissez les articles manuellement.');
        }

        $resultat = [];
        foreach ($articles as $a) {
            if (!is_array($a) || empty($a['designation'])) {
                continue;
            }
            $resultat[] = [
                'designation' => (string) $a['designation'],
                'quantite' => isset($a['quantite']) && is_numeric($a['quantite']) ? (float) $a['quantite'] : null,
                'unite' => isset($a['unite']) ? (string) $a['unite'] : '',
                'conditionnement' => isset($a['conditionnement']) ? (string) $a['conditionnement'] : '',
                'reference' => isset($a['reference']) ? (string) $a['reference'] : '',
                'marque' => isset($a['marque']) ? (string) $a['marque'] : '',
                'ambigu' => !empty($a['ambigu']),
                'note_ambiguite' => isset($a['note_ambiguite']) ? (string) $a['note_ambiguite'] : '',
            ];
        }
        return $resultat;
    }

    /**
     * [10/10] Lit une offre fournisseur (PDF, image, Excel, Word) et en sort les champs du
     * formulaire d'offre. Rien n'est enregistré ici : la personne relit et corrige le formulaire.
     *
     * @param array $fichiers fichiers préparés par preparerFichier()
     * @return array<string,mixed> champs reconnus uniquement (les absents sont omis)
     * @throws \RuntimeException
     */
    public static function extraireOffre(array $fichiers): array
    {
        $apiKey = Env::get('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            throw new \RuntimeException("La clé API IA n'est pas configurée (ANTHROPIC_API_KEY manquante dans .env).");
        }
        if (empty($fichiers)) {
            return [];
        }
        $prompt = <<<PROMPT
Le document joint est une offre / un devis / une facture proforma envoyé par un fournisseur à une société de sourcing/import-export.
Extrais ses informations sous forme d'UN objet JSON strict, sans aucun texte autour, sans balises de code.

Clés possibles (omets toute clé dont l'information n'est pas écrite dans le document) :
- "fournisseur": nom du vendeur / de la société qui émet l'offre
- "montant_total": nombre (total de l'offre, sans symbole ni séparateur de milliers)
- "devise": code parmi EUR, USD, XOF, XAF, GBP, CNY
- "incoterm": code parmi EXW, FCA, FAS, FOB, CFR, CIF, CPT, CIP, DAP, DPU, DDP
- "delai_livraison": texte (ex. "4 à 6 semaines")
- "validite_offre": date AAAA-MM-JJ
- "pays_origine", "lieu_depart": texte
- "quantite_min": MOQ, texte
- "disponibilite": texte
- "conditions_paiement": un code parmi comptant, acompte_solde, 30j, 45j, 60j, autre
- "garantie": texte
- "mode_transport": texte
- "poids_kg", "volume_m3": nombres ; "nombre_colis": entier
- "transport_montant", "assurance_montant", "emballage_montant", "douane_montant", "dedouanement_montant", "autres_frais_montant": nombres
- "notes": conditions particulières utiles, une à deux phrases
- "articles": tableau d'objets {"designation": texte, "quantite": nombre ou null, "unite": texte ou "", "prix_unitaire": nombre ou null}

Règles : ne rapporte que ce qui est écrit dans le document, n'invente et ne calcule jamais une valeur manquante. Si "montant_total" n'est pas écrit mais que les lignes le permettent, laisse-le de côté. Si le document n'est pas une offre, réponds {}.
PROMPT;

        $contenu = [];
        foreach ($fichiers as $f) {
            if ($f['type'] === 'image') {
                $contenu[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $f['mime'], 'data' => $f['base64']]];
            } elseif ($f['type'] === 'pdf') {
                $contenu[] = ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $f['base64']]];
            } else {
                $contenu[] = ['type' => 'text', 'text' => "Contenu du document « " . $f['nom'] . " » :\n" . $f['texte']];
            }
        }
        $contenu[] = ['type' => 'text', 'text' => $prompt];

        $ch = curl_init((string) Env::get('ANTHROPIC_API_URL', self::API_URL));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01'],
            CURLOPT_POSTFIELDS => json_encode(['model' => self::MODEL, 'max_tokens' => 4096, 'messages' => [['role' => 'user', 'content' => $contenu]]]),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erreurCurl = curl_error($ch);
        curl_close($ch);
        if ($response === false || $response === '') {
            throw new \RuntimeException('Impossible de contacter le service IA' . ($erreurCurl ? " : $erreurCurl" : '.'));
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException("Le service IA a répondu une erreur (HTTP $httpCode).");
        }
        $texte = trim((string) (json_decode($response, true)['content'][0]['text'] ?? ''));
        $texte = trim(preg_replace(['/^```(json)?/i', '/```$/'], '', $texte));
        $o = json_decode($texte, true);
        if (!is_array($o)) {
            throw new \RuntimeException("Réponse IA illisible, réessayez ou saisissez l'offre manuellement.");
        }

        // Nettoyage : on ne garde que des valeurs sûres, aux formats attendus par le formulaire.
        $res = [];
        foreach (['fournisseur', 'delai_livraison', 'pays_origine', 'lieu_depart', 'quantite_min', 'disponibilite', 'garantie', 'mode_transport', 'notes'] as $k) {
            if (isset($o[$k]) && is_string($o[$k]) && trim($o[$k]) !== '') {
                $res[$k] = mb_substr(trim($o[$k]), 0, 500);
            }
        }
        foreach (['montant_total', 'poids_kg', 'volume_m3', 'nombre_colis', 'transport_montant', 'assurance_montant', 'emballage_montant', 'douane_montant', 'dedouanement_montant', 'autres_frais_montant'] as $k) {
            if (isset($o[$k]) && is_numeric($o[$k])) {
                $res[$k] = $k === 'volume_m3' ? round((float) $o[$k], 3) : round((float) $o[$k], 2);
            }
        }
        if (isset($o['devise']) && in_array($o['devise'], ['EUR', 'USD', 'XOF', 'XAF', 'GBP', 'CNY'], true)) {
            $res['devise'] = $o['devise'];
        }
        if (isset($o['incoterm']) && array_key_exists($o['incoterm'], \App\Models\Demande::INCOTERMS)) {
            $res['incoterm_negocie'] = $o['incoterm'];
        }
        if (isset($o['conditions_paiement']) && array_key_exists($o['conditions_paiement'], \App\Models\Client::CONDITIONS_PAIEMENT)) {
            $res['conditions_paiement'] = $o['conditions_paiement'];
        }
        if (isset($o['validite_offre']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $o['validite_offre'])) {
            $res['validite_offre'] = $o['validite_offre'];
        }
        $res['articles'] = [];
        foreach ((array) ($o['articles'] ?? []) as $a) {
            if (!is_array($a) || empty($a['designation'])) {
                continue;
            }
            $res['articles'][] = [
                'designation' => mb_substr((string) $a['designation'], 0, 255),
                'quantite' => isset($a['quantite']) && is_numeric($a['quantite']) ? round((float) $a['quantite'], 2) : null,
                'unite' => isset($a['unite']) ? (string) $a['unite'] : '',
                'prix_unitaire' => isset($a['prix_unitaire']) && is_numeric($a['prix_unitaire']) ? round((float) $a['prix_unitaire'], 2) : null,
            ];
            if (count($res['articles']) >= 50) {
                break;
            }
        }
        return $res;
    }

    /** Formats lisibles par l'IA : extension => type. */
    public const FORMATS_LISIBLES = [
        'pdf' => 'pdf', 'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image',
        'xlsx' => 'texte', 'docx' => 'texte', 'txt' => 'texte', 'csv' => 'texte',
    ];
    private const TAILLE_MAX_FICHIER = 5 * 1024 * 1024;

    public static function estLisible(string $nomOriginal): bool
    {
        return isset(self::FORMATS_LISIBLES[strtolower(pathinfo($nomOriginal, PATHINFO_EXTENSION))]);
    }

    /**
     * Prépare un fichier joint pour l'envoi à l'IA.
     * @return array{type:string,nom:string,mime?:string,base64?:string,texte?:string}
     * @throws \RuntimeException si le fichier n'est pas lisible (message affichable à l'utilisateur)
     */
    public static function preparerFichier(string $chemin, string $nomOriginal): array
    {
        $ext = strtolower(pathinfo($nomOriginal, PATHINFO_EXTENSION));
        $type = self::FORMATS_LISIBLES[$ext] ?? null;
        if ($type === null) {
            throw new \RuntimeException("« $nomOriginal » : format non lisible par l'IA (formats acceptés : PDF, images, Excel .xlsx, Word .docx, texte).");
        }
        if (!is_file($chemin)) {
            throw new \RuntimeException("« $nomOriginal » : fichier introuvable sur le serveur.");
        }
        if (filesize($chemin) > self::TAILLE_MAX_FICHIER) {
            throw new \RuntimeException("« $nomOriginal » : fichier trop volumineux pour l'IA (5 Mo maximum).");
        }
        if ($type === 'pdf') {
            return ['type' => 'pdf', 'nom' => $nomOriginal, 'base64' => base64_encode((string) file_get_contents($chemin))];
        }
        if ($type === 'image') {
            $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
            return ['type' => 'image', 'nom' => $nomOriginal, 'mime' => $mimes[$ext], 'base64' => base64_encode((string) file_get_contents($chemin))];
        }
        $texte = match ($ext) {
            'xlsx' => self::texteXlsx($chemin),
            'docx' => self::texteDocx($chemin),
            default => (string) file_get_contents($chemin),
        };
        $texte = trim(mb_substr($texte, 0, 60000));
        if ($texte === '') {
            throw new \RuntimeException("« $nomOriginal » : aucun texte lisible dans ce fichier.");
        }
        return ['type' => 'texte', 'nom' => $nomOriginal, 'texte' => $texte];
    }

    /** Texte d'un classeur .xlsx : une ligne par ligne du tableur, cellules séparées par des tabulations. */
    public static function texteXlsx(string $chemin): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException("Lecture Excel indisponible sur ce serveur (extension zip absente).");
        }
        $zip = new \ZipArchive();
        if ($zip->open($chemin) !== true) {
            throw new \RuntimeException('Fichier Excel illisible.');
        }
        $partages = [];
        $xmlPartages = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlPartages !== false) {
            $sx = @simplexml_load_string($xmlPartages);
            if ($sx) {
                foreach ($sx->si as $si) {
                    $t = '';
                    if (isset($si->t)) { $t = (string) $si->t; }
                    else { foreach ($si->r as $r) { $t .= (string) $r->t; } }
                    $partages[] = $t;
                }
            }
        }
        $sortie = [];
        for ($i = 1; $i <= 5; $i++) {
            $xml = $zip->getFromName("xl/worksheets/sheet$i.xml");
            if ($xml === false) { continue; }
            $sx = @simplexml_load_string($xml);
            if (!$sx || !isset($sx->sheetData)) { continue; }
            $sortie[] = "--- Feuille $i ---";
            $n = 0;
            foreach ($sx->sheetData->row as $row) {
                if (++$n > 2000) { break; }
                $cells = [];
                foreach ($row->c as $c) {
                    $type = (string) $c['t'];
                    $val = isset($c->v) ? (string) $c->v : (isset($c->is->t) ? (string) $c->is->t : '');
                    if ($type === 's' && $val !== '') { $val = $partages[(int) $val] ?? ''; }
                    $cells[] = $val;
                }
                $ligne = rtrim(implode("\t", $cells));
                if ($ligne !== '') { $sortie[] = $ligne; }
            }
        }
        $zip->close();
        return implode("\n", $sortie);
    }

    /** Texte d'un document .docx (paragraphes). */
    public static function texteDocx(string $chemin): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException("Lecture Word indisponible sur ce serveur (extension zip absente).");
        }
        $zip = new \ZipArchive();
        if ($zip->open($chemin) !== true) {
            throw new \RuntimeException('Fichier Word illisible.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) { return ''; }
        $xml = preg_replace('#</w:p>#', "\n", $xml);
        $xml = preg_replace('#<w:tab/>#', "\t", $xml);
        return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }
}

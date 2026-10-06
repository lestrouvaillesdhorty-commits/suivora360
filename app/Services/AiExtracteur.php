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
    public static function extraireArticles(string $messageBrut): array
    {
        $apiKey = Env::get('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            throw new \RuntimeException("La clé API IA n'est pas configurée (ANTHROPIC_API_KEY manquante dans .env).");
        }
        if (trim($messageBrut) === '') {
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
Voici le message brut envoyé par un client à une société de sourcing/import-export.
Extrais la liste des articles/produits demandés sous forme d'un tableau JSON strict, sans aucun texte autour, sans balises de code.

Chaque élément : {"designation": string, "quantite": nombre ou null, "unite": string ou "", "conditionnement": string ou "", "reference": string ou "", "marque": string ou "", "ambigu": booléen, "note_ambiguite": string ou ""}.

Règles :
- "designation" : le produit lui-même (ex. "Biscuits"), jamais la quantité ni le conditionnement.
- "unite" : l'unité de comptage (ex. "Carton", "Palette", "Sac"), jamais un poids/volume total.
- "conditionnement" : la précision de conditionnement si elle est donnée (ex. "sacs de 50 kg", "carton de 12") — ne jamais recalculer un total à partir du nombre d'unités, juste rapporter ce que le client a écrit.
- "reference" et "marque" : uniquement si explicitement mentionnées.
- "ambigu" = true dès qu'une information nécessaire ne peut pas être déduite sans supposer un fait non donné (ex. poids total inconnu sans gabarit de palette) ; dans ce cas, laisse le champ concerné à null/"" plutôt que de l'inventer, et explique brièvement dans "note_ambiguite" ce qui reste à clarifier.
- Si aucun article n'est identifiable, réponds [].

Exemple — message "20 cartons de biscuits réf BX100 marque Fatima" :
[{"designation": "Biscuits", "quantite": 20, "unite": "Carton", "conditionnement": "", "reference": "BX100", "marque": "Fatima", "ambigu": false, "note_ambiguite": ""}]

Exemple — message "5 palettes de riz parfumé de 50 kg" (poids total non déductible du nombre de palettes) :
[{"designation": "Riz parfumé", "quantite": 5, "unite": "Palette", "conditionnement": "sacs de 50 kg", "reference": "", "marque": "", "ambigu": true, "note_ambiguite": "Poids total non précisé (dépend du nombre de sacs par palette)."}]

Message :
"""
{$messageBrut}
"""
PROMPT;

        $payload = json_encode([
            'model' => self::MODEL,
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
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
}

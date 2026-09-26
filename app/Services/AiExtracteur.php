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
     * @return array<int, array{designation:string, quantite:?float, unite:string}>
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

        $prompt = <<<PROMPT
Voici le message brut envoyé par un client à une société de sourcing/import-export.
Extrais la liste des articles/produits demandés sous forme d'un tableau JSON strict, sans aucun texte autour, sans balises de code.
Chaque élément : {"designation": string, "quantite": nombre ou null, "unite": string ou ""}.
Si aucun article n'est identifiable, réponds [].

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
            ];
        }
        return $resultat;
    }
}

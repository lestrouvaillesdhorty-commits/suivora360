<?php

namespace App\Services;

use App\Core\Env;

/**
 * Envoi d'emails "best effort" via la fonction native PHP mail() — aucune
 * librairie externe (PHPMailer, SMTP...) n'est disponible sur cet
 * hébergement mutualisé. Utilisé pour les notifications importantes
 * (ex : assignation d'un collaborateur sur un dossier).
 *
 * Volontairement non bloquant : un échec d'envoi (mail() renvoie false, ou
 * lève une erreur) ne doit jamais interrompre le flux applicatif qui a
 * déclenché la notification — voir chaque appelant, qui ignore la valeur
 * de retour ou l'entoure d'un try/catch.
 *
 * Limite connue à assumer honnêtement : sans SPF/DKIM/service d'envoi
 * dédié, la délivrabilité de mail() sur un hébergement mutualisé n'est pas
 * garantie (risque de classement en spam, voire de rejet par certains
 * fournisseurs). Si la fiabilité devient un problème, la prochaine étape
 * serait un vrai service transactionnel (Brevo, Mailgun...), hors périmètre
 * actuel faute de librairie disponible.
 */
class Mailer
{
    /**
     * @return bool true si mail() a accepté le message pour envoi (ne garantit pas la remise)
     */
    public static function envoyer(string $destinataire, string $sujet, string $corpsTexte, ?string $repondreA = null, ?array $pieceJointe = null): bool
    {
        if (trim($destinataire) === '' || !filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $fromAddress = Env::get('MAIL_FROM_ADDRESS', '');
        $fromName = Env::get('MAIL_FROM_NAME', Env::get('APP_NAME', 'Suivora360'));

        if ($fromAddress === '' || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            // Pas d'expéditeur configuré : on n'essaie même pas, mail() serait
            // de toute façon rejeté ou marqué comme spam par la plupart des
            // fournisseurs sans From: valide.
            return false;
        }

        $headers = [];
        $headers[] = 'From: ' . self::encodeHeaderValue($fromName) . ' <' . $fromAddress . '>';
        $headers[] = 'Reply-To: ' . (($repondreA && filter_var($repondreA, FILTER_VALIDATE_EMAIL)) ? $repondreA : $fromAddress);
        $headers[] = 'MIME-Version: 1.0';
        if ($pieceJointe && !empty($pieceJointe['contenu'])) {
            // Message multipart : texte + une pièce jointe (['nom', 'contenu', 'type']).
            $frontiere = 'suivora_' . bin2hex(random_bytes(8));
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $frontiere . '"';
            $nom = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($pieceJointe['nom'] ?? 'piece.pdf'));
            $corpsTexte = '--' . $frontiere . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
                . str_replace("\n", "\r\n", str_replace("\r\n", "\n", $corpsTexte)) . "\r\n"
                . '--' . $frontiere . "\r\n"
                . 'Content-Type: ' . ($pieceJointe['type'] ?? 'application/pdf') . '; name="' . $nom . "\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . 'Content-Disposition: attachment; filename="' . $nom . "\"\r\n\r\n"
                . chunk_split(base64_encode($pieceJointe['contenu']), 76, "\r\n")
                . '--' . $frontiere . "--\r\n";
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }

        $sujetEncode = '=?UTF-8?B?' . base64_encode($sujet) . '?=';

        try {
            return @mail($destinataire, $sujetEncode, $corpsTexte, implode("\r\n", $headers));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function encodeHeaderValue(string $value): string
    {
        // Encode le nom d'affichage s'il contient des caractères non-ASCII
        // (accents), pour rester conforme aux en-têtes email.
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}

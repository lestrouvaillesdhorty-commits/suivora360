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
    public static function envoyer(string $destinataire, string $sujet, string $corpsTexte): bool
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
        $headers[] = 'Reply-To: ' . $fromAddress;
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'MIME-Version: 1.0';

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

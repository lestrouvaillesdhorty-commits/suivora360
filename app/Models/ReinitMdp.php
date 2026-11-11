<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Env;
use App\Services\Mailer;

/**
 * « Mot de passe oublié » : liens de réinitialisation à usage unique.
 *
 * - Le lien est valable 1 heure ; seul son empreinte (sha256) est stockée.
 * - La demande ne révèle jamais si l'adresse a un compte (même réponse, dans tous les cas).
 * - Limites : 5 demandes par heure et par compte, 10 par heure et par adresse IP.
 * - Sûre avant migration : sans la table reinit_mdp (migrate_v53), la fonction est simplement inactive.
 */
class ReinitMdp
{
    public const VALIDITE_MINUTES = 60;
    public const MAX_PAR_COMPTE = 5;
    public const MAX_PAR_IP = 10;

    public static function disponible(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::connection()->query('SELECT 1 FROM reinit_mdp LIMIT 1');
                $ok = true;
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    private static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    private static function maintenant(): string
    {
        return date('Y-m-d H:i:s');
    }

    private static function compter(string $colonne, $valeur): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM reinit_mdp WHERE $colonne = ? AND created_at >= ?");
        $stmt->execute([$valeur, date('Y-m-d H:i:s', time() - 3600)]);
        return (int) $stmt->fetchColumn();
    }

    /** Traite une demande. Ne renvoie rien : l'écran est identique que le compte existe ou non. */
    public static function demander(string $email): void
    {
        if (!self::disponible()) {
            return;
        }
        $email = trim($email);
        $pdo = Database::connection();
        try {
            $ip = self::ip();
            if ($ip !== '' && self::compter('ip', $ip) >= self::MAX_PAR_IP) {
                return;
            }
            $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? Utilisateur::findByEmail($email) : null;
            $uid = ($user && !empty($user['actif'])) ? (int) $user['id'] : 0;
            // Toute demande est enregistrée (même sans compte) pour que la limite par adresse IP joue.
            if ($uid > 0 && self::compter('utilisateur_id', $uid) >= self::MAX_PAR_COMPTE) {
                return;
            }
            $jeton = bin2hex(random_bytes(32));
            $pdo->prepare('INSERT INTO reinit_mdp (utilisateur_id, jeton_hash, ip, expire_le, created_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$uid, $uid > 0 ? hash('sha256', $jeton) : '', $ip, date('Y-m-d H:i:s', time() + self::VALIDITE_MINUTES * 60), self::maintenant()]);
            $pdo->prepare('DELETE FROM reinit_mdp WHERE created_at < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
            if ($uid === 0) {
                return;
            }
            $lien = Organisation::urlBase((int) $user['organisation_id']) . '/index.php?r=mot-de-passe/reinitialiser&jeton=' . $jeton;
            $org = Organisation::find((int) $user['organisation_id']);
            $corps = 'Bonjour ' . $user['nom'] . ",\n\n"
                . "Vous avez demandé à changer votre mot de passe pour l'espace de " . ($org['nom'] ?? 'votre entreprise') . ".\n\n"
                . "Choisissez votre nouveau mot de passe en ouvrant ce lien :\n" . $lien . "\n\n"
                . 'Ce lien est valable ' . self::VALIDITE_MINUTES . " minutes et ne fonctionne qu'une fois.\n"
                . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.\n\n"
                . "Suivora360\n";
            Mailer::envoyer($user['email'], 'Votre nouveau mot de passe', $corps);
        } catch (\Throwable $e) {
            // Jamais d'erreur visible : l'écran reste identique.
        }
    }

    /** Renvoie la ligne du lien s'il est valide (non utilisé, non expiré, compte actif), sinon null. */
    public static function trouver(string $jeton): ?array
    {
        if (!self::disponible() || !preg_match('/^[a-f0-9]{64}$/', $jeton)) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM reinit_mdp WHERE jeton_hash = ? AND utilisateur_id > 0 AND utilise_le IS NULL AND expire_le > ?');
        $stmt->execute([hash('sha256', $jeton), self::maintenant()]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $user = Utilisateur::find((int) $row['utilisateur_id']);
        return ($user && !empty($user['actif'])) ? $row : null;
    }

    /** Change le mot de passe, brûle tous les liens du compte, ferme les anciennes sessions et prévient la personne. */
    public static function appliquer(array $ligne, string $nouveauMotDePasse): void
    {
        $uid = (int) $ligne['utilisateur_id'];
        $pdo = Database::connection();
        Utilisateur::updatePassword($uid, $nouveauMotDePasse, false);
        $pdo->prepare('UPDATE reinit_mdp SET utilise_le = ? WHERE utilisateur_id = ? AND utilise_le IS NULL')->execute([self::maintenant(), $uid]);
        try {
            $pdo->prepare('UPDATE utilisateurs SET mdp_modifie_le = ? WHERE id = ?')->execute([self::maintenant(), $uid]);
        } catch (\Throwable $e) {
        }
        $user = Utilisateur::find($uid);
        if ($user) {
            \App\Core\LimiteConnexion::reussite($user['email']);
            Mailer::envoyer(
                $user['email'],
                'Votre mot de passe a été modifié',
                'Bonjour ' . $user['nom'] . ",\n\nVotre mot de passe Suivora360 vient d'être modifié.\n"
                . "Si ce n'est pas vous, demandez immédiatement à votre administrateur de réinitialiser votre accès.\n\nSuivora360\n"
            );
        }
    }
}

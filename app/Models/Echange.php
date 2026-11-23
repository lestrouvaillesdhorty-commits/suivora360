<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Schema;
use App\Core\Storage;
use App\Services\Mailer;

/**
 * Échanges avec le client et le fournisseur (v72) :
 *  - messages : un fil par dossier pour le client, un fil par commande (bon) pour le fournisseur ;
 *  - notes de suivi : écrites par l'équipe, privées par défaut, visibles du client et/ou du fournisseur
 *    seulement si la case correspondante est cochée ;
 *  - contact destinataire d'un lien : ses alertes partent à l'e-mail de ce contact.
 *
 * Tout reste compatible avant migration : pret() renvoie false et les écrans se masquent.
 */
class Echange
{
    public const CLIENT = 'client';
    public const FOURNISSEUR = 'fournisseur';
    public const TAILLE_MAX = 10485760;
    public const TEXTE_MAX = 3000;
    public const MAX_PAR_HEURE = 10;
    private const EXT = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function pret(): bool
    {
        static $pret = null;
        if ($pret === null) {
            try {
                $pdo = Database::connection();
                $ok = true;
                foreach (['dossier_notes', 'dossier_messages'] as $t) {
                    if (Database::driver() === 'sqlite') {
                        $st = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
                    } else {
                        $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
                    }
                    $st->execute([$t]);
                    $ok = $ok && (bool) $st->fetch();
                }
                $pret = $ok;
            } catch (\Throwable $e) {
                $pret = false;
            }
        }
        return $pret;
    }

    /** Libellé d'une durée de lien : « 30 jours », « 6 mois », « 1 an ». */
    public static function libelleDuree(int $jours): string
    {
        return $jours === 365 ? '1 an' : ($jours === 180 ? '6 mois' : $jours . ' jours');
    }

    // ------------------------------------------------------------------ Notes de suivi

    public static function notes(int $dossierId): array
    {
        if (!self::pret()) {
            return [];
        }
        $st = Database::connection()->prepare('SELECT * FROM dossier_notes WHERE dossier_id = ? ORDER BY created_at DESC, id DESC');
        $st->execute([$dossierId]);
        return $st->fetchAll();
    }

    /** Notes que l'extérieur a le droit de lire ($qui = 'client' ou 'fournisseur'). */
    public static function notesVisibles(int $dossierId, string $qui): array
    {
        if (!self::pret()) {
            return [];
        }
        $col = $qui === self::FOURNISSEUR ? 'visible_fournisseur' : 'visible_client';
        $st = Database::connection()->prepare("SELECT id, texte, created_at FROM dossier_notes WHERE dossier_id = ? AND $col = 1 ORDER BY created_at DESC, id DESC");
        $st->execute([$dossierId]);
        return $st->fetchAll();
    }

    public static function ajouterNote(array $dossier, ?int $auteurId, string $texte, bool $visClient, bool $visFournisseur): int
    {
        $texte = mb_substr(trim($texte), 0, self::TEXTE_MAX);
        if ($texte === '') {
            return 0;
        }
        $maintenant = date('Y-m-d H:i:s');
        Database::connection()->prepare('INSERT INTO dossier_notes (dossier_id, filiale_id, auteur_id, texte, visible_client, visible_fournisseur, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([(int) $dossier['id'], (int) $dossier['filiale_id'], $auteurId, $texte, $visClient ? 1 : 0, $visFournisseur ? 1 : 0, $maintenant, $maintenant]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function modifierNote(int $dossierId, int $noteId, string $texte, bool $visClient, bool $visFournisseur): bool
    {
        $texte = mb_substr(trim($texte), 0, self::TEXTE_MAX);
        if ($texte === '') {
            return false;
        }
        $st = Database::connection()->prepare('UPDATE dossier_notes SET texte = ?, visible_client = ?, visible_fournisseur = ?, updated_at = ? WHERE id = ? AND dossier_id = ?');
        $st->execute([$texte, $visClient ? 1 : 0, $visFournisseur ? 1 : 0, date('Y-m-d H:i:s'), $noteId, $dossierId]);
        return $st->rowCount() > 0;
    }

    public static function retirerNote(int $dossierId, int $noteId): void
    {
        Database::connection()->prepare('DELETE FROM dossier_notes WHERE id = ? AND dossier_id = ?')->execute([$noteId, $dossierId]);
    }

    // ------------------------------------------------------------------ Messages

    /** Fil d'un dossier (canal client) ou d'une commande (canal fournisseur), du plus ancien au plus récent. */
    public static function messages(string $canal, int $dossierId, ?int $bonId = null): array
    {
        if (!self::pret()) {
            return [];
        }
        if ($canal === self::FOURNISSEUR) {
            $st = Database::connection()->prepare('SELECT * FROM dossier_messages WHERE canal = ? AND bon_id = ? ORDER BY created_at, id');
            $st->execute([$canal, (int) $bonId]);
        } else {
            $st = Database::connection()->prepare('SELECT * FROM dossier_messages WHERE canal = ? AND dossier_id = ? ORDER BY created_at, id');
            $st->execute([$canal, $dossierId]);
        }
        return $st->fetchAll();
    }

    public static function message(int $id): ?array
    {
        if (!self::pret()) {
            return null;
        }
        $st = Database::connection()->prepare('SELECT * FROM dossier_messages WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /**
     * Ajoute un message. $emetteur = 'interne' (équipe) ou 'externe' (client / fournisseur).
     * Retourne [id, erreur] : id = 0 et message d'erreur si refusé.
     */
    public static function envoyer(string $canal, array $dossier, ?int $bonId, string $emetteur, ?int $auteurId, string $auteurNom, string $texte, ?array $fichier): array
    {
        $texte = mb_substr(trim($texte), 0, self::TEXTE_MAX);
        $aFichier = $fichier && ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($texte === '' && !$aFichier) {
            return [0, 'Écrivez un message ou joignez un fichier.'];
        }
        $nom = null;
        $original = null;
        if ($aFichier) {
            if (($fichier['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file($fichier['tmp_name'] ?? '') || ($fichier['size'] ?? 0) > self::TAILLE_MAX || ($fichier['size'] ?? 0) <= 0) {
                return [0, 'Fichier non reçu : 10 Mo maximum.'];
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']);
            if (!isset(self::EXT[$mime])) {
                return [0, 'Format refusé : joignez un PDF ou une image (JPG, PNG).'];
            }
            $nom = bin2hex(random_bytes(12)) . '.' . self::EXT[$mime];
            $dir = $dossier && (int) ($dossier['id'] ?? 0) > 0 ? 'd' . (int) $dossier['id'] : 'b' . (int) $bonId;
            if (!move_uploaded_file($fichier['tmp_name'], Storage::path('uploads/echanges/' . $dir . '/' . $nom))) {
                return [0, "Le fichier n'a pas pu être enregistré. Réessayez."];
            }
            $original = mb_substr(preg_replace('/[^\p{L}\p{N} ._()-]/u', '', basename((string) ($fichier['name'] ?? 'document'))) ?: 'document', 0, 200);
        }
        Database::connection()->prepare(
            'INSERT INTO dossier_messages (dossier_id, filiale_id, canal, bon_id, emetteur, auteur_id, auteur_nom, texte, fichier, nom_fichier, lu_interne, lu_externe, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            (int) ($dossier['id'] ?? 0), (int) $dossier['filiale_id'], $canal === self::FOURNISSEUR ? self::FOURNISSEUR : self::CLIENT,
            $bonId ?: null, $emetteur === 'interne' ? 'interne' : 'externe', $auteurId, mb_substr($auteurNom, 0, 120), $texte, $nom, $original,
            $emetteur === 'interne' ? 1 : 0, $emetteur === 'interne' ? 0 : 1, date('Y-m-d H:i:s'),
        ]);
        return [(int) Database::connection()->lastInsertId(), null];
    }

    public static function cheminFichier(array $m): string
    {
        $dir = (int) $m['dossier_id'] > 0 ? 'd' . (int) $m['dossier_id'] : 'b' . (int) $m['bon_id'];
        return Storage::path('uploads/echanges/' . $dir . '/' . basename((string) $m['fichier']));
    }

    /** Envoie le fichier d'un message au navigateur (affichage dans la page, jamais exécuté). */
    public static function servirFichier(array $m): void
    {
        $chemin = self::cheminFichier($m);
        if (empty($m['fichier']) || !is_file($chemin)) {
            http_response_code(404);
            exit;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($chemin) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . str_replace('"', '', (string) $m['nom_fichier']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($chemin));
        readfile($chemin);
        exit;
    }

    /** Anti-spam : messages envoyés par l'extérieur sur ce fil depuis une heure. */
    public static function recentsExternes(string $canal, int $dossierId, ?int $bonId): int
    {
        if (!self::pret()) {
            return 0;
        }
        $depuis = date('Y-m-d H:i:s', time() - 3600);
        if ($canal === self::FOURNISSEUR) {
            $st = Database::connection()->prepare("SELECT COUNT(*) FROM dossier_messages WHERE canal = ? AND bon_id = ? AND emetteur = 'externe' AND created_at >= ?");
            $st->execute([$canal, (int) $bonId, $depuis]);
        } else {
            $st = Database::connection()->prepare("SELECT COUNT(*) FROM dossier_messages WHERE canal = ? AND dossier_id = ? AND emetteur = 'externe' AND created_at >= ?");
            $st->execute([$canal, $dossierId, $depuis]);
        }
        return (int) $st->fetchColumn();
    }

    // ------------------------------------------------------------------ Lu / non lu

    /** Messages reçus de l'extérieur que l'équipe n'a pas encore lus sur un dossier (tous canaux). */
    public static function nonLusInterne(int $dossierId): int
    {
        if (!self::pret() || $dossierId <= 0) {
            return 0;
        }
        $st = Database::connection()->prepare("SELECT COUNT(*) FROM dossier_messages WHERE dossier_id = ? AND emetteur = 'externe' AND lu_interne = 0");
        $st->execute([$dossierId]);
        return (int) $st->fetchColumn();
    }

    /** Idem pour une commande sans dossier ou une fiche commande. */
    public static function nonLusInterneBon(int $bonId): int
    {
        if (!self::pret() || $bonId <= 0) {
            return 0;
        }
        $st = Database::connection()->prepare("SELECT COUNT(*) FROM dossier_messages WHERE bon_id = ? AND emetteur = 'externe' AND lu_interne = 0");
        $st->execute([$bonId]);
        return (int) $st->fetchColumn();
    }

    public static function marquerLusInterne(int $dossierId, ?int $bonId = null): void
    {
        if (!self::pret()) {
            return;
        }
        if ($bonId) {
            Database::connection()->prepare("UPDATE dossier_messages SET lu_interne = 1 WHERE bon_id = ? AND emetteur = 'externe'")->execute([$bonId]);
        } elseif ($dossierId > 0) {
            Database::connection()->prepare("UPDATE dossier_messages SET lu_interne = 1 WHERE dossier_id = ? AND emetteur = 'externe'")->execute([$dossierId]);
        }
    }

    /** Réponses de l'équipe que l'extérieur n'a pas lues : sur un dossier (client) ou une commande (fournisseur). */
    public static function nonLusExterne(string $canal, int $dossierId, ?int $bonId = null): int
    {
        if (!self::pret()) {
            return 0;
        }
        if ($canal === self::FOURNISSEUR) {
            $st = Database::connection()->prepare("SELECT COUNT(*) FROM dossier_messages WHERE canal = ? AND bon_id = ? AND emetteur = 'interne' AND lu_externe = 0");
            $st->execute([$canal, (int) $bonId]);
        } else {
            $st = Database::connection()->prepare("SELECT COUNT(*) FROM dossier_messages WHERE canal = ? AND dossier_id = ? AND emetteur = 'interne' AND lu_externe = 0");
            $st->execute([$canal, $dossierId]);
        }
        return (int) $st->fetchColumn();
    }

    public static function marquerLusExterne(string $canal, int $dossierId, ?int $bonId = null): void
    {
        if (!self::pret()) {
            return;
        }
        if ($canal === self::FOURNISSEUR) {
            Database::connection()->prepare("UPDATE dossier_messages SET lu_externe = 1 WHERE canal = ? AND bon_id = ? AND emetteur = 'interne'")->execute([$canal, (int) $bonId]);
        } else {
            Database::connection()->prepare("UPDATE dossier_messages SET lu_externe = 1 WHERE canal = ? AND dossier_id = ? AND emetteur = 'interne'")->execute([$canal, $dossierId]);
        }
    }

    // ------------------------------------------------------------------ Contacts destinataires

    /** Contact principal + contacts actifs d'une fiche. $type = 'client' ou 'fournisseur'. Id 0 = contact principal. */
    public static function contacts(string $type, array $fiche): array
    {
        $liste = [];
        $nomPrincipal = trim(($fiche['contact_prenom'] ?? '') . ' ' . ($fiche['contact_nom'] ?? ''));
        $liste[] = [
            'id' => 0, 'prenom' => (string) ($fiche['contact_prenom'] ?? ''), 'nom' => $nomPrincipal !== '' ? $nomPrincipal : (string) ($fiche['nom'] ?? ''),
            'email' => (string) ($fiche['email'] ?? ''), 'telephone' => (string) ($fiche['telephone'] ?? ''), 'principal' => true,
        ];
        try {
            $autres = $type === self::FOURNISSEUR ? Fournisseur::contacts((int) $fiche['id']) : Client::contacts((int) $fiche['id'], true);
        } catch (\Throwable $e) {
            $autres = [];
        }
        foreach ($autres as $c) {
            if ((int) ($c['actif'] ?? 1) !== 1) {
                continue;
            }
            $liste[] = [
                'id' => (int) $c['id'], 'prenom' => (string) ($c['prenom'] ?? ''), 'nom' => trim(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? '')),
                'email' => (string) ($c['email'] ?? ''), 'telephone' => (string) ($c['telephone'] ?? ''), 'principal' => false,
            ];
        }
        return $liste;
    }

    /** Contact retenu pour un lien (ou le principal si aucun n'est choisi / s'il a disparu). */
    public static function contactDuLien(string $type, array $fiche, ?array $lien): array
    {
        $liste = self::contacts($type, $fiche);
        $voulu = (int) ($lien['contact_id'] ?? 0);
        foreach ($liste as $c) {
            if ((int) $c['id'] === $voulu) {
                return $c;
            }
        }
        return $liste[0];
    }

    /** Enregistre le contact destinataire d'un lien, après avoir vérifié qu'il appartient bien à la fiche. */
    public static function choisirContact(string $type, array $fiche, int $lienId, int $contactId): void
    {
        $table = $type === self::FOURNISSEUR ? 'fournisseur_liens' : 'client_portail_liens';
        $cle = $type === self::FOURNISSEUR ? 'fournisseur_id' : 'client_id';
        if (!Schema::colonne($table, 'contact_id')) {
            return;
        }
        $ok = false;
        foreach (self::contacts($type, $fiche) as $c) {
            if ((int) $c['id'] === $contactId) {
                $ok = true;
            }
        }
        if (!$ok) {
            return;
        }
        Database::connection()->prepare("UPDATE $table SET contact_id = ? WHERE id = ? AND $cle = ?")->execute([$contactId ?: null, $lienId, (int) $fiche['id']]);
    }

    // ------------------------------------------------------------------ Alertes

    /** Client rattaché à un dossier (demande d'origine, sinon cotation envoyée). */
    public static function clientIdDuDossier(array $dossier): int
    {
        $st = Database::connection()->prepare('SELECT client_id FROM demandes WHERE id = ?');
        $st->execute([(int) ($dossier['demande_id'] ?? 0)]);
        $id = (int) $st->fetchColumn();
        if ($id > 0) {
            return $id;
        }
        $st = Database::connection()->prepare('SELECT client_id FROM cotations WHERE dossier_id = ? AND client_id IS NOT NULL ORDER BY id DESC LIMIT 1');
        $st->execute([(int) $dossier['id']]);
        return (int) $st->fetchColumn();
    }

    /** Adresse du site pour les liens dans les e-mails. */
    public static function urlBase(int $filialeId): string
    {
        $filiale = Filiale::find($filialeId);
        $base = $filiale ? Organisation::urlBase((int) $filiale['organisation_id']) : '';
        return rtrim($base !== '' ? $base : (string) \App\Core\Env::get('APP_URL', ''), '/');
    }

    private static function lienActif(array $liens): ?array
    {
        foreach ($liens as $l) {
            if (empty($l['revoque_le']) && strtotime((string) $l['expire_le']) > time()) {
                return $l;
            }
        }
        return null;
    }

    /**
     * Prévient par e-mail le contact d'un lien actif (client ou fournisseur) que l'équipe a répondu ou ajouté une note.
     * Best-effort : ne bloque jamais l'action de l'équipe.
     */
    public static function prevenirExterne(string $canal, array $dossier, ?array $bon, string $sujet, string $texte): void
    {
        try {
            if ($canal === self::FOURNISSEUR) {
                $f = $bon ? Fournisseur::find((int) $bon['fournisseur_id']) : null;
                $lien = $f ? self::lienActif(FournisseurLien::liensDuFournisseur((int) $f['id'])) : null;
                if (!$f || !$lien) {
                    return;
                }
                $contact = self::contactDuLien(self::FOURNISSEUR, $f, $lien);
                $url = self::urlBase((int) $dossier['filiale_id']) . '/index.php?r=lien-fournisseur/' . $lien['token'] . '/commandes/' . (int) $bon['id'] . '&vue=messages';
            } else {
                $c = Client::find(self::clientIdDuDossier($dossier));
                $lien = $c ? self::lienActif(PortailClient::liensDuClient((int) $c['id'])) : null;
                if (!$c || !$lien) {
                    return;
                }
                $contact = self::contactDuLien(self::CLIENT, $c, $lien);
                $url = self::urlBase((int) $dossier['filiale_id']) . '/index.php?r=espace-client/' . $lien['token'] . '/dossiers/' . (int) $dossier['id'];
            }
            if (trim($contact['email']) === '') {
                return;
            }
            $bonjour = trim($contact['prenom']) !== '' ? 'Bonjour ' . trim($contact['prenom']) . ",\n\n" : "Bonjour,\n\n";
            Mailer::envoyer($contact['email'], $sujet, $bonjour . $texte . "\n\nVoir le dossier : " . $url);
        } catch (\Throwable $e) {
            // Jamais bloquant.
        }
    }

    /** Prévient l'équipe (cloche + e-mail) : responsable du dossier, sinon propriétaires. */
    public static function prevenirEquipe(array $dossier, string $titre, string $message, string $lien): void
    {
        try {
            $filiale = Filiale::find((int) $dossier['filiale_id']);
            if (!$filiale) {
                return;
            }
            $dest = [];
            if (!empty($dossier['responsable_id'])) {
                $u = Utilisateur::find((int) $dossier['responsable_id']);
                if ($u && (int) $u['actif'] === 1 && (int) $u['organisation_id'] === (int) $filiale['organisation_id']) {
                    $dest[] = $u;
                }
            }
            if (!$dest) {
                foreach (Utilisateur::allForOrganisation((int) $filiale['organisation_id']) as $u) {
                    if ($u['role'] === 'proprietaire' && (int) $u['actif'] === 1) {
                        $dest[] = $u;
                    }
                }
            }
            foreach ($dest as $u) {
                Notification::notifier($u, (int) $dossier['filiale_id'], 'message_externe', $titre, $message, $lien, 'dossier', (int) $dossier['id']);
            }
        } catch (\Throwable $e) {
            // Jamais bloquant.
        }
    }
}

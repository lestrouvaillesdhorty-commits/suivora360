<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Storage;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\EntrepriseInfo;
use App\Models\Filiale;

/** Page « Mon entreprise » : identité, logo, mentions et conditions reprises sur les devis. */
class EntrepriseController
{
    private function filialeCourante(array $user): int
    {
        $filiales = Filiale::visibleFor($user);
        $id = (int) ($_GET['filiale_id'] ?? $_POST['filiale_id'] ?? ($filiales[0]['id'] ?? 0));
        if (!$id || !Filiale::userCanAccess($user, $id)) {
            $id = (int) ($filiales[0]['id'] ?? 0);
        }
        return $id;
    }

    public function index(): void
    {
        Auth::requireParametres();
        $user = Auth::user();
        $filialeId = $this->filialeCourante($user);
        $filiale = $filialeId ? (Filiale::find($filialeId) ?: []) : [];
        $info = $filialeId ? EntrepriseInfo::forFiliale($filialeId) : [];
        View::render('parametres/entreprise', [
            'filiales' => Filiale::visibleFor($user),
            'filialeId' => $filialeId,
            'filiale' => $filiale,
            'info' => $info,
            'logoUri' => EntrepriseInfo::logoDataUri($info),
            'disponible' => EntrepriseInfo::disponible(),
        ]);
    }

    public function update(): void
    {
        Auth::requireParametres();
        $user = Auth::user();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, merci de réessayer.');
            header('Location: /index.php?r=parametres/entreprise');
            exit;
        }
        $filialeId = (int) ($_POST['filiale_id'] ?? 0);
        if (!$filialeId || !Filiale::userCanAccess($user, $filialeId) || !EntrepriseInfo::disponible()) {
            View::flash('erreur', "Enregistrement impossible (accès ou migration manquante).");
            header('Location: /index.php?r=parametres/entreprise');
            exit;
        }
        $retour = '/index.php?r=parametres/entreprise&filiale_id=' . $filialeId;
        $logo = null;
        $f = $_FILES['logo'] ?? null;
        if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, EntrepriseInfo::EXTENSIONS_LOGO, true) || $f['size'] > 2 * 1024 * 1024 || @getimagesize($f['tmp_name']) === false) {
                View::flash('erreur', 'Logo refusé : image PNG ou JPG de 2 Mo maximum.');
                header('Location: ' . $retour);
                exit;
            }
            $nom = 'filiale-' . $filialeId . '-' . bin2hex(random_bytes(6)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
            if (!move_uploaded_file($f['tmp_name'], Storage::path('uploads/logos/' . $nom))) {
                View::flash('erreur', "Impossible d'enregistrer le logo sur le serveur.");
                header('Location: ' . $retour);
                exit;
            }
            $logo = $nom;
        }
        EntrepriseInfo::save($filialeId, $_POST, $logo);
        AuditLog::log($filialeId, (int) $user['id'], 'maj_entreprise', 'filiale', $filialeId);
        View::flash('succes', 'Fiche entreprise enregistrée : elle apparaît désormais sur vos devis.');
        header('Location: ' . $retour);
        exit;
    }
}

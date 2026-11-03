<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Notification;

class NotificationController
{
    public function index(): void
    {
        $user = Auth::user();
        $notifications = Notification::recentesFor((int) $user['id'], 30);
        View::render('notifications/index', [
            'notifications' => $notifications,
        ]);
    }

    /** JSON pour l'alerte sonore / à l'écran : notifications non lues plus récentes que ?depuis=<id>. */
    public function nouvelles(): void
    {
        $user = Auth::user();
        $depuis = (int) ($_GET['depuis'] ?? 0);
        $out = [];
        foreach (Notification::recentesFor((int) $user['id'], 10) as $n) {
            if ((int) $n['lu'] === 0 && (int) $n['id'] > $depuis) {
                $out[] = ['id' => (int) $n['id'], 'titre' => (string) $n['titre'], 'message' => (string) $n['message'], 'lien' => (string) ($n['lien'] ?? '')];
            }
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['non_lues' => Notification::nonLuesCountFor((int) $user['id']), 'nouvelles' => array_reverse($out)]);
        exit;
    }

    public function marquerLue(array $params): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=notifications');
            exit;
        }

        $user = Auth::user();
        $notification = Notification::find((int) $params['id']);
        // Le lien n'est suivi que si la notification est bien la mienne (sinon on ne révèle pas l'adresse interne d'autrui).
        $estLaMienne = $notification && (int) $notification['utilisateur_id'] === (int) $user['id'];
        $destination = ($estLaMienne && !empty($notification['lien'])) ? $notification['lien'] : '/index.php?r=notifications';

        if ($notification && (int) $notification['utilisateur_id'] === (int) $user['id']) {
            Notification::marquerLue((int) $notification['id'], (int) $user['id']);
        }

        header('Location: ' . $destination);
        exit;
    }

    public function marquerToutesLues(): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=notifications');
            exit;
        }

        $user = Auth::user();
        Notification::marquerToutesLues((int) $user['id']);
        header('Location: /index.php?r=notifications');
        exit;
    }
}

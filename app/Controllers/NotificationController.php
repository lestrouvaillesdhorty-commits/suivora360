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

    public function marquerLue(array $params): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=notifications');
            exit;
        }

        $user = Auth::user();
        $notification = Notification::find((int) $params['id']);
        $destination = ($notification && !empty($notification['lien'])) ? $notification['lien'] : '/index.php?r=notifications';

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

<?php

namespace App\Core;

use App\Models\Notification;
use App\Models\Utilisateur;

class View
{
    private const BASE = __DIR__ . '/../../views/';

    public static function render(string $template, array $data = []): void
    {
        $data['currentUser'] = Auth::user();
        $data['csrfToken'] = Auth::csrfToken();
        $data['flash'] = self::takeFlash();
        // Calculé pour chaque page (coût négligeable) afin que la cloche de
        // notifications du menu reste toujours à jour, sans que chaque
        // contrôleur ait à y penser.
        $data['notifNonLues'] = $data['currentUser'] ? Notification::nonLuesCountFor((int) $data['currentUser']['id']) : 0;

        extract($data);
        $content = self::capture(self::BASE . $template . '.php', $data);
        require self::BASE . 'layout/main.php';
    }

    public static function renderPlain(string $template, array $data = []): void
    {
        extract($data);
        require self::BASE . $template . '.php';
    }

    private static function capture(string $file, array $data): string
    {
        extract($data);
        ob_start();
        require $file;
        return ob_get_clean();
    }

    public static function flash(string $type, string $message): void
    {
        Auth::start();
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    private static function takeFlash(): ?array
    {
        if (!empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

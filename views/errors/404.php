<div class="card">
  <h1>Page introuvable</h1>
  <p>La page que vous cherchez n'existe pas ou vous n'y avez pas accès.</p>
  <p style="font-size:12px;color:#9ca3af;word-break:break-all">Adresse demandée : <?= htmlspecialchars((string) ($_GET['r'] ?? '/'), ENT_QUOTES) ?></p>
  <a href="/index.php" class="btn">Retour au tableau de bord</a>
</div>

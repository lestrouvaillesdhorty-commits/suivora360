<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Lien indisponible</title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<?php include __DIR__ . '/_style.php'; ?></head><body>
<div class="w" style="padding-top:60px"><div class="card" style="max-width:480px;margin:0 auto;text-align:center">
<h1 style="font-size:18px"><?= !empty($bloque) ? 'Trop de tentatives' : 'Ce lien n\'est plus disponible' ?></h1>
<p class="mut"><?= !empty($bloque) ? 'Merci de patienter quelques minutes avant de réessayer.' : 'Il a peut-être expiré ou été révoqué. Merci de contacter directement votre interlocuteur pour obtenir un nouveau lien.' ?></p>
</div></div></body></html>

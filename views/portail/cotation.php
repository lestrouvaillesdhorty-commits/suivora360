<?php
use App\Core\View;
$fm = fn($m, $d) => number_format((float) $m, 0, ',', ' ') . ' ' . ($d !== null && $d !== '' ? $d : '');
$badges = ['envoyee' => ['À valider', 'ye'], 'acceptee' => ['Acceptée', 'gr'], 'refusee' => ['Refusée', 'rd']];
$bg = $badges[$cotation['statut']];
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Cotation <?= View::e($cotation['reference']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<?php include __DIR__ . '/_style.php'; ?></head><body>
<?php include __DIR__ . '/_entete.php'; ?>
<div class="w" style="padding-top:20px">
  <a href="<?= $base ?>&onglet=cotations" class="noprint mut">&larr; Retour à mon espace</a>
  <?php if (!empty($flashPortail)): ?><div class="alert <?= $flashPortail['type'] === 'succes' ? 'al-s' : 'al-e' ?>"><?= View::e($flashPortail['message']) ?></div><?php endif; ?>
  <div class="card" style="margin-top:14px">
    <span class="badge <?= $bg[1] ?>"><?= $bg[0] ?></span>
    <h1 style="margin-top:8px">Cotation <?= View::e($cotation['reference']) ?></h1>
    <div class="sub"><?= View::e($cotation['dossier_objet']) ?> · dossier <?= View::e($cotation['dossier_reference']) ?> · émise le <?= date('d/m/Y', strtotime($cotation['created_at'])) ?></div>
    <?php if (!empty($lignes)): ?>
    <table class="t" style="margin-top:16px"><thead><tr><th>Désignation</th><th class="n">Qté</th><th>Unité</th><th class="n">Prix unitaire</th><th class="n">Montant</th></tr></thead><tbody>
    <?php foreach ($lignes as $l): ?><tr><td><?= View::e($l['designation']) ?></td><td class="n"><?= View::e((string) $l['quantite']) ?></td><td><?= View::e($l['unite']) ?></td>
      <td class="n"><?= $l['prix_unitaire'] !== null ? number_format((float) $l['prix_unitaire'], 2, ',', ' ') : '—' ?></td><td class="n"><?= $l['montant'] !== null ? number_format((float) $l['montant'], 2, ',', ' ') : '—' ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
    <div class="big" style="margin-top:16px">Total : <?= $fm($cotation['montant_total'], $cotation['devise']) ?></div>
    <div class="mut" style="margin-top:6px">
      <?= !empty($cotation['incoterm_client']) ? 'Incoterm : ' . View::e($cotation['incoterm_client']) . ' · ' : '' ?>
      <?= !empty($cotation['mode_paiement_negocie']) ? 'Paiement : ' . View::e(\App\Models\Demande::MODES_PAIEMENT[$cotation['mode_paiement_negocie']] ?? $cotation['mode_paiement_negocie']) . ' · ' : '' ?>
      <?= !empty($cotation['validite_devis']) ? 'Valable jusqu\'au ' . date('d/m/Y', strtotime($cotation['validite_devis'])) : '' ?>
    </div>
    <div class="act"><button class="btn bs noprint" onclick="window.print()">Imprimer / enregistrer en PDF</button></div>
  </div>

  <?php if ($decisionPossible): ?>
  <div class="card cota noprint"><h2>Votre réponse</h2>
    <form method="post" action="<?= $base ?>/cotations/<?= (int) $cotation['id'] ?>/decision">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <label for="commentaire">Un commentaire pour votre interlocuteur (facultatif)</label>
      <input type="text" id="commentaire" name="commentaire" maxlength="300">
      <div class="act">
        <button type="submit" name="decision" value="acceptee" class="btn bp">J'accepte cette cotation</button>
        <button type="submit" name="decision" value="refusee" class="btn bs">Je refuse</button>
      </div>
      <div class="mut" style="margin-top:10px">Votre réponse est transmise immédiatement à votre interlocuteur.</div>
    </form>
  </div>
  <?php elseif ($cotation['statut'] === 'envoyee'): ?>
    <div class="alert al-e noprint">Cette cotation n'est plus valable en ligne. Contactez votre interlocuteur.</div>
  <?php endif; ?>
  <div class="priv">Ce lien est personnel : ne le partagez pas.</div>
</div></body></html>

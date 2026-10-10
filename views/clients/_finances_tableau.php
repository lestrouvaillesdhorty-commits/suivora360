<?php
use App\Core\View;

/** Tableau des indicateurs financiers par devise. Variables : $finances (null = indisponible), $lienDetail (bool). */
$fm = fn($m, $d) => number_format((float) $m, 0, ',', ' ') . ' ' . $d;
if ($finances === null): ?>
  <div class="empty-state">Non disponible — les données financières ne peuvent pas être lues pour le moment.</div>
<?php elseif (empty($finances['totaux'])): ?>
  <div class="empty-state">Aucune cotation acceptée ni facture sur cette période.</div>
<?php else: ?>
  <table class="responsive-cards">
    <thead><tr><th>Devise</th><th class="num">Cotations acceptées</th><th class="num">Facturé</th><th class="num">Encaissé</th><th class="num">Reste à encaisser</th></tr></thead>
    <tbody>
    <?php foreach ($finances['totaux'] as $devise => $t): ?>
      <tr>
        <td data-label="Devise"><strong><?= View::e($devise) ?></strong></td>
        <td data-label="Cotations acceptées" class="num"><?= isset($t['acceptees']) ? $fm($t['acceptees'], $devise) : '—' ?></td>
        <td data-label="Facturé" class="num"><?= isset($t['facture']) ? $fm($t['facture'], $devise) : '—' ?></td>
        <td data-label="Encaissé" class="num"><?= isset($t['encaisse']) ? $fm($t['encaisse'], $devise) : '—' ?></td>
        <td data-label="Reste à encaisser" class="num" style="white-space:nowrap;<?= !empty($t['reste']) ? 'color:#991b1b;font-weight:600' : '' ?>"><?= isset($t['reste']) ? $fm($t['reste'], $devise) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (count($finances['totaux']) > 1): ?><div style="font-size:12px;color:#888;margin-top:6px">Plusieurs devises : montants présentés séparément, sans conversion.</div><?php endif; ?>
<?php endif; ?>

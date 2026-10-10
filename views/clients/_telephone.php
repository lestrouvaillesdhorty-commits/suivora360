<?php
use App\Core\Telephone;
use App\Core\View;

/**
 * Champ téléphone avec indicatif international.
 * Variables : $tphIndicatif (ex. '+237'), $tphNumero, $tphId (préfixe d'id unique sur la page).
 * Envoie tel_indicatif + tel_numero (assemblés côté serveur par Telephone::composer()).
 */
?>
<div class="tel-champ" style="display:grid;grid-template-columns:minmax(120px,150px) 1fr;gap:8px">
  <select name="tel_indicatif" id="<?= View::e($tphId) ?>_ind" aria-label="Indicatif international">
    <option value="">Indicatif</option>
    <?php $dejaChoisi = false; foreach (Telephone::PAYS_INDICATIFS as $pays => $code): $sel = (!$dejaChoisi && $tphIndicatif === $code); if ($sel) { $dejaChoisi = true; } ?>
      <option value="<?= View::e($code) ?>" <?= $sel ? 'selected' : '' ?>><?= View::e($code . ' ' . $pays) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="tel" name="tel_numero" id="<?= View::e($tphId) ?>_num" value="<?= View::e($tphNumero) ?>" placeholder="Numéro" autocomplete="off" aria-label="Numéro de téléphone">
</div>

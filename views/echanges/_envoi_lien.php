<?php
use App\Core\Schema;
use App\Core\Telephone;
use App\Core\View;
use App\Models\Echange;
/**
 * Contact destinataire d'un lien + boutons « Envoyer par WhatsApp / par e-mail ».
 * Variables : $type ('client'|'fournisseur'), $fiche, $l (le lien), $url (adresse complète du lien),
 *             $phrase (ce que le message dit du lien), $actionContact, $csrf.
 */
$contact = Echange::contactDuLien($type, $fiche, $l);
$prenom = trim((string) $contact['prenom']);
$message = 'Bonjour' . ($prenom !== '' ? ' ' . $prenom : '') . ', ' . $phrase . ' : ' . $url;
$wa = Telephone::pourWhatsApp($contact['telephone']);
$table = $type === Echange::FOURNISSEUR ? 'fournisseur_liens' : 'client_portail_liens';
?>
<div style="margin-top:8px;background:#F5F9FA;border:1px solid #DDE9EC;border-radius:8px;padding:8px 10px;font-size:13px">
  <div><b>Contact destinataire :</b> <?= View::e($contact['nom']) ?><?= $contact['email'] !== '' ? ' · ' . View::e($contact['email']) : ' · <span style="color:#B4570F">pas d’e-mail</span>' ?><?= $contact['telephone'] !== '' ? ' · ' . View::e($contact['telephone']) : '' ?></div>
  <?php if (Schema::colonne($table, 'contact_id')): $liste = Echange::contacts($type, $fiche); if (count($liste) > 1): ?>
  <form method="post" action="<?= View::e($actionContact) ?>" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:6px">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrf) ?>">
    <select name="contact_id" aria-label="Contact destinataire"><?php foreach ($liste as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $contact['id'] ? 'selected' : '' ?>><?= View::e($c['nom']) ?><?= $c['principal'] ? ' (principal)' : '' ?></option><?php endforeach; ?></select>
    <button type="submit" class="btn btn-sm btn-secondary">Changer de contact</button>
  </form>
  <?php endif; endif; ?>
  <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
    <?php if ($wa !== ''): ?><a href="https://wa.me/<?= View::e($wa) ?>?text=<?= rawurlencode($message) ?>" target="_blank" rel="noopener" class="btn btn-sm" style="background:#25D366;border-color:#25D366;color:#fff" title="Ouvre WhatsApp avec le message prêt : rien n’est envoyé tant que vous n’appuyez pas sur Envoyer">Envoyer par WhatsApp</a><?php endif; ?>
    <?php if ($contact['email'] !== ''): ?><a href="mailto:<?= View::e($contact['email']) ?>?subject=<?= rawurlencode('Votre lien de suivi') ?>&body=<?= rawurlencode($message) ?>" class="btn btn-sm" title="Ouvre votre messagerie avec le message prêt">Envoyer par e-mail</a><?php endif; ?>
    <?php if ($wa === '' && $contact['email'] === ''): ?><span style="font-size:12px;color:#B4570F">Ajoutez un e-mail ou un numéro avec indicatif (+33…) sur la fiche de ce contact pour pouvoir lui envoyer le lien.</span><?php endif; ?>
    <?php if ($wa === '' && $contact['telephone'] !== ''): ?><span style="font-size:12px;color:#888">WhatsApp : le numéro doit commencer par l’indicatif (+33…).</span><?php endif; ?>
  </div>
</div>

<?php
// Grille de saisie des lignes d'une offre ou d'une cotation. Variables : $itemsPrecedents, $avecRemise, $articlesDemande, $avecNotes.
$avecDa = !empty($articlesDemande);
$avecNotes = !empty($avecNotes);
?>
<div class="gr" data-grille>
  <div class="gr-bar">
    <button type="button" class="btn btn-sm art-ajout" data-gr-add="article" id="add-item">+ Ajouter un article</button>
    <?php if ($avecNotes): ?><button type="button" class="btn btn-sm btn-secondary" data-gr-add="note">+ Note</button><?php endif; ?>
  </div>
  <div class="gr-wrap"><table class="gr-t gr-large"><thead><tr><th></th><?php if ($avecDa): ?><th style="width:180px">Article de la demande</th><?php endif; ?><th style="width:240px">Désignation</th><th style="width:80px">Qté</th><th style="width:100px">Unité</th><th style="width:110px">Prix unitaire</th><?php if (!empty($avecRemise)): ?><th style="width:80px">Remise %</th><?php endif; ?><th style="width:110px">Montant</th><th style="width:120px">Référence</th><th style="width:120px">Marque</th><th style="width:150px">Conditionnement</th><th style="width:170px">Caractéristiques</th><th></th></tr></thead>
    <tbody data-gr-body id="items-body"><?php unset($forceType); foreach ($itemsPrecedents ?? [] as $item) { include __DIR__ . '/_ligne_article.php'; } ?></tbody></table>
    <div class="gr-foot"><span data-gr-count>0 article</span><span>Tab : case suivante · Entrée : ligne suivante · coller depuis Excel possible</span></div></div>
  <?php if ($avecNotes): ?><div class="gr-hint">« + Note » ajoute une ligne de texte libre (remarque, condition) qui apparaîtra sur le devis.</div><?php endif; ?>
  <template data-gr-tpl="article" id="item-row-template"><?php $item = []; $forceType = 'article'; include __DIR__ . '/_ligne_article.php'; unset($forceType); ?></template>
  <?php if ($avecNotes): ?><template data-gr-tpl="note"><?php $item = []; $forceType = 'note'; include __DIR__ . '/_ligne_article.php'; unset($forceType); ?></template><?php endif; ?>
</div>

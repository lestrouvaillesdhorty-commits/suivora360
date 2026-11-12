<?php use App\Core\View; use App\Models\EntrantDemande; ?>
<h1>À valider</h1>
<div class="subtitle">Messages, photos, PDF et e-mails reçus. Rien n'est créé tant que vous n'avez pas validé.</div>

<?php if (!$disponible): ?>
  <div class="card"><div class="empty-state">Cette fonction demande une mise à jour de la base : ouvrez <code>/migrate_v57.php?token=…</code> une fois, puis revenez ici.</div></div>
<?php else: ?>
<form method="post" action="/index.php?r=a-valider/deposer" enctype="multipart/form-data" id="avForm">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
  <div class="av-depot" id="avDepot">
    <div class="av-titre">Déposez ici un mail, un PDF, une photo ou une capture d'écran</div>
    <div class="av-aide">ou collez un texte avec Ctrl+V</div>
    <input type="file" name="fichiers[]" id="avFichiers" multiple accept="image/*,.pdf,.docx,.xlsx,.txt,.eml" style="display:none">
    <div class="av-actions"><button type="button" class="btn btn-secondary btn-sm" id="avChoisir">Ajouter un fichier</button>
    <button type="button" class="btn btn-secondary btn-sm" id="avColler">Coller un message</button></div>
    <div id="avNoms" class="av-noms"></div>
    <div id="avTexteBloc" style="display:none;margin-top:10px"><textarea name="texte" id="avTexte" rows="5" placeholder="Collez ici le message (WhatsApp, e-mail…)" style="width:100%"></textarea></div>
    <div id="avEnvoyer" style="display:none;margin-top:10px"><button type="submit" class="btn">Envoyer vers À valider</button></div>
  </div>
</form>

<div class="card" style="margin-top:16px">
<?php if (!$entrants): ?>
  <div class="empty-state">Rien à valider pour le moment. Déposez un message ci-dessus, ou partagez-le depuis votre téléphone vers Suivora360.</div>
<?php else: ?>
  <table class="responsive-cards">
    <thead><tr><th>Reçu</th><th>Source</th><th>Contenu</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($entrants as $e):
        $apercu = trim((string) $e['texte']) !== '' ? (string) $e['texte'] : (string) $e['nom_original'];
        $apercu = mb_strimwidth(preg_replace('/\s+/', ' ', $apercu), 0, 90, '…');
    ?>
      <tr>
        <td data-label="Reçu"><?= View::e(date('d/m H:i', strtotime((string) $e['created_at']))) ?></td>
        <td data-label="Source"><span class="badge badge-gray"><?= View::e(EntrantDemande::libelleSource((string) $e['source'])) ?></span></td>
        <td data-label="Contenu"><?= View::e($apercu) ?></td>
        <td><a class="btn btn-sm" style="color:#fff" href="/index.php?r=a-valider/<?= (int) $e["id"] ?>">Vérifier</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?php if (!$iaDispo): ?><div class="hint" style="font-size:12.5px;color:#888;margin-top:8px">La lecture automatique des articles n'est pas activée : vous saisirez les articles à la main à la vérification.</div><?php endif; ?>

<style>
.av-depot{border:2px dashed #0B7C8A;border-radius:12px;background:#F2FAFB;padding:18px;text-align:center}
.av-depot.survol{background:#DDF1F3}
.av-titre{font-weight:700;font-size:14px;color:#0B7C8A}
.av-aide{font-size:12.5px;color:#5B6B7B;margin:6px 0 12px}
.av-actions{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
.av-noms{font-size:13px;margin-top:10px}@media (max-width:768px){.av-aide{display:none}.av-titre{margin-bottom:12px}}
</style>
<script>
(function(){
  var zone=document.getElementById('avDepot'),inp=document.getElementById('avFichiers'),form=document.getElementById('avForm');
  var noms=document.getElementById('avNoms'),bt=document.getElementById('avTexteBloc'),ta=document.getElementById('avTexte'),env=document.getElementById('avEnvoyer');
  function maj(){var l=[];for(var i=0;i<inp.files.length;i++)l.push(inp.files[i].name);noms.textContent=l.length?('Fichier(s) : '+l.join(', ')):'';env.style.display=(l.length||ta.value.trim()!=='')?'block':'none';}
  document.getElementById('avChoisir').onclick=function(){inp.click()};
  document.getElementById('avColler').onclick=function(){bt.style.display='block';ta.focus();maj()};
  inp.onchange=maj;ta.oninput=maj;
  function ajouter(fl){var dt=new DataTransfer();for(var i=0;i<inp.files.length;i++)dt.items.add(inp.files[i]);for(var j=0;j<fl.length;j++)dt.items.add(fl[j]);inp.files=dt.files;maj();}
  ['dragenter','dragover'].forEach(function(ev){zone.addEventListener(ev,function(e){e.preventDefault();zone.classList.add('survol')})});
  ['dragleave','drop'].forEach(function(ev){zone.addEventListener(ev,function(e){e.preventDefault();zone.classList.remove('survol')})});
  zone.addEventListener('drop',function(e){if(e.dataTransfer&&e.dataTransfer.files&&e.dataTransfer.files.length){ajouter(e.dataTransfer.files);}else{var t=e.dataTransfer?e.dataTransfer.getData('text'):'';if(t){bt.style.display='block';ta.value=(ta.value?ta.value+'\n':'')+t;maj();}}});
  document.addEventListener('paste',function(e){
    var cd=e.clipboardData;if(!cd)return;
    if(cd.files&&cd.files.length){ajouter(cd.files);e.preventDefault();return;}
    var cible=e.target&&e.target.tagName;if(cible==='TEXTAREA'||cible==='INPUT')return;
    var t=cd.getData('text');if(t){bt.style.display='block';ta.value=(ta.value?ta.value+'\n':'')+t;maj();e.preventDefault();}
  });
})();
</script>
<?php endif; ?>

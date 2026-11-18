<?php /** Style commun des documents imprimables (même rendu que l'aperçu du devis). Variables : $col, $mep. */ ?>
<style>
:root{--c:<?= \App\Core\View::e($col) ?>}
*{box-sizing:border-box} body{margin:0;background:#eef0f4;font:14px/1.45 -apple-system,Segoe UI,Roboto,sans-serif;color:#1c2030}
.bar{max-width:820px;margin:14px auto 0;padding:0 12px;display:flex;gap:8px;flex-wrap:wrap}
.bar a,.bar button{background:var(--c);color:#fff;border:0;border-radius:6px;padding:9px 14px;font-size:14px;text-decoration:none;cursor:pointer}
.bar a.s{background:#fff;color:var(--c);border:1px solid #BFE0E5}
.page{max-width:820px;margin:12px auto 24px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.1);display:flex;flex-direction:column;min-height:1000px}
.ent{padding:26px 34px 16px;border-bottom:4px solid var(--c);display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}
.emet .logo{max-height:64px;max-width:220px;display:block;margin-bottom:8px}.emet .coord{font-size:12.5px;color:#556;margin-top:3px}.ent .nom{font-size:22px;font-weight:700}.ent .doc{text-align:right}.ent .doc b{font-size:20px;letter-spacing:.5px;display:block}
.corps{padding:18px 34px;flex:1}
.bloc{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:18px}.bloc>div{flex:1;min-width:230px;background:#F2F7F8;background:color-mix(in srgb,var(--c) 8%,#fff);border-radius:8px;padding:10px 14px}
.bloc h3{margin:0 0 4px;font-size:12px;color:#667;font-weight:600}
table{width:100%;border-collapse:collapse;margin-top:6px}
th{background:var(--c);color:#fff;font-size:12px;text-align:left;padding:8px}td{padding:8px;border-bottom:1px solid #e6e8ef;vertical-align:top}
.d{font-size:12px;color:#667}.r{text-align:right;white-space:nowrap}
.tot{margin:16px 0 0 auto;width:min(340px,100%)}.tot div{display:flex;justify-content:space-between;padding:5px 0}
.tot .ttc{border-top:2px solid var(--c);margin-top:4px;padding-top:9px;font-size:18px;font-weight:700}
.cond{margin-top:18px;font-size:13px}.pied{padding:12px 34px;border-top:1px solid #e0e3ea;font-size:11.5px;color:#667;line-height:1.5}.pied .pr{text-align:right;color:#99a;margin-top:2px}.sign{display:flex;gap:18px;margin-top:26px;align-items:stretch}.sign>div:first-child{flex:1;font-size:13px}.sign .zone{flex:1;min-height:90px;border:1.5px dashed #BFE0E5;border-radius:10px}
.tw{overflow-x:auto}
<?php if ($mep === 'moderne'): ?>
.band{background:var(--c);color:#fff;padding:14px 34px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}.band b{font-size:20px;letter-spacing:.02em}.band .meta{text-align:right;font-size:12px;line-height:1.5}
.ent{border-bottom:0;padding-top:20px}.ent .emet .logo{background:#fff}
th{background:transparent!important;color:var(--c)!important;border-bottom:2px solid var(--c)}
.bloc>div{background:#fff;border:1px solid #DDE4EC;border-left:4px solid var(--c);border-radius:6px}
.tot .ttc{background:var(--c);color:#fff;border-top:0;padding:8px 10px;border-radius:6px;font-size:17px}
<?php elseif ($mep === 'sobre'): ?>
.ent{border-bottom:1px solid #1c2030}th{background:#eef0f4;color:#1c2030;border-bottom:2px solid #1c2030}.tot .ttc{border-top:2px solid #1c2030}.bar a,.bar button{background:#1c2030}.bar a.s{color:#1c2030;background:#fff;border-color:#c9ced8}
<?php endif; ?>
@media print{body{background:#fff}.bar{display:none}.page{box-shadow:none;margin:0;max-width:none;min-height:0}}
@media (max-width:600px){.ent,.corps,.pied{padding-left:16px;padding-right:16px}.ent .doc{text-align:left}}
.ent .emet .logo{max-height:64px}
.sec{margin:16px 0 6px;font-weight:700;color:var(--c)}
.bloc.liv>div{border-left:4px solid var(--c)}
</style>

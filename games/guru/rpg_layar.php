<?php
require __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/rpg.php';
$u = require_login('guru');
$g = db_row('SELECT * FROM games WHERE id=? AND user_id=?', [(int)($_GET['id'] ?? 0), $u['id']]);
if (!$g) { echo 'Game tidak ditemukan.'; exit; }
if ($g['template'] !== 'rpg_battle') { echo 'Bukan game RPG Battle.'; exit; }
$link = game_link($u['username'], $g['slug']);
?><!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Layar RPG Battle — <?= e($g['judul']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box}
  html,body{margin:0;height:100%;overflow:hidden;background:#0b0820;font-family:'Baloo 2','Trebuchet MS',system-ui,sans-serif;color:#fff}
  #wrap{display:grid;grid-template-rows:auto minmax(0,1fr) auto auto;height:100vh}
  #head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:6px 22px;background:linear-gradient(90deg,#4a3410,#2a1a55 45%,#2a1a55 55%,#3a2312);border-bottom:4px solid #f4b52c}
  #head .jd{font-size:1.5rem;font-weight:800;line-height:1.05}
  #head .sk{font-size:.85rem;opacity:.85}
  #rnd{font-size:1.7rem;font-weight:800;text-align:center;line-height:1.05}
  #rnd small{display:block;font-size:.95rem;font-weight:700;color:#ffd54f;letter-spacing:.06em}
  #tmr{width:70px;height:70px;border-radius:50%;background:#fff;color:#2a1a55;display:grid;place-items:center;font-size:2rem;font-weight:800;border:5px solid #f4b52c;flex:none}
  #tmr.low{background:#ffefef;color:#d7192a;animation:denyut .5s ease-in-out infinite}
  @keyframes denyut{50%{transform:scale(1.12)}}
  #mid{position:relative;min-height:0;background:linear-gradient(#150f3a,#5a2a7a 50%,#e0605a 56%,#4b3d5e 60%,#241d30)}
  svg#scene{width:100%;height:100%;display:block}
  #cap{position:absolute;left:50%;bottom:10px;transform:translateX(-50%);background:rgba(10,8,32,.88);color:#fff;border-radius:999px;padding:6px 28px;font-size:clamp(1rem,2.2vw,1.8rem);font-weight:800;white-space:nowrap;display:none;z-index:4;border:3px solid #f4b52c;max-width:96%;overflow:hidden;text-overflow:ellipsis}
  #kredit{background:#0b0820;color:#b9b3e6;text-align:center;font:600 .74rem system-ui,sans-serif;padding:2px 8px;opacity:.85}
  #panels{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:8px 12px;background:#120d2e}
  .tp{border-radius:14px;padding:6px 8px;border:3px solid}
  .tp.t1{border-color:#f6c400;background:linear-gradient(#2b2410,#1a1608)}
  .tp.t2{border-color:#b9783a;background:linear-gradient(#2a1c10,#190f08)}
  .tp h3{margin:0 0 4px;font-size:1.05rem;display:flex;justify-content:space-between;letter-spacing:.06em}
  .tp.t1 h3{color:#ffe36b}.tp.t2 h3{color:#e8b887}
  .cards{display:grid;grid-template-columns:repeat(4,1fr);gap:6px}
  .cd{background:rgba(255,255,255,.08);border-radius:10px;padding:5px 7px;position:relative;min-width:0}
  .cd .r{display:flex;align-items:center;gap:6px}
  .cd .av{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-size:.95rem;flex:none;border:2px solid #fff}
  .cd .nm{font-weight:800;font-size:.86rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.05}
  .cd .rl{font-size:.64rem;opacity:.75;line-height:1}
  .cd .hb{height:11px;border-radius:99px;background:rgba(0,0,0,.5);margin-top:4px;position:relative;overflow:hidden}
  .cd .hb i{display:block;height:100%;width:100%;background:linear-gradient(90deg,#2ecc71,#7bed9f);transition:width .6s}
  .cd .hb i.m{background:linear-gradient(90deg,#f39c12,#f9ca24)}.cd .hb i.k{background:linear-gradient(90deg,#e74c3c,#ff7675)}
  .cd .hb b{position:absolute;inset:0;font-size:.62rem;line-height:11px;text-align:center;font-weight:800;text-shadow:0 1px 1px #000}
  .cd.mati{opacity:.45;filter:grayscale(1)}
  .cd .st{position:absolute;right:5px;top:3px;font-size:.9rem}
  #lobi{position:absolute;top:8px;left:50%;transform:translateX(-50%);width:min(1100px,96%);display:none;grid-template-columns:auto 1fr;gap:16px;background:rgba(255,255,255,.95);color:#1c2436;border-radius:20px;padding:10px 16px;box-shadow:0 10px 30px rgba(0,0,0,.4);z-index:6}
  #lobi .qr{background:#fff;padding:5px;border-radius:12px;border:3px solid #f4b52c;line-height:0}
  #lobi .lh{font-size:1.5rem;color:#2a1a55;font-weight:800}
  #lobi .ll{font-size:.9rem;font-weight:600;margin-bottom:4px;word-break:break-all}
  #lchips{overflow:auto;max-height:90px;display:flex;flex-wrap:wrap;gap:2px}
  #lobi .chip{display:inline-block;background:#fff;border:2px solid #d0d7e5;border-radius:999px;padding:1px 10px;margin:2px;font-size:.92rem;font-weight:700}
  #lobi .chip.p{background:#fff8e1;border-color:#f4b52c}
  #ovl{position:absolute;inset:0;display:none;place-items:center;background:rgba(10,8,32,.78);z-index:6;padding:20px}
  .msgbox{background:#fff;color:#1c2436;border-radius:26px;padding:30px 50px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4)}
  .msgbox .em{font-size:4.5rem}.msgbox h2{margin:.1em 0;font-size:2.4rem}
  #banner{position:absolute;left:50%;top:7%;transform:translateX(-50%) scale(.6);opacity:0;z-index:7;text-align:center;pointer-events:none;transition:all .7s cubic-bezier(.2,1.5,.4,1)}
  #banner.on{opacity:1;transform:translateX(-50%) scale(1)}
  #banner .b1{white-space:nowrap;font-size:clamp(1.8rem,5vw,4.2rem);font-weight:800;color:#fff;text-shadow:0 4px 0 #2a1a55,0 0 30px rgba(0,0,0,.6),-3px -3px 0 #2a1a55,3px -3px 0 #2a1a55;line-height:1}
  #banner .b2{font-size:clamp(1rem,2.4vw,1.9rem);font-weight:800;color:#ffd54f;text-shadow:0 3px 0 #2a1a55,0 0 14px #000;margin-top:4px}
  #flash{position:absolute;inset:0;background:#fff;opacity:0;pointer-events:none;z-index:5}
  #conf{position:absolute;inset:0;pointer-events:none;overflow:hidden;z-index:8}
  .cf{position:absolute;top:-20px;width:12px;height:18px;animation:jatuhcf linear forwards}
  @keyframes jatuhcf{to{transform:translateY(110vh) rotate(720deg)}}
  #ctl{position:fixed;right:10px;bottom:6px;display:flex;gap:8px;z-index:20;opacity:.3}
  #ctl:hover{opacity:1}
  #ctl button{background:#fff;border:0;border-radius:10px;padding:6px 10px;font:700 .85rem system-ui;cursor:pointer}

  /* karakter */
  .pose{transform-origin:0 0}
  .u .pose{animation:bob 2.4s ease-in-out infinite;animation-delay:var(--d,0s)}
  @keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}
  .u.sad .pose{animation:none;transform:translateY(7px) scaleY(.95)}
  .u.sad .head{transform:rotate(26deg) translateY(4px)}
  .u.sad .f-ok,.u.ko .f-ok{display:none}
  .u.sad .f-sad{display:inline}
  .u.ko .f-ko{display:inline}
  .u .head,.u .pose{transition:transform .35s}
  .u.ko .pose{animation:none;transform:rotate(-88deg) translate(0,-4px);filter:grayscale(.85) brightness(.75)}
  .u.ko .lab{opacity:.6}
  .u.stealth .anim{opacity:.2}
  .u.hit .pose{filter:brightness(2.2) saturate(.4)}
  .u .aura,.u .dome{opacity:0;transition:opacity .4s}
  .u.siap .aura{opacity:1}
  .u.dome-on .dome{opacity:1}
  .u.pesta .pose{animation:loncat .7s ease-in-out infinite;animation-delay:var(--d,0s)}
  @keyframes loncat{0%,100%{transform:translateY(0)}50%{transform:translateY(-34px)}}
  .badge{font-size:26px;text-anchor:middle}
  .kedip{animation:kedip 1s ease-in-out infinite}
  @keyframes kedip{50%{opacity:.45}}
  .api{animation:api .35s ease-in-out infinite alternate;transform-origin:0 0}
  @keyframes api{from{transform:scale(1,.9)}to{transform:scale(1.08,1.12)}}
  .awan{animation:awan 90s linear infinite}
  @keyframes awan{from{transform:translateX(-300px)}to{transform:translateX(1900px)}}
</style>
</head><body>
<div id="wrap">
  <div id="head">
    <div><div class="sk"><?= e(setting('nama_sekolah')) ?> · RPG Battle 4 vs 4</div><div class="jd">⚔️ <?= e($g['judul']) ?></div></div>
    <div id="rnd">–</div>
    <div id="tmr">–</div>
  </div>
  <div id="mid">
    <svg id="scene" viewBox="0 0 1600 720" preserveAspectRatio="xMidYMid slice"></svg>
    <div id="flash"></div>
    <div id="lobi"></div>
    <div id="cap"></div>
    <div id="ovl"></div>
    <div id="banner"><div class="b1"></div><div class="b2"></div></div>
    <div id="conf"></div>
  </div>
  <div id="panels">
    <div class="tp t1"><h3><span>🟡 TIM KIRI</span><span id="h1"></span></h3><div class="cards" id="c1"></div></div>
    <div class="tp t2"><h3><span>🟤 TIM KANAN</span><span id="h2"></span></h3><div class="cards" id="c2"></div></div>
  </div>
  <div id="kredit">Dibuat oleh Subrata Pratama, S.Pd. - SMP Kartini 2 Batam</div>
</div>
<div id="ctl"><button id="b-suara">🔇 Aktifkan suara</button><button id="b-fs">⛶ Layar penuh</button></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function(){
  var GAME = <?= (int)$g['id'] ?>, LINK = <?= json_encode($link) ?>;
  var API = <?= json_encode(url('api_rpg.php')) ?>;
  var NS = 'http://www.w3.org/2000/svg';
  var $ = function(i){ return document.getElementById(i); };
  function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function sleep(ms){ return new Promise(function(r){ setTimeout(r, ms); }); }
  var PERAN = {tank:['Tank','🛡️'], assassin:['Assassin','🗡️'], mage:['Mage','🔮'], healer:['Healer','✨']};
  var ORDER = ['tank','assassin','mage','healer'];
  var TIMN = {1:'Kiri', 2:'Kanan'};
  var WARNA = {1:{tank:'#f6c400',assassin:'#4b2a78',mage:'#d32f2f',healer:'#f4f4f8'}, 2:{tank:'#8d5a2b',assassin:'#1a1a1f',mage:'#8fd8f5',healer:'#43a85f'}};
  // posisi formasi tim kiri: tank paling depan, assassin & mage sejajar di belakangnya, healer paling belakang (sejajar tank)
  var FORM = {tank:[672,545], assassin:[505,432], mage:[545,655], healer:[372,545]};
  var TOPH = {tank:178, assassin:152, mage:208, healer:166};   // tinggi karakter (untuk menaruh nama di atas kepala)
  var POS = [];
  ORDER.forEach(function(r, k){
    var f = FORM[r], s = 0.86 + (f[1]-432) / 223 * 0.18;
    POS[k] = {x:f[0], y:f[1], s:s, dir:1, top:TOPH[r]*s};
    POS[4+k] = {x:1600-f[0], y:f[1], s:s, dir:-1, top:TOPH[r]*s};
  });

  // ================= ADEGAN =================
  var PAL = {
    1:{ tank:{main:'#f7c61a',dark:'#a9780a',light:'#ffe680',acc:'#c0392b'},
        assassin:{main:'#3d1d63',dark:'#1d0a36',light:'#8a4fd6',acc:'#d9b3ff'},
        mage:{main:'#d62f2f',dark:'#7f0f0f',light:'#ff7a7a',acc:'#ffd54f'},
        healer:{main:'#fbfbfd',dark:'#b9bdd0',light:'#ffffff',acc:'#f4c542'} },
    2:{ tank:{main:'#8b5a2b',dark:'#4a2b10',light:'#c98f55',acc:'#f1d28a'},
        assassin:{main:'#1a1a22',dark:'#050509',light:'#52525f',acc:'#ff3b3b'},
        mage:{main:'#8fd8f5',dark:'#2f8fbf',light:'#e1f6ff',acc:'#ffffff'},
        healer:{main:'#43b05f',dark:'#1d7535',light:'#a4ecb4',acc:'#fff2a8'} } };
  var SKIN = ['#f1c9a0','#e3b184','#c98d63','#f6d3b0','#a8714a','#e8bf96','#f0c49a','#d29a70'];

  function wajah(p, ey, mata){
    var m = mata || '#1b1b24';
    return '<g class="face">'
      + '<g class="f-ok"><ellipse cx="-8" cy="'+ey+'" rx="3.3" ry="4.6" fill="'+m+'"/><ellipse cx="10" cy="'+ey+'" rx="3.3" ry="4.6" fill="'+m+'"/>'
      + '<circle cx="-7" cy="'+(ey-1.7)+'" r="1.3" fill="#fff"/><circle cx="11" cy="'+(ey-1.7)+'" r="1.3" fill="#fff"/>'
      + '<path d="M-4,'+(ey+11)+' q5,5 10,0" stroke="#7a3b2e" stroke-width="2.4" fill="none" stroke-linecap="round"/>'
      + '<circle cx="-15" cy="'+(ey+7)+'" r="4" fill="#ff8a8a" opacity=".45"/><circle cx="17" cy="'+(ey+7)+'" r="4" fill="#ff8a8a" opacity=".45"/></g>'
      + '<g class="f-sad" style="display:none"><path d="M-13,'+(ey+1)+' q5,4 10,0 M6,'+(ey+1)+' q5,4 10,0" stroke="#1b1b24" stroke-width="2.6" fill="none" stroke-linecap="round"/>'
      + '<path d="M-5,'+(ey+14)+' q6,-6 12,0" stroke="#7a3b2e" stroke-width="2.6" fill="none" stroke-linecap="round"/>'
      + '<path d="M20,'+(ey-6)+' q5,8 0,12 q-5,-4 0,-12z" fill="#8fd8ff"/></g>'
      + '<g class="f-ko" style="display:none"><path d="M-13,'+(ey-4)+' l9,9 m0,-9 l-9,9 M6,'+(ey-4)+' l9,9 m0,-9 l-9,9" stroke="#1b1b24" stroke-width="2.6" stroke-linecap="round"/>'
      + '<ellipse cx="3" cy="'+(ey+13)+'" rx="4" ry="3" fill="#7a3b2e"/></g></g>';
  }
  function kaki(p){
    return '<rect x="-17" y="-44" width="14" height="40" rx="6" fill="'+p.dark+'"/><rect x="3" y="-44" width="14" height="40" rx="6" fill="'+p.dark+'"/>'
      + '<ellipse cx="-9" cy="-3" rx="13" ry="6" fill="#2b2118"/><ellipse cx="13" cy="-3" rx="13" ry="6" fill="#2b2118"/>';
  }
  // lengan depan: bahu di (14,-82). Menunjuk ke bawah-depan; angkat (rotate negatif) saat mengeluarkan skill.
  function lengan(p, kulit, senjata){
    return '<g transform="translate(14,-82)"><g class="arm-f" style="transform-origin:0 0">'
      + '<line x1="0" y1="0" x2="22" y2="20" stroke="'+p.main+'" stroke-width="13" stroke-linecap="round"/>'
      + '<circle cx="23" cy="21" r="7" fill="'+kulit+'"/>' + (senjata||'') + '</g></g>';
  }
  function lenganBelakang(p, kulit, senjata){
    return '<g transform="translate(-14,-82)"><g class="arm-b" style="transform-origin:0 0">'
      + '<line x1="0" y1="0" x2="-14" y2="26" stroke="'+p.dark+'" stroke-width="12" stroke-linecap="round"/>'
      + '<circle cx="-15" cy="27" r="6.5" fill="'+kulit+'"/>' + (senjata||'') + '</g></g>';
  }
  function kepala(kulit, rambutDepan, atas, ey, mata, bawah){
    return '<g transform="translate(0,-92)"><g class="head" style="transform-origin:0 0">'
      + '<circle cx="0" cy="-22" r="25" fill="'+kulit+'"/>'
      + (rambutDepan||'') + wajah(null, ey, mata) + (bawah||'') + (atas||'') + '</g></g>';
  }

  function gambarKarakter(role, tim, idx){
    var p = PAL[tim][role], kulit = SKIN[(idx*3 + tim) % SKIN.length], s = '';
    if (role === 'tank') {
      var perisai = '<g transform="translate(14,2)"><circle r="31" fill="'+p.dark+'"/><circle r="26" fill="'+p.light+'" stroke="'+p.dark+'" stroke-width="3"/>'
        + '<circle r="8" fill="'+p.acc+'" stroke="'+p.dark+'" stroke-width="2"/><path d="M0,-24 L0,24 M-24,0 L24,0" stroke="'+p.dark+'" stroke-width="4" opacity=".55"/></g>';
      var pedang = '<g transform="rotate(8)"><rect x="-4" y="26" width="8" height="12" fill="#6b4a2b"/><rect x="-12" y="22" width="24" height="6" rx="2" fill="'+p.dark+'"/>'
        + '<path d="M-5,22 L0,-30 L5,22Z" fill="#e8eef5" stroke="#9aa6b2" stroke-width="2"/></g>';
      s += lenganBelakang(p, kulit, pedang) + kaki(p)
        + '<path d="M-30,-46 L-26,-96 Q0,-106 26,-96 L30,-46 Z" fill="'+p.main+'" stroke="'+p.dark+'" stroke-width="3.5"/>'
        + '<path d="M-19,-92 L19,-92 L15,-62 L-15,-62Z" fill="'+p.light+'" opacity=".55"/>'
        + '<rect x="-30" y="-60" width="60" height="9" fill="'+p.dark+'"/><circle cx="0" cy="-56" r="7" fill="'+p.acc+'" stroke="'+p.dark+'" stroke-width="2"/>'
        + '<circle cx="-28" cy="-92" r="13" fill="'+p.main+'" stroke="'+p.dark+'" stroke-width="3"/><circle cx="28" cy="-92" r="13" fill="'+p.main+'" stroke="'+p.dark+'" stroke-width="3"/>'
        + kepala(kulit, '',
            '<path d="M-26,-24 A26,26 0 0 1 26,-24 L26,-15 L-26,-15Z" fill="'+p.main+'" stroke="'+p.dark+'" stroke-width="3.5"/>'
            + '<rect x="-4" y="-22" width="8" height="20" rx="3" fill="'+p.dark+'"/>'
            + '<path d="M-3,-50 Q4,-78 30,-70 Q14,-62 12,-50Z" fill="'+p.acc+'" stroke="'+p.dark+'" stroke-width="2"/>', -20)
        + lengan(p, kulit, perisai);
    } else if (role === 'assassin') {
      var belati = '<g transform="translate(24,22) rotate(30)"><rect x="-3" y="0" width="6" height="12" fill="#3a2a1a"/><path d="M-7,0 L0,-30 L7,0Z" fill="#e9eef5" stroke="#a9b5c1" stroke-width="2"/></g>';
      var belati2 = '<g transform="translate(-14,28) rotate(-25)"><rect x="-3" y="0" width="6" height="12" fill="#3a2a1a"/><path d="M-7,0 L0,-26 L7,0Z" fill="#cfd6de" stroke="#8a95a0" stroke-width="2"/></g>';
      s += '<g class="aura"><ellipse cx="0" cy="-70" rx="46" ry="76" fill="'+p.light+'" opacity=".35"/><ellipse cx="0" cy="-70" rx="34" ry="62" fill="'+p.acc+'" opacity=".25"/></g>'
        + lenganBelakang(p, kulit, belati2) + kaki(p)
        + '<path d="M-14,-96 Q-40,-92 -52,-70 Q-34,-76 -16,-76Z" fill="'+p.acc+'" opacity=".9"/>'
        + '<path d="M-19,-46 L-16,-96 Q0,-102 16,-96 L19,-46Z" fill="'+p.main+'" stroke="'+p.dark+'" stroke-width="3"/>'
        + '<path d="M-17,-94 L16,-62 M17,-94 L-16,-62" stroke="'+p.light+'" stroke-width="4" opacity=".7"/>'
        + '<rect x="-19" y="-58" width="38" height="7" fill="'+p.dark+'"/><rect x="-4" y="-59" width="8" height="9" fill="'+p.acc+'"/>'
        + kepala(kulit, '',
            '<path d="M-24,-14 Q0,-6 24,-14 L24,-2 Q0,6 -24,-2Z" fill="'+p.dark+'"/>'
            + '<path d="M-28,-14 Q-30,-52 0,-54 Q30,-52 28,-14 Q18,-34 0,-34 Q-18,-34 -28,-14Z" fill="'+p.main+'" stroke="'+p.dark+'" stroke-width="3"/>'
            + '<path d="M-14,-34 Q0,-27 14,-34" stroke="'+p.light+'" stroke-width="2" fill="none" opacity=".6"/>', -20, p.acc)
        + lengan(p, kulit, belati);
    } else if (role === 'mage') {
      var orb = tim === 1 ? '#ff7043' : '#80deea';
      var tongkat = '<g transform="translate(2,0)"><line x1="4" y1="30" x2="12" y2="-92" stroke="#6b4a2b" stroke-width="6" stroke-linecap="round"/>'
        + '<circle cx="12" cy="-100" r="19" fill="'+orb+'" opacity=".35"/><circle cx="12" cy="-100" r="11" fill="'+orb+'" stroke="#fff" stroke-width="2"/>'
        + '<circle cx="9" cy="-104" r="3.5" fill="#fff" opacity=".8"/></g>';
      s += lenganBelakang(p, kulit, '') + kaki(p)
        + '<path d="M-38,-6 L-22,-96 Q0,-103 22,-96 L38,-6 Q0,8 -38,-6Z" fill="'+p.main+'" stroke="'+p.dark+'" stroke-width="3.5"/>'
        + '<path d="M-37,-8 Q0,6 37,-8 L38,-1 Q0,13 -38,-1Z" fill="'+p.acc+'" stroke="'+p.dark+'" stroke-width="2"/>'
        + '<path d="M-26,-70 Q0,-60 26,-70 L24,-58 Q0,-48 -24,-58Z" fill="'+p.dark+'" opacity=".5"/>'
        + '<rect x="-24" y="-62" width="48" height="8" fill="'+p.acc+'" stroke="'+p.dark+'" stroke-width="2"/>'
        + '<path d="M-10,-96 L0,-80 L10,-96" fill="'+p.light+'" stroke="'+p.dark+'" stroke-width="2"/>'
        + kepala(kulit, '<path d="M-25,-22 Q-30,-2 -20,6 Q-24,-12 -19,-26Z M25,-22 Q30,-2 20,6 Q24,-12 19,-26Z" fill="#5b3a21"/>',
            '<ellipse cx="0" cy="-42" rx="40" ry="9" fill="'+p.main+'" stroke="'+p.dark+'" stroke-width="3.5"/>'
            + '<path d="M-25,-44 L-4,-98 Q2,-108 14,-114 L16,-92 Q22,-64 25,-44Z" fill="'+p.main+'" stroke="'+p.dark+'" stroke-width="3.5"/>'
            + '<path d="M-26,-48 Q0,-40 26,-48 L25,-42 Q0,-34 -26,-42Z" fill="'+p.acc+'" stroke="'+p.dark+'" stroke-width="1.5"/>'
            + '<path d="M2,-76 l3,7 7,1 -5,5 1,7 -6,-4 -6,4 1,-7 -5,-5 7,-1z" fill="'+p.acc+'"/>', -20)
        + lengan(p, kulit, tongkat);
    } else {
      var tongkat2 = '<g transform="translate(2,0)"><line x1="4" y1="30" x2="12" y2="-96" stroke="#b08a4a" stroke-width="6" stroke-linecap="round"/>'
        + '<circle cx="12" cy="-104" r="20" fill="'+p.acc+'" opacity=".4"/><circle cx="12" cy="-104" r="11" fill="'+p.light+'" stroke="'+p.acc+'" stroke-width="3"/>'
        + '<path d="M12,-111 v14 M5,-104 h14" stroke="'+p.dark+'" stroke-width="3" stroke-linecap="round"/></g>';
      s += lenganBelakang(p, kulit, '') + kaki(p)
        + '<path d="M-34,-6 L-22,-96 Q0,-103 22,-96 L34,-6 Q0,8 -34,-6Z" fill="'+p.main+'" stroke="'+p.dark+'" stroke-width="3.5"/>'
        + '<path d="M-33,-8 Q0,6 33,-8 L34,-1 Q0,12 -34,-1Z" fill="'+p.acc+'" stroke="'+p.dark+'" stroke-width="2"/>'
        + '<path d="M-12,-96 L0,-50 L12,-96" fill="'+p.acc+'" opacity=".85"/>'
        + '<path d="M0,-84 v22 M-9,-74 h18" stroke="'+p.dark+'" stroke-width="4" stroke-linecap="round" opacity=".75"/>'
        + '<rect x="-22" y="-60" width="44" height="7" fill="'+p.acc+'" stroke="'+p.dark+'" stroke-width="1.5"/>'
        + kepala(kulit, '', '<path d="M-26,-26 Q-30,-52 0,-50 Q30,-52 26,-26 Q14,-40 0,-38 Q-14,-40 -26,-26Z" fill="#6b3f1f"/>'
            + '<path d="M-24,-20 Q-32,0 -22,12 Q-27,-4 -21,-22Z M24,-20 Q32,0 22,12 Q27,-4 21,-22Z" fill="#6b3f1f"/>'
            + '<ellipse cx="0" cy="-58" rx="21" ry="6" fill="none" stroke="'+p.acc+'" stroke-width="4"/><ellipse cx="0" cy="-58" rx="21" ry="6" fill="none" stroke="#fff6c0" stroke-width="1.5" opacity=".8"/>', -20)
        + lengan(p, kulit, tongkat2);
    }
    return s;
  }

  function mk(tag, attrs, inner){
    var e = document.createElementNS(NS, tag);
    if (attrs) for (var k in attrs) e.setAttribute(k, attrs[k]);
    if (inner != null) e.innerHTML = inner;
    return e;
  }

  function bangunAdegan(){
    var sc = $('scene');
    var defs = '<defs>'
      + '<linearGradient id="gLangit" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#150f3a"/><stop offset=".45" stop-color="#5a2a7a"/><stop offset=".72" stop-color="#e0605a"/><stop offset="1" stop-color="#ffb066"/></linearGradient>'
      + '<linearGradient id="gTanah" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4b3d5e"/><stop offset="1" stop-color="#241d30"/></linearGradient>'
      + '<radialGradient id="gArena" cx=".5" cy=".5" r=".6"><stop offset="0" stop-color="#8a7a9a"/><stop offset="1" stop-color="#4a3f5c"/></radialGradient>'
      + '<radialGradient id="gMatahari"><stop offset="0" stop-color="#fff2b8"/><stop offset="1" stop-color="#ffb347"/></radialGradient>'
      + '<radialGradient id="gApi"><stop offset="0" stop-color="#fff59d"/><stop offset=".5" stop-color="#ffb300"/><stop offset="1" stop-color="#e64a19" stop-opacity="0"/></radialGradient>'
      + '</defs>';
    var s = defs + '<rect x="0" y="0" width="1600" height="720" fill="url(#gLangit)"/><g transform="translate(0,70)">';
    for (var i = 0; i < 34; i++) s += '<circle cx="'+((i*197)%1600)+'" cy="'+((i*83)%230+10)+'" r="'+(1+(i%3)*.7)+'" fill="#fff" opacity="'+(.35+(i%4)*.15)+'"/>';
    s += '<circle cx="800" cy="330" r="120" fill="#ffd27a" opacity=".25"/><circle cx="800" cy="330" r="78" fill="url(#gMatahari)"/>'
      + '<g class="awan" opacity=".55"><ellipse cx="0" cy="130" rx="110" ry="24" fill="#c9a0e8"/><ellipse cx="70" cy="118" rx="70" ry="26" fill="#d9b8f0"/></g>'
      + '<g class="awan" style="animation-delay:-45s" opacity=".4"><ellipse cx="0" cy="200" rx="90" ry="20" fill="#e8b0c8"/><ellipse cx="55" cy="190" rx="55" ry="22" fill="#f0c8d8"/></g>'
      + '<path d="M0,380 L120,300 L230,360 L360,270 L500,370 L640,310 L760,380 L900,300 L1040,365 L1180,280 L1320,355 L1450,295 L1600,370 L1600,420 L0,420Z" fill="#3b2260"/>'
      + '<path d="M0,410 L160,350 L300,400 L470,340 L620,405 L800,370 L980,410 L1130,345 L1290,400 L1440,350 L1600,405 L1600,440 L0,440Z" fill="#2b1848"/>'
      // kastil siluet
      + '<g fill="#1c1030"><rect x="150" y="300" width="60" height="120"/><rect x="215" y="330" width="45" height="90"/><path d="M144,300 L180,250 L216,300Z"/><path d="M212,330 L238,290 L264,330Z"/><rect x="110" y="340" width="40" height="80"/>'
      + '<rect x="1390" y="300" width="60" height="120"/><rect x="1335" y="330" width="45" height="90"/><path d="M1384,300 L1420,250 L1456,300Z"/><path d="M1332,330 L1358,290 L1384,330Z"/><rect x="1450" y="340" width="40" height="80"/></g>'
      + '<g fill="#ffd27a" opacity=".85"><rect x="172" y="330" width="9" height="14"/><rect x="1412" y="330" width="9" height="14"/><rect x="226" y="360" width="8" height="12"/><rect x="1362" y="360" width="8" height="12"/></g>'
      + '<rect x="0" y="420" width="1600" height="300" fill="url(#gTanah)"/>'
      + '<ellipse cx="800" cy="508" rx="760" ry="136" fill="#1a1325" opacity=".5"/>'
      + '<ellipse cx="800" cy="500" rx="730" ry="128" fill="url(#gArena)" stroke="#2a2036" stroke-width="6"/>'
      + '<ellipse cx="800" cy="500" rx="690" ry="116" fill="none" stroke="#c9b8e0" stroke-width="2" opacity=".35"/>'
      + '<ellipse cx="520" cy="506" rx="290" ry="80" fill="#f6c400" opacity=".10" stroke="#f6c400" stroke-width="3" stroke-dasharray="14 10" />'
      + '<ellipse cx="1080" cy="506" rx="290" ry="80" fill="#b9783a" opacity=".12" stroke="#c98f55" stroke-width="3" stroke-dasharray="14 10"/>'
      + '<ellipse cx="520" cy="506" rx="200" ry="52" fill="none" stroke="#ffe680" stroke-width="2" opacity=".35"/>'
      + '<ellipse cx="1080" cy="506" rx="200" ry="52" fill="none" stroke="#e8b887" stroke-width="2" opacity=".35"/>'
      + '<line x1="800" y1="400" x2="800" y2="610" stroke="#fff" stroke-width="4" stroke-dasharray="12 12" opacity=".35"/>';
    // lantai batu
    for (var k = 0; k < 9; k++) s += '<path d="M'+(100+k*175)+',610 L'+(560+k*60)+',420" stroke="#2a2036" stroke-width="2" opacity=".25"/>';
    // obor
    [90, 1510].forEach(function(x){
      s += '<g transform="translate('+x+',470)"><rect x="-5" y="-110" width="10" height="110" fill="#5a4030"/><rect x="-14" y="-122" width="28" height="16" rx="4" fill="#3a2a20"/>'
        + '<circle cx="0" cy="-140" r="46" fill="url(#gApi)" opacity=".6"/><path class="api" d="M0,-166 Q18,-146 12,-128 Q0,-120 -12,-128 Q-18,-146 0,-166Z" fill="#ff9800"/><path class="api" d="M0,-154 Q9,-142 6,-130 Q0,-126 -6,-130 Q-9,-142 0,-154Z" fill="#fff176"/></g>';
    });
    s += '</g>';
    // spanduk tim
    s += '<g transform="translate(400,0)"><rect x="-4" y="0" width="8" height="30" fill="#3a2a20"/><path d="M-60,26 L60,26 L60,100 L0,126 L-60,100Z" fill="#f6c400" stroke="#a9780a" stroke-width="5"/><circle cx="0" cy="64" r="22" fill="#a9780a"/><path d="M-12,70 L0,48 L12,70Z" fill="#ffe680"/></g>'
      + '<g transform="translate(1200,0)"><rect x="-4" y="0" width="8" height="30" fill="#3a2a20"/><path d="M-60,26 L60,26 L60,100 L0,126 L-60,100Z" fill="#8b5a2b" stroke="#4a2b10" stroke-width="5"/><circle cx="0" cy="64" r="22" fill="#4a2b10"/><path d="M-12,56 L12,56 L0,78Z" fill="#f1d28a"/></g>';
    sc.innerHTML = s;
    // lapisan karakter, efek
    var units = mk('g', {id:'units'}); sc.appendChild(units);
    sc.appendChild(mk('g', {id:'fx'}));
    var urut = POS.map(function(p, i){ return i; }).sort(function(a, b){ return POS[a].y - POS[b].y; });
    urut.forEach(function(i){
      var p = POS[i], role = ORDER[i % 4], tim = i < 4 ? 1 : 2;
      var g = mk('g', {id:'u'+i, 'class':'u', transform:'translate('+p.x+','+p.y+')'});
      g.style.setProperty('--d', (-(i*0.37)).toFixed(2)+'s');
      g.innerHTML =
        '<ellipse cx="0" cy="3" rx="'+(44*p.s)+'" ry="'+(11*p.s)+'" fill="rgba(0,0,0,.35)"/>'
        + '<g transform="scale('+(p.dir*p.s)+','+p.s+')"><g class="anim"><g class="pose">'
        + '<g class="dome"><ellipse cx="0" cy="-70" rx="62" ry="86" fill="#ffe680" opacity=".25" stroke="#fff3b0" stroke-width="4"/><ellipse cx="0" cy="-70" rx="52" ry="76" fill="none" stroke="#ffd54f" stroke-width="2" opacity=".7"/></g>'
        + gambarKarakter(role, tim, i)
        + '</g></g></g>'
        + '<g class="lab" transform="translate(0,'+(-p.top-4)+') scale(.88)"><rect x="-62" y="-30" width="124" height="34" rx="12" fill="rgba(10,8,32,.85)" stroke="'+(tim===1?'#f6c400':'#c98f55')+'" stroke-width="2.5"/>'
        + '<text class="nmt" x="0" y="-12" text-anchor="middle" font-family="Baloo 2,sans-serif" font-weight="800" font-size="16" fill="#fff"></text>'
        + '<rect x="-52" y="-4" width="104" height="7" rx="3.5" fill="#000" opacity=".6"/><rect class="hpm" x="-52" y="-4" width="104" height="7" rx="3.5" fill="#2ecc71" style="transition:width .6s"/></g>'
        + '<g class="bdg" transform="translate(0,'+(-p.top-40)+')"><text class="badge" x="0" y="0"></text></g>';
      units.appendChild(g);
    });
  }

  // ================= KARTU HP =================
  function bangunKartu(){
    [1,2].forEach(function(t){
      var h = '';
      ORDER.forEach(function(r, k){
        var i = (t-1)*4 + k;
        h += '<div class="cd" id="cd'+i+'"><div class="r"><div class="av" style="background:'+WARNA[t][r]+'">'+PERAN[r][1]+'</div><div style="min-width:0"><div class="nm">–</div><div class="rl">'+PERAN[r][0]+'</div></div></div>'
          + '<div class="hb"><i style="width:100%"></i><b></b></div><span class="st"></span></div>';
      });
      $('c'+t).innerHTML = h;
    });
  }

  // ================= STATE TAMPILAN =================
  var S = { j:null, k:null, anim:0, hp:[], nama:[], sisa:null, t0:0, tikTerakhir:-1, animJalan:false, lobiDibuat:false, lk:'' };
  function pct(hp, mx){ return Math.max(0, Math.min(100, mx ? hp/mx*100 : 0)); }
  function hpk(p){ return p < 25 ? 'k' : p < 55 ? 'm' : ''; }
  function nm(i){ var u = S.j && S.j.unit[i]; return (u && u.nama) ? u.nama : PERAN[ORDER[i%4]][0]; }
  function lab(i){ return nm(i) + ' (' + PERAN[ORDER[i%4]][0] + ' ' + TIMN[i<4?1:2] + ')'; }
  function timNama(i){ return 'Tim ' + TIMN[i<4?1:2]; }

  function setHP(i, hp){
    var u = S.j.unit[i], p = pct(hp, u.mx);
    S.hp[i] = hp;
    var c = $('cd'+i), b = c.querySelector('.hb i');
    b.style.width = p + '%'; b.className = hpk(p);
    var m = $('u'+i).querySelector('.hpm'); m.setAttribute('width', 104*p/100); m.setAttribute('fill', p < 25 ? '#ff5252' : p < 55 ? '#f9ca24' : '#2ecc71');
    c.querySelector('.hb b').textContent = hp + ' / ' + u.mx;
    c.classList.toggle('mati', hp <= 0);
  }
  function setNama(i){
    var u = S.j.unit[i], n = u.nama || '—';
    var el = $('u'+i); el.querySelector('.nmt').textContent = n.length > 12 ? n.slice(0, 11) + '…' : n;
    var c = $('cd'+i); c.querySelector('.nm').textContent = u.nama || '(kosong)';
    el.style.opacity = u.nama ? 1 : .35;
  }
  function snap(j){
    for (var i = 0; i < 8; i++){
      var u = j.unit[i], el = $('u'+i);
      setNama(i); setHP(i, u.hp);
      el.classList.toggle('ko', !u.hidup);
      el.classList.toggle('siap', !!u.siap && u.hidup);
      $('cd'+i).classList.toggle('mati', !u.hidup);
    }
    $('h1').textContent = j.hidup[1] + ' / 4 hidup'; $('h2').textContent = j.hidup[2] + ' / 4 hidup';
  }
  function bersih(){
    for (var i = 0; i < 8; i++){
      var el = $('u'+i);
      ['sad','stealth','dome-on','hit','pesta'].forEach(function(c){ el.classList.remove(c); });
    }
    $('fx').innerHTML = '';
  }
  function lencana(j, mode){
    for (var i = 0; i < 8; i++){
      var u = j.unit[i], t = '';
      if (u.hidup && u.nama) {
        if (mode === 'pilih') t = u.kunci ? '✅' : '🤔';
        else if (mode === 'soal') t = u.sudah ? '✅' : '✏️';
      }
      var b = $('u'+i).querySelector('.badge'); b.textContent = t; b.setAttribute('class', 'badge' + (t === '🤔' || t === '✏️' ? ' kedip' : ''));
      var st = $('cd'+i).querySelector('.st'); st.textContent = t;
    }
  }

  // ================= EFEK =================
  var fxg;
  function wa(el, kf, opt){
    try { var a = el.animate(kf, opt); return a.finished.catch(function(){}); } catch(e){ return Promise.resolve(); }
  }
  function kepalaXY(i){ return {x: POS[i].x, y: POS[i].y - 130*POS[i].s}; }
  function tengahXY(i){ return {x: POS[i].x, y: POS[i].y - 70*POS[i].s}; }
  function teks(x, y, t, warna, ukuran, dur){
    var e = mk('text', {x:x, y:y, 'text-anchor':'middle', 'font-family':'Baloo 2,sans-serif', 'font-weight':'800', 'font-size':ukuran||34, fill:warna||'#fff', stroke:'#1a1030', 'stroke-width':ukuran>40?6:5, 'paint-order':'stroke'});
    e.textContent = t; fxg.appendChild(e);
    wa(e, [{transform:'translateY(0)', opacity:0},{opacity:1, offset:.15},{transform:'translateY(-70px)', opacity:1, offset:.7},{transform:'translateY(-92px)', opacity:0}], {duration:dur||1600, easing:'ease-out'}).then(function(){ e.remove(); });
  }
  function ledakan(x, y, warna, n, jari, ukuran){
    for (var i = 0; i < (n||10); i++){
      var a = Math.random()*Math.PI*2, r = (jari||60)*(.5+Math.random()*.7);
      var c = mk('circle', {cx:x, cy:y, r:(ukuran||6)*(.5+Math.random()), fill:Array.isArray(warna)?warna[i%warna.length]:warna});
      fxg.appendChild(c);
      (function(c, dx, dy){ wa(c, [{transform:'translate(0,0) scale(1)', opacity:1},{transform:'translate('+dx+'px,'+dy+'px) scale(.2)', opacity:0}], {duration:600+Math.random()*400, easing:'ease-out'}).then(function(){ c.remove(); }); })(c, Math.cos(a)*r, Math.sin(a)*r*.8);
    }
  }
  function cincin(x, y, warna, rx, ry, dur){
    var e = mk('ellipse', {cx:x, cy:y, rx:rx||40, ry:ry||14, fill:'none', stroke:warna, 'stroke-width':6});
    fxg.appendChild(e);
    wa(e, [{transform:'scale(.2)', opacity:.9, transformOrigin:x+'px '+y+'px'},{transform:'scale(1.8)', opacity:0, transformOrigin:x+'px '+y+'px'}], {duration:dur||900, easing:'ease-out'}).then(function(){ e.remove(); });
  }
  function terbang(x0, y0, x1, y1, markup, dur, ease){
    var g = mk('g', null, markup); fxg.appendChild(g);
    return wa(g, [{transform:'translate('+x0+'px,'+y0+'px)'},{transform:'translate('+x1+'px,'+y1+'px)'}], {duration:dur, easing:ease||'ease-in', fill:'forwards'}).then(function(){ g.remove(); });
  }
  function kilat(w, dur){
    var f = $('flash'); f.style.background = w || '#fff';
    wa(f, [{opacity:0},{opacity:.55},{opacity:0}], {duration:dur||350});
  }
  function pose(i, ms){
    var a = $('u'+i).querySelector('.arm-f');
    return wa(a, [{transform:'rotate(0deg)'},{transform:'rotate(-125deg)', offset:.25},{transform:'rotate(-125deg)', offset:.75},{transform:'rotate(0deg)'}], {duration:ms||1000, easing:'ease-in-out'});
  }
  function gerak(i, kf, opt){ return wa($('u'+i).querySelector('.anim'), kf, opt); }
  function tabrak(i){   // dikenai serangan: kilat putih + getar
    var el = $('u'+i); el.classList.add('hit'); setTimeout(function(){ el.classList.remove('hit'); }, 260);
    return gerak(i, [{transform:'translateX(0)'},{transform:'translateX(-16px)'},{transform:'translateX(11px)'},{transform:'translateX(-7px)'},{transform:'translateX(0)'}], {duration:440});
  }
  function arah(i){ return POS[i].dir; }
  // gerakan lokal (sumbu x lokal menghadap lawan) menuju titik global
  function keTarget(i, t, jarak){
    var dx = (POS[t].x - POS[i].x) * arah(i) / POS[i].s, dy = (POS[t].y - POS[i].y) / POS[i].s;
    var sisi = (jarak || 80) / POS[i].s;
    return {dx: dx - sisi, dy: dy};
  }
  function hantam(e, t, warna){
    var el = $('u'+t.u);
    if (t.bl) { teks(kepalaXY(t.u).x, kepalaXY(t.u).y - 10, 'MELESET!', '#cfd8e8', 30); return; }
    if (t.sdh) { teks(kepalaXY(t.u).x, kepalaXY(t.u).y, '…', '#ccc', 30); return; }
    tabrak(t.u);
    var k = kepalaXY(t.u), m = tengahXY(t.u);
    ledakan(m.x, m.y, warna || ['#fff','#ffd54f','#ff8a65'], 12, 70);
    var dm = t.r != null ? t.r : t.d;
    teks(k.x, k.y - 6, (t.pr ? '🛡️ ' : '') + '−' + dm, t.pr ? '#9fe3ff' : '#ff5252', Math.min(64, 34 + dm * .25));
    setHP(t.u, t.hp);
    sfx.hantam();
  }

  // ================= SUARA =================
  var ctx = null, suaraOn = false;
  function ac(){ if (!ctx) { try { ctx = new (window.AudioContext||window.webkitAudioContext)(); } catch(e){} } return ctx; }
  function nada(f, d, tipe, vol, tunda, f2){
    if (!suaraOn || !ac()) return;
    var t = ctx.currentTime + (tunda||0), o = ctx.createOscillator(), g = ctx.createGain();
    o.type = tipe||'sine'; o.frequency.setValueAtTime(f, t);
    if (f2) o.frequency.exponentialRampToValueAtTime(f2, t+d);
    g.gain.setValueAtTime(vol||.15, t); g.gain.exponentialRampToValueAtTime(.001, t+d);
    o.connect(g); g.connect(ctx.destination); o.start(t); o.stop(t+d+.02);
  }
  function derau(d, vol, tunda, fr){
    if (!suaraOn || !ac()) return;
    var n = ctx.sampleRate*d, b = ctx.createBuffer(1,n,ctx.sampleRate), a = b.getChannelData(0);
    for (var i=0;i<n;i++) a[i] = (Math.random()*2-1) * (1 - i/n);
    var s = ctx.createBufferSource(), f = ctx.createBiquadFilter(), g = ctx.createGain();
    s.buffer = b; f.type='bandpass'; f.frequency.value = fr||1200; g.gain.value = vol||.25;
    s.connect(f); f.connect(g); g.connect(ctx.destination); s.start(ctx.currentTime+(tunda||0));
  }
  var sfx = {
    mulai: function(){ nada(392,.18,'triangle',.2); nada(523,.18,'triangle',.2,.2); nada(784,.5,'triangle',.22,.4); },
    tik: function(){ nada(900,.07,'square',.07); },
    pilih: function(){ nada(660,.1,'sine',.15); nada(880,.15,'sine',.15,.1); },
    hantam: function(){ nada(160,.18,'sawtooth',.2,0,60); derau(.15,.25,0,900); },
    tebas: function(){ derau(.25,.22,0,3000); nada(1200,.2,'sawtooth',.06,0,300); },
    heal: function(){ [660,880,1100].forEach(function(f,i){ nada(f,.35,'sine',.12,i*.12); }); },
    perisai: function(){ nada(300,.4,'square',.1,0,600); nada(600,.4,'triangle',.12,.1); },
    meteor: function(){ nada(900,.8,'sawtooth',.1,0,80); derau(.7,.3,.5,500); },
    es: function(){ nada(1500,.4,'triangle',.1,0,500); derau(.4,.2,.4,4000); },
    kutuk: function(){ nada(120,.9,'sawtooth',.12,0,60); nada(90,.9,'sine',.15,.1); },
    bayang: function(){ nada(700,.5,'sine',.1,0,150); },
    gagal: function(){ nada(300,.5,'triangle',.15,0,120); },
    ko: function(){ nada(400,.7,'sawtooth',.14,0,50); derau(.3,.2,.4,300); },
    menang: function(){ [523,659,784,1047].forEach(function(f,i){ nada(f,.35,'triangle',.2,i*.16); }); nada(1047,.9,'triangle',.2,.7); derau(2,.25,.2,2500); },
    seri: function(){ nada(440,.3,'triangle',.2); nada(440,.5,'triangle',.2,.35); }
  };
  $('b-suara').onclick = function(){ suaraOn = !suaraOn; if (suaraOn) { ac(); if (ctx && ctx.resume) ctx.resume(); sfx.tik(); } this.textContent = suaraOn ? '🔊 Suara aktif' : '🔇 Aktifkan suara'; };
  $('b-fs').onclick = function(){ var d = document.documentElement; if (document.fullscreenElement) document.exitFullscreen(); else if (d.requestFullscreen) d.requestFullscreen(); };

  // ================= ANIMASI AKSI =================
  function cap(t){ var c = $('cap'); if (!t) { c.style.display = 'none'; return; } c.textContent = t; c.style.display = 'block'; }
  var NAMASKILL = {
    tank:{basic:'Serangan Dasar', s1:'Perisai Pelindung', s2:'Benteng Tim'},
    healer:{basic:'Serangan Dasar', s1:'Penyembuhan', s2:'Hujan Cahaya'},
    assassin:{basic:'Serangan Dasar', s1:'Tusukan Mematikan', s2:'Bayangan', strike:'Serangan Bayangan'},
    mage:{basic:'Serangan Dasar', s1:null, s2:'Kutukan'}
  };
  function namaSkill(i, s){
    var r = ORDER[i%4];
    if (r === 'mage' && s === 's1') return i < 4 ? 'Hujan Meteor' : 'Badai Es';
    return NAMASKILL[r][s] || s;
  }

  var AKSI = {};
  AKSI.perisai = function(e){
    cap('🛡️ ' + lab(e.u) + ' memakai ' + (e.m === 'semua' ? 'Benteng Tim!' : 'Perisai Pelindung untuk ' + nm(e.t) + '!'));
    sfx.perisai();
    var tl = e.m === 'semua' ? [0,1,2,3].map(function(k){ return (e.u < 4 ? 0 : 4) + k; }) : [e.t];
    var p = pose(e.u, 900);
    tl.forEach(function(t, k){ setTimeout(function(){
      { $('u'+t).classList.add('dome-on'); cincin(POS[t].x, POS[t].y - 60*POS[t].s, '#ffd54f', 50, 70, 700); }
    }, 250 + k*120); });
    return Promise.all([p, sleep(1500)]);
  };
  AKSI.bayangan = function(e){
    cap('👤 ' + lab(e.u) + ' menghilang ke dalam bayangan…');
    sfx.bayang();
    var m = tengahXY(e.u);
    ledakan(m.x, m.y, ['#7b3fc4','#1d0a36','#3a3a48','#b388ff'], 16, 80, 9);
    $('u'+e.u).classList.add('stealth'); $('u'+e.u).classList.add('siap');
    return sleep(1500);
  };
  AKSI.kutuk = function(e){
    var lawan = e.u < 4 ? 4 : 0, cx = lawan ? 1160 : 440;
    cap('💀 ' + lab(e.u) + ' melempar kutukan ke ' + 'Tim ' + TIMN[lawan ? 2 : 1] + '! (siapa yang terkena dirahasiakan)');
    sfx.kutuk();
    var k = kepalaXY(e.u), p = pose(e.u, 900);
    var orb = '<circle r="26" fill="#6a1b9a" opacity=".5"/><circle r="16" fill="#4a148c" stroke="#e1bee7" stroke-width="3"/><text y="8" text-anchor="middle" font-size="22">💀</text>';
    setTimeout(function(){
      terbang(POS[e.u].x + 40*arah(e.u), POS[e.u].y - 130*POS[e.u].s, cx, 440, orb, 800, 'ease-in-out').then(function(){
        ledakan(cx, 470, ['#6a1b9a','#311b92','#b388ff','#000'], 26, 150, 10); sfx.kutuk();
        for (var q = 0; q < 4; q++) (function(q){ setTimeout(function(){ teks(cx - 130 + q*88, 470 - (q%2)*30, '💀', '#fff', 36, 1200); }, q*80); })(q);
      });
    }, 350);
    return Promise.all([p, sleep(2100)]);
  };
  AKSI.gagal = function(e){
    var el = $('u'+e.u);
    cap('😞 ' + lab(e.u) + ' gagal! Tertunduk kecewa…');
    sfx.gagal();
    el.classList.add('sad');
    var k = kepalaXY(e.u);
    teks(k.x, k.y - 20, 'GAGAL…', '#b0bec5', 30, 1800);
    return sleep(900).then(function(){ return sleep(900); }).then(function(){ el.classList.remove('sad'); });
  };
  AKSI.heal = function(e){
    cap('💚 ' + lab(e.u) + ' memakai ' + (e.m === 'semua' ? 'Hujan Cahaya untuk seluruh timnya!' : 'Penyembuhan untuk ' + (e.h[0] ? nm(e.h[0].u) : '—') + '!'));
    sfx.heal();
    var p = pose(e.u, 1000);
    if (e.m === 'semua') { var c = tengahXY(e.u < 4 ? 1 : 5); cincin(e.u < 4 ? 520 : 1080, 506, '#69f0ae', 260, 70, 1200); }
    e.h.forEach(function(h, k){
      setTimeout(function(){
        var m = tengahXY(h.u);
        for (var q = 0; q < 6; q++) (function(q){ var x = m.x - 30 + q*12, c = mk('text', {x:x, y:m.y + 30, 'text-anchor':'middle', 'font-size':22, fill:'#69f0ae', stroke:'#0a3d1a', 'stroke-width':2, 'paint-order':'stroke', 'font-weight':'800'}); c.textContent = q % 2 ? '✚' : '✦'; fxg.appendChild(c);
          wa(c, [{transform:'translateY(0)', opacity:0},{opacity:1, offset:.2},{transform:'translateY(-90px)', opacity:0}], {duration:1100 + q*60, delay:q*60, easing:'ease-out'}).then(function(){ c.remove(); }); })(q);
        teks(m.x, kepalaXY(h.u).y - 8, h.n > 0 ? '+' + h.n : 'HP penuh', '#69f0ae', h.n > 0 ? 40 : 26);
        setHP(h.u, h.hp);
      }, 300 + k*150);
    });
    return Promise.all([p, sleep(1600)]);
  };
  AKSI.serang = function(e){
    var u = e.u, role = ORDER[u%4], tim = u < 4 ? 1 : 2, s = e.s, T = e.t;
    var nmS = namaSkill(u, s);
    if (e.m === 'semua') {
      var pre = (tim === 1 ? '☄️ ' : '❄️ ');
      cap(pre + lab(u) + ' memakai ' + nmS + ' ke seluruh ' + 'Tim ' + TIMN[tim === 1 ? 2 : 1] + '!');
      var p = pose(u, 1100);
      if (tim === 1) sfx.meteor(); else sfx.es();
      var proms = [];
      T.forEach(function(t, k){
        proms.push(sleep(500 + k*200).then(function(){
          var tx = POS[t.u].x, ty = POS[t.u].y - 50*POS[t.u].s;
          if (tim === 1) {
            var meteor = '<g transform="rotate(-35)"><ellipse cx="-70" cy="0" rx="90" ry="14" fill="#ff7043" opacity=".55"/><ellipse cx="-34" cy="0" rx="60" ry="9" fill="#ffd54f" opacity=".6"/></g><circle r="24" fill="#ff5722" stroke="#ffd54f" stroke-width="5"/><circle cx="-6" cy="-6" r="9" fill="#ffee58" opacity=".8"/>';
            return terbang(tx - 340, -80, tx, ty, meteor, 650, 'ease-in').then(function(){ kilat('#ffb347', 220); cincin(tx, POS[t.u].y, '#ff7043', 60, 20, 600); hantam(e, t, ['#ff5722','#ffd54f','#fff59d','#ff8a65']); });
          }
          var es = '<path d="M0,-34 L14,0 L0,34 L-14,0Z" fill="#b3ecff" stroke="#fff" stroke-width="3"/><path d="M0,-34 L6,0 L0,34" fill="#7fd8f5"/><path d="M0,-90 L0,-34" stroke="#e1f7ff" stroke-width="6" opacity=".6"/>';
          return terbang(tx + 20, -90, tx, ty, es, 600, 'ease-in').then(function(){ cincin(tx, POS[t.u].y, '#b3ecff', 60, 20, 600); hantam(e, t, ['#e1f7ff','#80deea','#ffffff']); });
        }));
      });
      return Promise.all([p, Promise.all(proms)]).then(function(){ return sleep(500); });
    }
    var t = T[0], tx = POS[t.u].x, ty = POS[t.u].y;
    if (s === 'basic' || s === 's1' || s === 'strike') {
      var jauh = (role === 'mage' || role === 'healer') && s === 'basic';
      cap((s === 'strike' ? '⚡ ' : '⚔️ ') + lab(u) + ' memakai ' + nmS + ' ke ' + lab(t.u) + '!');
      if (jauh) {
        var warna = role === 'mage' ? (tim === 1 ? '#ff7043' : '#80deea') : '#fff59d';
        var pr = pose(u, 800);
        return sleep(350).then(function(){
          sfx.tebas();
          return terbang(POS[u].x + 60*arah(u), POS[u].y - 130*POS[u].s, tx, ty - 70*POS[t.u].s, '<circle r="15" fill="'+warna+'" opacity=".5"/><circle r="9" fill="'+warna+'" stroke="#fff" stroke-width="2"/>', 450, 'ease-in');
        }).then(function(){ hantam(e, t, [warna,'#fff']); return sleep(900); });
      }
      var d = keTarget(u, t.u, s === 'strike' ? 40 : 70);
      if (s === 'strike') {
        // menghilang lalu muncul di depan sasaran dan menebas besar
        var an = gerak(u, [{opacity:1, transform:'translate(0,0)'},{opacity:0, offset:.2},{opacity:0, transform:'translate(0,0)', offset:.3},{opacity:0, transform:'translate('+d.dx+'px,'+d.dy+'px)', offset:.55},{opacity:1, transform:'translate('+d.dx+'px,'+d.dy+'px)', offset:.65},{opacity:1, transform:'translate('+d.dx+'px,'+d.dy+'px)', offset:.82},{opacity:1, transform:'translate(0,0)'}], {duration:1700, easing:'ease-in-out'});
        var m = tengahXY(u); ledakan(m.x, m.y, ['#7b3fc4','#000','#b388ff'], 12, 70, 8);
        return sleep(1000).then(function(){
          sfx.tebas(); sfx.hantam(); kilat('#ffffff', 300);
          var mx = tx, my = ty - 70*POS[t.u].s;
          var X = '<g stroke-linecap="round"><path d="M-80,-60 L80,60 M80,-60 L-80,60" stroke="#fff" stroke-width="12"/><path d="M-80,-60 L80,60 M80,-60 L-80,60" stroke="#ff3b3b" stroke-width="5"/></g>';
          var g = mk('g', {transform:'translate('+mx+','+my+')'}, X); fxg.appendChild(g);
          wa(g, [{opacity:0, transform:'translate('+mx+'px,'+my+'px) scale(.3)'},{opacity:1, transform:'translate('+mx+'px,'+my+'px) scale(1.1)', offset:.3},{opacity:0, transform:'translate('+mx+'px,'+my+'px) scale(1.4)'}], {duration:700}).then(function(){ g.remove(); });
          hantam(e, t, ['#ff3b3b','#fff','#b388ff']);
          return an;
        }).then(function(){ return sleep(250); });
      }
      var an2 = gerak(u, [{transform:'translate(0,0)'},{transform:'translate('+d.dx+'px,'+d.dy+'px)', offset:.35},{transform:'translate('+d.dx+'px,'+d.dy+'px)', offset:.55},{transform:'translate(0,0)'}], {duration:s === 's1' ? 1300 : 1100, easing:'ease-in-out'});
      return sleep(s === 's1' ? 480 : 400).then(function(){
        sfx.tebas();
        var mx = tx, my = ty - 70*POS[t.u].s;
        var cuts = s === 's1' ? 2 : 1;
        for (var c = 0; c < cuts; c++) (function(c){ setTimeout(function(){
          var g = mk('g', null, '<path d="M-50,-40 L50,40" stroke="#fff" stroke-width="9" stroke-linecap="round"/><path d="M-50,-40 L50,40" stroke="'+(role==='assassin'?'#d9b3ff':'#ffe680')+'" stroke-width="3" stroke-linecap="round"/>'); fxg.appendChild(g);
          wa(g, [{opacity:0, transform:'translate('+mx+'px,'+my+'px) rotate('+(c*90)+'deg) scale(.4)'},{opacity:1, transform:'translate('+mx+'px,'+my+'px) rotate('+(c*90)+'deg) scale(1)', offset:.4},{opacity:0, transform:'translate('+mx+'px,'+my+'px) rotate('+(c*90)+'deg) scale(1.2)'}], {duration:420}).then(function(){ g.remove(); });
          if (c === cuts - 1) hantam(e, t, role === 'assassin' ? ['#d9b3ff','#fff','#b388ff'] : ['#ffe680','#fff','#ffb74d']);
        }, c*180); })(c);
        return an2;
      }).then(function(){ return sleep(200); });
    }
    return sleep(800);
  };
  AKSI.ko = function(e){
    var el = $('u'+e.u);
    el.classList.add('ko'); el.classList.remove('stealth'); el.classList.remove('siap');
    $('cd'+e.u).classList.add('mati');
    var k = kepalaXY(e.u);
    teks(k.x, k.y - 20, '💫 PINGSAN', '#ffd54f', 32, 1800);
    sfx.ko();
    return sleep(900);
  };

  async function mainkan(j, tok){
    var ok = function(){ return tok === S.anim; };
    S.animJalan = true;
    var ev = j.kejadian || [];
    await sleep(500); if (!ok()) return;
    var g1 = ev.filter(function(e){ return e.k === 'perisai' || e.k === 'bayangan' || e.k === 'kutuk'; });
    for (var a = 0; a < g1.length; a++) { if (!ok()) return; await AKSI[g1[a].k](g1[a]); }
    if (g1.length) { cap(''); await sleep(250); }
    var g2 = ev.filter(function(e){ return e.k === 'heal' || e.k === 'gagal'; });
    if (g2.length) {
      // gagal & heal berjalan bersamaan agar terasa serempak
      var pr = g2.map(function(e, i){ return sleep(i*380).then(function(){ return ok() ? AKSI[e.k](e) : null; }); });
      await Promise.all(pr); if (!ok()) return; cap(''); await sleep(250);
    }
    var g3 = ev.filter(function(e){ return e.k === 'serang'; });
    for (var b = 0; b < g3.length; b++) { if (!ok()) return; await AKSI.serang(g3[b]); cap(''); await sleep(150); }
    var g4 = ev.filter(function(e){ return e.k === 'ko'; });
    if (g4.length) { await Promise.all(g4.map(function(e, i){ return sleep(i*250).then(function(){ return ok() ? AKSI.ko(e) : null; }); })); }
    if (!ok()) return;
    cap(''); S.animJalan = false;
    snap(j);
    $('u0').parentNode.querySelectorAll('.u').forEach(function(el){ el.classList.remove('sad'); });
  }

  // ================= LAYAR, LOBI, AKHIR =================
  function confetti(){
    var c = $('conf'), w = ['#f6c400','#ffffff','#b9783a','#ffd54f','#e1bee7','#80deea'];
    c.innerHTML = '';
    for (var i = 0; i < 120; i++){
      var d = document.createElement('div'); d.className = 'cf';
      d.style.left = Math.random()*100 + '%'; d.style.background = w[i % w.length];
      d.style.animationDuration = (3 + Math.random()*4) + 's'; d.style.animationDelay = (Math.random()*2.5) + 's';
      d.style.borderRadius = Math.random() > .5 ? '50%' : '2px';
      c.appendChild(d);
    }
    setTimeout(function(){ c.innerHTML = ''; }, 11000);
  }
  function banner(b1, b2){
    var b = $('banner');
    if (!b1) { b.classList.remove('on'); return; }
    b.querySelector('.b1').textContent = b1; b.querySelector('.b2').textContent = b2 || '';
    setTimeout(function(){ b.classList.add('on'); }, 50);
  }
  function alasanAkhir(j){
    var a = j.akhir || {}, al = String(a.alasan || '');
    var h = a.hidup || {1:j.hidup[1], 2:j.hidup[2]}, hp = a.hp || {};
    if (j.pemenang === 3) return 'Kedua tim sama kuat!';
    var m = 'Tim ' + TIMN[j.pemenang];
    if (al === 'habis') return 'Seluruh anggota Tim ' + TIMN[j.pemenang === 1 ? 2 : 1] + ' tumbang!';
    var tail = al.indexOf('hp') >= 0 ? ' Karakter hidup sama banyak (' + h[1] + '), total HP ' + hp[1] + ' vs ' + hp[2] + '.' : ' Karakter hidup ' + h[1] + ' vs ' + h[2] + '.';
    return (al.indexOf('guru') === 0 ? 'Diakhiri guru.' : 'Soal habis!') + tail;
  }

  function lobi(j){
    var el = $('lobi');
    if (!S.lobiDibuat){
      S.lobiDibuat = true;
      el.innerHTML = '<div class="qr" id="qrbox"></div><div><div class="lh">Ayo masuk lobi! <span id="lcount" style="font-size:1rem;color:#1c2436"></span></div>'
        + '<div class="ll">Pindai QR dengan HP, atau buka: ' + esc(LINK) + '</div><div id="lchips"></div></div>';
      var qb = $('qrbox');
      if (typeof QRCode !== 'undefined') new QRCode(qb, {text: LINK, width: 150, height: 150, correctLevel: QRCode.CorrectLevel.M});
      else qb.innerHTML = '<img alt="QR" width="150" height="150" src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(LINK) + '">';
    }
    el.style.display = 'grid';
    var key = JSON.stringify(j.pemain.map(function(p){ return [p.id, p.nama, p.u]; }));
    if (key === S.lk) return; S.lk = key;
    $('lcount').textContent = '· ' + j.pemain.length + ' murid masuk · ' + j.pemain.filter(function(p){ return p.u >= 0; }).length + '/8 sudah dapat peran';
    $('lchips').innerHTML = j.pemain.map(function(p){ return '<span class="chip' + (p.u < 0 ? ' p' : '') + '">' + (p.u >= 0 ? PERAN[ORDER[p.u%4]][1] + ' ' : '') + esc(p.nama) + '</span>'; }).join('') || '<span style="opacity:.6">Menunggu murid masuk…</span>';
  }
  function overlay(j){
    var o = $('ovl'), h, ko;
    if (j.st === 'lobi') lobi(j); else $('lobi').style.display = 'none';
    if (j.st === 'kosong') { ko = 'kosong'; h = '<div class="msgbox"><div class="em">⚔️</div><h2>Menunggu guru…</h2><p>Guru belum membuat sesi pertandingan.</p></div>'; }
    else if (j.st === 'jeda') { ko = 'jeda'; h = '<div class="msgbox"><div class="em">⏸️</div><h2>Pertandingan dijeda</h2><p>Menunggu guru melanjutkan…</p></div>'; }
    else { o.style.display = 'none'; S.ov = ''; return; }
    if (ko === S.ov) return;
    S.ov = ko; o.innerHTML = h; o.style.display = 'grid';
  }
  var FASE = {lobi:'MENUNGGU PEMAIN', pilih:'PILIH SKILL & TARGET', soal:'JAWAB SOAL', hasil:'AKSI!', selesai:'PERTANDINGAN SELESAI', jeda:'DIJEDA', kosong:'MENUNGGU GURU'};
  function hud(j){
    var r = $('rnd'), st = j.st === 'jeda' ? 'jeda' : j.st;
    if (j.st === 'lobi' || j.st === 'kosong') r.innerHTML = 'LOBI<small>' + FASE[j.st] + '</small>';
    else if (j.st === 'selesai') r.innerHTML = 'SELESAI<small>' + j.ronde + ' giliran</small>';
    else r.innerHTML = 'GILIRAN ' + j.ronde + '<small>' + FASE[st] + '</small>';
  }

  function terima(j){
    if (j.st === 'kosong') { j.unit = []; for (var q = 0; q < 8; q++) j.unit.push({i:q, tim:q<4?1:2, peran:ORDER[q%4], nama:'', hp:1, mx:1, hidup:true, kunci:false, sudah:false}); j.hidup = {1:4, 2:4}; j.pemain = []; j.ronde = 0; }
    S.j = j; S.sisa = j.sisa; S.t0 = Date.now();
    var k = j.st + '|' + j.sesi + '|' + j.ronde;
    if (j.st !== 'jeda' && k !== S.k) {
      S.k = k;
      S.anim++;                       // batalkan animasi sebelumnya
      S.animJalan = false;
      cap(''); banner(''); $('conf').innerHTML = '';
      if (j.st === 'lobi' || j.st === 'kosong') { bersih(); snap(j); lencana(j, ''); }
      else if (j.st === 'pilih') { bersih(); snap(j); sfx.mulai(); }
      else if (j.st === 'soal') { snap(j); }
      else if (j.st === 'hasil') { lencana(j, ''); var tok = S.anim; mainkan(j, tok); }
      else if (j.st === 'selesai') {
        snap(j);
        if (!(j.akhir && j.akhir.batal)) {
          var sedang = j.pemenang;
          for (var i = 0; i < 8; i++){
            var el = $('u'+i), tim = i < 4 ? 1 : 2, u = j.unit[i];
            if (!u.hidup) continue;
            if (sedang === 3 || tim === sedang) el.classList.add('pesta'); else el.classList.add('sad');
            el.classList.remove('stealth');
          }
          if (sedang === 3) { banner('🤝 SERI!', alasanAkhir(j)); sfx.seri(); }
          else { banner('🏆 TIM ' + TIMN[sedang].toUpperCase() + ' MENANG!', alasanAkhir(j)); sfx.menang(); setTimeout(confetti, 600); }
        }
      }
    }
    if (!S.animJalan && j.st !== 'hasil') {
      // pembaruan ringan antar-tick: status siap/pilih serta HP (aman selama animasi tidak berjalan)
      if (j.st === 'pilih') lencana(j, 'pilih');
      else if (j.st === 'soal') lencana(j, 'soal');
      else if (j.st !== 'jeda') lencana(j, '');
    }
    hud(j); overlay(j);
  }

  setInterval(function(){
    var t = $('tmr'), j = S.j;
    if (!j) return;
    var fase = j.st === 'jeda' ? j.sblm : j.st;
    var live = fase === 'pilih' || fase === 'soal';
    if (!live || S.sisa == null) { t.textContent = fase === 'hasil' ? '⚔️' : '–'; t.className = ''; return; }
    var s = j.st === 'jeda' ? S.sisa : Math.max(0, S.sisa - (Date.now() - S.t0)), d = Math.ceil(s/1000);
    t.textContent = d; t.className = d <= 5 ? 'low' : '';
    if (j.st !== 'jeda' && d <= 5 && d > 0 && d !== S.tikTerakhir) { S.tikTerakhir = d; sfx.tik(); }
  }, 200);

  function loop(){
    fetch(API + '?a=gstate&game=' + GAME, {cache:'no-store'}).then(function(r){ return r.json(); })
      .then(function(j){ if (j.ok !== false) terima(j); else { $('ovl').innerHTML = '<div class="msgbox"><h2>'+esc(j.pesan||'Terputus')+'</h2></div>'; $('ovl').style.display='grid'; S.ov=''; } })
      .catch(function(){}).then(function(){ setTimeout(loop, 700); });
  }

  bangunAdegan();
  bangunKartu();
  fxg = $('fx');
  loop();
  window.__rpg = {S:S, POS:POS, AKSI:AKSI, terima:terima};
})();
</script>
</body></html>

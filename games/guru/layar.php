<?php
require __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/live.php';
$u = require_login('guru');
$g = db_row('SELECT * FROM games WHERE id=? AND user_id=?', [(int)($_GET['id'] ?? 0), $u['id']]);
if (!$g) { echo 'Game tidak ditemukan.'; exit; }
$tpl = list_templates();
if (empty($tpl[$g['template']]['live'])) { echo 'Bukan game live.'; exit; }
$link = game_link($u['username'], $g['slug']);
?><!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Layar Tarik Tambang — <?= e($g['judul']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&display=swap" rel="stylesheet">
<style>
  :root{--merah:#d7192a;--putih:#fff}
  *{box-sizing:border-box}
  html,body{margin:0;height:100%;overflow:hidden;background:#0d2a5c;font-family:'Baloo 2','Trebuchet MS',system-ui,sans-serif;color:#fff}
  #wrap{display:grid;grid-template-rows:auto 1fr auto auto;height:100vh}
  #head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:8px 22px;background:linear-gradient(90deg,#b3121f,#d7192a 45%,#d7192a 55%,#b3121f);border-bottom:5px solid #fff}
  #head .jd{font-size:1.5rem;font-weight:800;line-height:1.1}
  #head .sk{font-size:.9rem;opacity:.9}
  #rnd{font-size:1.7rem;font-weight:800;text-align:center}
  #rnd small{display:block;font-size:.85rem;font-weight:600;opacity:.9}
  #tmr{width:74px;height:74px;border-radius:50%;background:#fff;color:#b3121f;display:grid;place-items:center;font-size:2rem;font-weight:800;border:5px solid #f4b52c;flex:none}
  #tmr.low{background:#ffefef;color:#d7192a;animation:denyut .5s ease-in-out infinite}
  @keyframes denyut{50%{transform:scale(1.12)}}
  #mid{position:relative;min-height:0;background:linear-gradient(#5db7ff,#a9dcff 60%,#63c256)}
  svg#scene{width:100%;height:100%;display:block}
  #cap{position:absolute;left:50%;bottom:16px;transform:translateX(-50%);background:rgba(13,42,92,.88);color:#fff;border-radius:999px;padding:8px 30px;font-size:clamp(1.1rem,2.6vw,2.1rem);font-weight:800;white-space:nowrap;display:none;z-index:4;border:3px solid #f4b52c}
  #kredit{background:#0d2a5c;color:#cfe0ff;text-align:center;font:600 .78rem system-ui,sans-serif;padding:3px 8px;opacity:.85}
  #foot{display:grid;grid-template-columns:1fr 1fr;gap:0}
  .sb{padding:8px 24px;font-size:1.25rem;font-weight:800;display:flex;justify-content:space-between;align-items:center}
  .sb.m{background:#d7192a;color:#fff}
  .sb.p{background:#fff;color:#b3121f}
  .sb small{font-size:.95rem;font-weight:700;opacity:.9}
  #lobi{position:absolute;top:8px;left:50%;transform:translateX(-50%);width:min(1180px,97%);max-height:46%;display:none;grid-template-columns:auto 1fr;gap:16px;background:rgba(255,255,255,.95);color:#1c2436;border-radius:20px;padding:10px 16px;box-shadow:0 10px 30px rgba(0,0,0,.3);z-index:6;overflow:hidden}
  #lobi .qr{background:#fff;padding:5px;border-radius:12px;border:3px solid #d7192a;line-height:0}
  #lobi .lh{font-size:1.5rem;color:#b3121f;font-weight:800}
  #lobi .ll{font-size:.9rem;font-weight:600;margin-bottom:4px;word-break:break-all}
  #lchips{overflow:auto;max-height:calc(46vh - 120px);display:flex;flex-wrap:wrap;align-items:center;gap:2px}
  #lobi .chip{font-size:.92rem;padding:1px 10px;margin:2px}
  #lobi .lg{font-weight:800;border-radius:8px;padding:2px 10px;margin:2px 4px 2px 10px;color:#fff;font-size:.95rem}
  #lobi .lg.m{background:#d7192a}#lobi .lg.p{background:#fff;color:#b3121f;border:2px solid #d7192a}#lobi .lg.u{background:#e0b84a;color:#3a2a00}
  #ovl{position:absolute;inset:0;display:none;place-items:center;background:rgba(10,30,70,.78);z-index:6;padding:20px}
  .lob{display:grid;grid-template-columns:auto 1fr;gap:28px;background:#fff;color:#1c2436;border-radius:26px;padding:26px 30px;max-width:1100px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.4);max-height:100%}
  .lob h2{margin:0 0 4px;font-size:2rem;color:#b3121f}
  .lob .qr{background:#fff;padding:8px;border-radius:14px;border:3px solid #d7192a;display:inline-block}
  .lob .lnk{font-size:.95rem;word-break:break-all;max-width:270px;margin-top:8px;font-weight:700}
  .lob .tims{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-content:start;overflow:auto;max-height:52vh}
  .lob .tk{border-radius:16px;padding:10px 12px;min-height:110px}
  .lob .tk.m{background:#fff0f0;border:3px solid #d7192a}.lob .tk.p{background:#f6f8fd;border:3px solid #9aa7c2}
  .lob .tk h3{margin:0 0 6px;font-size:1.25rem}
  .lob .un{grid-column:1/-1;background:#fff8e1;border:2px dashed #e0b84a}
  .chip{display:inline-block;background:#fff;border:2px solid #d0d7e5;border-radius:999px;padding:2px 12px;margin:3px;font-size:1.05rem;font-weight:700}
  .tk.m .chip{border-color:#f2a3a9}
  .msgbox{background:#fff;color:#1c2436;border-radius:26px;padding:30px 50px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4)}
  .msgbox .em{font-size:4.5rem}.msgbox h2{margin:.1em 0;font-size:2.4rem}
  #banner{position:absolute;left:50%;top:9%;transform:translateX(-50%) scale(.6);opacity:0;z-index:7;text-align:center;pointer-events:none;transition:all .7s cubic-bezier(.2,1.5,.4,1)}
  #banner.on{opacity:1;transform:translateX(-50%) scale(1)}
  #banner .b1{white-space:nowrap;font-size:clamp(1.8rem,5vw,4.2rem);font-weight:800;color:#fff;text-shadow:0 4px 0 #b3121f,0 0 30px rgba(0,0,0,.5),-3px -3px 0 #b3121f,3px -3px 0 #b3121f;line-height:1}
  #banner .b2{font-size:clamp(1.1rem,2.6vw,2.1rem);font-weight:800;color:#ffd54f;text-shadow:0 3px 0 #7a0c15}
  #conf{position:absolute;inset:0;pointer-events:none;overflow:hidden;z-index:8}
  .cf{position:absolute;top:-20px;width:12px;height:18px;animation:jatuhcf linear forwards}
  @keyframes jatuhcf{to{transform:translateY(110vh) rotate(720deg)}}
  #ctl{position:fixed;right:10px;bottom:70px;display:flex;gap:8px;z-index:20;opacity:.35}
  #ctl:hover{opacity:1}
  #ctl button{background:#fff;border:0;border-radius:10px;padding:8px 12px;font:700 .9rem system-ui;cursor:pointer}

  /* --- animasi karakter & adegan --- */
  .awak{transform:rotate(-10deg);transform-origin:0 0}
  .lgn{transform-origin:0 0}
  #tarik{transition:transform 2s cubic-bezier(.3,1.35,.5,1)}
  #tarik.idle .awak{animation:goyang 1.5s ease-in-out infinite}
  @keyframes goyang{0%,100%{transform:rotate(-8deg)}50%{transform:rotate(-13deg)}}
  #tarik.w1 .t1 .awak,#tarik.w2 .t2 .awak{animation:tarikkuat .45s ease-in-out 5}
  @keyframes tarikkuat{0%,100%{transform:rotate(-16deg)}50%{transform:rotate(-24deg)}}
  #tarik.seri .awak{animation:tegang .22s linear infinite}
  @keyframes tegang{0%,100%{transform:rotate(-11deg)}50%{transform:rotate(-15deg)}}
  #tarik.akhir1 .t2 .awak,#tarik.akhir2 .t1 .awak{animation:tersungkur 1.1s ease-in both;animation-delay:calc(var(--i) * .2s)}
  @keyframes tersungkur{0%{transform:rotate(-10deg)}50%{transform:rotate(45deg) translateY(4px)}100%{transform:rotate(86deg) translateY(2px)}}
  #tarik.akhir1 .t2 .lgn,#tarik.akhir2 .t1 .lgn{transform:rotate(70deg);transition:transform .6s;transition-delay:calc(var(--i) * .2s)}
  #tarik.akhir1 .t1 .awak,#tarik.akhir2 .t2 .awak,#tarik.akhir3 .awak{animation:loncat .65s ease-in-out infinite;animation-delay:calc(var(--i) * .07s)}
  @keyframes loncat{0%,100%{transform:rotate(0) translateY(0)}50%{transform:rotate(0) translateY(-30px)}}
  #tarik.akhir1 .t1 .lgn,#tarik.akhir2 .t2 .lgn,#tarik.akhir3 .lgn{transform:rotate(-140deg);transition:transform .4s}
  #tarik .awak.slip{animation:slip .9s ease-in var(--d,0s) forwards !important}
  @keyframes slip{0%{transform:rotate(-10deg)}30%{transform:rotate(8deg) translateX(-6px)}100%{transform:rotate(-84deg) translateY(3px)}}
  #tarik .awak.rebah{animation:rebah .9s ease-in var(--d,0s) forwards !important}
  @keyframes rebah{0%{transform:rotate(-10deg)}45%{transform:rotate(30deg) translateY(4px)}100%{transform:rotate(86deg) translateY(2px)}}
  #tarik .awak.rebah .lgn{transform:rotate(70deg);transition:transform .5s;transition-delay:var(--d,0s)}
  #tarik .awak.slip .lgn{transform:rotate(-55deg);transition:transform .5s;transition-delay:var(--d,0s)}
  #tali{transition:transform 1.2s ease-in}
  #tarik.akhir1 #tali,#tarik.akhir2 #tali{transform:translateY(100px)}
  .kibar{animation:kibar 1.3s ease-in-out infinite alternate;transform-origin:0 0}
  @keyframes kibar{from{transform:skewY(-5deg) scaleX(.94)}to{transform:skewY(5deg) scaleX(1)}}
  #spanduk{transition:opacity .4s}
  svg.lengang #spanduk{opacity:0}
  .awan{animation:awan 60s linear infinite}
  @keyframes awan{from{transform:translateX(-200px)}to{transform:translateX(1500px)}}
</style>
</head><body>
<div id="wrap">
  <div id="head">
    <div><div class="sk"><?= e(setting('nama_sekolah')) ?> · Dirgahayu RI</div><div class="jd">🪢 <?= e($g['judul']) ?></div></div>
    <div id="rnd">–</div>
    <div id="tmr">–</div>
  </div>
  <div id="mid">
    <svg id="scene" viewBox="0 0 1400 620" preserveAspectRatio="xMidYMax meet"></svg>
    <div id="lobi"></div>
    <div id="cap"></div>
    <div id="ovl"></div>
    <div id="banner"><div class="b1"></div><div class="b2"></div></div>
    <div id="conf"></div>
  </div>
  <div id="foot">
    <div class="sb m"><span>🔴 TIM MERAH</span><small id="s1"></small></div>
    <div class="sb p"><span>⚪ TIM PUTIH</span><small id="s2"></small></div>
  </div>
  <div id="kredit">Dibuat oleh Subrata Pratama, S.Pd. - SMP Kartini 2 Batam</div>
</div>
<div id="ctl"><button id="b-suara">🔇 Aktifkan suara</button><button id="b-fs">⛶ Layar penuh</button></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function(){
  var GAME = <?= (int)$g['id'] ?>, LINK = <?= json_encode($link) ?>;
  var API = <?= json_encode(url('api_live.php')) ?>;
  var SHIFT = 220, GY = 470;
  var NS = 'http://www.w3.org/2000/svg';
  var $ = function(i){ return document.getElementById(i); };
  function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }

  // ================= ADEGAN =================
  function anak(i, tim){
    var kulit = ['#f2c9a0','#e0a877','#c68a5b','#a06a44','#8a5a3a'][(i + (tim===2?2:0)) % 5];
    var rambut = ['#1b1b1b','#3b2416','#111','#5a341c','#222'][(i*3 + tim) % 5];
    var baju = tim===1 ? '#d7192a' : '#ffffff', garis = tim===1 ? '#ffffff' : '#d7192a';
    var celana = tim===1 ? '#5a1622' : '#1f3a78';
    return '<g class="awak">'
      + '<path d="M-2,-68 L-18,-4" stroke="'+celana+'" stroke-width="12" stroke-linecap="round"/>'
      + '<path d="M6,-68 L26,-2" stroke="'+celana+'" stroke-width="12" stroke-linecap="round"/>'
      + '<ellipse cx="-21" cy="0" rx="12" ry="5" fill="#222"/><ellipse cx="30" cy="0" rx="12" ry="5" fill="#222"/>'
      + '<rect x="-15" y="-126" width="33" height="62" rx="13" fill="'+baju+'" stroke="'+garis+'" stroke-width="3"/>'
      + '<rect x="-15" y="-100" width="33" height="9" fill="'+garis+'"/>'
      + '<circle cx="3" cy="-145" r="18" fill="'+kulit+'"/>'
      + '<path d="M-15,-148 A18,18 0 0 1 21,-148 Z" fill="'+rambut+'"/>'
      + '<rect x="-15" y="-153" width="36" height="8" rx="2" fill="#d7192a"/><rect x="-15" y="-150.5" width="36" height="3" fill="#fff"/>'
      + '<circle cx="12" cy="-142" r="2.4" fill="#222"/><path d="M8,-134 q5,4 10,0" stroke="#7a3b2e" stroke-width="2" fill="none" stroke-linecap="round"/>'
      + '<g transform="translate(4,-114)"><g class="lgn">'
      + '<line x1="0" y1="0" x2="46" y2="10" stroke="'+kulit+'" stroke-width="9" stroke-linecap="round"/>'
      + '<line x1="0" y1="0" x2="15" y2="3.3" stroke="'+baju+'" stroke-width="13" stroke-linecap="round"/>'
      + '<circle cx="47" cy="10" r="6.5" fill="'+kulit+'"/></g></g>'
      + '</g>';
  }
  function bendera(x, y, tinggiTiang, label){
    return '<g transform="translate('+x+','+GY+')">'
      + '<ellipse cx="0" cy="4" rx="34" ry="9" fill="rgba(0,0,0,.25)"/>'
      + '<rect x="-6" y="-'+tinggiTiang+'" width="12" height="'+tinggiTiang+'" rx="4" fill="url(#gTiang)"/>'
      + '<circle cx="0" cy="-'+tinggiTiang+'" r="9" fill="#f4b52c" stroke="#a87400" stroke-width="2"/>'
      + '<g transform="translate(6,-'+(tinggiTiang-6)+')"><g class="kibar">'
      + '<rect x="0" y="0" width="104" height="35" fill="#d7192a"/><rect x="0" y="35" width="104" height="35" fill="#fff" stroke="#ddd" stroke-width="1"/></g></g>'
      + '<rect x="-46" y="14" width="92" height="26" rx="8" fill="#fff" stroke="#b3121f" stroke-width="3"/>'
      + '<text x="0" y="33" text-anchor="middle" font-family="Baloo 2,sans-serif" font-weight="800" font-size="17" fill="#b3121f">'+label+'</text>'
      + '</g>';
  }
  function bunting(x0, x1, y0, sag, n){
    var s = '';
    for (var k=0;k<=n;k++){
      var t = k/n, x = x0 + (x1-x0)*t, y = y0 + sag*4*t*(1-t);
      s += '<path d="M'+(x-17)+','+y+' L'+(x+17)+','+y+' L'+x+','+(y+38)+' Z" fill="'+(k%2?'#fff':'#d7192a')+'" stroke="#c9c9c9" stroke-width="1"/>';
    }
    var d = 'M'+x0+','+y0+' Q'+((x0+x1)/2)+','+(y0+sag*2)+' '+x1+','+y0;
    return '<path d="'+d+'" stroke="#6b4a2b" stroke-width="3" fill="none"/>'+s;
  }
  function pohonKecil(x, y, s){
    return '<g transform="translate('+x+','+y+') scale('+s+')"><rect x="-4" y="-40" width="8" height="40" fill="#6b4a2b"/><circle cx="0" cy="-58" r="28" fill="#2f8f3a"/><circle cx="-18" cy="-44" r="18" fill="#3aa347"/><circle cx="18" cy="-46" r="18" fill="#3aa347"/></g>';
  }
  function bangunAdegan(){
    var th = new Date().getFullYear() - 1945;
    var s = '<defs>'
      + '<linearGradient id="gLangit" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5db7ff"/><stop offset="1" stop-color="#d7f0ff"/></linearGradient>'
      + '<linearGradient id="gRumput" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#63c256"/><stop offset="1" stop-color="#2f8f3a"/></linearGradient>'
      + '<linearGradient id="gTiang" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#e8d2a6"/><stop offset=".5" stop-color="#fff2d6"/><stop offset="1" stop-color="#c9a76a"/></linearGradient>'
      + '<radialGradient id="gMatahari"><stop offset="0" stop-color="#fff7c2"/><stop offset="1" stop-color="#ffd54f"/></radialGradient>'
      + '</defs>'
      + '<rect width="1400" height="620" fill="url(#gLangit)"/>'
      + '<circle cx="1240" cy="120" r="90" fill="#fff7c2" opacity=".35"/><circle cx="1240" cy="120" r="56" fill="url(#gMatahari)"/>'
      + '<g class="awan" opacity=".95"><ellipse cx="0" cy="150" rx="70" ry="24" fill="#fff"/><ellipse cx="45" cy="138" rx="50" ry="26" fill="#fff"/><ellipse cx="-40" cy="142" rx="44" ry="20" fill="#fff"/></g>'
      + '<g class="awan" style="animation-delay:-30s" opacity=".9"><ellipse cx="0" cy="210" rx="60" ry="20" fill="#fff"/><ellipse cx="38" cy="200" rx="42" ry="22" fill="#fff"/></g>'
      + '<path d="M0,400 Q200,330 420,385 T820,375 T1200,380 T1400,360 L1400,470 L0,470 Z" fill="#7fcf7b"/>'
      + '<path d="M0,430 Q300,385 600,425 T1400,410 L1400,480 L0,480 Z" fill="#54b556"/>'
      + pohonKecil(90,440,1) + pohonKecil(210,450,.8) + pohonKecil(1180,445,1) + pohonKecil(1310,440,.9)
      + '<rect x="0" y="'+(GY-20)+'" width="1400" height="200" fill="url(#gRumput)"/>'
      // pagar umbul-umbul
      + (function(){ var o=''; for (var x=60;x<1400;x+=130){ o += '<g transform="translate('+x+','+(GY-14)+')"><rect x="-2" y="-92" width="4" height="92" fill="#8a6a3b"/><g transform="translate(2,-90)"><g class="kibar" style="animation-delay:-'+(x%7)/5+'s"><rect width="46" height="14" fill="#d7192a"/><rect y="14" width="46" height="14" fill="#fff" stroke="#ddd" stroke-width=".8"/></g></g></g>'; } return o; })()
      // arena tanah
      + '<ellipse cx="700" cy="'+(GY+42)+'" rx="620" ry="70" fill="#d9b47a" opacity=".55"/>'
      + '<rect x="0" y="590" width="1400" height="15" fill="#d7192a"/><rect x="0" y="605" width="1400" height="15" fill="#fff"/>'
      + '<line x1="700" y1="'+(GY-10)+'" x2="700" y2="'+(GY+80)+'" stroke="#fff" stroke-width="5" stroke-dasharray="12 10" opacity=".8"/>'
      + '<text x="360" y="'+(GY+96)+'" text-anchor="middle" font-family="Baloo 2,sans-serif" font-weight="800" font-size="46" fill="#d7192a" opacity=".55">MERAH</text>'
      + '<text x="1040" y="'+(GY+96)+'" text-anchor="middle" font-family="Baloo 2,sans-serif" font-weight="800" font-size="46" fill="#fff" opacity=".7">PUTIH</text>'
      // tiang penanda
      + bendera(700-SHIFT, GY, 300, 'MERAH') + bendera(700+SHIFT, GY, 300, 'PUTIH')
      // adegan tarik
      + '<g transform="translate(700,0)"><g id="tarik" class="idle" style="transform:translateX(0px)">'
      + '<g id="tali"><path id="tl1" d="" stroke="#7a4a1f" stroke-width="13" fill="none" stroke-linecap="round"/>'
      + '<path id="tl2" d="" stroke="#e0b878" stroke-width="13" fill="none" stroke-dasharray="6 9" stroke-linecap="round"/>'
      + '<g id="pita" transform="translate(0,'+(GY-100)+') scale(1.5)"><rect x="-9" y="-3" width="18" height="6" rx="3" fill="#f4b52c"/><rect x="-11" y="3" width="22" height="20" fill="#d7192a" stroke="#7a0c15" stroke-width="1.5"/><rect x="-11" y="23" width="22" height="20" fill="#fff" stroke="#999" stroke-width="1.5"/></g></g>'
      + '<g class="t1" id="gt1"></g><g class="t2" id="gt2"></g></g></g>';
    // spanduk & umbul-umbul atas
    s += bunting(0, 700, 14, 60, 18) + bunting(700, 1400, 14, 60, 18);
    s += '<g id="spanduk" transform="translate(700,80)"><rect x="-170" y="0" width="340" height="62" rx="12" fill="#d7192a" stroke="#fff" stroke-width="5"/>'
      + '<text x="0" y="44" text-anchor="middle" font-family="Baloo 2,sans-serif" font-weight="800" font-size="38" fill="#fff">DIRGAHAYU RI</text>'
      + '</g>';
    $('scene').innerHTML = s;
  }

  // ===== Jumlah orang mengikuti jumlah HP tiap tim di lobi (maksimal 15 vs 15) =====
  var cur = {n1: -1, n2: -1};
  function susunTim(n1, n2){
    n1 = Math.max(0, Math.min(15, n1|0)); n2 = Math.max(0, Math.min(15, n2|0));
    if (n1 === cur.n1 && n2 === cur.n2) return;
    cur.n1 = n1; cur.n2 = n2;
    var m = Math.max(n1, n2, 1);
    var sp = m <= 5 ? 62 : Math.min(62, 342 / (m - 1));                    // jarak antar orang, makin banyak makin dempet
    var sc = m <= 5 ? 1 : Math.max(.55, Math.min(1, .45 + sp / 62 * .55)); // ukuran orang
    function tim(n, t){
      var h = '';
      for (var i = n - 1; i >= 0; i--){
        var x = 88 + i * sp;
        h += '<g transform="translate(' + (t === 1 ? -x : x) + ',' + GY + ') scale(' + sc + ')" class="ch" style="--i:' + i + '"><g' + (t === 2 ? ' transform="scale(-1,1)"' : '') + '>' + anak(i, t) + '</g></g>';
      }
      return h;
    }
    $('gt1').innerHTML = tim(n1, 1); $('gt2').innerHTML = tim(n2, 2);
    var hy = GY - 104 * sc;
    var L = n1 ? -(88 + (n1 - 1) * sp) + 51 * sc - 14 : -60, R = n2 ? (88 + (n2 - 1) * sp) - 51 * sc + 14 : 60;
    var d = 'M' + L + ',' + hy + ' Q0,' + (hy + 8) + ' ' + R + ',' + hy, w = 13 * Math.max(.6, sc);
    ['tl1', 'tl2'].forEach(function(id){ $(id).setAttribute('d', d); $(id).setAttribute('stroke-width', w); });
    $('pita').setAttribute('transform', 'translate(0,' + (hy + 4) + ') scale(' + (1.5 * Math.max(.7, sc)) + ')');
  }
  // orang yang menjawab salah tergelincir (dipilih acak); berdiri lagi saat tarikan baru
  function tergelincir(t, k){
    var els = [].slice.call(document.querySelectorAll('#gt' + t + ' .ch'));
    for (var i = els.length - 1; i > 0; i--){ var j = Math.floor(Math.random() * (i + 1)); var x = els[i]; els[i] = els[j]; els[j] = x; }
    els.slice(0, Math.max(0, Math.min(k, els.length))).forEach(function(g){
      var a = g.querySelector('.awak'); a.style.setProperty('--d', (Math.random() * .8).toFixed(2) + 's'); a.classList.add('slip');
    });
  }
  // tim yang kalah cepat (benar sama banyak): anggota yang masih berdiri tertarik semuanya dan jatuh ke arah lawan
  function rebah(t){
    [].forEach.call(document.querySelectorAll('#gt' + t + ' .ch'), function(g){
      var a = g.querySelector('.awak'); if (a.classList.contains('slip')) return;
      a.style.setProperty('--d', (Math.random() * .5).toFixed(2) + 's'); a.classList.add('rebah');
    });
  }
  function berdiri(){ [].forEach.call(document.querySelectorAll('.awak.slip, .awak.rebah'), function(a){ a.classList.remove('slip'); a.classList.remove('rebah'); }); }

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
  function derau(d, vol, tunda){
    if (!suaraOn || !ac()) return;
    var n = ctx.sampleRate*d, b = ctx.createBuffer(1,n,ctx.sampleRate), a = b.getChannelData(0);
    for (var i=0;i<n;i++) a[i] = (Math.random()*2-1) * (1 - i/n);
    var s = ctx.createBufferSource(), f = ctx.createBiquadFilter(), g = ctx.createGain();
    s.buffer = b; f.type='bandpass'; f.frequency.value = 1400; g.gain.value = vol||.25;
    s.connect(f); f.connect(g); g.connect(ctx.destination); s.start(ctx.currentTime+(tunda||0));
  }
  var sfx = {
    mulai: function(){ nada(2300,.18,'sine',.2); nada(2300,.18,'sine',.2,.22); nada(2500,.5,'sine',.2,.44); },
    tik: function(){ nada(900,.07,'square',.08); },
    tarik: function(){ nada(110,.25,'triangle',.3); nada(130,.25,'triangle',.3,.3); nada(160,.35,'triangle',.3,.6); derau(.5,.12,.1); },
    jatuh: function(){ nada(600,.9,'sawtooth',.12,0,90); derau(.6,.2,.9); },
    menang: function(){ [523,659,784,1047].forEach(function(f,i){ nada(f,.35,'triangle',.2,i*.16); }); nada(1047,.9,'triangle',.2,.7); derau(2.2,.3,.2); },
    seri: function(){ nada(440,.3,'triangle',.2); nada(440,.5,'triangle',.2,.35); }
  };
  $('b-suara').onclick = function(){ suaraOn = !suaraOn; if (suaraOn) { ac(); if (ctx && ctx.resume) ctx.resume(); sfx.tik(); } this.textContent = suaraOn ? '🔊 Suara aktif' : '🔇 Aktifkan suara'; };
  $('b-fs').onclick = function(){ var d = document.documentElement; if (document.fullscreenElement) document.exitFullscreen(); else if (d.requestFullscreen) d.requestFullscreen(); };

  // ================= KEADAAN =================
  var S = { k: null, j: null, sisa: null, t0: 0, tickTerakhir: -1, bannerT: 0, qrDibuat: false, hash: '' };
  var tarik;

  function setPos(j){
    var f = Math.max(-1, Math.min(1, j.pos / j.selisih));
    tarik.style.transform = 'translateX(' + (-f * SHIFT) + 'px)';
  }
  function cap(t){ var c = $('cap'); if (!t) { c.style.display = 'none'; return; } c.textContent = t; c.style.display = 'block'; }
  function banner(b1, b2){
    var b = $('banner');
    if (!b1) { b.classList.remove('on'); return; }
    b.querySelector('.b1').textContent = b1; b.querySelector('.b2').textContent = b2 || '';
    setTimeout(function(){ b.classList.add('on'); }, 50);
  }
  function confetti(){
    var c = $('conf'), w = ['#d7192a','#ffffff','#ffd54f','#ef5350','#ffffff'];
    c.innerHTML = '';
    for (var i=0;i<120;i++){
      var d = document.createElement('div'); d.className = 'cf';
      d.style.left = Math.random()*100 + '%'; d.style.background = w[i % w.length];
      d.style.animationDuration = (3 + Math.random()*4) + 's'; d.style.animationDelay = (Math.random()*2.5) + 's';
      d.style.borderRadius = Math.random() > .5 ? '50%' : '2px'; d.style.border = '1px solid rgba(0,0,0,.12)';
      c.appendChild(d);
    }
    setTimeout(function(){ c.innerHTML = ''; }, 11000);
  }
  function setKelas(k){ tarik.setAttribute('class', k); }

  function transisi(j){
    $('conf').innerHTML = ''; banner(''); S.tahanPos = false;
    if (j.st !== 'hasil') berdiri();
    if (j.st === 'soal') {
      setKelas('idle'); cap('');
      if (j.ronde === 1 || j.extra) sfx.mulai(); else sfx.tik();
    } else if (j.st === 'hasil') {
      var l = j.riwayat[j.riwayat.length-1] || {win:0,lang:0,b1:0,b2:0};
      var n1 = j.jml[1], n2 = j.jml[2];
      var s1 = n1 - (l.b1 || 0), s2 = n2 - (l.b2 || 0);                   // jumlah yang menjawab salah / tidak menjawab
      berdiri(); tergelincir(1, s1); tergelincir(2, s2);
      if (l.win === 0) { setKelas('seri'); sfx.seri(); }
      else {
        setKelas('w' + l.win); sfx.tarik();
        if (l.cepat) {
          // benar sama banyak: kedua tim tampak seri, lalu tim yang kalah cepat tertarik semuanya
          S.tahanPos = true; var lambat = l.win === 1 ? 2 : 1, kk = S.k;
          setTimeout(function(){ if (S.k !== kk) return; S.tahanPos = false; if (S.j) setPos(S.j); rebah(lambat); sfx.jatuh(); }, 1600);
        } else setTimeout(sfx.jatuh, 900);
      }
    } else if (j.st === 'selesai') {
      cap('');
      if (j.pemenang === 3) {
        setKelas('akhir3'); banner('🤝 SERI!', 'Kedua tim sama kuat'); sfx.seri();
      } else {
        setKelas('akhir' + j.pemenang);
        banner('🏆 TIM ' + (j.pemenang===1?'MERAH':'PUTIH') + ' MENANG!', 'INDONESIA MERDEKA!');
        sfx.jatuh(); setTimeout(sfx.menang, 1400);
      }
      setTimeout(confetti, 600);
    }
  }

  var lobiDibuat = false;
  function lobi(j){
    var el = $('lobi');
    if (!lobiDibuat){
      lobiDibuat = true;
      el.innerHTML = '<div class="qr" id="qrbox"></div><div><div class="lh">Ayo masuk lobi! <span id="lcount" style="font-size:1rem;color:#1c2436"></span></div>'
        + '<div class="ll">Pindai QR dengan HP, atau buka: ' + esc(LINK) + '</div><div id="lchips"></div></div>';
      var qb = $('qrbox');
      if (typeof QRCode !== 'undefined') new QRCode(qb, {text: LINK, width: 170, height: 170, correctLevel: QRCode.CorrectLevel.M});
      else qb.innerHTML = '<img alt="QR" width="170" height="170" src="https://api.qrserver.com/v1/create-qr-code/?size=170x170&data=' + encodeURIComponent(LINK) + '">';
    }
    el.style.display = 'grid';
    var key = JSON.stringify(j.pemain.map(function(p){ return [p.id, p.nama, p.tim, p.gabung_ke]; }));
    if (key === S.lk) return; S.lk = key;
    var H = j.pemain.filter(function(p){ return p.gabung_ke === null; });
    var nm = function(p){ var m = j.pemain.filter(function(x){ return x.gabung_ke === p.id; })[0]; return '<span class="chip">' + esc(p.nama) + (m ? ' & ' + esc(m.nama) : '') + '</span>'; };
    var grup = function(t, cls, lbl){ var L = H.filter(function(p){ return p.tim === t; }); return L.length ? '<span class="lg ' + cls + '">' + lbl + ' (' + L.length + ')</span>' + L.map(nm).join('') : ''; };
    $('lcount').textContent = '· ' + j.pemain.length + ' murid masuk';
    $('lchips').innerHTML = grup(1, 'm', '🔴 MERAH') + grup(2, 'p', '⚪ PUTIH') + grup(0, 'u', 'Belum bertim') || '<span style="opacity:.6">Menunggu murid masuk…</span>';
  }
  function overlay(j){
    var o = $('ovl'), h, keyOv;
    if (j.st === 'lobi') lobi(j); else $('lobi').style.display = 'none';
    if (j.st === 'kosong') { keyOv = 'kosong'; h = '<div class="msgbox"><div class="em">🪢</div><h2>Menunggu guru…</h2><p>Guru belum membuat sesi pertandingan.</p></div>'; }
    else if (j.st === 'jeda') { keyOv = 'jeda'; h = '<div class="msgbox"><div class="em">⏸️</div><h2>Pertandingan dijeda</h2><p>Menunggu guru melanjutkan…</p></div>'; }
    else { o.style.display = 'none'; S.ov = ''; return; }
    if (keyOv === S.ov) return;
    S.ov = keyOv; o.innerHTML = h; o.style.display = 'grid';
  }

  function hud(j){
    var r = $('rnd');
    if (j.st === 'lobi' || j.st === 'kosong') r.innerHTML = 'LOBI<small>menunggu pemain</small>';
    else if (j.st === 'selesai') r.innerHTML = 'SELESAI<small>' + j.riwayat.length + ' tarikan</small>';
    else r.innerHTML = esc(String(j.label || '').toUpperCase()) + '<small>' + (j.sub ? esc(j.sub) + ' · ' : '') + 'tali: ' + Math.abs(j.pos) + ' dari ' + j.selisih + ' langkah</small>';
    var live = j.st === 'soal' || j.st === 'jeda';
    $('s1').textContent = j.jml[1] + ' HP' + (live ? ' · menjawab ' + j.sudah[1] + '/' + j.jml[1] : '');
    $('s2').textContent = j.jml[2] + ' HP' + (live ? ' · menjawab ' + j.sudah[2] + '/' + j.jml[2] : '');
    $('scene').setAttribute('class', j.st === 'selesai' ? 'lengang' : '');
  }

  function terima(j){
    if (j.st === 'kosong') { j.jml = [0,0,0]; j.sudah = [0,0,0]; j.riwayat = []; j.pos = 0; j.selisih = 1; j.pemain = []; j.ronde = 0; j.total = 0; j.label = ''; j.sub = ''; }
    S.j = j; S.sisa = j.sisa; S.t0 = Date.now();
    susunTim(j.jml[1], j.jml[2]);
    var k = j.st + '|' + j.sesi + '|' + j.ronde;
    if (k !== S.k) {
      var pertama = S.k === null; S.k = k;
      if (j.st === 'lobi' || j.st === 'kosong') { setKelas('idle'); berdiri(); cap(''); banner(''); $('conf').innerHTML = ''; }
      else if (j.st !== 'jeda') transisi(j);
    }
    if (!S.tahanPos) setPos(j);
    hud(j); overlay(j);
  }

  setInterval(function(){
    var t = $('tmr'), j = S.j;
    if (!j) return;
    var live = j.st === 'soal' || j.st === 'jeda';
    if (!live || S.sisa == null) { t.textContent = live ? '∞' : '–'; t.className = ''; return; }
    var s = j.st === 'jeda' ? S.sisa : Math.max(0, S.sisa - (Date.now() - S.t0)), d = Math.ceil(s/1000);
    t.textContent = d; t.className = d <= 5 ? 'low' : '';
    if (j.st === 'soal' && d <= 5 && d > 0 && d !== S.tickTerakhir) { S.tickTerakhir = d; sfx.tik(); }
  }, 200);

  function loop(){
    fetch(API + '?a=gstate&game=' + GAME, {cache:'no-store'}).then(function(r){ return r.json(); })
      .then(function(j){ if (j.ok !== false) terima(j); else { $('ovl').innerHTML = '<div class="msgbox"><h2>'+esc(j.pesan||'Terputus')+'</h2></div>'; $('ovl').style.display='grid'; S.ov=''; } })
      .catch(function(){}).then(function(){ setTimeout(loop, 700); });
  }

  bangunAdegan();
  tarik = $('tarik');
  susunTim(0, 0);
  loop();
})();
</script>
</body></html>

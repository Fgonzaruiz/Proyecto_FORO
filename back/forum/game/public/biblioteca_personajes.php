<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer, $theme;

$prefix = TABLE_PREFIX;

try {
    $query = $db->query("SELECT * FROM {$prefix}game_personajes ORDER BY id ASC");
    $chars = [];
    while ($row = $db->fetch_array($query)) {
        $stats = !empty($row['stats_json']) ? json_decode($row['stats_json'], true) : [];
        $chars[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'race' => $row['race'],
            'race_name' => $row['race_name'],
            'job' => $row['occupation'],
            'job_name' => $row['occupation_name'],
            'desc' => $row['desc'],
            'details' => $row['details'],
            'rango' => $row['rango'],
            'tripulacion' => $row['tripulacion'],
            'recompensa' => $row['recompensa'],
            'banner' => $row['banner'],
            'stats' => [
                'FUE' => (int)($stats['fue'] ?? $stats['str'] ?? $row['stat_fp'] ?? 5),
                'AGI' => (int)($stats['agi'] ?? $row['stat_dp'] ?? 5),
                'DES' => (int)($stats['des'] ?? $stats['res'] ?? $row['stat_rp'] ?? 5),
                'INST' => (int)($stats['inst'] ?? $stats['vol'] ?? $row['stat_vp'] ?? 5),
                'ESP' => (int)($stats['esp'] ?? $stats['vol'] ?? $row['stat_vp'] ?? 5),
                'INT' => (int)($stats['int'] ?? $row['stat_ip'] ?? 5),
            ]
        ];
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Error al cargar Personajes</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

$b_url = $mybb->settings['bburl'] . '/images/game/personaje_banner.png';

$cards = [];
foreach ($chars as $c) {
    $sj = htmlspecialchars(json_encode($c['stats']), ENT_QUOTES, 'UTF-8');
    $cards[] = '<div class="rpg-lib-card" data-id="' . $c['id'] . '" data-name="' . htmlspecialchars($c['name']) . '" data-race="' . $c['race'] . '" data-job="' . $c['job'] . '" data-desc="' . htmlspecialchars($c['desc']) . '" data-details="' . htmlspecialchars($c['details']) . '" data-img="' . $b_url . '" data-stats=\'' . $sj . '\' data-rango="' . htmlspecialchars($c['rango']) . '" data-tripulacion="' . htmlspecialchars($c['tripulacion']) . '" data-recompensa="' . htmlspecialchars($c['recompensa']) . '"><div class="rpg-lib-card-img" style="background-image: url(\'' . $b_url . '\');"><span class="rpg-lib-card-badge">' . htmlspecialchars($c['race_name']) . '</span></div><div class="rpg-lib-card-body"><h2 class="rpg-lib-card-title">' . htmlspecialchars($c['name']) . '</h2><p class="rpg-lib-card-desc">' . htmlspecialchars($c['desc']) . '</p><div class="rpg-lib-card-stats"><span class="rpg-lib-card-stat"><i class="fas fa-briefcase"></i> ' . htmlspecialchars($c['job_name']) . '</span></div></div></div>';
}
$cards_html = implode("\n", $cards);

ob_start();
?>
<div class="rpg-lib-container">
  <div class="rpg-lib-banner" style="background-image: url('<?= $b_url ?>');">
    <div class="rpg-lib-banner-content">
      <h1>Biblioteca: Personajes</h1>
      <p>Explora todos los personajes del foro de rol, sus razas, ocupaciones y estad&iacute;sticas.</p>
    </div>
  </div>
  <div class="rpg-lib-body">
    <aside class="rpg-lib-sidebar">
      <h3><i class="fas fa-filter"></i> Filtros</h3>
      <div class="rpg-filter-group">
        <span class="rpg-filter-label">Nombre del Personaje</span>
        <div class="rpg-search-wrapper"><input type="text" id="lib-search" class="textbox" placeholder="Buscar personaje..."><i class="fas fa-search"></i></div>
      </div>
      <div class="rpg-filter-group">
        <span class="rpg-filter-label">Raza</span>
        <label class="rpg-filter-option"><input type="checkbox" name="race" value="humano" checked><span class="rpg-filter-checkbox"></span>Humano</label>
        <label class="rpg-filter-option"><input type="checkbox" name="race" value="gyojin" checked><span class="rpg-filter-checkbox"></span>Gyojin</label>
        <label class="rpg-filter-option"><input type="checkbox" name="race" value="mink" checked><span class="rpg-filter-checkbox"></span>Reno / Mink</label>
      </div>
      <div class="rpg-filter-group">
        <span class="rpg-filter-label">Ocupaci&oacute;n / Rol</span>
        <label class="rpg-filter-option"><input type="checkbox" name="job" value="capitan" checked><span class="rpg-filter-checkbox"></span>Capit&aacute;n</label>
        <label class="rpg-filter-option"><input type="checkbox" name="job" value="combatiente" checked><span class="rpg-filter-checkbox"></span>Combatiente</label>
        <label class="rpg-filter-option"><input type="checkbox" name="job" value="navegante" checked><span class="rpg-filter-checkbox"></span>Navegante</label>
        <label class="rpg-filter-option"><input type="checkbox" name="job" value="medico" checked><span class="rpg-filter-checkbox"></span>M&eacute;dico</label>
        <label class="rpg-filter-option"><input type="checkbox" name="job" value="arqueologo" checked><span class="rpg-filter-checkbox"></span>Arque&oacute;logo</label>
        <label class="rpg-filter-option"><input type="checkbox" name="job" value="carpintero" checked><span class="rpg-filter-checkbox"></span>Carpintero</label>
      </div>
    </aside>
    <main class="rpg-lib-content">
      <div class="rpg-lib-grid" id="lib-grid"><?= $cards_html ?></div>
    </main>
  </div>
</div>

<div class="rpg-lib-modal" id="lib-modal">
  <div class="rpg-lib-modal-content">
    <span class="rpg-lib-modal-close" id="modal-close">&times;</span>
    <div class="rpg-lib-modal-banner" id="modal-banner"></div>
    <div class="rpg-lib-modal-body">
      <div class="rpg-lib-modal-header rpg-modal-header-sticky" style="border-bottom:1px solid var(--border-color);padding-bottom:15px;">
        <h2 class="rpg-lib-modal-title" id="modal-title">Nombre</h2>
        <span class="rpg-lib-modal-badge" id="modal-badge">Raza</span>
      </div>
      <div class="rpg-modal-grid">
        <div class="rpg-modal-column-left">
          <div class="rpg-modal-npc-section-title"><i class="fas fa-chart-pie"></i> Distribuci&oacute;n de Stats</div>
          <div class="rpg-radar-container" id="modal-radar-wrapper"></div>
        </div>
        <div class="rpg-modal-column-right">
          <div class="rpg-modal-npc-section-title"><i class="fas fa-history"></i> Biograf&iacute;a y Habilidades</div>
          <p class="rpg-lib-modal-desc" id="modal-details" style="margin:0;min-height:60px;">Biograf&iacute;a del personaje...</p>
          <div class="rpg-modal-npc-section-title" style="margin-top:10px;"><i class="fas fa-address-card"></i> Datos del Cap&iacute;tulo</div>
          <div class="rpg-lib-modal-stats">
            <div class="rpg-lib-modal-stat-box"><div class="rpg-lib-modal-stat-lbl">Tripulaci&oacute;n</div><div class="rpg-lib-modal-stat-val" id="modal-stat-tripulacion">Sombreros de Paja</div></div>
            <div class="rpg-lib-modal-stat-box"><div class="rpg-lib-modal-stat-lbl">Rango / Rol</div><div class="rpg-lib-modal-stat-val" id="modal-stat-rango">Oficial</div></div>
            <div class="rpg-lib-modal-stat-box"><div class="rpg-lib-modal-stat-lbl">Recompensa</div><div class="rpg-lib-modal-stat-val" id="modal-stat-recompensa">0 Berries</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded",function(){
const si=document.getElementById("lib-search");
const rc=document.querySelectorAll("input[name='race']");
const jc=document.querySelectorAll("input[name='job']");
const cd=document.querySelectorAll(".rpg-lib-card");
const m=document.getElementById("lib-modal");
const mc=document.getElementById("modal-close");
const mb=document.getElementById("modal-banner");
const mt=document.getElementById("modal-title");
const mbd=document.getElementById("modal-badge");
const md=document.getElementById("modal-details");
const mrw=document.getElementById("modal-radar-wrapper");
const sT=document.getElementById("modal-stat-tripulacion");
const sR=document.getElementById("modal-stat-rango");
const sRc=document.getElementById("modal-stat-recompensa");

function radar(s){
const k=['FUE','AGI','DES','INST','ESP','INT'];
const l=['Fuerza','Agilidad','Destreza','Instinto','Espíritu','Intelecto'];
const mv=150,cx=170,cy=170,ra=100;
let g='',a='',lm=[];
for(let i=1;i<=5;i++){let r=ra*(i/5),p=[];for(let j=0;j<6;j++){let A=(j*60-90)*Math.PI/180;p.push((cx+r*Math.cos(A)).toFixed(1)+','+(cy+r*Math.sin(A)).toFixed(1))};g+='<polygon points="'+p.join(' ')+'" class="rpg-radar-polygon-bg"/>'}
for(let j=0;j<6;j++){let A=(j*60-90)*Math.PI/180;a+='<line x1="'+cx+'" y1="'+cy+'" x2="'+(cx+ra*Math.cos(A)).toFixed(1)+'" y2="'+(cy+ra*Math.sin(A)).toFixed(1)+'" class="rpg-radar-line"/>'}
let vp=[];for(let j=0;j<6;j++){let v=s[k[j]]||10,r=ra*Math.min(v,mv)/mv,A=(j*60-90)*Math.PI/180;vp.push((cx+r*Math.cos(A)).toFixed(1)+','+(cy+r*Math.sin(A)).toFixed(1))};let vg='<polygon points="'+vp.join(' ')+'" class="rpg-radar-polygon-value"/>';
for(let j=0;j<6;j++){let lb=l[j],v=s[k[j]]||0,A=(j*60-90)*Math.PI/180,x=cx+(ra+22)*Math.cos(A),y=cy+(ra+22)*Math.sin(A),an='middle';if(Math.cos(A)>0.1)an='start';else if(Math.cos(A)<-0.1)an='end';lm.push('<text x="'+x.toFixed(1)+'" y="'+(y+4).toFixed(1)+'" text-anchor="'+an+'" class="rpg-radar-label">'+lb+' ('+v+')</text>')}
return '<svg viewBox="0 0 340 340" class="rpg-radar-svg">'+g+a+vg+lm.join('')+'</svg>';}

function fl(){let t=si.value.toLowerCase().trim();let ar=[],aj=[];rc.forEach(function(c){if(c.checked)ar.push(c.value)});jc.forEach(function(c){if(c.checked)aj.push(c.value)});cd.forEach(function(c){let n=c.getAttribute("data-name").toLowerCase(),r=c.getAttribute("data-race"),j=c.getAttribute("data-job");c.style.display=(n.includes(t)&&ar.includes(r)&&aj.includes(j))?"flex":"none"})}
si.addEventListener("input",fl);rc.forEach(function(c){c.addEventListener("change",fl)});jc.forEach(function(c){c.addEventListener("change",fl)});
cd.forEach(function(c){c.addEventListener("click",function(){let n=this.getAttribute("data-name"),r=this.querySelector(".rpg-lib-card-badge").textContent,d=this.getAttribute("data-details"),i=this.getAttribute("data-img"),s=JSON.parse(this.getAttribute("data-stats"));mb.style.backgroundImage="url('"+i+"')";mt.textContent=n;mbd.textContent=r;md.textContent=d;sT.textContent=this.getAttribute("data-tripulacion");sR.textContent=this.getAttribute("data-rango");sRc.textContent=this.getAttribute("data-recompensa");mrw.innerHTML=radar(s);m.classList.add("open");document.body.classList.add("modal-open")})});
mc.addEventListener("click",function(){m.classList.remove("open");document.body.classList.remove("modal-open")});
m.addEventListener("click",function(e){if(e.target===m){m.classList.remove("open");document.body.classList.remove("modal-open")}});
});
</script>
<?php
$content = ob_get_clean();
game_render_page('Biblioteca de Personajes', $content);

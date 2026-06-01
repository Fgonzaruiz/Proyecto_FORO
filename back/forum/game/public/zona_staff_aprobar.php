<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if (!isset($mybb) || !is_object($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$uid = (int)$mybb->user['uid'];
$prefix = TABLE_PREFIX;

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
$pj_name = '';

if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT name, staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $staff_level = (int)$pj['staff_level'];
        $pj_name = $pj['name'];
    }
}

if ($staff_level < 1) {
    header('Location: ../index.php');
    exit;
}

$status_labels = [
    'pendiente' => ['label' => 'Sin Revisar', 'color' => '#ef4444', 'icon' => 'fa-clock'],
    'revision'  => ['label' => 'En Revisión', 'color' => '#f59e0b', 'icon' => 'fa-sync-alt'],
    'aprobada'  => ['label' => 'Aprobada', 'color' => '#10b981', 'icon' => 'fa-check-circle'],
    'rechazada' => ['label' => 'Rechazada', 'color' => '#ef4444', 'icon' => 'fa-times-circle'],
];

$b_url = $mybb->settings['bburl'];

ob_start();
?>
<style>
/* New Perk-based Linaje Styles */
.gene-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.gene-card {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 16px;
    background: var(--bg-main);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
    text-align: left;
}
.gene-card:hover { border-color: rgba(99,102,241,0.4); transform: translateX(3px); }
.gene-card.passive-primary { border-left: 3px solid #10b981; }
.gene-card.passive-secondary { border-left: 3px solid #f59e0b; }
.gene-card.perk-racial { border-left: 3px solid var(--accent-indigo); }
.gene-card.perk-general { border-left: 3px solid var(--accent-purple); }
.gene-card-icon { width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.gene-card-info { flex: 1; display: flex; flex-direction: column; }
.gene-card-name { font-weight: 800; font-size: 13px; color: var(--text-primary); margin-bottom: 2px; font-family: var(--font-heading); text-transform: uppercase; letter-spacing: 0.5px; }
.gene-card-desc { font-size: 11px; color: var(--text-muted); line-height: 1.4; margin-top: 4px; margin-bottom: 6px; }
.gene-card-badge {
    align-self: flex-start;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 2px 8px;
    border-radius: 10px;
    flex-shrink: 0;
}
</style>
<div class="rpg-staff-zone">
  <div class="rpg-staff-header" style="background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(99,102,241,0.1));">
    <div class="rpg-staff-header-content">
      <h1><i class="fas fa-user-check"></i> Aprobar Personajes</h1>
      <p>Revisa las fichas de personaje pendientes de aprobaci&oacute;n. <strong><?= htmlspecialchars($pj_name) ?></strong></p>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="aprobar-filter-bar">
    <button class="aprobar-filter-btn active" data-filter="">Todos</button>
    <button class="aprobar-filter-btn" data-filter="pendiente" style="color:#ef4444;">Sin Revisar</button>
    <button class="aprobar-filter-btn" data-filter="revision" style="color:#f59e0b;">En Revisión</button>
    <button class="aprobar-filter-btn" data-filter="aprobada" style="color:#10b981;">Aprobadas</button>
    <button class="aprobar-filter-btn" data-filter="rechazada" style="color:#ef4444;">Rechazadas</button>
  </div>

  <div class="aprobar-layout">
    <!-- LEFT: Character List -->
    <div class="aprobar-list" id="aprobar-list">
      <div class="aprobar-list-header">
        <span>Personajes</span>
        <span class="aprobar-count" id="aprobar-count">0</span>
      </div>
      <div id="aprobar-list-items">
        <div class="aprobar-empty">Cargando...</div>
      </div>
    </div>

    <!-- RIGHT: Preview Panel -->
    <div class="aprobar-preview" id="aprobar-preview">
      <div class="aprobar-empty" style="padding:60px 20px; text-align:center; color:var(--text-muted);">
        <i class="fas fa-user-check" style="font-size:48px; display:block; margin-bottom:15px; opacity:0.3;"></i>
        Selecciona un personaje para revisar su ficha
      </div>
    </div>
  </div>
</div>



<script>
// ==================== LINAJE PERK SYSTEM CATALOG ====================
var LINAJE_DATA = {
    pasivas: {
        'Humano': [
            { id:'p_hum_adapt', type:'primaria', name:'Maestro sin Maestro', icon:'fa-graduation-cap', iconColor:'#10b981', desc:'Aprende oficios y técnicas un 20% más rápido que otras razas. Bono de adaptabilidad en entornos desconocidos.' },
            { id:'p_hum_luck',  type:'secundaria', name:'Suerte del Mar', icon:'fa-dice', iconColor:'#f59e0b', desc:'Una vez por arco, rerollea automáticamente un dado con desventaja. La fortuna acompaña al audaz.' }
        ],
        'Mink': [
            { id:'p_mink_pelaje', type:'primaria', name:'Pelaje Conductor', icon:'fa-bolt', iconColor:'#10b981', desc:'Inmunidad natural al frío extremo. +1 a tiradas de resistencia en clima adverso.' },
            { id:'p_mink_electro', type:'primaria', name:'Electro Innato', icon:'fa-charging-station', iconColor:'#06b6d4', desc:'Puede canalizar pequeñas descargas eléctricas en combate cuerpo a cuerpo.' },
            { id:'p_mink_noche', type:'secundaria', name:'Instinto Nocturno', icon:'fa-moon', iconColor:'#f59e0b', desc:'Visión perfecta en oscuridad total. Inmune a penalizadores de combate nocturno.' }
        ],
        'Gyojin': [
            { id:'p_gyojin_agua', type:'primaria', name:'Respiración Anfibia', icon:'fa-water', iconColor:'#10b981', desc:'Respira y combate igual de bien bajo el agua. Velocidad de nado 5x superior al humano.' },
            { id:'p_gyojin_fuerza', type:'primaria', name:'Fuerza de las Profundidades', icon:'fa-dumbbell', iconColor:'#3b82f6', desc:'Fuerza física ×10 respecto a un humano medio. Ventaja automática en tests de fuerza bruta.' },
            { id:'p_gyojin_karate', type:'secundaria', name:'Afinidad Karate Gyojin', icon:'fa-hand-paper', iconColor:'#f59e0b', desc:'Bono de +2 en tiradas de Karate Gyojin. El agua obece a tu llamada.' }
        ],
        'Gigante': [
            { id:'p_gigante_talla', type:'primaria', name:'Talla Colosal', icon:'fa-expand-arrows-alt', iconColor:'#10b981', desc:'Tu tamaño físico da ventaja en empujes y ataques de área. Inmune a derribo por fuerzas menores.' },
            { id:'p_gigante_pv', type:'primaria', name:'Vida Monumental', icon:'fa-heart', iconColor:'#ef4444', desc:'PV base aumentado en un 30%. Tu vitalidad atemoriza a los rivales.' },
            { id:'p_gigante_terror', type:'secundaria', name:'Presencia Aterradora', icon:'fa-skull', iconColor:'#f59e0b', desc:'Enemigos de nivel bajo deben superar una tirada de moral al enfrentarte directamente.' }
        ],
        'Piernas Largas': [
            { id:'p_ll_velocidad', type:'primaria', name:'Zancada Monumental', icon:'fa-running', iconColor:'#10b981', desc:'Velocidad de movimiento superior en tierra firme. Puedes cubrir distancias enormes en pocos pasos.' },
            { id:'p_ll_alcance', type:'primaria', name:'Alcance Extendido', icon:'fa-arrows-alt-v', iconColor:'#3b82f6', desc:'Ataques de patada tienen rango superior. Puedes golpear objetivos a distancia media sin moverse.' },
            { id:'p_ll_equilibrio', type:'secundaria', name:'Equilibrio Perfecto', icon:'fa-balance-scale', iconColor:'#f59e0b', desc:'Inmune a efectos de derribo en terreno inestable. Nunca pierde el balance en cubierta de barco.' }
        ],
        'Brazos Largos': [
            { id:'p_bl_alcance', type:'primaria', name:'Brazos de Gigante', icon:'fa-hand-rock', iconColor:'#10b981', desc:'Alcance físico muy superior. Ventaja en ataques de rango largo y golpes a distancia.' },
            { id:'p_bl_agarre', type:'primaria', name:'Agarre Férreo', icon:'fa-grip-strength', iconColor:'#3b82f6', desc:'Muy difícil escapar de un agarre o lucha de control. +3 a tiradas de presa.' },
            { id:'p_bl_lanzar', type:'secundaria', name:'Proyectil Viviente', icon:'fa-baseball-ball', iconColor:'#f59e0b', desc:'Puede lanzar objetos medianos con precisión y potencia extremas.' }
        ],
        'Cuello Largo': [
            { id:'p_cl_vision', type:'primaria', name:'Vista Panorámica', icon:'fa-eye', iconColor:'#10b981', desc:'Puede elevar la cabeza para ver por encima de obstáculos altos. Ventaja en reconocimiento.' },
            { id:'p_cl_mira', type:'primaria', name:'Mira Natural', icon:'fa-crosshairs', iconColor:'#3b82f6', desc:'Bono a tiradas de observación y detección a larga distancia.' },
            { id:'p_cl_oido', type:'secundaria', name:'Oído Amplificado', icon:'fa-assistive-listening-systems', iconColor:'#f59e0b', desc:'Oye conversaciones lejanas con una tirada de Instinto moderada.' }
        ],
        'Tontatta': [
            { id:'p_ton_mini', type:'primaria', name:'Miniaturización Extrema', icon:'fa-compress-arrows-alt', iconColor:'#10b981', desc:'Tamaño diminuto, casi invisible para razas grandes. Ventaja en infiltración y ocultamiento.' },
            { id:'p_ton_fuerza', type:'primaria', name:'Fuerza Desproporcionada', icon:'fa-fist-raised', iconColor:'#3b82f6', desc:'Fuerza física muy superior a su tamaño. Puede mover objetos muchísimo más grandes.' },
            { id:'p_ton_herbo', type:'secundaria', name:'Herbolaria Élite', icon:'fa-leaf', iconColor:'#f59e0b', desc:'Conocimiento de plantas y venenos del Bosque de Tontatta. +2 a tiradas de medicina natural.' }
        ],
        'Buccaner': [
            { id:'p_buc_sangre', type:'primaria', name:'Sangre Ardiente', icon:'fa-fire', iconColor:'#10b981', desc:'El Haki fluye de forma más natural e intensa. Menor tiempo de entrenamiento para desarrollarlo.' },
            { id:'p_buc_aguante', type:'primaria', name:'Cuerpo Forjado', icon:'fa-shield-alt', iconColor:'#ef4444', desc:'Resistencia a lesiones graves. Ignora el primer penalizador de daño por combate en cada escena.' },
            { id:'p_buc_leyenda', type:'secundaria', name:'Herencia Legendaria', icon:'fa-crown', iconColor:'#f59e0b', desc:'Figuras de autoridad te reconocen inconscientemente. Bono social con facciones históricas.' }
        ],
        'Lunarian': [
            { id:'p_lun_fuego', type:'primaria', name:'Llama Racial', icon:'fa-fire-alt', iconColor:'#10b981', desc:'Genera llamas naturales en la espalda. Inmune al daño por fuego normal.' },
            { id:'p_lun_vuelo', type:'primaria', name:'Alas de Ceniza', icon:'fa-feather-alt', iconColor:'#8b5cf6', desc:'Puede planar y descender controladamente. No vuelo sostenido, pero saltos enormes.' },
            { id:'p_lun_dura', type:'secundaria', name:'Cuerpo de Piedra', icon:'fa-chess-rook', iconColor:'#f59e0b', desc:'Resistencia física excepcional. Reduce daño físico recibido un 10% de forma pasiva.' }
        ],
        'Skypean': [
            { id:'p_sky_alas', type:'primaria', name:'Alas de Isla', icon:'fa-wind', iconColor:'#10b981', desc:'Puede planar largas distancias usando corrientes de aire. Control superior en alturas.' },
            { id:'p_sky_mantra', type:'primaria', name:'Observación Innata', icon:'fa-broadcast-tower', iconColor:'#06b6d4', desc:'Sensibilidad natural al Mantra/Haki de Observación. Menor umbral para detectarlo.' },
            { id:'p_sky_dial', type:'secundaria', name:'Dialecto del Cielo', icon:'fa-comments', iconColor:'#f59e0b', desc:'Comunicación fluida con otras razas celestiales. Acceso a conocimientos del Cielo Superior.' }
        ]
    },
    racial: {
        'Humano': [
            { id:'lr_hum_tenaz', name:'Tenacidad Pura', icon:'fa-hand-rock', iconColor:'#6366f1', desc:'Una vez por evento, no caes inconsciente automáticamente por daño letal.' },
            { id:'lr_hum_estudio', name:'Estudiante Dedicado', icon:'fa-book', iconColor:'#6366f1', desc:'Bono +1 en cualquier tirada de Intelecto una vez por escena.' },
            { id:'lr_hum_lider', name:'Liderazgo Natural', icon:'fa-users', iconColor:'#6366f1', desc:'Compañeros cercanos ganan +1 en moral mientras no estés incapacitado.' }
        ],
        'Mink': [
            { id:'lr_mink_sulong', name:'Furia Sulong', icon:'fa-moon', iconColor:'#6366f1', desc:'Bajo la luna llena, stats ofensivos aumentan dramáticamente durante la escena.' },
            { id:'lr_mink_rastro', name:'Rastreador Experto', icon:'fa-paw', iconColor:'#6366f1', desc:'Puede seguir rastros de olfato con éxito automático en condiciones normales.' },
            { id:'lr_mink_pack', name:'Mentalidad de Manada', icon:'fa-users-cog', iconColor:'#6366f1', desc:'Bono de coordinación con aliados. +1 a ataques en pareja con otro personaje.' }
        ],
        'Gyojin': [
            { id:'lr_gyojin_corriente', name:'Maestro de Corrientes', icon:'fa-water', iconColor:'#6366f1', desc:'Control de corrientes marinas en un radio pequeño. Útil para naufragios y emboscadas acuáticas.' },
            { id:'lr_gyojin_peces', name:'Habla con Peces', icon:'fa-fish', iconColor:'#6366f1', desc:'Puede comunicarse con criaturas marinas. Fuente de inteligencia única.' },
            { id:'lr_gyojin_sangre', name:'Sangre del Océano', icon:'fa-tint', iconColor:'#6366f1', desc:'En entornos acuáticos, todas las tiradas de combate tienen +1.' }
        ],
        'Gigante': [
            { id:'lr_gigante_arma', name:'Arma Gigante', icon:'fa-hammer', iconColor:'#6366f1', desc:'Puede empuñar armas de tamaño descomunal inutilizables para otras razas.' },
            { id:'lr_gigante_voz', name:'Voz del Trueno', icon:'fa-volume-up', iconColor:'#6366f1', desc:'Un grito aturde a todos en un radio cercano. Una vez por combate.' }
        ],
        'Piernas Largas': [
            { id:'lr_ll_patada', name:'Patada Devastadora', icon:'fa-shoe-prints', iconColor:'#6366f1', desc:'Una patada cargada rompe estructuras de madera o piedra blanda. +2 a tiradas de impacto.' },
            { id:'lr_ll_corrida', name:'Velocista del Mar', icon:'fa-tachometer-alt', iconColor:'#6366f1', desc:'En campo abierto, nadie puede alcanzarte si decides huir. Éxito automático en escapar.' }
        ],
        'Brazos Largos': [
            { id:'lr_bl_instrumento', name:'Virtuoso Instrumental', icon:'fa-music', iconColor:'#6366f1', desc:'Bono especial al tocar instrumentos de cuerda. Perfecto para oficios musicales o de precisión.' },
            { id:'lr_bl_trabajo', name:'Trabajador Infatigable', icon:'fa-hard-hat', iconColor:'#6366f1', desc:'Doble rendimiento en tareas manuales largas (construcción, reparación de barcos, etc.).' }
        ],
        'Cuello Largo': [
            { id:'lr_cl_testigo', name:'Testigo Perfecto', icon:'fa-binoculars', iconColor:'#6366f1', desc:'Nunca puede ser engañado en una escena de negociación si observa el lenguaje corporal.' },
            { id:'lr_cl_vigia', name:'Vigía de Viga', icon:'fa-search', iconColor:'#6366f1', desc:'En barco, su turno de vigia nunca produce falsos negativos.' }
        ],
        'Tontatta': [
            { id:'lr_ton_veneno', name:'Alquimista Secreto', icon:'fa-flask', iconColor:'#6366f1', desc:'Puede fabricar venenos y antidotos con plantas comunes. Efecto moderado garantizado.' },
            { id:'lr_ton_construir', name:'Constructor Férreo', icon:'fa-cogs', iconColor:'#6366f1', desc:'Puede reparar mecanismos complejos sin herramientas. Tiempo de reparación ÷3.' },
            { id:'lr_ton_red', name:'Red de Túneles', icon:'fa-network-wired', iconColor:'#6366f1', desc:'Conoce o puede crear túneles subterráneos. Movimiento oculto en lugares apropiados.' }
        ],
        'Buccaner': [
            { id:'lr_buc_haki', name:'Legado del Haki', icon:'fa-fist-raised', iconColor:'#6366f1', desc:'Desbloquea el Haki de Armadura o Observación antes que la media. Entrenamiento acelerado.' },
            { id:'lr_buc_alianza', name:'Pacto de Sangre', icon:'fa-handshake', iconColor:'#6366f1', desc:'Una promesa hecha por un Buccaner es magicamente vinculante. Aliados confían un 30% más.' }
        ],
        'Lunarian': [
            { id:'lr_lun_llama_atk', name:'Llama Ofensiva', icon:'fa-fire', iconColor:'#6366f1', desc:'Puede lanzar bengalas o llamaradas como proyectil. Daño de fuego moderado a distancia corta.' },
            { id:'lr_lun_invulnerable', name:'Momento de Piedra', icon:'fa-gem', iconColor:'#6366f1', desc:'Una vez por combate, activa invulnerabilidad total durante 1 acción. La llama en la espalda se apaga.' }
        ],
        'Skypean': [
            { id:'lr_sky_dial_arma', name:'Maestro de Dials', icon:'fa-compact-disc', iconColor:'#6366f1', desc:'Puede usar Dials con maestría sin entrenamiento especial. +1 uso por Dial en escena.' },
            { id:'lr_sky_tormenta', name:'Hijo de la Tormenta', icon:'fa-cloud-lightning', iconColor:'#6366f1', desc:'En zonas de tormenta eléctrica, tiene ventaja en todas las tiradas físicas.' }
        ]
    },
    general: [
        { id:'lg_acero',     name:'Piel de Acero',     icon:'fa-shield-alt',        iconColor:'#a855f7', desc:'Reduce un 5% el daño físico recibido de forma pasiva.' },
        { id:'lg_voluntad',  name:'Voluntad Férrea',  icon:'fa-brain',             iconColor:'#a855f7', desc:'+2 a tiradas de resistencia mental. Inmunidad a efectos de miedo menor.' },
        { id:'lg_sombra',    name:'Paso Silencioso',   icon:'fa-user-ninja',        iconColor:'#a855f7', desc:'Ventaja en tiradas de sigilo en exteriores nocturnos.' },
        { id:'lg_vida',      name:'Vitalidad Extra',   icon:'fa-heartbeat',         iconColor:'#a855f7', desc:'+15 a PV máximos. Tu cuerpo aguanta más de lo normal.' },
        { id:'lg_energia',   name:'Reserva de Energía', icon:'fa-bolt',            iconColor:'#a855f7', desc:'+10 a PE máximos. Tu espíritu arde con fuerza adicional.' },
        { id:'lg_olfato',    name:'Sentido Agudizado', icon:'fa-search',            iconColor:'#a855f7', desc:'Detección pasiva de emboscadas en un radio de 10m.' },
        { id:'lg_fortuna',   name:'Golpe de Suerte',   icon:'fa-dice-d20',          iconColor:'#a855f7', desc:'Una vez por escena, convierte un fallo en un éxito menor inesperado.' },
        { id:'lg_navegante', name:'Navegante Instintivo', icon:'fa-compass',         iconColor:'#a855f7', desc:'Bono +2 en tiradas de navegación. Nunca se pierde en mar abierto.' }
    ]
};

function findPerkById(id) {
    var p = LINAJE_DATA.general.find(function(item) { return item.id === id; });
    if (p) return p;
    for (var r in LINAJE_DATA.racial) {
        p = LINAJE_DATA.racial[r].find(function(item) { return item.id === id; });
        if (p) return p;
    }
    for (var r in LINAJE_DATA.pasivas) {
        p = LINAJE_DATA.pasivas[r].find(function(item) { return item.id === id; });
        if (p) return p;
    }
    return null;
}

function makeAprobarPerkCard(p, cssClass, iconBg, badgeLabel, badgeColor) {
    return '<div class="gene-card ' + cssClass + '">' +
        '<div class="gene-card-icon" style="' + iconBg + '">' +
            '<i class="fas ' + p.icon + '" style="color:' + p.iconColor + ';"></i>' +
        '</div>' +
        '<div class="gene-card-info">' +
            '<div class="gene-card-name">' + escapeHtml(p.name) + '</div>' +
            '<div class="gene-card-desc">' + escapeHtml(p.desc) + '</div>' +
        '</div>' +
        '<div class="gene-card-badge" style="background:' + badgeColor + '22; color:' + badgeColor + ';">' + badgeLabel + '</div>' +
    '</div>';
}

var currentPJ = null;
var currentFilter = '';

var statusConfig = {
  'pendiente': { label: 'Sin Revisar', color: '#ef4444', icon: 'fa-clock' },
  'revision':  { label: 'En Revisión', color: '#f59e0b', icon: 'fa-sync-alt' },
  'aprobada':  { label: 'Aprobada', color: '#10b981', icon: 'fa-check-circle' },
  'rechazada': { label: 'Rechazada', color: '#ef4444', icon: 'fa-times-circle' },
};

function loadList(filter) {
  currentFilter = filter || '';
  var url = '<?= $b_url ?>/game/ajax/personajes_pendientes_list.php';
  if (filter) url += '?filter=' + encodeURIComponent(filter);

  fetch(url)
    .then(function(r) {
      if (!r.ok) { throw new Error('HTTP ' + r.status); }
      return r.json();
    })
    .then(function(res) {
      if (!res.ok) { throw new Error(res.error?.message || 'Error del servidor'); }
      renderList(res.data);
    })
    .catch(function(err) {
      document.getElementById('aprobar-list-items').innerHTML = '<div class="aprobar-empty">Error: ' + err.message + '</div>';
    });
}

function renderList(chars) {
  var container = document.getElementById('aprobar-list-items');
  var countEl = document.getElementById('aprobar-count');
  countEl.textContent = chars.length;

  if (!chars.length) {
    container.innerHTML = '<div class="aprobar-empty">No hay personajes en esta categor&iacute;a</div>';
    return;
  }

  var html = '';
  chars.forEach(function(c) {
    var cfg = statusConfig[c.status] || { label: c.status, color: '#94a3b8', icon: 'fa-question' };
    var avatarUrl = c.avatar || 'https://placehold.co/290x450';
    html += '<div class="aprobar-list-item" data-id="' + c.id + '" onclick="selectChar(' + c.id + ')">';
    html += '  <div class="aprobar-list-item-avatar" style="background-image:url(' + avatarUrl + ');"></div>';
    html += '  <div class="aprobar-list-item-body">';
    html += '    <div class="aprobar-list-item-name">' + escapeHtml(c.name) + '</div>';
    html += '    <div class="aprobar-list-item-user">' + escapeHtml(c.username) + '</div>';
    html += '    <span class="aprobar-list-item-status" style="color:' + cfg.color + ';"><i class="fas ' + cfg.icon + '"></i> ' + cfg.label + '</span>';
    html += '  </div>';
    html += '</div>';
  });
  container.innerHTML = html;
}

function selectChar(id) {
  // Highlight selected
  var items = document.querySelectorAll('.aprobar-list-item');
  items.forEach(function(item) {
    item.classList.toggle('selected', parseInt(item.getAttribute('data-id')) === id);
  });

  // Fetch preview
  var preview = document.getElementById('aprobar-preview');
  preview.innerHTML = '<div class="aprobar-empty" style="padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i><br>Cargando ficha...</div>';

  var url = '<?= $b_url ?>/game/ajax/get_personaje_preview.php?pj=' + id;
  fetch(url)
    .then(function(r) {
      if (!r.ok) { throw new Error('HTTP ' + r.status); }
      return r.json();
    })
    .then(function(res) {
      if (!res.ok) { throw new Error(res.error?.message || 'Error del servidor'); }
      renderPreview(res.data);
      currentPJ = res.data;
    })
    .catch(function(err) {
      preview.innerHTML = '<div class="aprobar-empty">Error: ' + err.message + '</div>';
    });
}

function renderPreview(data) {
  var cfg = statusConfig[data.status] || { label: data.status, color: '#94a3b8', icon: 'fa-question' };
  var avatarUrl = data.avatar || 'https://placehold.co/290x450';
  var stats = data.stats || {};
  var bio = data.bio || {};
  var linaje = data.linaje || {};

  var html = '';
  // Avatar section
  html += '<div class="aprobar-preview-avatar" style="background-image:url(' + avatarUrl + ');"></div>';

  // Name + badges row
  html += '<div class="aprobar-preview-body">';
  html += '  <h2 class="aprobar-preview-name">' + escapeHtml(data.name) + '</h2>';
  html += '  <div class="aprobar-preview-badges">';
  html += '    <span class="aprobar-preview-badge" style="color:' + cfg.color + ';border-color:' + cfg.color + ';"><i class="fas ' + cfg.icon + '"></i> ' + cfg.label + '</span>';
  if (data.rango) html += '    <span class="aprobar-preview-badge" style="color:var(--accent-purple);border-color:var(--accent-purple);"><i class="fas fa-medal"></i> ' + escapeHtml(data.rango) + '</span>';
  if (data.faction) html += '    <span class="aprobar-preview-badge" style="color:var(--accent-indigo);border-color:var(--accent-indigo);"><i class="fas fa-flag"></i> ' + escapeHtml(data.faction) + '</span>';
  if (data.is_staff) html += '    <span class="aprobar-preview-badge" style="color:#fff;background:var(--accent-indigo);border-color:var(--accent-indigo);"><i class="fas fa-star"></i> Staff</span>';
  html += '  </div>';

  // Left info box (arquetipo, oficio, genes)
  html += '  <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; background:var(--bg-card); border-radius:var(--radius-md); padding:15px; border:1px solid var(--border-color); margin-bottom:20px;">';
  html += '    <div style="display:flex; align-items:center; gap:10px;">';
  html += '      <i class="fas fa-fist-raised" style="color:var(--accent-indigo); font-size:18px;"></i>';
  html += '      <div><div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Arquetipo Belico</div><div style="font-weight:700; color:var(--accent-indigo); font-size:13px;">' + escapeHtml(bio.arquetipo) + '</div></div>';
  html += '    </div>';
  html += '    <div style="display:flex; align-items:center; gap:10px;">';
  html += '      <i class="fas fa-briefcase" style="color:var(--accent-purple); font-size:18px;"></i>';
  html += '      <div><div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Oficio</div><div style="font-weight:700; color:var(--accent-purple); font-size:13px;">' + escapeHtml(data.occupation_name || 'Ninguno') + '</div></div>';
  html += '    </div>';
  var geneNames = linaje.geneNames || [];
  var genesText = geneNames.length ? geneNames.slice(0, 3).join(', ') + (geneNames.length > 3 ? ' +' + (geneNames.length - 3) : '') : 'Ninguno';
  html += '    <div style="display:flex; align-items:center; gap:10px;">';
  html += '      <i class="fas fa-dna" style="color:var(--accent-purple); font-size:18px;"></i>';
  html += '      <div><div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Genes Activos</div><div style="font-weight:700; color:var(--accent-purple); font-size:13px;">' + escapeHtml(genesText) + '</div></div>';
  html += '    </div>';
  html += '    <div style="display:flex; align-items:center; gap:10px;">';
  html += '      <i class="fas fa-user" style="color:var(--text-muted); font-size:18px;"></i>';
  html += '      <div><div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Jugador</div><div style="font-weight:700; color:var(--text-primary); font-size:13px;">' + escapeHtml(data.username) + '</div></div>';
  html += '    </div>';
  html += '  </div>';

  // Stats bars
  html += '  <h3 style="font-size:12px; font-family:var(--font-heading); color:var(--text-muted); text-transform:uppercase; margin-bottom:10px;">Atributos Base</h3>';
  var statMeta = [
    { key: 'str', label: 'FUERZA', color: '#6366f1' },
    { key: 'agi', label: 'AGILIDAD', color: '#10b981' },
    { key: 'res', label: 'RESISTENCIA', color: '#f59e0b' },
    { key: 'vol', label: 'VOLUNTAD', color: '#ef4444' },
  ];
  statMeta.forEach(function(s) {
    var val = parseInt(stats[s.key] || 0);
    var pct = Math.min(100, val * 10);
    html += '  <div style="margin-bottom:12px;">';
    html += '    <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>' + s.label + '</span><span>' + val + '</span></div>';
    html += '    <div style="background:var(--bg-card); border-radius:10px; height:8px; width:100%; overflow:hidden; margin-top:4px;">';
    html += '      <div style="height:100%; background:linear-gradient(90deg,' + s.color + ',' + s.color + 'cc); border-radius:10px; width:' + pct + '%;"></div>';
    html += '    </div>';
    html += '  </div>';
  });

  // TABS: Bio, Linaje
  html += '  <div class="pj-preview-tabs" style="display:flex; border-bottom:2px solid var(--border-color); margin:24px 0;">';
  html += '    <div class="pj-preview-tab aprobar-tab active" data-tab="bio" onclick="switchAprobarTab(\'bio\', this)" style="padding:10px 20px; font-weight:700; font-size:14px; color:var(--accent-indigo); cursor:pointer; border-bottom:3px solid var(--accent-indigo); transition:all 0.2s;"><i class="fas fa-file-alt"></i> Biografia</div>';
  html += '    <div class="pj-preview-tab aprobar-tab" data-tab="linaje" onclick="switchAprobarTab(\'linaje\', this)" style="padding:10px 20px; font-weight:700; font-size:14px; color:var(--text-muted); cursor:pointer; border-bottom:3px solid transparent; transition:all 0.2s;"><i class="fas fa-dna"></i> Mapa Genetico</div>';
  html += '  </div>';

  // TAB: BIOGRAFIA
  html += '  <div id="aprobTab_bio" class="aprobar-tab-content" style="display:block;">';

  // Info grid
  html += '    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:30px; background:var(--bg-surface); padding:20px; border-radius:var(--radius-md); border:1px solid var(--border-color);">';
  html += '      <div style="font-size:14px;"><strong>Edad:</strong> ' + escapeHtml(bio.age) + '</div>';
  html += '      <div style="font-size:14px;"><strong>Origen:</strong> ' + escapeHtml(bio.origin) + '</div>';
  html += '      <div style="font-size:14px;"><strong>Raza:</strong> ' + escapeHtml(bio.race) + '</div>';
  html += '      <div style="font-size:14px;"><strong>PB:</strong> ' + escapeHtml(bio.pb) + '</div>';
  html += '    </div>';

  // Apariencia Fisica
  html += '    <h3 style="font-family:var(--font-heading); font-size:16px; color:var(--text-primary); margin-bottom:10px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">Apariencia Fisica</h3>';
  html += '    <div class="aprobar-scroll-box">' + escapeHtml(bio.physique || 'Sin registrar.') + '</div>';

  // Perfil Psicologico
  html += '    <h3 style="font-family:var(--font-heading); font-size:16px; color:var(--text-primary); margin-bottom:10px; border-bottom:1px solid var(--border-color); padding-bottom:5px; margin-top:24px;">Perfil Psicologico</h3>';
  html += '    <div class="aprobar-scroll-box">' + escapeHtml(bio.psychology || bio.desc || 'Sin historia registrada.') + '</div>';

  // Extras
  html += '    <h3 style="font-family:var(--font-heading); font-size:16px; color:var(--text-primary); margin-bottom:10px; border-bottom:1px solid var(--border-color); padding-bottom:5px; margin-top:24px;">Extras y Notas</h3>';
  html += '    <div class="aprobar-scroll-box">' + escapeHtml(bio.extras || bio.details || 'Sin notas extras.') + '</div>';

  html += '  </div>';

  // TAB: LINAJE
  html += '  <div id="aprobTab_linaje" class="aprobar-tab-content" style="display:none;">';
  html += '    <p style="color:var(--text-muted); font-size:14px; margin-bottom:20px;">Perks de Linaje del personaje — pasivas innatas y habilidades elegidas.</p>';
  if (linaje.version === 2) {
    var hasAnyPerks = false;
    
    // Pasivas
    var pasivas = linaje.pasivas || [];
    if (pasivas.length > 0) {
      hasAnyPerks = true;
      html += '    <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:#10b981; margin-bottom:10px; display:flex; align-items:center; gap:8px;">';
      html += '      <i class="fas fa-shield-alt"></i> Pasivas Innatas';
      html += '    </div>';
      html += '    <div class="gene-cards-grid">';
      pasivas.forEach(function(pid) {
        var p = findPerkById(pid);
        if (p) {
          var is_prim = (p.type === 'primaria');
          html += makeAprobarPerkCard(p,
            is_prim ? 'passive-primary' : 'passive-secondary',
            is_prim ? 'background:rgba(16,185,129,0.12); border:2px solid rgba(16,185,129,0.35);' : 'background:rgba(245,158,11,0.1); border:2px solid rgba(245,158,11,0.3);',
            is_prim ? 'PRIMARIA' : 'SECUNDARIA',
            is_prim ? '#10b981' : '#f59e0b'
          );
        }
      });
      html += '    </div>';
    }

    // Racial
    var elegidos_racial = linaje.elegidos_racial || [];
    if (elegidos_racial.length > 0) {
      hasAnyPerks = true;
      html += '    <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:var(--accent-indigo); margin-top:20px; margin-bottom:10px; display:flex; align-items:center; gap:8px;">';
      html += '      <i class="fas fa-dna"></i> Linaje Racial';
      html += '    </div>';
      html += '    <div class="gene-cards-grid">';
      elegidos_racial.forEach(function(pid) {
        var p = findPerkById(pid) || { id: pid, name: pid, icon: 'fa-dna', iconColor: 'var(--accent-indigo)', desc: 'Perk racial seleccionado.' };
        html += makeAprobarPerkCard(p, 'perk-racial',
          'background:rgba(99,102,241,0.1); border:2px solid rgba(99,102,241,0.3);',
          'RACIAL', '#6366f1');
      });
      html += '    </div>';
    }

    // General
    var elegidos_general = linaje.elegidos_general || [];
    if (elegidos_general.length > 0) {
      hasAnyPerks = true;
      html += '    <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:var(--accent-purple); margin-top:20px; margin-bottom:10px; display:flex; align-items:center; gap:8px;">';
      html += '      <i class="fas fa-star"></i> Linaje General';
      html += '    </div>';
      html += '    <div class="gene-cards-grid">';
      elegidos_general.forEach(function(pid) {
        var p = findPerkById(pid) || { id: pid, name: pid, icon: 'fa-star', iconColor: 'var(--accent-purple)', desc: 'Perk general seleccionado.' };
        html += makeAprobarPerkCard(p, 'perk-general',
          'background:rgba(168,85,247,0.1); border:2px solid rgba(168,85,247,0.3);',
          'GENERAL', '#a855f7');
      });
      html += '    </div>';
    }

    if (!hasAnyPerks) {
      html += '    <div style="padding: 30px; text-align:center; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">';
      html += '      <i class="fas fa-scroll" style="font-size: 40px; color: var(--accent-indigo); opacity: 0.5; margin-bottom:15px;"></i>';
      html += '      <h4 style="color:var(--text-primary); margin-bottom:5px;">Sin Perks de Linaje</h4>';
      html += '      <p style="color:var(--text-muted); font-size:13px;">Este personaje no tiene perks de linaje asignados todavía.</p>';
      html += '    </div>';
    }
  } else {
    // Legacy v1
    html += '    <div style="padding:12px 16px; background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.3); border-radius:var(--radius-md); margin-bottom:20px; display:flex; align-items:center; gap:12px;">';
    html += '      <i class="fas fa-info-circle" style="color:#f59e0b; font-size:18px;"></i>';
    html += '      <div style="text-align:left;">';
    html += '        <div style="font-weight:800; font-size:12px; color:#f59e0b; text-transform:uppercase; letter-spacing:0.5px;">Ficha en formato antiguo</div>';
    html += '        <div style="font-size:12px; color:var(--text-muted);">El sistema de Linaje de este personaje será actualizado en la próxima revisión de ficha.</div>';
    html += '      </div>';
    html += '    </div>';

    if (geneNames.length) {
      html += '    <div class="gene-cards-grid">';
      geneNames.forEach(function(g) {
        var dummyPerk = { id: 'legacy', name: g, icon: 'fa-dna', iconColor: 'var(--accent-indigo)', desc: 'Gen activo (formato antiguo).' };
        html += makeAprobarPerkCard(dummyPerk, 'perk-racial',
          'background:rgba(99,102,241,0.1); border:2px solid rgba(99,102,241,0.3);',
          'RACIAL', '#6366f1');
      });
      html += '    </div>';
    } else {
      html += '    <div style="padding:30px; text-align:center; background:var(--bg-surface); border-radius:var(--radius-md); border:1px dashed var(--border-color);">';
      html += '      <i class="fas fa-dna" style="font-size:40px; color:var(--accent-purple); opacity:0.5; margin-bottom:15px;"></i>';
      html += '      <h4 style="color:var(--text-primary); margin-bottom:5px;">Sin Genes Extra</h4>';
      html += '      <p style="color:var(--text-muted); font-size:13px;">Este personaje no ha desarrollado genes mas alla de los basicos de su raza.</p>';
      html += '    </div>';
    }
  }
  html += '  </div>';

  // Actions
  html += '  <div class="aprobar-preview-actions" id="aprobar-actions">';
  if (data.status !== 'aprobada') {
    html += '    <button class="pj-btn-add" onclick="accionAprobar(' + data.id + ',\'aprobar\')" style="background:linear-gradient(135deg,#10b981,#059669) !important;"><i class="fas fa-check"></i> Aprobar</button>';
  }
  html += '    <button class="pj-btn-add" onclick="openModerar(' + data.id + ',\'' + data.status + '\')"><i class="fas fa-comment-dots"></i> Moderar</button>';
  if (data.status !== 'pendiente') {
    html += '    <button class="pj-btn-add" onclick="accionAprobar(' + data.id + ',\'pendiente\')" style="background:linear-gradient(135deg,#f59e0b,#d97706) !important;"><i class="fas fa-undo"></i> Volver a Pendiente</button>';
  }
  if (data.status !== 'rechazada') {
    html += '    <button class="pj-btn-add" onclick="accionAprobar(' + data.id + ',\'rechazar\')" style="background:linear-gradient(135deg,#ef4444,#dc2626) !important;"><i class="fas fa-times"></i> Rechazar</button>';
  }
  html += '  </div>';

  // Inline moderate section (hidden)
  html += '  <div class="aprobar-moderate" id="aprobar-moderate" style="display:none;">';
  html += '    <div class="aprobar-moderate-title"><i class="fas fa-comment-dots"></i> Mensaje al Jugador</div>';
  html += '    <p class="aprobar-moderate-desc">Escribe un mensaje para el jugador. Se le notificara junto con el cambio de estado.</p>';
  html += '    <textarea id="moderate-mensaje" class="aprobar-moderate-textarea" placeholder="Escribe tu mensaje aqui..."></textarea>';
  html += '    <div class="aprobar-moderate-actions">';
  html += '      <button class="pj-btn-add" onclick="toggleModerate()" style="background:var(--bg-main);color:var(--text-primary);border:1px solid var(--border-color)!important;box-shadow:none!important;">Cancelar</button>';
  html += '      <button class="pj-btn-add" onclick="enviarModeracion()"><i class="fas fa-paper-plane"></i> Enviar</button>';
  html += '    </div>';
  html += '  </div>';

  html += '</div>';

  document.getElementById('aprobar-preview').innerHTML = html;
}

function switchAprobarTab(tab, btn) {
  var tabs = document.querySelectorAll('.aprobar-tab');
  tabs.forEach(function(t) {
    t.style.color = 'var(--text-muted)';
    t.style.borderBottomColor = 'transparent';
  });
  btn.style.color = 'var(--accent-indigo)';
  btn.style.borderBottomColor = 'var(--accent-indigo)';

  var contents = document.querySelectorAll('.aprobar-tab-content');
  contents.forEach(function(c) { c.style.display = 'none'; });
  document.getElementById('aprobTab_' + tab).style.display = 'block';
}

function accionAprobar(personajeId, action) {
  var btn = event && event.currentTarget ? event.currentTarget : document.querySelector('#aprobar-actions button');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...'; }

  fetch('<?= $b_url ?>/game/ajax/aprobar_personaje.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ personaje_id: personajeId, action: action })
  })
  .then(function(r) { return r.json(); })
  .then(function(res) {
    if (res.ok) {
      loadList(currentFilter);
      selectChar(personajeId);
    } else {
      alert('Error: ' + (res.error && res.error.message ? res.error.message : 'Desconocido'));
    }
  })
  .catch(function(err) {
    alert('Error de red: ' + err.message);
  });
}

var currentModeratingId = null;

function openModerar(personajeId, statusActual) {
  currentModeratingId = personajeId;
  var el = document.getElementById('aprobar-moderate');
  el.style.display = 'block';
  document.getElementById('moderate-mensaje').value = '';
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function toggleModerate() {
  var el = document.getElementById('aprobar-moderate');
  el.style.display = el.style.display === 'block' ? 'none' : 'block';
}

function enviarModeracion() {
  var mensaje = document.getElementById('moderate-mensaje').value.trim();
  if (!mensaje) {
    alert('Escribe un mensaje para el jugador.');
    return;
  }

  var btn = event && event.currentTarget ? event.currentTarget : null;
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...'; }

  fetch('<?= $b_url ?>/game/ajax/aprobar_personaje.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ personaje_id: currentModeratingId, action: 'revision', mensaje: mensaje })
  })
  .then(function(r) { return r.json(); })
  .then(function(res) {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar'; }
    document.getElementById('aprobar-moderate').style.display = 'none';
    if (res.ok) {
      loadList(currentFilter);
      selectChar(currentModeratingId);
    } else {
      alert('Error: ' + (res.error && res.error.message ? res.error.message : 'Desconocido'));
    }
  })
  .catch(function(err) {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar'; }
    alert('Error de red: ' + err.message);
  });
}

function escapeHtml(str) {
  if (!str) return '';
  var div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

// Filter buttons
document.addEventListener('DOMContentLoaded', function() {
  var filterBtns = document.querySelectorAll('.aprobar-filter-btn');
  filterBtns.forEach(function(btn) {
    btn.addEventListener('click', function() {
      filterBtns.forEach(function(b) { b.classList.remove('active'); });
      btn.classList.add('active');
      loadList(btn.getAttribute('data-filter'));
    });
  });
  loadList('');
});
</script>
<?php
$content = ob_get_clean();
game_render_page("Aprobar Personajes", $content);

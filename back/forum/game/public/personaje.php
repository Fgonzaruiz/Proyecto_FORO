<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer, $theme;
$prefix = TABLE_PREFIX;
$user_id = (int)($mybb->user['uid'] ?? 0);

// If ?pj= is specified, load that character (any visible character)
$req_pj_id = isset($_GET['pj']) ? (int)$_GET['pj'] : 0;

// Get active character from user_config
$cfg = null;
if ($user_id) {
    $db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used) VALUES ({$user_id}, 1, 0) ON DUPLICATE KEY UPDATE user_id=user_id");
    $cfg_q = $db->query("SELECT * FROM {$prefix}game_user_config WHERE user_id = {$user_id}");
    $cfg = $db->fetch_array($cfg_q);
}

$active_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$load_id = $req_pj_id ?: $active_id;

$char = null;
if ($load_id) {
    $query = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$load_id} LIMIT 1");
    $row = $db->fetch_array($query);
    if ($row) {
        $data = !empty($row['data_json']) ? json_decode($row['data_json'], true) : [];
        if (!is_array($data)) $data = [];
        $stats = !empty($row['stats_json']) ? json_decode($row['stats_json'], true) : [];
        if (!is_array($stats)) $stats = [];
        $cronologia = !empty($row['cronologia_json']) ? json_decode($row['cronologia_json'], true) : [];
        if (!is_array($cronologia)) $cronologia = [];
        $cronologia['diario'] = $cronologia['diario'] ?? [];
        $cronologia['relaciones'] = $cronologia['relaciones'] ?? [];

        $char = [
            'id'          => (int)$row['id'],
            'user_id'     => (int)$row['user_id'],
            'name'        => $row['name'],
            'race_name'   => !empty($row['race_name']) ? $row['race_name'] : ($data['race'] ?? 'Desconocida'),
            'is_staff'    => (bool)$row['is_staff'],
            'job_name'    => !empty($row['occupation_name']) ? $row['occupation_name'] : ($data['job'] ?? 'Ninguno'),
            'rango'       => !empty($row['rango']) ? $row['rango'] : ($data['rank'] ?? ''),
            'avatar'      => !empty($row['avatar']) ? $row['avatar'] : ($data['avatar'] ?? ''),
            'faction'     => !empty($row['faction']) ? $row['faction'] : ($data['faction'] ?? ''),
            'approved'    => (bool)($row['approved'] ?? 0),
            
            // Legacy fallbacks for bio
            'desc'        => $row['desc'] ?? '',
            'details'     => $row['details'] ?? '',
            
            // JSON Fields
            'age'         => $data['age'] ?? 'Desconocida',
            'origin'      => $data['origin'] ?? 'Desconocido',
            'pb'          => $data['pb'] ?? 'Desconocido',
            'physique'    => $data['physique'] ?? '',
            'psychology'  => $data['psychology'] ?? '',
            'extras'      => $data['extras'] ?? '',
            'arquetipo'   => $data['arquetipo'] ?? 'Desconocido',
            'linaje'      => $data['linaje'] ?? [],
            
            // New Tabs Data
            'cronologia'  => $cronologia,
            
            // New Stats
            'stats'       => [
                'fue' => (int)($stats['fue'] ?? $stats['str'] ?? (isset($row['stat_fp']) ? $row['stat_fp'] : 5)),
                'agi' => (int)($stats['agi'] ?? (isset($row['stat_dp']) ? $row['stat_dp'] : 5)),
                'des' => (int)($stats['des'] ?? $stats['res'] ?? (isset($row['stat_rp']) ? $row['stat_rp'] : 5)),
                'inst' => (int)($stats['inst'] ?? $stats['vol'] ?? (isset($row['stat_vp']) ? $row['stat_vp'] : 5)),
                'esp' => (int)($stats['esp'] ?? $stats['vol'] ?? (isset($row['stat_vp']) ? $row['stat_vp'] : 5)),
                'int' => (int)($stats['int'] ?? (isset($row['stat_ip']) ? $row['stat_ip'] : 5)),
            ],
        ];
        
        // Sort Diario
        usort($char['cronologia']['diario'], function($a, $b) {
            $peso_a = ((int)($a['year'] ?? 0) * 400) + ((int)($a['season'] ?? 0) * 100) + (int)($a['day'] ?? 0);
            $peso_b = ((int)($b['year'] ?? 0) * 400) + ((int)($b['season'] ?? 0) * 100) + (int)($b['day'] ?? 0);
            return $peso_a <=> $peso_b;
        });
    }
}

// 1. Calculate Global Rol Date (shared function in bootstrap.php)
$global_date_string = game_global_rol_date();

// 2. Load all characters for the Select (remove approved filter so any char can be linked)
$all_chars = [];
$chars_q = $db->query("SELECT id, name FROM {$prefix}game_personajes ORDER BY name ASC");
while ($c = $db->fetch_array($chars_q)) {
    $all_chars[] = $c;
}

$bb = $mybb->settings['bburl'];
$b_url = $bb . '/images/game/personaje_banner.png';

ob_start();
?>
<style>
/* Pestañas para la ficha */
.pj-preview-tabs { display: flex; border-bottom: 2px solid var(--border-color); margin-bottom: 24px; }
.pj-preview-tab {
    padding: 10px 20px; font-family: var(--font-heading); font-weight: 700; font-size: 14px;
    color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent;
    margin-bottom: -2px; transition: all 0.2s ease;
}
.pj-preview-tab:hover { color: var(--text-primary); }
.pj-preview-tab.active { color: var(--accent-indigo); border-bottom-color: var(--accent-indigo); }
.pj-preview-tab-content { display: none; }
.pj-preview-tab-content.active { display: block; }

/* Barras de stats (copiadas del creador) */
.rpg-preview-stat-bar { background: var(--bg-card); border-radius: 10px; height: 8px; width: 100%; overflow: hidden; margin-top: 4px; }
.rpg-preview-stat-fill { height: 100%; background: linear-gradient(90deg, var(--accent-indigo), var(--accent-purple)); border-radius: 10px; transition: width 0.5s ease; }
.rpg-preview-stat-row { margin-bottom: 12px; text-align: left; }

/* Gene cards */
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
.gene-card-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
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
.gene-card.passive-primary { border-left: 3px solid #10b981; }
.gene-card.passive-secondary { border-left: 3px solid #f59e0b; }
.gene-card.perk-racial { border-left: 3px solid var(--accent-indigo); }
.gene-card.perk-general { border-left: 3px solid var(--accent-purple); }

/* Custom Scrollbars for boxes */
.pj-scroll-box {
    background: var(--bg-surface); border: 1px solid var(--border-color);
    border-radius: var(--radius-md); padding: 20px; height: 280px;
    overflow-y: auto; margin-bottom: 30px; font-size: 14px; line-height: 1.7; color: var(--text-secondary);
}
.pj-scroll-box::-webkit-scrollbar { width: 6px; }
.pj-scroll-box::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 4px; }
.pj-scroll-box::-webkit-scrollbar-thumb { background: var(--accent-indigo); border-radius: 4px; }

/* Timeline (Diario estilo libreta) */
.pj-timeline { position: relative; margin-top: 20px; }
.pj-timeline-item { background: #fdfbf7; color: #333; padding: 20px 25px; border-radius: 4px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); position: relative; border-left: 5px solid #d4c5b0; font-family: 'Georgia', serif; }
.pj-timeline-item::before { content: ''; position: absolute; left: 8px; right: 8px; top: 8px; bottom: 8px; border: 1px dashed rgba(0,0,0,0.08); pointer-events: none; }
.pj-timeline-date { font-family: var(--font-heading); font-size: 13px; font-weight: 700; color: #8c7b66; border-bottom: 2px solid rgba(212,197,176,0.3); padding-bottom: 5px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px; }
.pj-timeline-desc { font-size: 15px; color: #4a4a4a; line-height: 1.8; margin-bottom: 15px; font-style: italic; }
.pj-timeline-link { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #8c7b66; background: #f0e9df; padding: 6px 14px; border-radius: 20px; text-decoration: none; transition: all 0.2s; border: 1px solid #d4c5b0; font-family: var(--font-main); font-style: normal; }
.pj-timeline-link:hover { background: #d4c5b0; color: #fff; }

/* Relations Grid */
.pj-relations-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; margin-top: 20px; }
.pj-relation-card { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 15px; text-align: center; transition: transform 0.2s, border-color 0.2s; }
.pj-relation-card:hover { transform: translateY(-5px); border-color: var(--accent-purple); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
.pj-relation-img { width: 75px; height: 75px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-indigo); margin: 0 auto 12px auto; display: block; padding: 3px; background: rgba(255,255,255,0.05); }
.pj-relation-name { font-family: var(--font-heading); font-size: 15px; font-weight: 700; color: var(--text-primary); margin-bottom: 5px; }
.pj-relation-tag-wrap { display: flex; flex-wrap: wrap; gap: 4px; justify-content: center; margin-top: 4px; }
.pj-relation-tag { font-size: 10px; font-weight: 700; text-transform: uppercase; display: inline-block; padding: 2px 8px; border-radius: 10px; letter-spacing: 0.3px; }

/* Tag selector */
.pj-tag-selector { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px; }
.pj-tag-option { font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 14px; cursor: pointer; border: 2px solid transparent; transition: all 0.15s; opacity: 0.5; user-select: none; }
.pj-tag-option.selected { opacity: 1; border-color: currentColor; box-shadow: 0 0 8px rgba(0,0,0,0.15); }
.pj-tag-option:hover { opacity: 0.8; }
.pj-tag-picker { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px; }
.pj-tag { font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 14px; cursor: pointer; border: 2px solid transparent; background: rgba(0,0,0,0.1); transition: all 0.15s; opacity: 0.5; user-select: none; }
.pj-tag.active { opacity: 1; background: currentColor !important; color: #fff !important; box-shadow: 0 0 8px rgba(0,0,0,0.15); }
.pj-tag:hover { opacity: 0.8; }


/* In-situ Modals (Beautified & Made Theme-Independent) */
.pj-modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.6) !important;
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    backdrop-filter: blur(4px);
}
.pj-modal {
    background: var(--bg-surface, #1a1c2e) !important;
    color: var(--text-primary, #e2e8f0) !important;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08)) !important;
    border-radius: 16px !important;
    width: 560px;
    max-width: 94vw;
    padding: 30px !important;
    box-shadow: var(--shadow-main, 0 25px 50px rgba(0, 0, 0, 0.7)) !important;
    position: relative;
    overflow: visible;
    box-sizing: border-box !important;
}
.pj-modal::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--accent-indigo, #6366f1), var(--accent-purple, #a855f7));
    border-top-left-radius: 16px;
    border-top-right-radius: 16px;
}
.pj-modal-title {
    font-family: var(--font-heading, inherit);
    font-size: 18px !important;
    color: var(--text-primary) !important;
    margin-bottom: 20px !important;
    text-align: center;
    font-weight: 800 !important;
}
.pj-modal .form-group {
    margin-bottom: 16px !important;
}
.pj-modal .textbox {
    background: var(--bg-main, rgba(0, 0, 0, 0.3)) !important;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1)) !important;
    color: var(--text-primary, #ffffff) !important;
    border-radius: 8px !important;
    padding: 12px 14px !important;
    transition: all 0.3s !important;
    width: 100% !important;
    box-sizing: border-box !important;
    font-size: 13px !important;
}
.pj-modal .textbox:focus {
    background: var(--bg-card, rgba(0, 0, 0, 0.5)) !important;
    border-color: var(--accent-indigo, #6366f1) !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2) !important;
    outline: none !important;
}
.pj-modal label {
    font-size: 11px !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    color: var(--text-secondary, rgba(255, 255, 255, 0.6)) !important;
    font-weight: 700 !important;
    margin-bottom: 6px !important;
    display: block !important;
}
.pj-btn-add {
    background: linear-gradient(135deg, #6366f1, #a855f7) !important;
    color: white !important;
    border: none !important;
    padding: 10px 20px !important;
    border-radius: 8px !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    cursor: pointer !important;
    transition: transform 0.2s, box-shadow 0.2s !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3) !important;
}
.pj-btn-add:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5) !important;
}

.pj-modal-actions {
    text-align: right !important;
    margin-top: 20px !important;
    display: flex !important;
    justify-content: flex-end !important;
    gap: 12px !important;
}

.pj-edit-list {
    max-height: 380px !important;
    overflow-y: auto !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 10px !important;
    padding-right: 5px !important;
}
/* Custom Premium Scrollbar for lists */
.pj-edit-list::-webkit-scrollbar {
    width: 6px !important;
}
.pj-edit-list::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.02) !important;
    border-radius: 4px !important;
}
.pj-edit-list::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15) !important;
    border-radius: 4px !important;
    transition: background 0.2s !important;
}
.pj-edit-list::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3) !important;
}

.pj-edit-item {
    background: var(--bg-card, rgba(255, 255, 255, 0.03)) !important;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08)) !important;
    border-radius: 12px !important;
    padding: 12px 16px !important;
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 15px !important;
    width: 100% !important;
    box-sizing: border-box !important;
    transition: all 0.2s ease-in-out !important;
}
.pj-edit-item:hover {
    background: var(--bg-card-hover, rgba(255, 255, 255, 0.06)) !important;
    border-color: var(--border-hover, rgba(255, 255, 255, 0.15)) !important;
    transform: translateY(-1px) !important;
}
.pj-edit-item-body {
    flex: 1 1 auto !important;
    min-width: 0 !important;
}
.pj-edit-item-actions {
    display: flex !important;
    gap: 8px !important;
    flex-shrink: 0 !important;
    margin-left: auto !important; /* Pushes action buttons to the far right */
    align-items: center !important;
}
.pj-edit-btn {
    width: 32px !important;
    height: 32px !important;
    border-radius: 8px !important;
    border: none !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 13px !important;
    transition: all 0.2s ease !important;
    box-sizing: border-box !important;
    padding: 0 !important; /* Resets any button padding */
}
.pj-edit-btn:hover {
    transform: scale(1.08) translateY(-1px) !important;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3) !important;
}
.pj-edit-btn-edit {
    background: rgba(59, 130, 246, 0.25) !important;
    color: #93c5fd !important;
    border: 1px solid rgba(59, 130, 246, 0.4) !important;
}
.pj-edit-btn-edit:hover {
    background: #3b82f6 !important;
    color: #ffffff !important;
    border-color: #3b82f6 !important;
}
.pj-edit-btn-del {
    background: rgba(239, 68, 68, 0.25) !important;
    color: #fca5a5 !important;
    border: 1px solid rgba(239, 68, 68, 0.4) !important;
}
.pj-edit-btn-del:hover {
    background: #ef4444 !important;
    color: #ffffff !important;
    border-color: #ef4444 !important;
}

/* Tabs inside Modals */
.pj-modal-tab-btn {
    background: linear-gradient(135deg, #6366f1, #a855f7) !important;
    color: #ffffff !important;
    border: none !important;
    padding: 8px 16px !important;
    font-family: var(--font-heading, inherit) !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    cursor: pointer !important;
    transition: transform 0.2s, box-shadow 0.2s !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3) !important;
    border-radius: var(--radius-md, 8px) !important;
    opacity: 0.6 !important;
}
.pj-modal-tab-btn:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5) !important;
    opacity: 1 !important;
}
.pj-modal-tab-btn.active {
    opacity: 1 !important;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.5) !important;
}
.pj-cat-counter { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
.pj-cat-chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px 4px 6px; border-radius: 6px; font-size: 11px; font-weight: 700; line-height: 1; }
.pj-cat-chip .num { font-size: 14px; font-weight: 800; }
.pj-cat-picker { cursor:pointer; border-radius:8px; padding:6px 16px; font-weight:700; font-size:12px; transition:all 0.15s; opacity:0.6; user-select:none; }
.pj-cat-picker:hover { opacity:0.9; }
.pj-cat-picker.active { opacity:1; box-shadow: 0 0 10px rgba(0,0,0,0.3); }
</style>

<div class="rpg-char-page" style="max-width: 1200px; margin: 0 auto;">
  <?php if (!$char): ?>
    <?php if ($req_pj_id): ?>
    <div class="rpg-char-empty">
      <i class="fas fa-user-slash"></i>
      <h2>Personaje no encontrado</h2>
      <p>El personaje solicitado no existe o no est&aacute; disponible.</p>
    </div>
    <?php elseif (!$user_id): ?>
    <div class="rpg-char-empty">
      <i class="fas fa-user-lock"></i>
      <h2>Debes iniciar sesi&oacute;n</h2>
      <p>Inicia sesi&oacute;n en el foro para ver tu ficha de personaje.</p>
    </div>
    <?php else: ?>
    <div class="rpg-char-empty">
      <i class="fas fa-user-plus"></i>
      <h2>No tienes personaje</h2>
      <p>A&uacute;n no se ha vinculado ning&uacute;n personaje a tu cuenta. ¡Ve a la gesti&oacute;n de personajes para crear uno!</p>
    </div>
    <?php endif; ?>
  <?php else: ?>
  
  <?php
    $genes_activos = (!empty($char['linaje']['geneNames'])) ? implode(', ', $char['linaje']['geneNames']) : 'Ninguno';
    
    // Evaluate permissions based on ACTIVE CHARACTER
    $active_char_is_staff = false;
    if ($active_id && $active_id !== (int)($char['id'])) {
        $active_q = $db->query("SELECT is_staff FROM {$prefix}game_personajes WHERE id = {$active_id} LIMIT 1");
        if ($a_row = $db->fetch_array($active_q)) {
            $active_char_is_staff = (bool)$a_row['is_staff'];
        }
    } elseif ($active_id && $char && $active_id === (int)$char['id']) {
        $active_char_is_staff = (bool)$char['is_staff'];
    }
    
    $is_active_pj = ($char && $active_id === (int)$char['id']);
    $can_edit = $is_active_pj;
    $can_view_private = ($is_active_pj || $active_char_is_staff);
  ?>

  <div style="display: flex; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden; min-height: 700px; margin-top: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
      
      <!-- LEFT COLUMN (Avatar & Stats) -->
      <div style="width: 320px; background: var(--bg-surface); border-right: 1px solid var(--border-color); display:flex; flex-direction:column; flex-shrink: 0;">
          <div style="width:100%; height:450px; min-height:450px; background-size:cover; background-position:center; background-image:url('<?= htmlspecialchars($char['avatar'] ?: 'https://placehold.co/320x450') ?>'); border-bottom: 2px solid var(--accent-indigo);"></div>
          
          <div style="padding: 20px;">
              <h2 style="font-family:var(--font-heading); font-size:24px; color:var(--text-primary); margin-bottom:10px; text-align:center;"><?= htmlspecialchars($char['name']) ?></h2>
              
              <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap; margin-bottom: 15px;">
                  <?php if ($char['status'] === 'aprobada'): ?>
                      <span style="background:rgba(16, 185, 129, 0.1); color:#10b981; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-check-circle"></i> Aprobada</span>
                  <?php elseif ($char['status'] === 'revision'): ?>
                      <span style="background:rgba(245, 158, 11, 0.1); color:#f59e0b; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-sync-alt"></i> En Revisión</span>
                  <?php elseif ($char['status'] === 'rechazada'): ?>
                      <span style="background:rgba(239, 68, 68, 0.1); color:#ef4444; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-times-circle"></i> Rechazada</span>
                  <?php else: ?>
                      <span style="background:rgba(239, 68, 68, 0.1); color:#ef4444; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-clock"></i> Pendiente</span>
                  <?php endif; ?>
                  <span style="background:rgba(99,102,241,0.1); color:var(--accent-indigo); padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-flag"></i> Facci&oacute;n</span>
                  <span style="background:rgba(168,85,247,0.1); color:var(--accent-purple); padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-medal"></i> <?= htmlspecialchars($char['rango'] ?: 'Sin Rango') ?></span>
                  <?php if ($char['is_staff']): ?>
                    <span style="background:var(--accent-indigo); color:#fff; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-star"></i> Staff</span>
                  <?php endif; ?>
              </div>
              
              <div style="background: var(--bg-card); border-radius: var(--radius-md); padding: 15px; border: 1px solid var(--border-color); margin-bottom: 20px;">
                  <div style="display:flex; align-items:center; gap:10px; margin-bottom: 10px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">
                      <i class="fas fa-shield-alt" style="color:var(--text-secondary); font-size:20px;"></i>
                      <div>
                          <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Arquetipo B&eacute;lico</div>
                          <div style="font-weight:700; color:var(--text-primary); font-size:14px;"><?= htmlspecialchars($char['arquetipo']) ?></div>
                      </div>
                  </div>
                  <div style="display:flex; align-items:center; gap:10px; margin-bottom: 10px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">
                      <i class="fas fa-anchor" style="color:var(--text-secondary); font-size:20px;"></i>
                      <div>
                          <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Oficio</div>
                          <div style="font-weight:700; color:var(--text-primary); font-size:14px;"><?= htmlspecialchars($char['job_name'] ?: 'Ninguno') ?></div>
                      </div>
                  </div>
                  <div style="display:flex; align-items:center; gap:10px;">
                      <i class="fas fa-dna" style="color:var(--accent-purple); font-size:20px;"></i>
                      <div>
                          <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Genes Activos</div>
                          <div style="font-weight:700; color:var(--accent-purple); font-size:13px; line-height:1.2;"><?= $genes_activos ?></div>
                      </div>
                  </div>
              </div>
              
              <?php
              $fue = $char['stats']['fue'];
              $agi = $char['stats']['agi'];
              $des = $char['stats']['des'];
              $inst = $char['stats']['inst'];
              $esp = $char['stats']['esp'];
              $int = $char['stats']['int'];

              $pv = ($fue * 4) + ($agi * 2) + ($esp * 3) + ($int * 1);
              $pe = ($esp * 4) + ($des * 3) + ($agi * 2) + ($int * 1);
              ?>
              
              <!-- Puntos de Vida y Energía -->
              <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                  <div style="flex: 1; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: var(--radius-md); padding: 10px; text-align: center;">
                      <div style="font-size: 10px; color: #f87171; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px;">Puntos de Vida (PV)</div>
                      <div style="font-size: 20px; font-weight: 800; color: #ef4444; margin-top: 4px;"><?= $pv ?></div>
                  </div>
                  <div style="flex: 1; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: var(--radius-md); padding: 10px; text-align: center;">
                      <div style="font-size: 10px; color: #60a5fa; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px;">Puntos de Energía (PE)</div>
                      <div style="font-size: 20px; font-weight: 800; color: #3b82f6; margin-top: 4px;"><?= $pe ?></div>
                  </div>
              </div>

              <h3 style="font-size:12px; font-family:var(--font-heading); color:var(--text-muted); text-transform:uppercase; margin-bottom:10px;">Atributos Base</h3>
              <div class="rpg-preview-stat-row">
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>FUERZA (FUE)</span><span><?= $fue ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="width:<?= min(100, $fue * 10) ?>%; background:linear-gradient(90deg, #6366f1, #4f46e5);"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>AGILIDAD (AGI)</span><span><?= $agi ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="width:<?= min(100, $agi * 10) ?>%; background:linear-gradient(90deg,#10b981,#059669);"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>DESTREZA (DES)</span><span><?= $des ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="width:<?= min(100, $des * 10) ?>%; background:linear-gradient(90deg,#3b82f6,#2563eb);"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>INSTINTO (INST)</span><span><?= $inst ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="width:<?= min(100, $inst * 10) ?>%; background:linear-gradient(90deg,#06b6d4,#0891b2);"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>ESPÍRITU (ESP)</span><span><?= $esp ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="style=width:0%; width:<?= min(100, $esp * 10) ?>%; background:linear-gradient(90deg,#ec4899,#db2777);"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>INTELECTO (INT)</span><span><?= $int ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="width:<?= min(100, $int * 10) ?>%; background:linear-gradient(90deg,#f59e0b,#d97706);"></div></div>
              </div>
          </div>
      </div>
      
      <!-- RIGHT COLUMN (Tabs & Content) -->
      <div style="flex:1; padding: 40px; overflow-y:auto;">
          <div class="pj-preview-tabs">
              <div class="pj-preview-tab active" onclick="switchPjTab('bio', this)"><i class="fas fa-file-alt"></i> Biograf&iacute;a</div>
              <div class="pj-preview-tab" onclick="switchPjTab('linaje', this)"><i class="fas fa-dna"></i> Mapa Gen&eacute;tico</div>
              <div class="pj-preview-tab" onclick="switchPjTab('cronologia', this)"><i class="fas fa-calendar-alt"></i> Bit&aacute;cora</div>
              <?php if ($can_view_private): ?>
              <div class="pj-preview-tab" onclick="switchPjTab('deck', this)"><i class="fas fa-layer-group"></i> Deck</div>
              <div class="pj-preview-tab" onclick="switchPjTab('gestion', this)"><i class="fas fa-cogs"></i> Gesti&oacute;n</div>
              <?php endif; ?>
          </div>

          <!-- TAB: BIO -->
          <div id="pjTab_bio" class="pj-preview-tab-content active">
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:30px; background:var(--bg-surface); padding:20px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                  <div style="font-size:14px;"><strong>Edad:</strong> <?= htmlspecialchars($char['age']) ?></div>
                  <div style="font-size:14px;"><strong>Origen:</strong> <?= htmlspecialchars($char['origin']) ?></div>
                  <div style="font-size:14px;"><strong>Raza:</strong> <?= htmlspecialchars($char['race_name']) ?></div>
                  <div style="font-size:14px;"><strong>PB:</strong> <?= htmlspecialchars($char['pb']) ?></div>
              </div>
              
              <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">Apariencia F&iacute;sica</h3>
              <div class="pj-scroll-box" style="height: 200px;">
                  <?= nl2br(htmlspecialchars($char['physique'] ?: 'Sin registrar.')) ?>
              </div>
              
              <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">Perfil Psicol&oacute;gico</h3>
              <div class="pj-scroll-box" style="height: 200px;">
                  <?= nl2br(htmlspecialchars($char['psychology'] ?: ($char['desc'] ?: 'Sin historia registrada.'))) ?>
              </div>
              
              <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">Extras y Notas</h3>
              <div class="pj-scroll-box" style="height: 200px;">
                  <?= nl2br(htmlspecialchars($char['extras'] ?: ($char['details'] ?: 'Sin notas extras.'))) ?>
              </div>
          </div>

          <!-- TAB: LINAJE -->
          <div id="pjTab_linaje" class="pj-preview-tab-content">
              <?php
              $linaje_v = $char['linaje']['version'] ?? 1;
              $pasiva_ids   = $char['linaje']['pasivas']          ?? [];
              $racial_ids   = $char['linaje']['elegidos_racial']  ?? [];
              $general_ids  = $char['linaje']['elegidos_general'] ?? [];
              $has_perks_v2 = ($linaje_v >= 2);

              // Full perk catalog (mirror of JS LINAJE_DATA)
              $linaje_catalog_pasivas = [
                'Humano'       => [ ['id'=>'p_hum_adapt','type'=>'primaria','name'=>'Maestro sin Maestro','icon'=>'fa-graduation-cap','iconColor'=>'#10b981','desc'=>'Aprende oficios un 20% más rápido. Bono en entornos desconocidos.'], ['id'=>'p_hum_luck','type'=>'secundaria','name'=>'Suerte del Mar','icon'=>'fa-dice','iconColor'=>'#f59e0b','desc'=>'Una vez por arco, rerollea un dado con desventaja.'] ],
                'Mink'         => [ ['id'=>'p_mink_pelaje','type'=>'primaria','name'=>'Pelaje Conductor','icon'=>'fa-bolt','iconColor'=>'#10b981','desc'=>'Inmunidad al frío extremo. +1 resistencia en clima adverso.'], ['id'=>'p_mink_electro','type'=>'primaria','name'=>'Electro Innato','icon'=>'fa-charging-station','iconColor'=>'#06b6d4','desc'=>'Descarga eléctrica en combate cuerpo a cuerpo.'], ['id'=>'p_mink_noche','type'=>'secundaria','name'=>'Instinto Nocturno','icon'=>'fa-moon','iconColor'=>'#f59e0b','desc'=>'Visión perfecta en oscuridad total.'] ],
                'Gyojin'       => [ ['id'=>'p_gyojin_agua','type'=>'primaria','name'=>'Respiración Anfibia','icon'=>'fa-water','iconColor'=>'#10b981','desc'=>'Combate igual de bien bajo el agua. Nado 5x superior.'], ['id'=>'p_gyojin_fuerza','type'=>'primaria','name'=>'Fuerza de las Profundidades','icon'=>'fa-dumbbell','iconColor'=>'#3b82f6','desc'=>'×10 fuerza respecto a un humano.'], ['id'=>'p_gyojin_karate','type'=>'secundaria','name'=>'Afinidad Karate Gyojin','icon'=>'fa-hand-paper','iconColor'=>'#f59e0b','desc'=>'+2 en tiradas de Karate Gyojin.'] ],
                'Gigante'      => [ ['id'=>'p_gigante_talla','type'=>'primaria','name'=>'Talla Colosal','icon'=>'fa-expand-arrows-alt','iconColor'=>'#10b981','desc'=>'Ventaja en empujes y ataques de área.'], ['id'=>'p_gigante_pv','type'=>'primaria','name'=>'Vida Monumental','icon'=>'fa-heart','iconColor'=>'#ef4444','desc'=>'PV base +30%.'], ['id'=>'p_gigante_terror','type'=>'secundaria','name'=>'Presencia Aterradora','icon'=>'fa-skull','iconColor'=>'#f59e0b','desc'=>'Enemigos de nivel bajo deben superar tirada de moral.'] ],
                'Piernas Largas'=> [ ['id'=>'p_ll_velocidad','type'=>'primaria','name'=>'Zancada Monumental','icon'=>'fa-running','iconColor'=>'#10b981','desc'=>'Velocidad superior en tierra firme.'], ['id'=>'p_ll_alcance','type'=>'primaria','name'=>'Alcance Extendido','icon'=>'fa-arrows-alt-v','iconColor'=>'#3b82f6','desc'=>'Patadas de rango superior.'], ['id'=>'p_ll_equilibrio','type'=>'secundaria','name'=>'Equilibrio Perfecto','icon'=>'fa-balance-scale','iconColor'=>'#f59e0b','desc'=>'Inmune a derribo en terreno inestable.'] ],
                'Brazos Largos' => [ ['id'=>'p_bl_alcance','type'=>'primaria','name'=>'Brazos de Gigante','icon'=>'fa-hand-rock','iconColor'=>'#10b981','desc'=>'Alcance físico superior. Ventaja en rango largo.'], ['id'=>'p_bl_agarre','type'=>'primaria','name'=>'Agarre Férreo','icon'=>'fa-hand-rock','iconColor'=>'#3b82f6','desc'=>'+3 en tiradas de presa.'], ['id'=>'p_bl_lanzar','type'=>'secundaria','name'=>'Proyectil Viviente','icon'=>'fa-baseball-ball','iconColor'=>'#f59e0b','desc'=>'Lanza objetos medianos con precisión extrema.'] ],
                'Cuello Largo'  => [ ['id'=>'p_cl_vision','type'=>'primaria','name'=>'Vista Panorámica','icon'=>'fa-eye','iconColor'=>'#10b981','desc'=>'Ve por encima de obstáculos altos.'], ['id'=>'p_cl_mira','type'=>'primaria','name'=>'Mira Natural','icon'=>'fa-crosshairs','iconColor'=>'#3b82f6','desc'=>'Bono en observación a larga distancia.'], ['id'=>'p_cl_oido','type'=>'secundaria','name'=>'Oído Amplificado','icon'=>'fa-assistive-listening-systems','iconColor'=>'#f59e0b','desc'=>'Oye conversaciones lejanas.'] ],
                'Tontatta'     => [ ['id'=>'p_ton_mini','type'=>'primaria','name'=>'Miniaturización Extrema','icon'=>'fa-compress-arrows-alt','iconColor'=>'#10b981','desc'=>'Tamaño ínfimo, ventaja en infiltración.'], ['id'=>'p_ton_fuerza','type'=>'primaria','name'=>'Fuerza Desproporcionada','icon'=>'fa-fist-raised','iconColor'=>'#3b82f6','desc'=>'Fuerza muy superior a su tamaño.'], ['id'=>'p_ton_herbo','type'=>'secundaria','name'=>'Herbolaria Élite','icon'=>'fa-leaf','iconColor'=>'#f59e0b','desc'=>'+2 en medicina natural.'] ],
                'Buccaner'     => [ ['id'=>'p_buc_sangre','type'=>'primaria','name'=>'Sangre Ardiente','icon'=>'fa-fire','iconColor'=>'#10b981','desc'=>'Haki más natural. Entrenamiento acelerado.'], ['id'=>'p_buc_aguante','type'=>'primaria','name'=>'Cuerpo Forjado','icon'=>'fa-shield-alt','iconColor'=>'#ef4444','desc'=>'Ignora primer penalizador de daño por escena.'], ['id'=>'p_buc_leyenda','type'=>'secundaria','name'=>'Herencia Legendaria','icon'=>'fa-crown','iconColor'=>'#f59e0b','desc'=>'Figuras de autoridad te reconocen inconscientemente.'] ],
                'Lunarian'     => [ ['id'=>'p_lun_fuego','type'=>'primaria','name'=>'Llama Racial','icon'=>'fa-fire-alt','iconColor'=>'#10b981','desc'=>'Genera llamas naturales. Inmune al fuego.'], ['id'=>'p_lun_vuelo','type'=>'primaria','name'=>'Alas de Ceniza','icon'=>'fa-feather-alt','iconColor'=>'#8b5cf6','desc'=>'Planea y desciende controladamente.'], ['id'=>'p_lun_dura','type'=>'secundaria','name'=>'Cuerpo de Piedra','icon'=>'fa-chess-rook','iconColor'=>'#f59e0b','desc'=>'Reduce daño físico recibido un 10%.'] ],
                'Skypean'      => [ ['id'=>'p_sky_alas','type'=>'primaria','name'=>'Alas de Isla','icon'=>'fa-wind','iconColor'=>'#10b981','desc'=>'Planea largas distancias con corrientes de aire.'], ['id'=>'p_sky_mantra','type'=>'primaria','name'=>'Observación Innata','icon'=>'fa-broadcast-tower','iconColor'=>'#06b6d4','desc'=>'Sensibilidad natural al Haki de Observación.'], ['id'=>'p_sky_dial','type'=>'secundaria','name'=>'Dialecto del Cielo','icon'=>'fa-comments','iconColor'=>'#f59e0b','desc'=>'Comunicación fluida con razas celestiales.'] ],
              ];
              $linaje_catalog_racial = [
                'Humano'       => [ ['id'=>'lr_hum_tenaz','name'=>'Tenacidad Pura','icon'=>'fa-hand-rock','iconColor'=>'#6366f1','desc'=>'Una vez por evento, no caes inconsciente por daño letal.'], ['id'=>'lr_hum_estudio','name'=>'Estudiante Dedicado','icon'=>'fa-book','iconColor'=>'#6366f1','desc'=>'+1 a tiradas de Intelecto una vez por escena.'], ['id'=>'lr_hum_lider','name'=>'Liderazgo Natural','icon'=>'fa-users','iconColor'=>'#6366f1','desc'=>'Compañeros cercanos +1 en moral.'] ],
                'Mink'         => [ ['id'=>'lr_mink_sulong','name'=>'Furia Sulong','icon'=>'fa-moon','iconColor'=>'#6366f1','desc'=>'Bajo luna llena, stats ofensivos aumentan.'], ['id'=>'lr_mink_rastro','name'=>'Rastreador Experto','icon'=>'fa-paw','iconColor'=>'#6366f1','desc'=>'Sigue rastros de olfato con éxito automático.'], ['id'=>'lr_mink_pack','name'=>'Mentalidad de Manada','icon'=>'fa-users-cog','iconColor'=>'#6366f1','desc'=>'+1 en ataques coordinados con aliados.'] ],
                'Gyojin'       => [ ['id'=>'lr_gyojin_corriente','name'=>'Maestro de Corrientes','icon'=>'fa-water','iconColor'=>'#6366f1','desc'=>'Control de corrientes marinas en radio pequeño.'], ['id'=>'lr_gyojin_peces','name'=>'Habla con Peces','icon'=>'fa-fish','iconColor'=>'#6366f1','desc'=>'Comunicación con criaturas marinas.'], ['id'=>'lr_gyojin_sangre','name'=>'Sangre del Océano','icon'=>'fa-tint','iconColor'=>'#6366f1','desc'=>'+1 en combate en entornos acuáticos.'] ],
                'Gigante'      => [ ['id'=>'lr_gigante_arma','name'=>'Arma Gigante','icon'=>'fa-hammer','iconColor'=>'#6366f1','desc'=>'Empuña armas descomunales.'], ['id'=>'lr_gigante_voz','name'=>'Voz del Trueno','icon'=>'fa-volume-up','iconColor'=>'#6366f1','desc'=>'Grito aturde a todos en radio cercano, 1/combate.'] ],
                'Piernas Largas'=> [ ['id'=>'lr_ll_patada','name'=>'Patada Devastadora','icon'=>'fa-shoe-prints','iconColor'=>'#6366f1','desc'=>'+2 en impacto. Rompe estructuras de madera.'], ['id'=>'lr_ll_corrida','name'=>'Velocista del Mar','icon'=>'fa-tachometer-alt','iconColor'=>'#6366f1','desc'=>'Éxito automático al escapar en campo abierto.'] ],
                'Brazos Largos' => [ ['id'=>'lr_bl_instrumento','name'=>'Virtuoso Instrumental','icon'=>'fa-music','iconColor'=>'#6366f1','desc'=>'Bono en instrumentos de cuerda.'], ['id'=>'lr_bl_trabajo','name'=>'Trabajador Infatigable','icon'=>'fa-hard-hat','iconColor'=>'#6366f1','desc'=>'Doble rendimiento en tareas manuales largas.'] ],
                'Cuello Largo'  => [ ['id'=>'lr_cl_testigo','name'=>'Testigo Perfecto','icon'=>'fa-binoculars','iconColor'=>'#6366f1','desc'=>'No puede ser engañado en negociaciones con contacto visual.'], ['id'=>'lr_cl_vigia','name'=>'Vigía de Viga','icon'=>'fa-search','iconColor'=>'#6366f1','desc'=>'Turno de vigía sin falsos negativos.'] ],
                'Tontatta'     => [ ['id'=>'lr_ton_veneno','name'=>'Alquimista Secreto','icon'=>'fa-flask','iconColor'=>'#6366f1','desc'=>'Fabrica venenos y antídotos con plantas comunes.'], ['id'=>'lr_ton_construir','name'=>'Constructor Férreo','icon'=>'fa-cogs','iconColor'=>'#6366f1','desc'=>'Repara mecanismos complejos sin herramientas.'], ['id'=>'lr_ton_red','name'=>'Red de Túneles','icon'=>'fa-network-wired','iconColor'=>'#6366f1','desc'=>'Crea túneles subterráneos. Movimiento oculto.'] ],
                'Buccaner'     => [ ['id'=>'lr_buc_haki','name'=>'Legado del Haki','icon'=>'fa-fist-raised','iconColor'=>'#6366f1','desc'=>'Desbloquea Haki antes de lo normal.'], ['id'=>'lr_buc_alianza','name'=>'Pacto de Sangre','icon'=>'fa-handshake','iconColor'=>'#6366f1','desc'=>'Aliados confían un 30% más de forma innata.'] ],
                'Lunarian'     => [ ['id'=>'lr_lun_llama_atk','name'=>'Llama Ofensiva','icon'=>'fa-fire','iconColor'=>'#6366f1','desc'=>'Lanza llamaradas como proyectil a distancia corta.'], ['id'=>'lr_lun_invulnerable','name'=>'Momento de Piedra','icon'=>'fa-gem','iconColor'=>'#6366f1','desc'=>'Invulnerabilidad 1 acción, 1/combate.'] ],
                'Skypean'      => [ ['id'=>'lr_sky_dial_arma','name'=>'Maestro de Dials','icon'=>'fa-compact-disc','iconColor'=>'#6366f1','desc'=>'+1 uso por Dial en escena.'], ['id'=>'lr_sky_tormenta','name'=>'Hijo de la Tormenta','icon'=>'fa-cloud','iconColor'=>'#6366f1','desc'=>'Ventaja en zonas de tormenta eléctrica.'] ],
              ];
              $linaje_catalog_general = [
                ['id'=>'lg_acero','name'=>'Piel de Acero','icon'=>'fa-shield-alt','iconColor'=>'#a855f7','desc'=>'Reduce 5% daño físico recibido.'],
                ['id'=>'lg_voluntad','name'=>'Voluntad Férrea','icon'=>'fa-brain','iconColor'=>'#a855f7','desc'=>'+2 resistencia mental. Inmune a miedo menor.'],
                ['id'=>'lg_sombra','name'=>'Paso Silencioso','icon'=>'fa-user-ninja','iconColor'=>'#a855f7','desc'=>'Ventaja en sigilo nocturno.'],
                ['id'=>'lg_vida','name'=>'Vitalidad Extra','icon'=>'fa-heartbeat','iconColor'=>'#a855f7','desc'=>'+15 PV máximos.'],
                ['id'=>'lg_energia','name'=>'Reserva de Energía','icon'=>'fa-bolt','iconColor'=>'#a855f7','desc'=>'+10 PE máximos.'],
                ['id'=>'lg_olfato','name'=>'Sentido Agudizado','icon'=>'fa-search','iconColor'=>'#a855f7','desc'=>'Detección pasiva de emboscadas en 10m.'],
                ['id'=>'lg_fortuna','name'=>'Golpe de Suerte','icon'=>'fa-dice-d20','iconColor'=>'#a855f7','desc'=>'1/escena convierte fallo en éxito menor.'],
                ['id'=>'lg_navegante','name'=>'Navegante Instintivo','icon'=>'fa-compass','iconColor'=>'#a855f7','desc'=>'+2 navegación. Nunca se pierde en mar.'],
              ];

              // Helper to find perk by id
              if (!function_exists('find_perk_in_catalog')) {
                  function find_perk_in_catalog(string $id, array $catalogs): ?array {
                      foreach ($catalogs as $cat) {
                          if (is_array($cat)) {
                              // could be nested race->perks or flat
                              if (isset($cat['id'])) {
                                  if ($cat['id'] === $id) return $cat;
                              } else {
                                  foreach ($cat as $p) {
                                      if (is_array($p) && ($p['id'] ?? '') === $id) return $p;
                                  }
                              }
                          }
                      }
                      return null;
                  }
              }

              if (!function_exists('render_perk_card')) {
                  function render_perk_card(array $p, string $type_class, string $icon_bg, string $badge_label, string $badge_color): string {
                      return '<div class="gene-card ' . $type_class . '">' .
                          '<div class="gene-card-icon" style="' . $icon_bg . '">' .
                              '<i class="fas ' . htmlspecialchars($p['icon']) . '" style="color:' . htmlspecialchars($p['iconColor']) . ';"></i>' .
                          '</div>' .
                          '<div class="gene-card-info">' .
                              '<div class="gene-card-name">' . htmlspecialchars($p['name']) . '</div>' .
                              '<div class="gene-card-desc">' . htmlspecialchars($p['desc']) . '</div>' .
                          '</div>' .
                          '<div class="gene-card-badge" style="background:' . $badge_color . '22; color:' . $badge_color . ';">' . $badge_label . '</div>' .
                      '</div>';
                  }
              }
              ?>

              <?php if ($has_perks_v2): ?>

                  <?php
                  // Build pasiva display from pasiva_ids stored
                  $displayed_pasivas = [];
                  foreach ($linaje_catalog_pasivas as $race_perks) {
                      foreach ($race_perks as $p) {
                          if (in_array($p['id'], $pasiva_ids, true)) $displayed_pasivas[] = $p;
                      }
                  }
                  // Fallback: if pasiva_ids empty, auto-infer from race
                  if (empty($displayed_pasivas)) {
                      $race_key = $char['race_name'] ?? '';
                      if (isset($linaje_catalog_pasivas[$race_key])) {
                          $displayed_pasivas = $linaje_catalog_pasivas[$race_key];
                      }
                  }
                  ?>

                  <?php if (!empty($displayed_pasivas)): ?>
                  <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:#10b981; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                      <i class="fas fa-shield-alt"></i> Pasivas Innatas
                  </div>
                  <div class="gene-cards-grid">
                  <?php foreach ($displayed_pasivas as $p):
                      $is_prim = ($p['type'] === 'primaria');
                      echo render_perk_card($p,
                          $is_prim ? 'passive-primary' : 'passive-secondary',
                          $is_prim ? 'background:rgba(16,185,129,0.12); border:2px solid rgba(16,185,129,0.35);' : 'background:rgba(245,158,11,0.1); border:2px solid rgba(245,158,11,0.3);',
                          $is_prim ? 'PRIMARIA' : 'SECUNDARIA',
                          $is_prim ? '#10b981' : '#f59e0b'
                      );
                  endforeach; ?>
                  </div>
                  <?php endif; ?>

                  <?php
                  // Racial perks
                  $racial_display = [];
                  foreach ($linaje_catalog_racial as $race_perks) {
                      foreach ($race_perks as $p) {
                          if (in_array($p['id'], $racial_ids, true)) $racial_display[] = $p;
                      }
                  }
                  ?>
                  <?php if (!empty($racial_display)): ?>
                  <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:var(--accent-indigo); margin-top:20px; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                      <i class="fas fa-dna"></i> Linaje Racial
                  </div>
                  <div class="gene-cards-grid">
                  <?php foreach ($racial_display as $p):
                      echo render_perk_card($p, 'perk-racial',
                          'background:rgba(99,102,241,0.1); border:2px solid rgba(99,102,241,0.3);',
                          'RACIAL', '#6366f1');
                  endforeach; ?>
                  </div>
                  <?php endif; ?>

                  <?php
                  // General perks
                  $general_display = [];
                  foreach ($linaje_catalog_general as $p) {
                      if (in_array($p['id'], $general_ids, true)) $general_display[] = $p;
                  }
                  ?>
                  <?php if (!empty($general_display)): ?>
                  <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:var(--accent-purple); margin-top:20px; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                      <i class="fas fa-star"></i> Linaje General
                  </div>
                  <div class="gene-cards-grid">
                  <?php foreach ($general_display as $p):
                      echo render_perk_card($p, 'perk-general',
                          'background:rgba(168,85,247,0.1); border:2px solid rgba(168,85,247,0.3);',
                          'GENERAL', '#a855f7');
                  endforeach; ?>
                  </div>
                  <?php endif; ?>

                  <?php if (empty($displayed_pasivas) && empty($racial_display) && empty($general_display)): ?>
                  <div style="padding: 30px; text-align:center; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                      <i class="fas fa-scroll" style="font-size: 40px; color: var(--accent-indigo); opacity: 0.5; margin-bottom:15px;"></i>
                      <h4 style="color:var(--text-primary); margin-bottom:5px;">Sin Perks de Linaje</h4>
                      <p style="color:var(--text-muted); font-size:13px;">Este personaje no tiene perks de linaje asignados todavía.</p>
                  </div>
                  <?php endif; ?>

              <?php else: ?>
                  <!-- Legacy v1: show banner + old gene names -->
                  <div style="padding:12px 16px; background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.3); border-radius:var(--radius-md); margin-bottom:20px; display:flex; align-items:center; gap:12px;">
                      <i class="fas fa-info-circle" style="color:#f59e0b; font-size:18px;"></i>
                      <div>
                          <div style="font-weight:800; font-size:12px; color:#f59e0b; text-transform:uppercase; letter-spacing:0.5px;">Ficha en formato antiguo</div>
                          <div style="font-size:12px; color:var(--text-muted);">El sistema de Linaje de este personaje será actualizado en la próxima revisión de ficha.</div>
                      </div>
                  </div>
                  <?php if (empty($char['linaje']['geneNames'])): ?>
                  <div style="padding: 30px; text-align:center; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                      <i class="fas fa-dna" style="font-size: 40px; color: var(--accent-purple); opacity: 0.5; margin-bottom:15px;"></i>
                      <h4 style="color:var(--text-primary); margin-bottom:5px;">Sin datos de Linaje</h4>
                      <p style="color:var(--text-muted); font-size:13px;">Este personaje no tiene genes registrados en el sistema antiguo.</p>
                  </div>
                  <?php else: ?>
                  <div class="gene-cards-container">
                      <?php foreach ($char['linaje']['geneNames'] as $geneName): ?>
                      <div class="gene-card perk-racial">
                          <div class="gene-card-icon" style="background:rgba(99,102,241,0.1); border:2px solid rgba(99,102,241,0.3);"><i class="fas fa-dna" style="color:var(--accent-indigo);"></i></div>
                          <div class="gene-card-info">
                              <div class="gene-card-name"><?= htmlspecialchars($geneName) ?></div>
                              <div class="gene-card-desc">Gen activo (formato antiguo).</div>
                          </div>
                      </div>
                      <?php endforeach; ?>
                  </div>
                  <?php endif; ?>
              <?php endif; ?>
          </div>

          <!-- TAB: CRONOLOGIA -->
          <div id="pjTab_cronologia" class="pj-preview-tab-content">
              <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:10px; margin-bottom:20px;">
                  <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin:0;">Diario de Aventuras</h3>
                  <?php if ($can_edit): ?>
                      <div style="display:flex; gap:8px;">
                          <button class="pj-btn-add" onclick="openNewDiario()"><i class="fas fa-plus"></i> Añadir</button>
                          <button class="pj-btn-add" onclick="openEditDiario()"><i class="fas fa-list"></i> Editar</button>
                      </div>
                  <?php endif; ?>
              </div>
              
              <?php
               $cat_list = ['Pasado'=>'#8b5cf6','Presente'=>'#10b981','Mision'=>'#f59e0b','Evento'=>'#3b82f6','Trama'=>'#ef4444','Fic'=>'#ec4899','Off_Rol'=>'#6b7280'];
               $cat_names = ['Pasado'=>'Pasado','Presente'=>'Presente','Mision'=>'Misión','Evento'=>'Evento','Trama'=>'Trama','Fic'=>'Fic','Off_Rol'=>'Off Rol'];
               $cat_counts = [];
              foreach ($cat_list as $cn => $cc) $cat_counts[$cn] = 0;
              foreach ($char['cronologia']['diario'] as $entry) {
                  $ec = $entry['category'] ?? 'Presente';
                  if (isset($cat_counts[$ec])) $cat_counts[$ec]++;
              }
              ?>
              <div class="pj-cat-counter">
                  <?php foreach ($cat_list as $cn => $cc): ?>
                  <span class="pj-cat-chip" style="color:<?= $cc ?>;background:<?= $cc ?>22;">
                      <span class="num"><?= $cat_counts[$cn] ?></span> <?= $cat_names[$cn] ?? $cn ?>
                  </span>
                  <?php endforeach; ?>
              </div>

              <?php if (empty($char['cronologia']['diario'])): ?>
                  <p style="color:var(--text-muted); font-size:14px; text-align:center; margin-bottom:40px;">No hay registros en el diario.</p>
              <?php else: ?>
                  <div class="pj-scroll-box" style="height: 350px;">
                      <div class="pj-timeline">
                      <?php 
                      $s_names = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
                      foreach ($char['cronologia']['diario'] as $entry): 
                          $d = $entry['day'] ?? '?';
                          $s_id = $entry['season'] ?? 0;
                          $y = $entry['year'] ?? '?';
                          $s_name = $s_names[$s_id] ?? 'Desconocida';
                          $fecha_str = "Día {$d} de {$s_name}, Año {$y}";
                          $entry_cat = $entry['category'] ?? 'Presente';
                          $cat_color = $cat_list[$entry_cat] ?? '#6366f1';
                          $thread_name = $entry['thread_name'] ?? '';
                          $participants = $entry['participants'] ?? [];
                      ?>
                          <div class="pj-timeline-item-wrapper" style="margin-bottom:15px; border: 1px dashed var(--border-color); padding: 15px; border-radius: 6px; background: var(--bg-surface);">
                              <div class="pj-timeline-item" style="border-left: 4px solid <?= $cat_color ?>; padding-left: 15px;">
                                  <div class="pj-timeline-date" style="display:flex;align-items:center;gap:10px; color: <?= $cat_color ?>;">
                                      <span style="text-transform:uppercase; letter-spacing:1px; font-size:11px; font-weight:800;"><?= htmlspecialchars($cat_names[$entry_cat] ?? $entry_cat) ?></span>
                                      <span style="font-size:12px; font-weight:600; opacity:0.7;">&bull; <?= mb_strtoupper(htmlspecialchars($fecha_str)) ?></span>
                                  </div>
                                  <?php if ($thread_name): ?>
                                      <div style="margin-top:10px; font-size:14px; font-weight:700; color:var(--accent-indigo);"><?= htmlspecialchars($thread_name) ?></div>
                                  <?php endif; ?>
                                  <div class="pj-timeline-desc" style="margin-top:8px; font-size:14px; line-height:1.6; color:var(--text-primary); font-style:italic; white-space:pre-wrap;"><?= htmlspecialchars($entry['desc'] ?? '') ?></div>
                                  <?php if (!empty($participants)): ?>
                                      <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:6px;">
                                          <?php foreach ($participants as $pj): ?>
                                              <span style="font-size:11px; font-weight:600; color:var(--text-primary); background:var(--bg-main); padding:4px 10px; border-radius:12px; border:1px solid var(--border-color); display:inline-flex; align-items:center; gap:5px;"><i class="fas fa-user" style="color:var(--text-muted);"></i> <?= htmlspecialchars($pj['name'] ?? '?') ?></span>
                                          <?php endforeach; ?>
                                      </div>
                                  <?php endif; ?>
                                  <?php if (!empty($entry['link'])): ?>
                                      <a href="<?= htmlspecialchars((string)($entry['link'] ?? '')) ?>" class="pj-timeline-link" target="_blank" style="margin-top:15px; display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--accent-indigo); font-weight:800; text-decoration:none; background:#f4f1e1; padding:8px 16px; border-radius:20px; border:1px solid #e5dfc5; text-transform:uppercase; letter-spacing:0.5px;"><i class="fas fa-book-open"></i> Leer Tema</a>
                                  <?php endif; ?>
                              </div>
                          </div>
                      <?php endforeach; ?>
                      </div>
                  </div>
              <?php endif; ?>

              <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:10px; margin-top:40px; margin-bottom:20px;">
                  <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin:0;">Red de Contactos</h3>
                  <?php if ($can_edit): ?>
                      <div style="display:flex; gap:8px;">
                          <button class="pj-btn-add" onclick="openNewRelacion()"><i class="fas fa-plus"></i> Añadir Contacto</button>
                          <button class="pj-btn-add" onclick="openEditRelacion()"><i class="fas fa-cog"></i> Editar</button>
                          <button class="pj-btn-add" onclick="openNewGroup()"><i class="fas fa-users"></i> Crear Grupo</button>
                      </div>
                  <?php endif; ?>
              </div>

              <?php
              $tag_colors = [
                  'Amigo' => '#10b981', 'Compañero' => '#3b82f6', 'Aliado' => '#3b82f6',
                  'Rival' => '#f59e0b', 'Enemigo' => '#ef4444', 'Némesis' => '#ef4444',
                  'Familiar' => '#ec4899', 'Hermano' => '#ec4899', 'Hermana' => '#ec4899',
                  'Padre' => '#8b5cf6', 'Madre' => '#8b5cf6',
                  'Maestro' => '#f97316', 'Mentor' => '#f97316',
                  'Aprendiz' => '#06b6d4', 'Protegido' => '#06b6d4',
                  'Interés Romántico' => '#ec4899', 'Cónyuge' => '#ec4899', 'Amante' => '#ec4899',
                  'Conocido' => '#6b7280', 'Socio' => '#8b5cf6', 'Cómplice' => '#8b5cf6',
                  'Subordinado' => '#64748b', 'Superior' => '#64748b',
                  'Adversario' => '#f59e0b', 'Seguidor' => '#06b6d4', 'Líder' => '#f97316',
                  'Miembro' => '#6b7280',
              ];
              ?>
              <?php if (empty($char['cronologia']['relaciones'])): ?>
                  <p style="color:var(--text-muted); font-size:14px; text-align:center;">No hay relaciones registradas.</p>
              <?php else: ?>
                  <div style="position:relative;">
                      <!-- Controles integrados flotantes en la esquina superior derecha -->
                      <div style="position:absolute; top:15px; right:15px; z-index:10; display:flex; gap:15px;">
                          <button id="btn-view-graph" style="background:none; border:none; color:var(--text-primary); font-size:22px; cursor:pointer; opacity:1; transition:opacity 0.2s;" onclick="document.getElementById('pj-view-graph').style.display='block'; document.getElementById('pj-view-list').style.display='none'; this.style.opacity=1; document.getElementById('btn-view-list').style.opacity=0.4;" title="Mapa de Relaciones"><i class="fas fa-project-diagram"></i></button>
                          <button id="btn-view-list" style="background:none; border:none; color:var(--text-primary); font-size:22px; cursor:pointer; opacity:0.4; transition:opacity 0.2s;" onclick="document.getElementById('pj-view-graph').style.display='none'; document.getElementById('pj-view-list').style.display='block'; this.style.opacity=1; document.getElementById('btn-view-graph').style.opacity=0.4;" title="Vista Lista"><i class="fas fa-th-large"></i></button>
                      </div>
                      
                      <div id="pj-view-graph">
                          <div id="pj-network-container" style="width: 100%; height: 500px; background: radial-gradient(circle, var(--bg-surface) 0%, var(--bg-main) 100%); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; position: relative;"></div>
                          <script src="../../jscripts/game/game_network.js?v=<?= time() ?>"></script>
                      </div>
                      
                      <div id="pj-view-list" style="display:none; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-md); padding-top:40px;">
                          <div class="pj-scroll-box" style="height: 460px; border:none; background:transparent;">
                              <div class="pj-relations-grid">
                              <?php foreach ($char['cronologia']['relaciones'] as $rel):
                                  $tags = $rel['tags'] ?? [];
                                  if (empty($tags) && !empty($rel['relation'])) $tags = [$rel['relation']];
                                  if (!is_array($tags)) $tags = [$tags];
                              ?>
                                  <?php if (!empty($rel['pj_id'])): ?>
                                      <a href="personaje.php?pj=<?= htmlspecialchars((string)$rel['pj_id']) ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                  <?php endif; ?>
                                  <div class="pj-relation-card" style="position:relative;">
                                      <?php if (!empty($rel['is_npc'])): ?>
                                          <div style="position:absolute; top:-5px; right:-5px; background:#f59e0b; color:#000; font-size:9px; font-weight:800; padding:2px 6px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.5); z-index:2;">NPC</div>
                                      <?php endif; ?>
                                      <img src="<?= htmlspecialchars($rel['image'] ?: 'https://placehold.co/70x70') ?>" class="pj-relation-img">
                                      <div class="pj-relation-name"><?= htmlspecialchars($rel['name']) ?></div>
                                      <div class="pj-relation-tag-wrap">
                                          <?php foreach ($tags as $t): $t = trim($t); if (!$t) continue; $c = $tag_colors[$t] ?? '#6366f1'; ?>
                                          <span class="pj-relation-tag" style="color:<?= $c ?>; background:<?= $c ?>22;"><?= htmlspecialchars($t) ?></span>
                                          <?php endforeach; ?>
                                      </div>
                                      <?php if (!empty($rel['desc'])): ?>
                                          <div style="font-size:11px; color:var(--text-muted); margin-top:8px; line-height:1.4;"><?= htmlspecialchars($rel['desc']) ?></div>
                                      <?php endif; ?>
                                  </div>
                                  <?php if (!empty($rel['pj_id'])): ?>
                                      </a>
                                  <?php endif; ?>
                              <?php endforeach; ?>
                              </div>
                          </div>
                      </div>
                  </div>
              <?php endif; ?>
          </div>

          <?php if ($can_view_private): ?>
          <!-- TAB: DECK -->
          <div id="pjTab_deck" class="pj-preview-tab-content">
              <div id="rpg-character-deck-container" data-char-id="<?= $char['id'] ?>" data-is-owner="<?= (int)($char['user_id'] == $mybb->user['uid']) ?>">
                  <div style="text-align:center; padding: 40px; color: var(--text-muted);">
                      <i class="fas fa-circle-notch fa-spin" style="font-size: 24px; margin-bottom: 10px;"></i><br>
                      Cargando Deck...
                  </div>
              </div>
          </div>

          <!-- TAB: GESTION -->
          <div id="pjTab_gestion" class="pj-preview-tab-content">
              <div style="padding: 50px 30px; text-align:center; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                  <i class="fas fa-cogs" style="font-size: 50px; color: var(--text-muted); opacity: 0.5; margin-bottom:20px;"></i>
                  <h4 style="color:var(--text-primary); margin-bottom:10px; font-size:20px;">Panel de Gestión en Mantenimiento</h4>
                  <p style="color:var(--text-muted); font-size:14px;">El panel de administración del personaje, inventario y consumibles se encuentra bajo construcción.</p>
              </div>
          </div>
          <?php endif; ?>
      </div>
      
  </div>
  </div>
  
  <?php if ($can_edit): ?>
  <!-- MODAL DIARIO -->
  <div id="modal_diario" class="pj-modal-overlay" onclick="if(event.target===this){this.style.display='none'; document.getElementById('modal_gestionar_diario').style.display='flex';}">
      <div class="pj-modal">
          <div class="pj-modal-title">Añadir Entrada al Diario</div>
          <div class="form-group">
              <label>Link al Tema de Rol</label>
              <div style="display:flex; gap:8px;">
                  <input type="url" id="diario_link" class="textbox" style="flex:1;" placeholder="https://foro.com/showthread.php?tid=123" onblur="autoDetectThread(this.value)">
                  <button class="pj-btn-add" style="flex-shrink:0; padding:8px 18px;" onclick="autoDetectThread(document.getElementById('diario_link').value)"><i class="fas fa-sync-alt"></i> Detectar</button>
              </div>
              <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Pega el enlace del hilo y presiona "Detectar" para auto-completar los datos.</div>
          </div>
          <div id="diario_auto_data" style="display:none; background:var(--bg-main); border:1px solid var(--border-color); border-radius:8px; padding:15px; margin-bottom:16px;">
              <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                  <i class="fas fa-check-circle" style="color:#10b981;"></i>
                  <span style="font-size:13px; font-weight:700; color:var(--text-primary);">Datos detectados del hilo</span>
              </div>
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:12px;">
                  <div><span style="color:var(--text-muted);">Título:</span> <span id="diario_detected_title" style="color:var(--text-primary); font-weight:600;"></span></div>
                  <div><span style="color:var(--text-muted);">Tipo:</span> <span id="diario_detected_cat" style="font-weight:700;"></span></div>
                  <div><span style="color:var(--text-muted);">Fecha:</span> <span id="diario_detected_date" style="color:var(--text-primary); font-weight:600;"></span></div>
                  <div><span style="color:var(--text-muted);">Participantes:</span> <span id="diario_detected_parts" style="color:var(--text-primary); font-weight:600;"></span></div>
              </div>
              <input type="hidden" id="diario_thread_id" value="">
              <input type="hidden" id="diario_cat" value="">
              <input type="hidden" id="diario_day" value="">
              <input type="hidden" id="diario_season" value="">
              <input type="hidden" id="diario_year" value="">
          </div>
          <div class="form-group">
              <label>Descripción</label>
              <textarea id="diario_desc" class="textbox" rows="4" placeholder="Resumen de los hechos..."></textarea>
          </div>
          <div class="pj-modal-actions">
              <button class="pj-btn-add" onclick="document.getElementById('modal_diario').style.display='none'; document.getElementById('modal_gestionar_diario').style.display='flex'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('diario')"><i class="fas fa-check"></i> Confirmar</button>
          </div>
      </div>
  </div>



  <!-- MODAL RELACION -->
  <div id="modal_relacion" class="pj-modal-overlay" onclick="if(event.target===this){this.style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex';}">
      <div class="pj-modal">
          <div class="pj-modal-title" id="rel_modal_title">Añadir Relación</div>
          <div class="form-group">
              <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                  <input type="checkbox" id="rel_is_npc" onchange="toggleRelNpc(this)">
                  Es un NPC (Personaje No Jugador)
              </label>
          </div>
          <div class="form-group" id="rel_pj_box">
              <label>Personaje del Foro <span style="color:var(--text-muted);font-weight:400;text-transform:none;">— empieza a escribir para buscar</span></label>
              <input type="text" id="rel_pj_search" class="textbox" placeholder="Buscar personaje..." autocomplete="off" oninput="searchPersonaje(this.value)">
              <select id="rel_pj_id" style="display:none;">
                  <option value="">Selecciona un personaje</option>
                  <?php foreach($all_chars as $c): ?>
                  <option value="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                  <?php endforeach; ?>
              </select>
              <div id="rel_pj_results" style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;"></div>
          </div>
          <div class="form-group" id="rel_npc_box" style="display:none;">
              <label>Nombre del NPC</label>
              <input type="text" id="rel_npc_name" class="textbox" placeholder="Ej: Alcalde de la ciudad">
          </div>
          <div class="form-group">
              <label>Descripción Corta</label>
              <input type="text" id="rel_desc" class="textbox" placeholder="Breve nota sobre la relación...">
          </div>
          <div class="form-group">
              <label>Imagen (URL 70x70 aprox)</label>
              <input type="url" id="rel_img" class="textbox" placeholder="https://i.imgur.com/...">
          </div>
          <div class="form-group">
              <label>Etiquetas (Elige hasta 3)</label>
              <div class="pj-tag-picker" id="rel_tag_picker">
                  <?php foreach ($tag_colors as $lbl => $c): ?>
                      <div class="pj-tag" data-tag="<?= htmlspecialchars($lbl) ?>" data-color="<?= $c ?>" style="border-color:<?= $c ?>; color:<?= $c ?>;"><?= htmlspecialchars($lbl) ?></div>
                  <?php endforeach; ?>
              </div>
              <input type="hidden" id="rel_tags" value="">
          </div>

          <hr style="border:0; border-top:1px solid var(--border-color); margin:20px 0;">
          <div class="form-group">
              <label style="display:flex; align-items:center; gap:8px; font-weight:700; cursor:pointer;">
                  <input type="checkbox" id="rel_add_conn" onchange="document.getElementById('rel_conn_options').style.display=this.checked?'block':'none'">
                  ¿Crear una línea de conexión explícita en la red?
              </label>
          </div>
          <div id="rel_conn_options" style="display:none; padding:15px; background:var(--bg-main); border:1px solid var(--border-color); border-radius:8px; margin-bottom:15px;">
              <p style="font-size:12px; color:var(--text-secondary); margin-top:0;">El origen ser&aacute; este contacto que est&aacute;s creando/editando.</p>
              <div class="form-group">
                  <label>Enlazar con (Destino)</label>
                  <select id="rel_conn_target" class="textbox"></select>
              </div>
              <div class="form-group">
                  <label>Nombre de la Conexión (Ej: Novios, Hermanos)</label>
                  <input type="text" id="rel_conn_label" class="textbox" placeholder="Aparecerá en la línea...">
              </div>
              <div class="form-group">
                  <label>Color de la Línea</label>
                  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;" id="rel_conn_colors">
                      <?php $g_colors = ['#10b981','#3b82f6','#6366f1','#8b5cf6','#ec4899','#ef4444','#f97316','#f59e0b']; foreach ($g_colors as $c): ?>
                          <div class="conn-color-swatch-rel" data-color="<?= $c ?>" style="width:28px; height:28px; border-radius:50%; background:<?= $c ?>; cursor:pointer; border:2px solid transparent; transition:transform 0.15s;" onclick="selectConnColorRel(this)"></div>
                      <?php endforeach; ?>
                  </div>
                  <input type="hidden" id="rel_conn_color" value="#ec4899">
              </div>
          </div>
          
          <div style="text-align:right; margin-top:20px;">
              <button class="pj-btn-add" style="margin-right:10px;" onclick="document.getElementById('modal_relacion').style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('relacion')"><i class="fas fa-check"></i> Confirmar</button>
          </div>
      </div>
  </div>

  <!-- MODAL GESTIONAR DIARIO -->
  <div id="modal_gestionar_diario" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
      <div class="pj-modal" style="width: 520px; max-width: 95vw;">
          <div class="pj-modal-title">Diario de Aventuras</div>
          


          <div style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
              <span style="font-size:12px; color:var(--text-muted);">Administra o añade nuevas memorias a tu cronología.</span>
              <button class="pj-btn-add" style="padding:6px 12px; font-size:12px;" onclick="openNewDiario()"><i class="fas fa-plus"></i> Añadir Entrada</button>
          </div>

          <div id="diario-list" class="pj-edit-list" style="height: 320px; overflow-y: auto;"></div>

          <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px; border-top: 1px solid var(--border-color); padding-top:20px;">
              <button class="pj-btn-add" onclick="document.getElementById('modal_gestionar_diario').style.display='none'">Cerrar</button>
              <button class="pj-btn-add" style="background:var(--accent-emerald,#10b981); color:#fff;" onclick="saveBatchCronologia()"><i class="fas fa-save"></i> Guardar Todo</button>
          </div>
      </div>
  </div>

  <!-- MODAL EDITAR RELACIONES Y GRUPOS (TABBED DASHBOARD) -->
  <div id="modal_gestionar_relaciones" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
      <div class="pj-modal" style="width: 540px; max-width: 95vw; padding: 25px;">
          <div class="pj-modal-title" style="margin-bottom:15px;">Cuaderno de Relaciones y Red</div>
          
          <!-- Tabbed navigation -->
          <div class="pj-modal-tabs" style="display:flex; border-bottom:1px solid var(--border-color); margin-bottom:20px; gap:5px; justify-content: center;">
              <button class="pj-modal-tab-btn active" onclick="switchRelTab('contactos',this)">Contactos y NPCs</button>
              <button class="pj-modal-tab-btn" onclick="switchRelTab('grupos',this)">Grupos y Facciones</button>
              <button class="pj-modal-tab-btn" onclick="switchRelTab('conexiones',this)">Conexiones de Red</button>
          </div>

          <!-- TAB CONTENT: CONTACTOS -->
          <div id="tab-contactos" class="pj-tab-content">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                  <span style="font-size:12px; color:var(--text-muted);">Administra tus relaciones directas con otros personajes del foro o NPCs.</span>
                  <button class="pj-btn-add" style="background:var(--accent-indigo); color:#fff; padding:6px 12px; font-size:12px;" onclick="openNewRelacion()"><i class="fas fa-user-plus"></i> Añadir Contacto</button>
              </div>
              <div id="contactos-list" class="pj-edit-list" style="height: 320px; overflow-y: auto;"></div>
          </div>

          <!-- TAB CONTENT: GRUPOS -->
          <div id="tab-grupos" class="pj-tab-content" style="display:none;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                  <span style="font-size:12px; color:var(--text-muted);">Organiza tus contactos en grupos (ej: tu tripulación, gremios, familia).</span>
                  <button class="pj-btn-add" style="background:var(--accent-indigo); color:#fff; padding:6px 12px; font-size:12px;" onclick="openNewGroup()"><i class="fas fa-users"></i> Crear Grupo</button>
              </div>
              <div id="grupos-list" class="pj-edit-list" style="height: 320px; overflow-y: auto;"></div>
          </div>

          <!-- TAB CONTENT: CONEXIONES -->
          <div id="tab-conexiones" class="pj-tab-content" style="display:none;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                  <span style="font-size:12px; color:var(--text-muted);">Dibuja uniones y vínculos personalizados entre contactos en el mapa de red.</span>
                  <button class="pj-btn-add" style="background:var(--accent-indigo); color:#fff; padding:6px 12px; font-size:12px;" onclick="openNewConnection()"><i class="fas fa-link"></i> Añadir Conexión</button>
              </div>
              <div id="conexiones-list" class="pj-edit-list" style="height: 320px; overflow-y: auto;"></div>
          </div>

          <!-- Footer Actions -->
          <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px; border-top: 1px solid var(--border-color); padding-top:20px;">
              <button class="pj-btn-add" onclick="document.getElementById('modal_gestionar_relaciones').style.display='none'">Cerrar</button>
              <button class="pj-btn-add" style="background:var(--accent-emerald,#10b981); color:#fff;" onclick="saveBatchCronologia()"><i class="fas fa-save"></i> Guardar Todo</button>
          </div>
      </div>
  </div>

  <!-- MODAL GRUPO -->
  <div id="modal_group" class="pj-modal-overlay" onclick="if(event.target===this){this.style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex';}">
      <div class="pj-modal" style="width: 500px;">
          <div class="pj-modal-title" id="group_modal_title">Crear Grupo</div>
          
          <div class="form-group">
              <label>Nombre del Grupo</label>
              <input type="text" id="grp_name" class="textbox" placeholder="Ej: La Tripulación, Familia Real, etc.">
          </div>
          
          <div class="form-group">
              <label>Color del Grupo</label>
              <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;" id="grp_colors">
                  <?php 
                  $g_colors = ['#10b981','#3b82f6','#6366f1','#8b5cf6','#ec4899','#ef4444','#f97316','#f59e0b'];
                  foreach ($g_colors as $c): ?>
                      <div class="grp-color-swatch" data-color="<?= $c ?>" style="width:28px; height:28px; border-radius:50%; background:<?= $c ?>; cursor:pointer; border:2px solid transparent; transition:transform 0.15s;" onclick="selectGroupColor(this)"></div>
                  <?php endforeach; ?>
              </div>
              <input type="hidden" id="grp_color" value="#6366f1">
          </div>

          <div class="form-group">
              <label>Seleccionar Miembros (Mín. 2)</label>
              <div class="pj-scroll-box" id="grp_members_container" style="height: 180px; padding:10px; background:var(--bg-main); border:1px solid var(--border-color); margin-bottom:0;">
                  <!-- Will be rendered dynamically via JS -->
              </div>
          </div>

          <div style="text-align:right; margin-top:30px;">
              <button class="pj-btn-add" style="margin-right:10px;" onclick="document.getElementById('modal_group').style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('group')"><i class="fas fa-check"></i> Confirmar</button>
          </div>
      </div>
  </div>

  <!-- MODAL CONEXION -->
  <div id="modal_connection" class="pj-modal-overlay" onclick="if(event.target===this){this.style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex';}">
      <div class="pj-modal" style="width: 500px;">
          <div class="pj-modal-title" id="conn_modal_title">Crear Conexión</div>
          
          <div class="form-group">
              <label>Contacto A</label>
              <select id="conn_source" class="textbox"></select>
          </div>

          <div class="form-group">
              <label>Contacto B</label>
              <select id="conn_target" class="textbox"></select>
          </div>
          
          <div class="form-group">
              <label>Nombre de la Relación (Ej: Novios, Hermanos)</label>
              <input type="text" id="conn_label" class="textbox" placeholder="Aparecerá en la línea...">
          </div>
          
          <div class="form-group">
              <label>Color de la Línea</label>
              <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;" id="conn_colors">
                  <?php $g_colors = ['#10b981','#3b82f6','#6366f1','#8b5cf6','#ec4899','#ef4444','#f97316','#f59e0b']; foreach ($g_colors as $c): ?>
                      <div class="conn-color-swatch" data-color="<?= $c ?>" style="width:28px; height:28px; border-radius:50%; background:<?= $c ?>; cursor:pointer; border:2px solid transparent; transition:transform 0.15s;" onclick="selectConnColor(this)"></div>
                  <?php endforeach; ?>
              </div>
              <input type="hidden" id="conn_color" value="#ec4899">
          </div>

          <div style="text-align:right; margin-top:30px;">
              <button class="pj-btn-add" style="margin-right:10px;" onclick="document.getElementById('modal_connection').style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('connection')"><i class="fas fa-check"></i> Confirmar</button>
          </div>
      </div>
  </div>

  <?php endif; ?>
  <?php endif; ?>
</div>

<script>
window.onerror = function(msg, url, lineNo, columnNo, error) {
    var errStr = 'Error: ' + msg + '\nURL: ' + url + '\nLine: ' + lineNo + '\nColumn: ' + columnNo + '\nError object: ' + JSON.stringify(error);
    var div = document.createElement('div');
    div.style.cssText = 'position:fixed;top:0;left:0;right:0;background:red;color:white;z-index:999999;padding:20px;white-space:pre-wrap;font-family:monospace;';
    div.innerText = errStr;
    document.body.appendChild(div);
    return false;
};
window.addEventListener("unhandledrejection", function(e) {
    var div = document.createElement('div');
    div.style.cssText = 'position:fixed;top:0;left:0;right:0;background:orange;color:white;z-index:999999;padding:20px;white-space:pre-wrap;font-family:monospace;';
    div.innerText = 'Unhandled Promise Rejection: ' + e.reason;
    document.body.appendChild(div);
});

var tagColors = <?= json_encode($tag_colors, JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
var catColors = <?= json_encode($cat_list_display ?? ['Pasado'=>'#8b5cf6','Presente'=>'#10b981','Mision'=>'#f59e0b','Evento'=>'#3b82f6','Trama'=>'#ef4444','Fic'=>'#ec4899','Off_Rol'=>'#6b7280'], JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
var seasonNames = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
window.__PJ_NETWORK_DATA = {
    relaciones: <?= json_encode($char['cronologia']['relaciones'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
    groups: <?= json_encode($char['cronologia']['groups'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
    connections: <?= json_encode($char['cronologia']['connections'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
    diario: <?= json_encode($char['cronologia']['diario'] ?? [], JSON_UNESCAPED_UNICODE) ?>
};

window.draftNetworkData = {
    relaciones: [],
    groups: [],
    connections: [],
    diario: []
};
function initDraftData() {
    if (window.__PJ_NETWORK_DATA) {
        window.draftNetworkData = JSON.parse(JSON.stringify(window.__PJ_NETWORK_DATA));
        if(!window.draftNetworkData.diario) window.draftNetworkData.diario = [];
    }
}
initDraftData();

function renderNetworkLists() {
    var cList = document.getElementById('contactos-list');
    var gList = document.getElementById('grupos-list');
    var cnList = document.getElementById('conexiones-list');
    var dList = document.getElementById('diario-list');
    
    // Update options in modal_relacion and modal_connection
    var selTarget = document.getElementById('rel_conn_target');
    var selConnSource = document.getElementById('conn_source');
    var selConnTarget = document.getElementById('conn_target');
    var grpMembersContainer = document.getElementById('grp_members_container');
    
    var htmlOpts = '<option value="">Selecciona Contacto...</option>';
    var mHtml = '';
    
    if (window.draftNetworkData.relaciones && window.draftNetworkData.relaciones.length > 0) {
        window.draftNetworkData.relaciones.forEach(function(r) {
            htmlOpts += '<option value="'+r.id+'">'+escapeHtml(r.name)+'</option>';
            
            mHtml += '<label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:8px; border-radius:6px; transition:background 0.2s;" onmouseover="this.style.background=\'rgba(255,255,255,0.05)\'" onmouseout="this.style.background=\'transparent\'">';
            mHtml += '<input type="checkbox" name="grp_members[]" value="' + escapeHtml(r.id) + '" style="width:16px; height:16px;">';
            mHtml += '<img src="' + escapeHtml(r.image || 'https://placehold.co/24x24') + '" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">';
            mHtml += '<span style="font-size:13px; color:var(--text-primary); text-transform:none; letter-spacing:normal; font-weight:normal;">' + escapeHtml(r.name) + '</span>';
            mHtml += '</label>';
        });
    } else {
        mHtml = '<div style="font-size:12px; color:var(--text-muted); text-align:center; padding-top:20px;">No tienes contactos. Añade contactos primero.</div>';
    }
    
    if(selTarget) selTarget.innerHTML = htmlOpts;
    if(selConnSource) selConnSource.innerHTML = htmlOpts;
    if(selConnTarget) selConnTarget.innerHTML = htmlOpts;
    if(grpMembersContainer) grpMembersContainer.innerHTML = mHtml;
    
    // Render Diario
    if(dList) {
        if(window.draftNetworkData.diario.length === 0) {
            dList.innerHTML = '<p style="color:var(--text-muted); font-size:13px; text-align:center; padding: 20px 0;">No hay entradas en el diario.</p>';
        } else {
            var dHtml = '';
            window.draftNetworkData.diario.forEach(function(entry, index) {
                var sName = seasonNames[entry.season] || 'Desconocida';
                var fechaStr = "Día " + entry.day + " de " + sName + ", Año " + entry.year;
                var cc = catColors[entry.category] || '#6366f1';
                var chars = Array.from(entry.desc || '');
                var shortDesc = chars.slice(0, 80).join('');
                if(chars.length > 80) shortDesc += '...';
                
                dHtml += '<div class="pj-edit-item" data-category="'+entry.category+'" style="border-left: 4px solid '+cc+'; background: linear-gradient(to right, '+cc+'08, transparent); margin-bottom: 10px;">';
                dHtml += '<div class="pj-edit-item-body" style="padding-right:15px;">';
                dHtml += '<div style="display:flex; align-items:center; gap:8px; color:'+cc+';">';
                dHtml += '<span style="text-transform:uppercase; letter-spacing:1px; font-size:11px; font-weight:800;">'+escapeHtml(entry.category)+'</span>';
                dHtml += '<span style="color:var(--text-muted); font-size:12px; font-weight:600;">&bull; '+escapeHtml(fechaStr)+'</span>';
                dHtml += '</div>';
                if (entry.thread_name) {
                    dHtml += '<div style="margin-top:2px; font-size:12px; font-weight:700; color:var(--accent-indigo);">'+escapeHtml(entry.thread_name)+'</div>';
                }
                dHtml += '<div style="margin-top:6px; font-size:13px; line-height:1.4; color:var(--text-primary);">'+escapeHtml(shortDesc)+'</div>';
                if (entry.participants && entry.participants.length > 0) {
                    dHtml += '<div style="margin-top:4px; display:flex; flex-wrap:wrap; gap:3px;">';
                    entry.participants.forEach(function(p) {
                        dHtml += '<span style="font-size:10px; font-weight:600; color:var(--text-secondary); background:rgba(255,255,255,0.05); padding:1px 6px; border-radius:8px; border:1px solid var(--border-color);"><i class="fas fa-user"></i> '+escapeHtml(p.name||'?')+'</span>';
                    });
                    dHtml += '</div>';
                }
                dHtml += '</div>';
                dHtml += '<div class="pj-edit-item-actions">';
                dHtml += '<button class="pj-edit-btn pj-edit-btn-edit" title="Editar" data-index="'+index+'"><i class="fas fa-pen"></i></button>';
                dHtml += '<button class="pj-edit-btn pj-edit-btn-del" title="Eliminar" onclick="deleteDraftEntry(\'diario\', \''+entry.id+'\')"><i class="fas fa-trash"></i></button>';
                dHtml += '</div></div>';
            });
            dList.innerHTML = dHtml;
            
            // Attach event listeners for edit buttons
            var editBtns = dList.querySelectorAll('.pj-edit-btn-edit');
            editBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var idx = this.getAttribute('data-index');
                    var item = window.draftNetworkData.diario[idx];
                    editDiarioEntryDraftObj(item);
                });
            });
        }
    }

    // Render Contactos
    if(cList) {
        if(!window.draftNetworkData.relaciones || window.draftNetworkData.relaciones.length === 0) {
            cList.innerHTML = '<p style="color:var(--text-muted); font-size:13px; text-align:center; padding: 20px 0;">No hay relaciones registradas.</p>';
        } else {
            var cHtml = '';
            window.draftNetworkData.relaciones.forEach(function(rel) {
                var tagsHtml = '';
                var rtags = rel.tags || [];
                if(rtags.length === 0 && rel.relation) rtags = [rel.relation];
                rtags.forEach(function(t) {
                    if(!t) return;
                    var c = tagColors[t] || '#6366f1';
                    tagsHtml += '<span style="color:'+c+'; margin-right:10px; font-weight:600;">'+escapeHtml(t)+'</span>';
                });
                var jsonStr = JSON.stringify(rel).replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                
                cHtml += '<div class="pj-edit-item" style="margin-bottom: 10px;">';
                cHtml += '<div style="display:flex; align-items:center; gap:15px; flex:1; min-width:0;">';
                cHtml += '<img src="'+escapeHtml(rel.image || 'https://placehold.co/40x40')+'" style="width:42px; height:42px; border-radius:50%; object-fit:cover; border: 2px solid rgba(255,255,255,0.1); flex-shrink:0;">';
                cHtml += '<div style="min-width:0;"><div style="font-size:14px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px;">'+escapeHtml(rel.name);
                if(rel.is_npc) cHtml += '<span style="font-size:9px; background:#f59e0b; color:#000; padding:1px 5px; border-radius:4px; font-weight:800; text-transform:uppercase;">NPC</span>';
                cHtml += '</div><div style="font-size:11px; margin-top:4px; display:flex; gap:6px; flex-wrap:wrap;">'+tagsHtml+'</div></div></div>';
                cHtml += '<div class="pj-edit-item-actions">';
                cHtml += '<button class="pj-edit-btn pj-edit-btn-edit" title="Editar" onclick="editRelacionEntryDraft(\''+jsonStr+'\')"><i class="fas fa-pen"></i></button>';
                cHtml += '<button class="pj-edit-btn pj-edit-btn-del" title="Eliminar" onclick="deleteDraftEntry(\'relacion\', \''+rel.id+'\')"><i class="fas fa-trash"></i></button>';
                cHtml += '</div></div>';
            });
            cList.innerHTML = cHtml;
        }
    }
    
    // Render Groups
    if(gList) {
        if(!window.draftNetworkData.groups || window.draftNetworkData.groups.length === 0) {
            gList.innerHTML = '<p style="color:var(--text-muted); font-size:13px; text-align:center; padding: 20px 0;">No hay grupos creados.</p>';
        } else {
            var gHtml = '';
            window.draftNetworkData.groups.forEach(function(grp) {
                var jsonStr = JSON.stringify(grp).replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                gHtml += '<div class="pj-edit-item" style="margin-bottom: 10px; border-left: 4px solid '+grp.color+';">';
                gHtml += '<div style="display:flex; align-items:center; gap:12px; flex:1; min-width:0;">';
                gHtml += '<span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:'+grp.color+'; box-shadow: 0 0 8px '+grp.color+'; flex-shrink:0;"></span>';
                gHtml += '<div style="font-size:14px; font-weight:700; color:var(--text-primary); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">'+escapeHtml(grp.name)+'</div>';
                gHtml += '</div>';
                gHtml += '<div style="font-size:11px; color:var(--text-muted); font-weight:600; background:rgba(255,255,255,0.05); border-radius:8px; padding: 3px 8px; flex-shrink:0; margin-right:5px;">'+(grp.members?grp.members.length:0)+' miembros</div>';
                gHtml += '<div class="pj-edit-item-actions">';
                gHtml += '<button class="pj-edit-btn pj-edit-btn-edit" title="Editar" onclick="editGroupEntry(\''+grp.id+'\', \''+jsonStr+'\')"><i class="fas fa-pen"></i></button>';
                gHtml += '<button class="pj-edit-btn pj-edit-btn-del" title="Eliminar" onclick="deleteDraftEntry(\'group\', \''+grp.id+'\')"><i class="fas fa-trash"></i></button>';
                gHtml += '</div></div>';
            });
            gList.innerHTML = gHtml;
        }
    }
    
    // Render Connections
    if(cnList) {
        if(!window.draftNetworkData.connections || window.draftNetworkData.connections.length === 0) {
            cnList.innerHTML = '<p style="color:var(--text-muted); font-size:13px; text-align:center; padding: 20px 0;">No hay conexiones explícitas.</p>';
        } else {
            var cnHtml = '';
            window.draftNetworkData.connections.forEach(function(conn) {
                var jsonStr = JSON.stringify(conn).replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                cnHtml += '<div class="pj-edit-item" style="margin-bottom: 10px;">';
                cnHtml += '<div style="display:flex; align-items:center; gap:12px; flex:1; min-width:0; font-size:13px;">';
                cnHtml += '<span style="font-weight:700; color:'+conn.color+'; background:'+conn.color+'15; border: 1px solid '+conn.color+'33; padding: 2px 8px; border-radius:6px; flex-shrink:0;">'+escapeHtml(conn.label)+'</span>';
                cnHtml += '<span style="color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><i class="fas fa-link" style="margin-right:6px; opacity:0.5;"></i>'+escapeHtml(conn.source_name||'ID:'+conn.source)+' <i class="fas fa-arrows-alt-h" style="margin:0 6px; opacity:0.5;"></i> '+escapeHtml(conn.target_name||'ID:'+conn.target)+'</span>';
                cnHtml += '</div>';
                cnHtml += '<div class="pj-edit-item-actions">';
                cnHtml += '<button class="pj-edit-btn pj-edit-btn-edit" title="Editar" onclick="editConnectionEntry(\''+conn.id+'\', \''+jsonStr+'\')"><i class="fas fa-pen"></i></button>';
                cnHtml += '<button class="pj-edit-btn pj-edit-btn-del" title="Eliminar" onclick="deleteDraftEntry(\'connection\', \''+conn.id+'\')"><i class="fas fa-trash"></i></button>';
                cnHtml += '</div></div>';
            });
            cnList.innerHTML = cnHtml;
        }
    }
}
document.addEventListener("DOMContentLoaded", renderNetworkLists);

function escapeHtml(text) {
    if(!text) return '';
    var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

function switchRelTab(tabName, el) {
    if (!el) el = event ? event.currentTarget : null;
    document.querySelectorAll('.pj-tab-content').forEach(function(e) {
        e.style.display = 'none';
    });
    document.getElementById('tab-' + tabName).style.display = 'block';
    document.querySelectorAll('.pj-modal-tab-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });
    if (el) el.classList.add('active');
}



var selectedTags = new Set();
var selectedPjId = 0;
var selectedPjName = '';
var editingEntryId = null;

document.querySelectorAll('.pj-tag').forEach(function(el) {
    el.addEventListener('click', function() {
        var tag = this.dataset.tag;
        if (selectedTags.has(tag)) {
            selectedTags.delete(tag);
            this.classList.remove('selected');
            this.style.background = 'transparent';
            this.style.color = this.dataset.color;
        } else {
            if (selectedTags.size < 3) {
                selectedTags.add(tag);
                this.classList.add('selected');
                this.style.background = this.dataset.color;
                this.style.color = '#fff';
            }
        }
        updateTagsHidden();
    });
});

function updateTagsHidden() {
    document.getElementById('rel_tags').value = JSON.stringify(Array.from(selectedTags));
}

function toggleRelNpc(el) {
    document.getElementById('rel_npc_box').style.display = el.checked ? 'block' : 'none';
    document.getElementById('rel_pj_box').style.display = el.checked ? 'none' : 'block';
}

function searchPersonaje(q) {
    var select = document.getElementById('rel_pj_id');
    var results = document.getElementById('rel_pj_results');
    results.innerHTML = '';
    if (!q || q.length < 1) return;
    var found = false;
    for (var i = 0; i < select.options.length; i++) {
        var opt = select.options[i];
        if (!opt.value) continue;
        var name = opt.getAttribute('data-name') || opt.text;
        if (name.toLowerCase().indexOf(q.toLowerCase()) !== -1) {
            var chip = document.createElement('span');
            chip.className = 'pj-tag-option selected';
            chip.style.cssText = 'color:#3b82f6;background:#3b82f622;border-color:#3b82f6;';
            chip.textContent = name;
            chip.onclick = function(n, id) { return function() { selectPersonaje(id, n); }; }(name, opt.value);
            results.appendChild(chip);
            found = true;
        }
    }
}

function selectPersonaje(id, name) {
    selectedPjId = parseInt(id);
    selectedPjName = name;
    document.getElementById('rel_pj_search').value = name;
    document.getElementById('rel_pj_results').innerHTML = '';
}

function switchPjTab(tabId, tabEl) {
    document.querySelectorAll('.pj-preview-tab').forEach(function(t){ t.classList.remove('active'); });
    document.querySelectorAll('.pj-preview-tab-content').forEach(function(c){ c.classList.remove('active'); });
    tabEl.classList.add('active');
    var target = document.getElementById('pjTab_' + tabId);
    if(target) target.classList.add('active');
}

function editDiarioEntryDraftObj(item) {
    document.getElementById('diario_desc').value = item.desc || '';
    document.getElementById('diario_link').value = item.link || '';
    document.getElementById('diario_thread_id').value = item.thread_id || '';
    document.getElementById('diario_cat').value = item.category || 'Presente';
    document.getElementById('diario_day').value = item.day || '';
    document.getElementById('diario_season').value = item.season || 0;
    document.getElementById('diario_year').value = item.year || '';

    // Show detected data box if thread_id exists
    var detectedBox = document.getElementById('diario_auto_data');
    if (item.thread_id) {
        var seasonNames = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
        var sName = seasonNames[item.season] || 'Desconocida';
        document.getElementById('diario_detected_title').textContent = item.thread_name || 'Tema #' + item.thread_id;
        document.getElementById('diario_detected_cat').textContent = item.category === 'Off_Rol' ? 'Off Rol' : (item.category || 'Presente');
        document.getElementById('diario_detected_cat').style.color = catColors[item.category] || '#6366f1';
        document.getElementById('diario_detected_date').textContent = 'Día ' + (item.day || '?') + ' de ' + sName + ', Año ' + (item.year || '?');
        var partsHtml = '';
        if (item.participants && item.participants.length > 0) {
            partsHtml = item.participants.map(function(p) { return p.name; }).join(', ');
        } else {
            partsHtml = 'Sin datos de participantes';
        }
        document.getElementById('diario_detected_parts').textContent = partsHtml;
        detectedBox.style.display = 'block';
    } else {
        detectedBox.style.display = 'none';
    }

    editingEntryId = item.id;
    document.getElementById('modal_gestionar_diario').style.display = 'none';
    document.getElementById('modal_diario').style.display = 'flex';
}

function editDiarioEntryDraft(jsonStr) {
    var item = JSON.parse(jsonStr);
    editDiarioEntryDraftObj(item);
}

function editRelacionEntryDraft(jsonStr) {
    var item = JSON.parse(jsonStr);
    document.getElementById('rel_modal_title').textContent = 'Editar Contacto';
    // Populate dropdown options first
    renderNetworkLists();
    document.getElementById('rel_img').value = item.image || '';
    document.getElementById('rel_desc').value = item.desc || '';
    
    var isNpc = item.is_npc ? true : false;
    document.getElementById('rel_is_npc').checked = isNpc;
    toggleRelNpc(document.getElementById('rel_is_npc'));
    
    if (isNpc) {
        document.getElementById('rel_npc_name').value = item.name || '';
    } else {
        selectedPjId = item.pj_id || 0;
        selectedPjName = item.name || '';
        document.getElementById('rel_pj_search').value = item.name || '';
    }
    
    selectedTags.clear();
    document.querySelectorAll('.pj-tag').forEach(function(el) {
        el.classList.remove('selected');
        el.style.background = 'transparent';
        el.style.color = el.dataset.color;
    });
    
    var tags = item.tags || [];
    if(tags.length === 0 && item.relation) tags = [item.relation];
    
    document.querySelectorAll('.pj-tag').forEach(function(el) {
        if (tags.indexOf(el.dataset.tag) !== -1) {
            selectedTags.add(el.dataset.tag);
            el.classList.add('selected');
            el.style.background = el.dataset.color;
            el.style.color = '#fff';
        }
    });
    updateTagsHidden();
    editingEntryId = item.id;
    
    document.getElementById('rel_add_conn').checked = false;
    document.getElementById('rel_conn_options').style.display = 'none';
    
    document.getElementById('modal_gestionar_relaciones').style.display = 'none';
    document.getElementById('modal_relacion').style.display = 'flex';
}

function selectConnColorRel(el) {
    document.querySelectorAll('.conn-color-swatch-rel').forEach(function(c) {
        c.style.transform = 'none';
        c.style.borderColor = 'transparent';
    });
    el.style.transform = 'scale(1.2)';
    el.style.borderColor = '#fff';
    document.getElementById('rel_conn_color').value = el.dataset.color;
}

function selectGroupColor(el) {
    document.querySelectorAll('.grp-color-swatch').forEach(function(c) {
        c.style.transform = 'none';
        c.style.borderColor = 'transparent';
    });
    el.style.transform = 'scale(1.2)';
    el.style.borderColor = '#fff';
    document.getElementById('grp_color').value = el.dataset.color;
}

function selectConnColor(el) {
    document.querySelectorAll('.conn-color-swatch').forEach(function(c) {
        c.style.transform = 'none';
        c.style.borderColor = 'transparent';
    });
    el.style.transform = 'scale(1.2)';
    el.style.borderColor = '#fff';
    document.getElementById('conn_color').value = el.dataset.color;
}

function autoDetectThread(url) {
    var detectedBox = document.getElementById('diario_auto_data');
    if (!url) {
        detectedBox.style.display = 'none';
        return;
    }
    // Show loading state
    document.getElementById('diario_detected_title').textContent = 'Detectando...';
    detectedBox.style.display = 'block';

    fetch(AJAX_BASE + '/get_thread_diary_data.php?url=' + encodeURIComponent(url))
    .then(function(r) { return r.json(); })
    .then(function(resp) {
        if (resp.ok && resp.data) {
            var d = resp.data;
            var seasonNames = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
            var sName = seasonNames[d.season] || 'Desconocida';
            document.getElementById('diario_detected_title').textContent = d.thread_name;
            document.getElementById('diario_detected_cat').textContent = d.category === 'Off_Rol' ? 'Off Rol' : d.category;
            document.getElementById('diario_detected_cat').style.color = catColors[d.category] || '#6366f1';
            document.getElementById('diario_detected_date').textContent = 'Día ' + d.day + ' de ' + sName + ', Año ' + d.year;
            var partsHtml = '';
            if (d.participants && d.participants.length > 0) {
                partsHtml = d.participants.map(function(p) { return p.name; }).join(', ');
            } else {
                partsHtml = 'Solo tú (aún sin otros participantes)';
            }
            document.getElementById('diario_detected_parts').textContent = partsHtml;
            document.getElementById('diario_thread_id').value = d.thread_id;
            document.getElementById('diario_cat').value = d.category;
            document.getElementById('diario_day').value = d.day;
            document.getElementById('diario_season').value = d.season;
            document.getElementById('diario_year').value = d.year;
            detectedBox.style.display = 'block';
        } else {
            document.getElementById('diario_detected_title').textContent = 'No se pudo detectar el hilo.';
            document.getElementById('diario_detected_cat').textContent = '';
            document.getElementById('diario_detected_date').textContent = '';
            document.getElementById('diario_detected_parts').textContent = '';
            document.getElementById('diario_thread_id').value = '';
            document.getElementById('diario_cat').value = 'Presente';
            document.getElementById('diario_day').value = '';
            document.getElementById('diario_season').value = '';
            document.getElementById('diario_year').value = '';
        }
    })
    .catch(function() {
        document.getElementById('diario_detected_title').textContent = 'Error de conexión al detectar.';
        document.getElementById('diario_thread_id').value = '';
    });
}

function openNewDiario() {
    editingEntryId = null;
    document.getElementById('diario_desc').value = '';
    document.getElementById('diario_link').value = '';
    document.getElementById('diario_auto_data').style.display = 'none';
    document.getElementById('diario_thread_id').value = '';
    document.getElementById('diario_cat').value = 'Presente';
    document.getElementById('diario_day').value = '';
    document.getElementById('diario_season').value = '';
    document.getElementById('diario_year').value = '';
    document.getElementById('modal_gestionar_diario').style.display = 'none';
    document.getElementById('modal_diario').style.display = 'flex';
}

function openNewRelacion() {
    editingEntryId = null;
    document.getElementById('rel_modal_title').textContent = 'Añadir Contacto';
    document.getElementById('rel_desc').value = '';
    document.getElementById('rel_img').value = '';
    document.getElementById('rel_is_npc').checked = false;
    toggleRelNpc(document.getElementById('rel_is_npc'));
    document.getElementById('rel_npc_name').value = '';
    document.getElementById('rel_pj_search').value = '';
    selectedPjId = 0; selectedPjName = '';
    document.getElementById('rel_tags').value = '';
    selectedTags.clear();
    document.querySelectorAll('.pj-tag').forEach(function(t) { t.classList.remove('active', 'selected'); t.style.background='transparent'; t.style.color=t.dataset.color; });
    
    document.getElementById('rel_add_conn').checked = false;
    document.getElementById('rel_conn_options').style.display = 'none';
    
    document.getElementById('modal_gestionar_relaciones').style.display = 'none';
    document.getElementById('modal_relacion').style.display = 'flex';
}

function openNewGroup() {
    editingEntryId = null;
    document.getElementById('group_modal_title').textContent = 'Crear Grupo';
    document.getElementById('grp_name').value = '';
    // Populate checkboxes first
    renderNetworkLists();
    document.querySelectorAll('input[name="grp_members[]"]').forEach(function(cb) { cb.checked = false; });
    document.getElementById('modal_gestionar_relaciones').style.display = 'none';
    document.getElementById('modal_group').style.display = 'flex';
}

function openNewConnection() {
    editingEntryId = null;
    document.getElementById('conn_modal_title').textContent = 'Añadir Conexión Explícita';
    document.getElementById('conn_label').value = '';
    // Populate selects first
    renderNetworkLists();
    document.getElementById('conn_source').value = '';
    document.getElementById('conn_target').value = '';
    document.getElementById('modal_gestionar_relaciones').style.display = 'none';
    document.getElementById('modal_connection').style.display = 'flex';
}

function editGroupEntry(id, jsonStr) {
    try {
        var grp = JSON.parse(jsonStr);
        document.getElementById('group_modal_title').textContent = 'Editar Grupo';
        document.getElementById('grp_name').value = grp.name || '';
        
        var color = grp.color || '#6366f1';
        document.getElementById('grp_color').value = color;
        document.querySelectorAll('.grp-color-swatch').forEach(function(c) {
            if (c.dataset.color === color) {
                c.style.transform = 'scale(1.2)';
                c.style.borderColor = '#fff';
            } else {
                c.style.transform = 'none';
                c.style.borderColor = 'transparent';
            }
        });
        
        // Populate checkboxes first
        renderNetworkLists();
        var members = grp.members || [];
        document.querySelectorAll('input[name="grp_members[]"]').forEach(function(cb) {
            cb.checked = members.indexOf(cb.value) !== -1;
        });
        
        editingEntryId = id;
        document.getElementById('modal_gestionar_relaciones').style.display = 'none';
        document.getElementById('modal_group').style.display = 'flex';
    } catch (e) {
        console.error("Error parsing group JSON", e);
    }
}

function editConnectionEntry(id, jsonStr) {
    try {
        var conn = JSON.parse(jsonStr);
        document.getElementById('conn_modal_title').textContent = 'Editar Conexión';
        document.getElementById('conn_label').value = conn.label || '';
        // Populate first
        renderNetworkLists();
        document.getElementById('conn_source').value = conn.source || '';
        document.getElementById('conn_target').value = conn.target || '';
        
        var color = conn.color || '#ec4899';
        document.getElementById('conn_color').value = color;
        document.querySelectorAll('.conn-color-swatch').forEach(function(c) {
            if (c.dataset.color === color) {
                c.style.transform = 'scale(1.2)';
                c.style.borderColor = '#fff';
            } else {
                c.style.transform = 'none';
                c.style.borderColor = 'transparent';
            }
        });
        
        editingEntryId = id;
        document.getElementById('modal_gestionar_relaciones').style.display = 'none';
        document.getElementById('modal_connection').style.display = 'flex';
    } catch (e) {
        console.error("Error parsing connection JSON", e);
    }
}

function deleteDraftEntry(type, id) {
    if (type === 'relacion') {
        window.draftNetworkData.relaciones = window.draftNetworkData.relaciones.filter(function(i) { return i.id !== id; });
    } else if (type === 'group') {
        window.draftNetworkData.groups = window.draftNetworkData.groups.filter(function(i) { return i.id !== id; });
    } else if (type === 'connection') {
        window.draftNetworkData.connections = window.draftNetworkData.connections.filter(function(i) { return i.id !== id; });
    } else if (type === 'diario') {
        window.draftNetworkData.diario = window.draftNetworkData.diario.filter(function(i) { return i.id !== id; });
    }
    renderNetworkLists();
}

function deleteEntry(type, id) {
    if(type === 'relacion' || type === 'group' || type === 'connection' || type === 'diario') {
        deleteDraftEntry(type, id);
        return;
    }
    if (!confirm('¿Estás seguro de eliminar esta entrada?')) return;
    fetch(AJAX_BASE + '/update_cronologia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pj_id: <?= (int)($char['id'] ?? 0) ?>, type: type, action: 'delete', entry_id: id })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) { window.location.reload(); }
        else { alert('Error: ' + (data.error ? data.error.message : 'Desconocido')); }
    })
    .catch(function() { alert('Error de conexión.'); });
}

function selectDiaryCat(el) {
    document.querySelectorAll('.pj-cat-picker').forEach(function(c){ c.classList.remove('active'); });
    el.classList.add('active');
    document.getElementById('diario_cat').value = el.dataset.cat;
}

function openEditRelacion() {
    editingEntryId = null;
    document.getElementById('modal_gestionar_relaciones').style.display = 'flex';
}

function openEditDiario() {
    editingEntryId = null;
    renderNetworkLists();
    document.getElementById('modal_gestionar_diario').style.display = 'flex';
}

<?php if ($can_edit): ?>
var AJAX_BASE = '<?= rtrim($bb, '/') ?>/game/ajax';

function saveCronologia(type) {
    var payload = { pj_id: <?= (int)($char['id'] ?? 0) ?>, type: type };
    if (type === 'diario') {
        payload.link = document.getElementById('diario_link').value;
        payload.desc = document.getElementById('diario_desc').value;
        var tid = document.getElementById('diario_thread_id').value;
        if (tid) {
            payload.thread_id = parseInt(tid);
            payload.day = parseInt(document.getElementById('diario_day').value) || 1;
            payload.season = parseInt(document.getElementById('diario_season').value) || 0;
            payload.year = parseInt(document.getElementById('diario_year').value) || 1;
            payload.category = document.getElementById('diario_cat').value;
        } else {
            payload.day = 1;
            payload.season = 0;
            payload.year = 1;
            payload.category = 'Presente';
        }
        if(!payload.desc) { alert("La descripción es obligatoria."); return; }
    } else if (type === 'relacion') {
        var is_npc = document.getElementById('rel_is_npc').checked;
        payload.is_npc = is_npc;
        if (is_npc) {
            payload.npc_name = document.getElementById('rel_npc_name').value;
            if (!payload.npc_name) { alert("El nombre del NPC es obligatorio."); return; }
        } else {
            payload.target_pj_id = selectedPjId;
            payload.target_pj_name = selectedPjName;
            if (!payload.target_pj_id) { alert("Busca y selecciona un personaje de los resultados."); return; }
        }
        payload.tags = Array.from(selectedTags);
        payload.desc = document.getElementById('rel_desc').value;
        payload.image = document.getElementById('rel_img').value;
        if (payload.tags.length === 0) { alert("Selecciona al menos una etiqueta de relación."); return; }
    } else if (type === 'group') {
        payload.name = document.getElementById('grp_name').value;
        payload.color = document.getElementById('grp_color').value;
        var members = [];
        document.querySelectorAll('input[name="grp_members[]"]:checked').forEach(function(cb) {
            members.push(cb.value);
        });
        payload.members = members;
        if (!payload.name) { alert("El nombre del grupo es obligatorio."); return; }
        if (members.length < 2) { alert("Selecciona al menos 2 miembros para el grupo."); return; }
    } else if (type === 'connection') {
        payload.source = document.getElementById('conn_source').value;
        payload.target = document.getElementById('conn_target').value;
        payload.label = document.getElementById('conn_label').value;
        payload.color = document.getElementById('conn_color').value;
        
        if (!payload.source || !payload.target) { alert("Selecciona Contacto A y Contacto B."); return; }
        if (payload.source === payload.target) { alert("El Contacto A y el Contacto B no pueden ser el mismo."); return; }
        if (!payload.label) { alert("El nombre de la conexión es obligatorio."); return; }
    }

    if (editingEntryId) { payload.entry_id = editingEntryId; }
    
    // --- BATCH SAVE LOGIC FOR ALL NETWORK & DIARIO ARRAYS ---
    if (type === 'relacion' || type === 'group' || type === 'connection' || type === 'diario') {
        var newId = payload.entry_id || ('temp_' + Math.random().toString(36).substr(2, 9));
        
        if (type === 'diario') {
            var newDiario = {
                id: newId,
                day: payload.day,
                season: payload.season,
                year: payload.year,
                category: payload.category,
                desc: payload.desc,
                link: payload.link
            };
            if (payload.thread_id) {
                newDiario.thread_id = payload.thread_id;
                var tid = payload.thread_id;
                // If we have participants from auto-detect, copy them
                var detectedTitle = document.getElementById('diario_detected_title');
                if (detectedTitle && detectedTitle.textContent && detectedTitle.textContent.indexOf('No se pudo') === -1) {
                    newDiario.thread_name = detectedTitle.textContent;
                }
                var partsEl = document.getElementById('diario_detected_parts');
                if (partsEl && partsEl.textContent) {
                    var names = partsEl.textContent.split(', ').filter(function(n) { return n && n !== 'Solo tú (aún sin otros participantes)' && n !== 'Sin datos de participantes'; });
                    if (names.length > 0) {
                        newDiario.participants = names.map(function(n) { return { name: n }; });
                    }
                }
            }
            var idx = window.draftNetworkData.diario.findIndex(function(d){ return d.id === newId; });
            if(idx > -1) window.draftNetworkData.diario[idx] = newDiario;
            else window.draftNetworkData.diario.push(newDiario);
            
        } else if (type === 'relacion') {
            var newRel = {
                id: newId,
                name: payload.is_npc ? payload.npc_name : payload.target_pj_name,
                is_npc: payload.is_npc,
                pj_id: payload.target_pj_id || 0,
                tags: payload.tags,
                desc: payload.desc,
                image: payload.image
            };
            var idx = window.draftNetworkData.relaciones.findIndex(function(r){ return r.id === newId; });
            if(idx > -1) window.draftNetworkData.relaciones[idx] = newRel;
            else window.draftNetworkData.relaciones.push(newRel);
            
            // Check if we also want to add a connection
            if (document.getElementById('rel_add_conn') && document.getElementById('rel_add_conn').checked) {
                var cTarget = document.getElementById('rel_conn_target').value;
                var cLabel = document.getElementById('rel_conn_label').value;
                var cColor = document.getElementById('rel_conn_color').value;
                if (cTarget && cLabel) {
                    var targetName = '???';
                    var tgtObj = window.draftNetworkData.relaciones.find(function(x){ return x.id === cTarget; });
                    if(tgtObj) targetName = tgtObj.name;
                    var newConn = {
                        id: 'temp_' + Math.random().toString(36).substr(2, 9),
                        source: newId,
                        target: cTarget,
                        source_name: newRel.name,
                        target_name: targetName,
                        label: cLabel,
                        color: cColor
                    };
                    window.draftNetworkData.connections.push(newConn);
                }
            }
        } else if (type === 'group') {
            var newGrp = { id: newId, name: payload.name, color: payload.color, members: payload.members };
            var idx = window.draftNetworkData.groups.findIndex(function(g){ return g.id === newId; });
            if(idx > -1) window.draftNetworkData.groups[idx] = newGrp;
            else window.draftNetworkData.groups.push(newGrp);
        } else if (type === 'connection') {
            var sName='???', tName='???';
            var sObj = window.draftNetworkData.relaciones.find(function(x){ return x.id === payload.source; });
            var tObj = window.draftNetworkData.relaciones.find(function(x){ return x.id === payload.target; });
            if(sObj) sName = sObj.name;
            if(tObj) tName = tObj.name;
            
            var newConn = {
                id: newId,
                source: payload.source,
                target: payload.target,
                source_name: sName,
                target_name: tName,
                label: payload.label,
                color: payload.color
            };
            var idx = window.draftNetworkData.connections.findIndex(function(c){ return c.id === newId; });
            if(idx > -1) window.draftNetworkData.connections[idx] = newConn;
            else window.draftNetworkData.connections.push(newConn);
        }
        
        renderNetworkLists();
        if (typeof window.reinitGameNetwork === 'function') window.reinitGameNetwork();
        
        editingEntryId = null;
        if (type === 'diario') {
            document.getElementById('diario_desc').value = '';
            document.getElementById('diario_link').value = '';
            document.getElementById('modal_diario').style.display = 'none';
            document.getElementById('modal_gestionar_diario').style.display = 'flex';
        } else if (type === 'relacion') {
            document.getElementById('rel_desc').value = '';
            document.getElementById('rel_img').value = '';
            document.getElementById('rel_is_npc').checked = false;
            toggleRelNpc(document.getElementById('rel_is_npc'));
            document.getElementById('rel_npc_name').value = '';
            document.getElementById('rel_pj_search').value = '';
            document.getElementById('rel_tags').value = '';
            document.querySelectorAll('.pj-tag').forEach(function(t) { t.classList.remove('active'); });
            document.getElementById('modal_relacion').style.display = 'none';
            document.getElementById('modal_gestionar_relaciones').style.display = 'flex';
        } else if (type === 'group') {
            document.getElementById('grp_name').value = '';
            document.querySelectorAll('input[name="grp_members[]"]').forEach(function(cb) { cb.checked = false; });
            document.getElementById('modal_group').style.display = 'none';
            document.getElementById('modal_gestionar_relaciones').style.display = 'flex';
        } else if (type === 'connection') {
            document.getElementById('conn_label').value = '';
            document.getElementById('modal_connection').style.display = 'none';
            document.getElementById('modal_gestionar_relaciones').style.display = 'flex';
        }
        return;
    }
    // ---------------------------------------------
    
    fetch(AJAX_BASE + '/update_cronologia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) { window.location.reload(); }
        else { alert('Error al guardar: ' + (data.error ? data.error.message : 'Desconocido')); }
    })
    .catch(function() { alert('Error de conexión.'); });
}

function saveBatchCronologia() {
    fetch(AJAX_BASE + '/update_cronologia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            pj_id: <?= (int)($char['id'] ?? 0) ?>,
            type: 'network_batch',
            data: window.draftNetworkData
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) { window.location.reload(); }
        else { alert('Error al guardar: ' + (data.error ? data.error.message : 'Desconocido')); }
    })
    .catch(function() { alert('Error de conexión.'); });
}
<?php endif; ?>
</script>
<?php
$content = ob_get_clean();
game_render_page('Mi Personaje', $content);

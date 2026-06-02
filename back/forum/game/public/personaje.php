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
                  <div style="display:flex; align-items:center; gap:10px;">
                      <i class="fas fa-anchor" style="color:var(--text-secondary); font-size:20px;"></i>
                      <div>
                          <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Oficio</div>
                          <div style="font-weight:700; color:var(--text-primary); font-size:14px;"><?= htmlspecialchars($char['job_name'] ?: 'Ninguno') ?></div>
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
              <div class="pj-preview-tab" onclick="switchPjTab('linaje', this)"><i class="fas fa-dna"></i> Factor Linaje</div>
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

              $catalog_path = __DIR__ . '/../data/linaje_system.json';
              $linaje_catalog = [];
              if (file_exists($catalog_path)) {
                  $linaje_catalog = json_decode(file_get_contents($catalog_path), true);
              }

              // Helper to find and enrich perk by id in the new catalog
              if (!function_exists('enrich_perk_in_php')) {
                  function enrich_perk_in_php(array $p): array {
                      if (isset($p['icon']) && isset($p['iconColor'])) return $p;
                      $icon = 'fa-dna';
                      $iconColor = '#6366f1';
                      $id = $p['id'] ?? '';
                      if (strpos($id, 'pp_') === 0) {
                          $p['icon'] = 'fa-shield-alt';
                          $p['iconColor'] = '#10b981';
                          return $p;
                      }
                      if (strpos($id, 'ps_') === 0) {
                          $p['icon'] = 'fa-crown';
                          $p['iconColor'] = '#f59e0b';
                          return $p;
                      }
                      if (strpos($id, 'g_linaje_fuego') === 0) { $icon = 'fa-fire'; $iconColor = '#ef4444'; }
                      elseif (strpos($id, 'g_linaje_rayo') === 0) { $icon = 'fa-bolt'; $iconColor = '#eab308'; }
                      elseif (strpos($id, 'g_linaje_hielo') === 0) { $icon = 'fa-snowflake'; $iconColor = '#06b6d4'; }
                      elseif (strpos($id, 'g_linaje_viento') === 0) { $icon = 'fa-wind'; $iconColor = '#a855f7'; }
                      elseif (strpos($id, 'g_linaje_tierra') === 0) { $icon = 'fa-mountain'; $iconColor = '#b45309'; }
                      elseif (strpos($id, 'g_linaje_agua') === 0) { $icon = 'fa-water'; $iconColor = '#3b82f6'; }
                      elseif (strpos($id, 'g_piel_acero') === 0) { $icon = 'fa-shield-alt'; $iconColor = '#6b7280'; }
                      elseif (strpos($id, 'g_vitalidad') === 0) { $icon = 'fa-heartbeat'; $iconColor = '#ef4444'; }
                      elseif (strpos($id, 'g_energia') === 0) { $icon = 'fa-bolt'; $iconColor = '#3b82f6'; }
                      elseif (strpos($id, 'g_constitucion') === 0) { $icon = 'fa-dumbbell'; $iconColor = '#f43f5e'; }
                      elseif (strpos($id, 'g_metabolismo') === 0) { $icon = 'fa-utensils'; $iconColor = '#10b981'; }
                      elseif (strpos($id, 'g_resistencia') === 0) { $icon = 'fa-hand-rock'; $iconColor = '#f59e0b'; }
                      elseif (strpos($id, 'g_regeneracion') === 0) { $icon = 'fa-leaf'; $iconColor = '#10b981'; }
                      elseif (strpos($id, 'g_mente') === 0 || strpos($id, 'g_intelecto') === 0 || strpos($id, 'g_lucidez') === 0 || strpos($id, 'g_concentracion') === 0) { $icon = 'fa-brain'; $iconColor = '#3b82f6'; }
                      elseif (strpos($id, 'g_voluntad_ferrea') === 0) { $icon = 'fa-fingerprint'; $iconColor = '#6366f1'; }
                      elseif (strpos($id, 'g_instinto') === 0) { $icon = 'fa-compass'; $iconColor = '#8b5cf6'; }
                      elseif (strpos($id, 'g_paso') === 0 || strpos($id, 'g_sombra') === 0) { $icon = 'fa-user-ninja'; $iconColor = '#475569'; }
                      elseif (strpos($id, 'g_agilidad') === 0) { $icon = 'fa-running'; $iconColor = '#10b981'; }
                      elseif (strpos($id, 'g_evasion') === 0) { $icon = 'fa-wind'; $iconColor = '#06b6d4'; }
                      elseif (strpos($id, 'g_parkour') === 0) { $icon = 'fa-shoe-prints'; $iconColor = '#f59e0b'; }
                      elseif (strpos($id, 'g_haki_obs') === 0) { $icon = 'fa-eye'; $iconColor = '#6366f1'; }
                      elseif (strpos($id, 'g_haki_arm') === 0) { $icon = 'fa-shield-alt'; $iconColor = '#6b7280'; }
                      elseif (strpos($id, 'g_haki_conq') === 0) { $icon = 'fa-crown'; $iconColor = '#db2777'; }
                      elseif (strpos($id, 'g_suerte') === 0 || strpos($id, 'g_golpe') === 0 || strpos($id, 'g_fortuna') === 0) { $icon = 'fa-dice-d20'; $iconColor = '#f59e0b'; }
                      elseif (strpos($id, 'g_carisma') === 0 || strpos($id, 'g_presencia') === 0 || strpos($id, 'g_inspiracion') === 0 || strpos($id, 'g_nombre_temido') === 0 || strpos($id, 'g_voz_rey') === 0) { $icon = 'fa-comments'; $iconColor = '#ec4899'; }
                      elseif (strpos($id, 'g_manos_') === 0 || strpos($id, 'g_dedos_') === 0 || strpos($id, 'g_ojo_') === 0 || strpos($id, 'g_genio_') === 0 || strpos($id, 'g_cocinero_') === 0) { $icon = 'fa-tools'; $iconColor = '#06b6d4'; }
                      elseif (strpos($id, 'g_cuatro_brazos') === 0) { $icon = 'fa-hand-paper'; $iconColor = '#3b82f6'; }
                      elseif (strpos($id, 'g_tercer_ojo') === 0) { $icon = 'fa-eye'; $iconColor = '#a855f7'; }
                      elseif (strpos($id, 'g_sangre_fria') === 0) { $icon = 'fa-snowflake'; $iconColor = '#06b6d4'; }
                      elseif (strpos($id, 'g_linaje_marino') === 0) { $icon = 'fa-anchor'; $iconColor = '#3b82f6'; }
                      elseif (strpos($id, 'g_gula') === 0) { $icon = 'fa-cookie-bite'; $iconColor = '#b45309'; }
                      elseif (strpos($id, 'g_pelo') === 0) { $icon = 'fa-magic'; $iconColor = '#db2777'; }
                      elseif (strpos($id, 'g_piel_color') === 0) { $icon = 'fa-palette'; $iconColor = '#10b981'; }
                      elseif (strpos($id, 'g_no_dormir') === 0) { $icon = 'fa-eye-slash'; $iconColor = '#64748b'; }
                      elseif (strpos($id, 'g_sangre_de_gigante') === 0) { $icon = 'fa-expand-arrows-alt'; $iconColor = '#ef4444'; }
                      elseif (strpos($id, 'g_cuerpo_elastico') === 0) { $icon = 'fa-dumbbell'; $iconColor = '#10b981'; }
                      elseif (strpos($id, 'rh_') === 0) { $icon = 'fa-user'; $iconColor = '#6366f1'; }
                      elseif (strpos($id, 'rm_') === 0) { $icon = 'fa-paw'; $iconColor = '#10b981'; }
                      elseif (strpos($id, 'rg_') === 0) { $icon = 'fa-fish'; $iconColor = '#06b6d4'; }
                      elseif (strpos($id, 'rgi_') === 0) { $icon = 'fa-expand-arrows-alt'; $iconColor = '#ef4444'; }
                      elseif (strpos($id, 'rt_') === 0) { $icon = 'fa-seedling'; $iconColor = '#10b981'; }
                      elseif (strpos($id, 'rb_') === 0) { $icon = 'fa-anchor'; $iconColor = '#f59e0b'; }
                      elseif (strpos($id, 'rl_') === 0) { $icon = 'fa-feather-alt'; $iconColor = '#ec4899'; }
                      elseif (strpos($id, 'rs_') === 0) { $icon = 'fa-cloud'; $iconColor = '#06b6d4'; }
                      elseif (strpos($id, 'ro_') === 0) { $icon = 'fa-ghost'; $iconColor = '#ef4444'; }
                      elseif (strpos($id, 'rsi_') === 0) { $icon = 'fa-tint'; $iconColor = '#3b82f6'; }
                      $p['icon'] = $icon;
                      $p['iconColor'] = $iconColor;
                      return $p;
                  }
              }

              if (!function_exists('find_perk_in_new_catalog')) {
                  function find_perk_in_new_catalog(string $id, array $catalog): ?array {
                      if (isset($catalog['arbol_general'])) {
                          foreach ($catalog['arbol_general'] as $cat) {
                              if (isset($cat['perks']) && is_array($cat['perks'])) {
                                  foreach ($cat['perks'] as $p) {
                                      if (($p['id'] ?? '') === $id) return enrich_perk_in_php($p);
                                  }
                              }
                          }
                      }
                      if (isset($catalog['arboles_raciales'])) {
                          foreach ($catalog['arboles_raciales'] as $race => $tree) {
                              if (isset($tree['perks']) && is_array($tree['perks'])) {
                                  foreach ($tree['perks'] as $p) {
                                      if (($p['id'] ?? '') === $id) return enrich_perk_in_php($p);
                                  }
                              }
                          }
                      }
                      if (isset($catalog['pasivas_primarias'])) {
                          foreach ($catalog['pasivas_primarias'] as $race => $list) {
                              if (is_array($list)) {
                                  foreach ($list as $p) {
                                      if (($p['id'] ?? '') === $id) {
                                          $p['type'] = 'primaria';
                                          return enrich_perk_in_php($p);
                                      }
                                  }
                              }
                          }
                      }
                      if (isset($catalog['pasivas_secundarias'])) {
                          foreach ($catalog['pasivas_secundarias'] as $race => $list) {
                              if (is_array($list)) {
                                  foreach ($list as $p) {
                                      if (($p['id'] ?? '') === $id) {
                                          $p['type'] = 'secundaria';
                                          return enrich_perk_in_php($p);
                                      }
                                  }
                              }
                          }
                      }
                      return null;
                  }
              }

              if (!function_exists('render_perk_card')) {
                  function render_perk_card(array $p, string $type_class, string $icon_bg, string $badge_label, string $badge_color): string {
                      $cost_html = '';
                      if (isset($p['cost']) && $p['cost'] > 0) {
                          $cost_html = '<div style="position: absolute; top: 12px; right: 80px; font-family: var(--font-heading); font-size: 10px; font-weight: 800; background: rgba(99, 102, 241, 0.1); color: var(--accent-indigo); padding: 2px 6px; border-radius: 4px;">' . (int)$p['cost'] . ' PTS</div>';
                      }
                      return '<div class="gene-card ' . $type_class . '" style="position: relative;">' .
                          $cost_html .
                          '<div class="gene-card-icon" style="' . $icon_bg . '">' .
                              '<i class="fas ' . htmlspecialchars($p['icon'] ?? 'fa-dna') . '" style="color:' . htmlspecialchars($p['iconColor'] ?? '#6366f1') . ';"></i>' .
                          '</div>' .
                          '<div class="gene-card-info">' .
                              '<div class="gene-card-name">' . htmlspecialchars($p['name'] ?? '') . '</div>' .
                              '<div class="gene-card-desc">' . htmlspecialchars($p['desc'] ?? '') . '</div>' .
                          '</div>' .
                          '<div class="gene-card-badge" style="background:' . $badge_color . '22; color:' . $badge_color . ';">' . $badge_label . '</div>' .
                      '</div>';
                  }
              }
              ?>

              <?php if ($has_perks_v2): ?>

                  <?php
                  $displayed_pasivas = [];
                  foreach ($pasiva_ids as $pid) {
                      $found = find_perk_in_new_catalog($pid, $linaje_catalog);
                      if ($found) $displayed_pasivas[] = $found;
                  }

                  if (empty($displayed_pasivas)) {
                      $char_race = $char['race_name'] ?? '';
                      $races = [];
                      if (strpos($char_race, 'Híbrido') === 0 || strpos($char_race, 'Hibrido') === 0) {
                          if (preg_match('/Híbrid[o|a]\s*\(([^\\/]+)\s*\\/\s*([^)]+)\)/i', $char_race, $matches)) {
                              $races[] = trim($matches[1]);
                              $races[] = trim($matches[2]);
                          }
                      } else {
                          $races[] = $char_race;
                      }
                      
                      foreach ($races as $r) {
                          $prim = $linaje_catalog['pasivas_primarias'][$r] ?? [];
                          foreach ($prim as $p) {
                              $p['type'] = 'primaria';
                              $displayed_pasivas[] = enrich_perk_in_php($p);
                          }
                          if (count($races) === 1) {
                              $sec = $linaje_catalog['pasivas_secundarias'][$r] ?? [];
                              foreach ($sec as $p) {
                                  $p['type'] = 'secundaria';
                                  $displayed_pasivas[] = enrich_perk_in_php($p);
                              }
                          }
                      }
                  }

                  $racial_display = [];
                  foreach ($racial_ids as $pid) {
                      $found = find_perk_in_new_catalog($pid, $linaje_catalog);
                      if ($found) $racial_display[] = $found;
                  }

                  $general_display = [];
                  foreach ($general_ids as $pid) {
                      $found = find_perk_in_new_catalog($pid, $linaje_catalog);
                      if ($found) $general_display[] = $found;
                  }

                  $char_race = $char['race_name'] ?? '';
                  $max_points = 4;
                  if (strpos($char_race, 'Híbrido') === 0 || strpos($char_race, 'Hibrido') === 0) {
                      if (preg_match('/Híbrid[o|a]\s*\(([^\\/]+)\s*\\/\s*([^)]+)\)/i', $char_race, $matches)) {
                          $race_dom = trim($matches[1]);
                          $pts_dom = $linaje_catalog['puntos_linaje_por_raza'][$race_dom] ?? 20;
                          $max_points = $pts_dom - 4;
                      }
                  } else {
                      $max_points = $linaje_catalog['puntos_linaje_por_raza'][$char_race] ?? 4;
                  }

                  $spent_points = 0;
                  foreach ($racial_display as $p) {
                      $spent_points += ($p['cost'] ?? 1);
                  }
                  foreach ($general_display as $p) {
                      $spent_points += ($p['cost'] ?? 1);
                  }
                  $sobrante = $max_points - $spent_points;
                  $bonus_pp = $sobrante * 3;
                  ?>

                  <div class="linaje-slots-bar" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 14px 20px; margin-bottom: 20px; background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(168,85,247,0.03)); border-radius: var(--radius-lg); border: 1px solid rgba(99,102,241,0.2);">
                      <div class="linaje-slots-group" style="display: flex; align-items: center; gap: 12px;">
                          <span class="linaje-slots-label" style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);"><i class="fas fa-gem" style="color:var(--accent-indigo);"></i> Puntos de Linaje:</span>
                          <?php if ($max_points <= 10): ?>
                              <div class="linaje-slots-dots" style="display: flex; gap: 6px;">
                                  <?php for ($i = 0; $i < $max_points; $i++): ?>
                                      <div class="linaje-slot-dot <?= ($i < $spent_points) ? 'filled' : '' ?>" style="width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--border-color); background: <?= ($i < $spent_points) ? 'var(--accent-indigo)' : 'var(--bg-main)' ?>; <?= ($i < $spent_points) ? 'box-shadow: 0 0 8px rgba(99,102,241,0.5);' : '' ?>"></div>
                                  <?php endfor; ?>
                              </div>
                          <?php endif; ?>
                          <span class="linaje-slots-count" style="font-family: var(--font-heading); font-weight: 900; font-size: 22px; color: var(--accent-purple);"><?= $spent_points ?>/<?= $max_points ?></span>
                      </div>
                      <div id="linajeSobranteBonus" style="font-size: 12px; font-weight: 700; color: #10b981; text-transform: uppercase; letter-spacing: 0.5px;">
                          Puntos Sobrantes: <?= $sobrante ?> PL = <?= $bonus_pp ?> PP de Bonus
                      </div>
                  </div>

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
          <?php
          $catalog_cards = [];
          if ($char) {
              $cat_q = $db->query("
                  SELECT id, name, card_type, rank 
                  FROM {$prefix}game_cards 
                  WHERE id NOT IN (
                      SELECT card_id FROM {$prefix}game_character_cards WHERE character_id = {$char['id']}
                  )
                  ORDER BY name ASC
              ");
              while ($c = $db->fetch_array($cat_q)) {
                  $catalog_cards[] = $c;
              }
          }
          $pp_available = 0;
          if ($char) {
              $data_decoded = !empty($row['data_json']) ? json_decode($row['data_json'], true) : [];
              if (isset($data_decoded['pp'])) {
                  $pp_available = (int)$data_decoded['pp'];
              } elseif (isset($char['linaje']['bonusPP'])) {
                  $pp_available = (int)$char['linaje']['bonusPP'];
              }
          }
          ?>
          <div id="pjTab_gestion" class="pj-preview-tab-content">
              <style>
                  .rpg-pp-display { background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(168,85,247,0.06)); border: 1px solid rgba(99,102,241,0.2); border-radius: 10px; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
                  .rpg-pp-display h3 { margin: 0; font-size: 14px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
                  .rpg-pp-val { font-size: 24px; font-weight: 900; color: var(--accent-indigo); text-shadow: 0 0 10px rgba(99,102,241,0.3); font-family: var(--font-heading); }
                  
                  .rpg-attr-buy-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
                  .rpg-attr-buy-card { background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 10px; padding: 15px 18px; display: flex; flex-direction: column; gap: 12px; transition: border-color 0.2s; position: relative; }
                  .rpg-attr-buy-card:hover { border-color: rgba(99,102,241,0.3); }
                  .rpg-attr-buy-header { display: flex; align-items: center; gap: 10px; }
                  .rpg-attr-buy-icon { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
                  .rpg-attr-buy-name { font-weight: 800; font-size: 12px; color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.5px; font-family: var(--font-heading); }
                  .rpg-attr-buy-value { font-size: 15px; font-weight: 900; color: var(--text-primary); margin-left: auto; }
                  .rpg-attr-buy-actions { display: flex; align-items: center; justify-content: space-between; gap: 10px; border-top: 1px solid var(--border-color); padding-top: 10px; }
                  .rpg-attr-buy-cost { font-size: 11px; color: var(--text-muted); font-weight: 700; }
                  .rpg-attr-buy-cost span { color: var(--accent-indigo); }
                  .rpg-attr-buy-btn { background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple)); border: none; border-radius: 6px; color: #fff; padding: 8px 15px; font-weight: 800; font-size: 11px; text-transform: uppercase; cursor: pointer; transition: opacity 0.2s; display: inline-flex; align-items: center; gap: 6px; }
                  .rpg-attr-buy-btn:hover { opacity: 0.9; }

                  .rpg-chat-container { display: flex; flex-direction: column; height: 350px; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; }
                  .rpg-chat-messages { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 10px; }
                  .rpg-chat-bubble { padding: 10px 14px; border-radius: 8px; max-width: 85%; font-size: 13px; line-height: 1.5; word-break: break-word; position: relative; }
                  .rpg-chat-bubble.player { background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.15); align-self: flex-end; color: var(--text-primary); }
                  .rpg-chat-bubble.staff { background: rgba(168,85,247,0.08); border: 1px solid rgba(168,85,247,0.15); align-self: flex-start; color: var(--text-primary); }
                  .rpg-chat-bubble-meta { font-size: 9px; color: var(--text-muted); margin-bottom: 4px; display: flex; justify-content: space-between; font-weight: 700; }
                  .rpg-chat-input-bar { display: flex; border-top: 1px solid var(--border-color); background: var(--bg-surface); }
                  .rpg-chat-input { flex: 1; border: none; background: transparent; color: var(--text-primary); padding: 12px 15px; font-size: 13px; outline: none; }
                  .rpg-chat-send { background: var(--accent-indigo); color: #fff; border: none; padding: 0 20px; font-weight: 800; font-size: 13px; cursor: pointer; }

                  .rpg-req-split { display: flex; gap: 20px; min-height: 480px; }
                  .rpg-req-list { width: 260px; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; overflow-y: auto; max-height: 480px; flex-shrink: 0; }
                  .rpg-req-item { padding: 12px 15px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background 0.2s; }
                  .rpg-req-item:hover { background: rgba(255,255,255,0.02); }
                  .rpg-req-item.active { background: rgba(99,102,241,0.08); border-left: 3px solid var(--accent-indigo); }
                  .rpg-req-detail { flex: 1; display: flex; flex-direction: column; gap: 15px; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; }

                  .rpg-card-preview-mini { width: 220px; background: var(--bg-card); border: 2px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: var(--shadow-card); font-size: 12px; flex-shrink: 0; }

                  /* Premium Dashboard Grid & Card Styles */
                  .rpg-gestion-panel { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 25px; }
                  .rpg-gestion-dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-top: 15px; }
                  .rpg-gestion-card {
                      background: var(--bg-main);
                      border: 1px solid var(--border-color);
                      border-radius: 12px;
                      padding: 24px;
                      display: flex;
                      flex-direction: column;
                      gap: 15px;
                      cursor: pointer;
                      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                      position: relative;
                      overflow: hidden;
                      box-shadow: var(--shadow-card);
                      text-decoration: none !important;
                  }
                  .rpg-gestion-card::before {
                      content: '';
                      position: absolute;
                      top: 0; left: 0; width: 100%; height: 100%;
                      background: linear-gradient(135deg, rgba(99,102,241,0.03), rgba(168,85,247,0.03));
                      opacity: 0;
                      transition: opacity 0.3s;
                  }
                  .rpg-gestion-card:hover {
                      transform: translateY(-4px);
                      border-color: var(--accent-indigo);
                      box-shadow: 0 8px 25px rgba(99,102,241,0.12);
                  }
                  .rpg-gestion-card:hover::before { opacity: 1; }
                  .rpg-gestion-card-icon {
                      width: 46px;
                      height: 46px;
                      border-radius: 12px;
                      display: flex;
                      align-items: center;
                      justify-content: center;
                      font-size: 18px;
                      color: #fff;
                      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                      transition: transform 0.3s;
                  }
                  .rpg-gestion-card:hover .rpg-gestion-card-icon { transform: scale(1.1); }
                  .rpg-gestion-card-body { display: flex; flex-direction: column; gap: 6px; }
                  .rpg-gestion-card-body h3 { margin: 0; font-size: 15px; font-weight: 800; color: var(--text-primary); font-family: var(--font-heading); letter-spacing: 0.5px; }
                  .rpg-gestion-card-body p { margin: 0; font-size: 12px; color: var(--text-muted); line-height: 1.5; }
                  .rpg-gestion-card-footer {
                      margin-top: auto;
                      display: flex;
                      justify-content: space-between;
                      align-items: center;
                      font-size: 10px;
                      font-weight: 800;
                      text-transform: uppercase;
                      letter-spacing: 0.5px;
                      border-top: 1px solid var(--border-color);
                      padding-top: 12px;
                  }
                  .rpg-gestion-card-tag { color: var(--accent-indigo); }
                  .rpg-gestion-card-badge { background: var(--accent-rose); color: #fff; padding: 2px 8px; border-radius: 20px; font-size: 9px; font-weight: 800; }

                  /* Back Button Styles */
                  .rpg-back-btn {
                      background: rgba(255, 255, 255, 0.02);
                      border: 1px solid var(--border-color);
                      color: var(--text-secondary);
                      padding: 8px 16px;
                      border-radius: 8px;
                      font-family: var(--font-heading);
                      font-weight: 800;
                      font-size: 11px;
                      text-transform: uppercase;
                      letter-spacing: 1px;
                      cursor: pointer;
                      display: inline-flex;
                      align-items: center;
                      gap: 8px;
                      transition: all 0.2s;
                      margin-bottom: 20px;
                  }
                  .rpg-back-btn:hover {
                      background: rgba(99, 102, 241, 0.06);
                      border-color: var(--accent-indigo);
                      color: var(--text-primary);
                  }
              </style>

              <div class="rpg-gestion-panel">
                  <!-- DASHBOARD LANDING VIEW -->
                  <div id="gestion_dashboard" style="display:block;">
                      <div class="rpg-pp-display">
                          <div>
                              <h3>Panel de Gestión del Personaje</h3>
                              <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">Utiliza tus Puntos de Progresión (PP) o solicita nuevas cartas y adiciones al catálogo.</div>
                          </div>
                          <div class="rpg-pp-val"><i class="fas fa-gem"></i> <span id="val_available_pp"><?= $pp_available ?></span> PP</div>
                      </div>

                      <div class="rpg-gestion-dashboard-grid">
                          <!-- CARD 1: ATRIBUTOS -->
                          <div class="rpg-gestion-card" onclick="switchGestionSubtab('atributos')">
                              <div class="rpg-gestion-card-icon" style="background: linear-gradient(135deg, var(--accent-indigo), var(--accent-blue));">
                                  <i class="fas fa-chart-line"></i>
                              </div>
                              <div class="rpg-gestion-card-body">
                                  <h3>Comprar Atributos</h3>
                                  <p>Mejora tus estadísticas base (Fuerza, Agilidad, Espíritu, etc.) canjeando tus PP acumulados.</p>
                              </div>
                              <div class="rpg-gestion-card-footer">
                                  <span class="rpg-gestion-card-tag">5 PP / Punto</span>
                                  <i class="fas fa-chevron-right" style="color: var(--text-muted);"></i>
                              </div>
                          </div>

                          <!-- CARD 2: CREACIÓN DE CARTA -->
                          <div class="rpg-gestion-card" onclick="switchGestionSubtab('crear_carta')">
                              <div class="rpg-gestion-card-icon" style="background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));">
                                  <i class="fas fa-wand-magic-sparkles"></i>
                              </div>
                              <div class="rpg-gestion-card-body">
                                  <h3>Proponer Carta</h3>
                                  <p>Envía una propuesta de carta personalizada (técnica, equipo, etc.) para moderar y equilibrar junto al staff.</p>
                              </div>
                              <div class="rpg-gestion-card-footer">
                                  <span class="rpg-gestion-card-tag">Bajo revisión</span>
                                  <i class="fas fa-chevron-right" style="color: var(--text-muted);"></i>
                              </div>
                          </div>

                          <!-- CARD 3: CARTA CATÁLOGO -->
                          <div class="rpg-gestion-card" onclick="switchGestionSubtab('solicitar_catalogo')">
                              <div class="rpg-gestion-card-icon" style="background: linear-gradient(135deg, var(--accent-blue), var(--accent-teal));">
                                  <i class="fas fa-clone"></i>
                              </div>
                              <div class="rpg-gestion-card-body">
                                  <h3>Carta de Catálogo</h3>
                                  <p>Solicita que se te añada una carta oficial existente en el catálogo del foro (misiones, eventos, etc.).</p>
                              </div>
                              <div class="rpg-gestion-card-footer">
                                  <span class="rpg-gestion-card-tag">Catálogo oficial</span>
                                  <i class="fas fa-chevron-right" style="color: var(--text-muted);"></i>
                              </div>
                          </div>

                          <!-- CARD 4: HISTORIAL Y CONVERSACIONES -->
                          <div class="rpg-gestion-card" onclick="switchGestionSubtab('historial')">
                              <div class="rpg-gestion-card-icon" style="background: linear-gradient(135deg, var(--accent-rose), var(--accent-orange));">
                                  <i class="fas fa-clipboard-list"></i>
                              </div>
                              <div class="rpg-gestion-card-body">
                                  <h3>Mis Solicitudes</h3>
                                  <p>Revisa tus solicitudes activas, responde en el chat de discusión y confirma tu conformidad.</p>
                              </div>
                              <div class="rpg-gestion-card-footer">
                                  <span class="rpg-gestion-card-tag">Mensajes e historial</span>
                                  <span id="dashboard-requests-badge" class="rpg-gestion-card-badge" style="display:none;">0 activa(s)</span>
                              </div>
                          </div>
                      </div>
                  </div>

                  <!-- SUBTAB: ATRIBUTOS -->
                  <div id="gestion_subtab_atributos" class="gestion-subtab-content" style="display:none;">
                      <button class="rpg-back-btn" onclick="showGestionDashboard()">
                          <i class="fas fa-arrow-left"></i> Volver a Gestión
                      </button>

                      <div class="rpg-pp-display">
                          <div>
                              <h3>Puntos de Progresión disponibles</h3>
                              <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">Consigue PP participando en el foro para canjear por mejoras de atributos.</div>
                          </div>
                          <div class="rpg-pp-val"><i class="fas fa-gem"></i> <span><?= $pp_available ?></span> PP</div>
                      </div>

                      <?php if ($char['status'] !== 'aprobada'): ?>
                          <div style="padding:40px; text-align:center; color:var(--text-muted); background:var(--bg-main); border:1px solid var(--border-color); border-radius:8px;">
                              <i class="fas fa-lock" style="font-size:28px; color:var(--accent-amber); margin-bottom:12px; display:block;"></i>
                              Tu personaje debe estar **Aprobado** por el staff para poder comprar puntos de atributos.
                          </div>
                      <?php else: ?>
                          <div class="rpg-attr-buy-grid">
                              <?php
                              $stats_labels = [
                                  'fue' => ['Fuerza', 'fa-dumbbell', 'linear-gradient(135deg, rgba(99,102,241,0.15), rgba(99,102,241,0.05))', '#6366f1'],
                                  'agi' => ['Agilidad', 'fa-running', 'linear-gradient(135deg, rgba(16,185,129,0.15), rgba(16,185,129,0.05))', '#10b981'],
                                  'des' => ['Destreza', 'fa-crosshairs', 'linear-gradient(135deg, rgba(59,130,246,0.15), rgba(59,130,246,0.05))', '#3b82f6'],
                                  'inst' => ['Instinto', 'fa-compass', 'linear-gradient(135deg, rgba(6,182,212,0.15), rgba(6,182,212,0.05))', '#06b6d4'],
                                  'esp' => ['Espíritu', 'fa-fire', 'linear-gradient(135deg, rgba(236,72,153,0.15), rgba(236,72,153,0.05))', '#ec4899'],
                                  'int' => ['Intelecto', 'fa-brain', 'linear-gradient(135deg, rgba(245,158,11,0.15), rgba(245,158,11,0.05))', '#f59e0b'],
                              ];
                              foreach ($stats_labels as $key => $lbl):
                                  $curr_val = $char['stats'][$key];
                              ?>
                                  <div class="rpg-attr-buy-card">
                                      <div class="rpg-attr-buy-header">
                                          <div class="rpg-attr-buy-icon" style="background: <?= $lbl[2] ?>; color: <?= $lbl[3] ?>;">
                                              <i class="fas <?= $lbl[1] ?>"></i>
                                          </div>
                                          <div class="rpg-attr-buy-name"><?= $lbl[0] ?></div>
                                          <div class="rpg-attr-buy-value" id="val_stat_<?= $key ?>"><?= $curr_val ?></div>
                                      </div>
                                      <div class="rpg-attr-buy-actions">
                                          <div class="rpg-attr-buy-cost">Precio: <span>5 PP</span></div>
                                          <button class="rpg-attr-buy-btn" onclick="buyStatPoint('<?= $key ?>')">
                                              <i class="fas fa-plus-circle"></i> Comprar +1
                                          </button>
                                      </div>
                                  </div>
                              <?php endforeach; ?>
                          </div>
                      <?php endif; ?>
                  </div>

                  <!-- SUBTAB: CREACIÓN DE CARTA -->
                  <div id="gestion_subtab_crear_carta" class="gestion-subtab-content" style="display:none;">
                      <button class="rpg-back-btn" onclick="showGestionDashboard()">
                          <i class="fas fa-arrow-left"></i> Volver a Gestión
                      </button>

                      <div style="max-width:700px; margin:0 auto; background:var(--bg-main); border:1px solid var(--border-color); border-radius:12px; padding:30px; display:flex; flex-direction:column; gap:20px; box-shadow:var(--shadow-card);">
                          <h3 style="margin:0; font-size:16px; color:var(--text-primary); border-bottom:1px solid var(--border-color); padding-bottom:12px; display:flex; align-items:center; gap:10px; font-family:var(--font-heading); font-weight:800;">
                              <i class="fas fa-wand-magic-sparkles" style="color:var(--accent-purple); font-size:18px;"></i> Proponer Nueva Carta Personalizada
                          </h3>
                          <p style="font-size:12px; color:var(--text-muted); margin:0; line-height:1.6;">
                              Propón una técnica, equipo, Akuma no Mi o NPC menor adaptado a tu personaje. Rellena las especificaciones técnicas para agilizar la revisión del Staff. Tras enviarla, podrás conversar con los moderadores para ajustar sus efectos.
                          </p>

                          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                              <!-- FILA 1: Nombre + Tipo -->
                              <div>
                                  <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Nombre de la Carta</label>
                                  <input type="text" id="req_new_name" class="textbox" placeholder="Ej: Puñetazo Explosivo" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                              </div>
                              <div>
                                  <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Tipo de Carta</label>
                                  <select id="req_new_type" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                      <option value="tecnica">Técnica</option>
                                      <option value="equipo">Equipo</option>
                                      <option value="akuma_no_mi">Akuma no Mi</option>
                                      <option value="haki">Haki</option>
                                      <option value="npc_menor">NPC Menor</option>
                                  </select>
                              </div>

                              <!-- FILA 2: Activación + Rango -->
                              <div>
                                  <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Activación</label>
                                  <select id="req_new_activation" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                      <option value="activa">Activa</option>
                                      <option value="pasiva">Pasiva</option>
                                      <option value="reactiva">Reactiva</option>
                                  </select>
                              </div>
                              <div>
                                  <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Rango de la Carta</label>
                                  <select id="req_new_rank" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                      <option value="C">C (Común)</option>
                                      <option value="B">B (Poco común)</option>
                                      <option value="A">A (Raro)</option>
                                      <option value="S">S (Épico)</option>
                                      <option value="SS">SS (Legendario)</option>
                                  </select>
                              </div>

                              <!-- FILA 3: Tags (ancho completo) -->
                              <div style="grid-column: 1 / -1;">
                                  <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Tags / Etiquetas</label>
                                  <div id="req_new_tag-selector">
                                      <div id="req_new_tag-selected" style="display: flex; flex-wrap: wrap; gap: 4px; min-height: 28px; padding: 4px 0;"></div>
                                      <div id="req_new_tag-dropdown" style="display: none; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-surface); max-height: 250px; overflow-y: auto; margin-top: 8px;"></div>
                                      <button type="button" id="req_new_tag-toggle-btn" class="rpg-action-btn rpg-btn-secondary" style="margin-top: 6px; padding: 6px 12px; font-size: 13px;">Seleccionar Tags</button>
                                      <input type="hidden" id="req_new_tags" value="">
                                  </div>
                              </div>

                              <!-- FILA 4: Descripción (ancho completo) -->
                              <div style="grid-column: 1 / -1;">
                                  <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Descripción y Efecto Propuesto</label>
                                  <textarea id="req_new_desc" class="textbox" rows="4" placeholder="Describe el efecto de la carta..." style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); resize:vertical;"></textarea>
                              </div>

                              <!-- FILA 5: Coste PE + Ejecución -->
                              <div>
                                  <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Coste PE</label>
                                  <input type="text" id="req_new_cost" class="textbox" placeholder="Ej: 3 PE o —" value="—" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                              </div>
                              <div>
                                  <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Atributo de Ejecución</label>
                                  <select id="req_new_stat" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                      <option value="">—</option>
                                      <option value="FUE">FUE (Fuerza)</option>
                                      <option value="AGI">AGI (Agilidad)</option>
                                      <option value="DES">DES (Destreza)</option>
                                      <option value="INST">INST (Instinto)</option>
                                      <option value="ESP">ESP (Espíritu)</option>
                                      <option value="INT">INT (Inteligencia)</option>
                                  </select>
                              </div>

                              <!-- FILA 6: Dados (ancho completo) -->
                              <div style="grid-column: 1 / -1; border-top: 1px solid var(--border-color); padding-top: 12px;">
                                  <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Dados / Fórmula de daño</label>
                                  <div id="req_new_dice-builder">
                                      <div id="req_new_dice-groups" style="margin-bottom: 8px;"></div>
                                      <div style="display: flex; gap: 8px; margin-top: 4px;">
                                          <button type="button" id="req_new_dice-add-group" class="rpg-action-btn rpg-btn-secondary" style="padding: 4px 10px; font-size: 12px;">+ Añadir dados</button>
                                          <button type="button" id="req_new_dice-add-arma" class="rpg-action-btn rpg-btn-secondary" style="padding: 4px 10px; font-size: 12px;">+ [ARMA]</button>
                                          <button type="button" id="req_new_dice-add-municion" class="rpg-action-btn rpg-btn-secondary" style="padding: 4px 10px; font-size: 12px;">+ [MUNICION]</button>
                                      </div>

                                      <div style="display: flex; gap: 12px; margin-top: 8px; flex-wrap: wrap;">
                                          <div>
                                              <label style="font-size: 10px; color: var(--text-muted); display: block; margin-bottom: 2px;">Bonus fijo</label>
                                              <input type="number" id="req_new_dice-fixed" min="0" value="0" class="textbox" style="width: 70px; padding: 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px; background: var(--bg-surface); border:1px solid var(--border-color);">
                                          </div>
                                          <div>
                                              <label style="font-size: 10px; color: var(--text-muted); display: block; margin-bottom: 2px;">Atributo</label>
                                              <select id="req_new_dice-stat" class="textbox" style="width: 90px; padding: 4px 20px 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px; background-position: right 6px top 50% !important; background-size: 8px auto !important; background-color: var(--bg-surface); border:1px solid var(--border-color);">
                                                  <option value="">—</option>
                                                  <option value="FUE">FUE</option>
                                                  <option value="AGI">AGI</option>
                                                  <option value="DES">DES</option>
                                                  <option value="INST">INST</option>
                                                  <option value="ESP">ESP</option>
                                                  <option value="INT">INT</option>
                                              </select>
                                          </div>
                                          <div>
                                              <label style="font-size: 10px; color: var(--text-muted); display: block; margin-bottom: 2px;">Mult/Div</label>
                                              <input type="text" id="req_new_dice-stat-mod" class="textbox" placeholder="Ej: 2.5* o /2" style="width: 100px; padding: 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px; background: var(--bg-surface); border:1px solid var(--border-color);">
                                          </div>
                                          <div>
                                              <label style="font-size: 10px; color: var(--text-muted); display: block; margin-bottom: 2px;">Sufijo</label>
                                              <input type="text" id="req_new_dice-suffix" class="textbox" placeholder="[FUEGO]" style="width: 110px; padding: 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px; background: var(--bg-surface); border:1px solid var(--border-color);">
                                          </div>
                                          <div style="display: flex; align-items: flex-end;">
                                              <div style="padding: 0 12px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: monospace; font-size: 12px; height: 28px; display: flex; align-items: center;">
                                                  <span style="font-size: 11px; color: var(--text-muted); margin-right: 6px;">Fórmula:</span>
                                                  <span id="req_new_dice-preview" style="color: var(--text-primary); font-weight: bold;">—</span>
                                              </div>
                                          </div>
                                      </div>
                                      <input type="hidden" id="req_new_dice" value="">
                                  </div>
                              </div>

                              <!-- FILA 7: Reposo y Duración -->
                              <div style="grid-column: 1 / -1; border-top: 1px solid var(--border-color); padding-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                  <div>
                                      <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Turnos de Reposo</label>
                                      <input type="number" id="req_new_reposo" min="0" value="0" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                  </div>
                                  <div>
                                      <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Duración (en turnos - 0 = Turno de activación)</label>
                                      <input type="number" id="req_new_duracion" min="0" value="0" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                  </div>
                              </div>

                              <!-- FILA 8: Notas + URL Imagen -->
                              <div>
                                  <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Notas Internas / Upgrades</label>
                                  <textarea id="req_new_notes" class="textbox" rows="2" placeholder="Notas sobre mejoras futuras, etc..." style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); resize:vertical;"></textarea>
                              </div>
                              <div>
                                  <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">URL Imagen</label>
                                  <input type="url" id="req_new_image" class="textbox" placeholder="https://i.imgur.com/..." style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                              </div>
                          </div>

                          <button class="pj-btn-add" style="margin-top:5px; width:100%; justify-content:center; padding:12px; font-weight:800;" onclick="submitCustomCardRequest()"><i class="fas fa-paper-plane"></i> Enviar Propuesta al Staff</button>
                      </div>
                  </div>

                  <!-- SUBTAB: CARTA CATÁLOGO -->
                  <div id="gestion_subtab_solicitar_catalogo" class="gestion-subtab-content" style="display:none;">
                      <button class="rpg-back-btn" onclick="showGestionDashboard()">
                          <i class="fas fa-arrow-left"></i> Volver a Gestión
                      </button>

                      <div style="max-width:650px; margin:0 auto; background:var(--bg-main); border:1px solid var(--border-color); border-radius:12px; padding:30px; display:flex; flex-direction:column; gap:20px; box-shadow:var(--shadow-card);">
                          <h3 style="margin:0; font-size:16px; color:var(--text-primary); border-bottom:1px solid var(--border-color); padding-bottom:12px; display:flex; align-items:center; gap:10px; font-family:var(--font-heading); font-weight:800;">
                              <i class="fas fa-clone" style="color:var(--accent-indigo); font-size:18px;"></i> Solicitar Carta del Catálogo
                          </h3>
                          <p style="font-size:12px; color:var(--text-muted); margin:0; line-height:1.6;">
                              Solicita que se te asigne una de las cartas preexistentes del catálogo oficial del juego.
                          </p>
                          
                          <div class="form-group">
                              <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Seleccionar Carta</label>
                              <select id="req_existing_id" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                  <option value="">Selecciona una carta...</option>
                                  <?php foreach ($catalog_cards as $cc): ?>
                                      <option value="<?= $cc['id'] ?>">[<?= $cc['rank'] ?>] <?= htmlspecialchars($cc['name']) ?> (<?= ucfirst($cc['card_type']) ?>)</option>
                                  <?php endforeach; ?>
                              </select>
                          </div>
                          <div class="form-group">
                              <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Nota / Justificación (Opcional)</label>
                              <textarea id="req_existing_note" class="textbox" rows="5" placeholder="Indica dónde obtuviste esta carta (ej: link a post de entrenamiento, premio de misión o compra de tienda)..." style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); resize:vertical;"></textarea>
                          </div>
                          <button class="pj-btn-add" style="margin-top:5px; width:100%; justify-content:center; padding:12px; font-weight:800; background:linear-gradient(135deg, var(--accent-indigo), var(--accent-purple)) !important;" onclick="submitCatalogCardRequest()"><i class="fas fa-paper-plane"></i> Solicitar Adición</button>
                      </div>
                  </div>

                  <!-- SUBTAB: HISTORIAL -->
                  <div id="gestion_subtab_historial" class="gestion-subtab-content" style="display:none;">
                      <button class="rpg-back-btn" onclick="showGestionDashboard()">
                          <i class="fas fa-arrow-left"></i> Volver a Gestión
                      </button>

                      <div class="rpg-req-split">
                          <!-- LEFT: Requests List -->
                          <div class="rpg-req-list" id="my-requests-list-items">
                              <div style="padding:20px; text-align:center; color:var(--text-muted);">Cargando solicitudes...</div>
                          </div>

                          <!-- RIGHT: Request Details -->
                          <div class="rpg-req-detail" id="my-request-detail-panel">
                              <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-muted); text-align:center;">
                                  <i class="fas fa-envelope-open-text" style="font-size:40px; color:var(--text-muted); opacity:0.3; margin-bottom:15px;"></i>
                                  Selecciona una solicitud de la lista para ver su conversación y estado.
                              </div>
                          </div>
                      </div>
                  </div>
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

// === RPG GESTION JAVASCRIPT ===
function switchGestionSubtab(subtabId) {
    // Hide dashboard panel
    var dbPanel = document.getElementById('gestion_dashboard');
    if (dbPanel) dbPanel.style.display = 'none';
    
    // Hide all subtab contents
    document.querySelectorAll('.gestion-subtab-content').forEach(function(e) {
        e.style.display = 'none';
    });
    
    // Show selected subtab content
    var target = document.getElementById('gestion_subtab_' + subtabId);
    if(target) target.style.display = 'block';
    
    if (subtabId === 'historial') {
        loadMyRequests();
    }
}

function showGestionDashboard() {
    // Hide all subtab contents
    document.querySelectorAll('.gestion-subtab-content').forEach(function(e) {
        e.style.display = 'none';
    });
    
    // Show dashboard panel
    var dbPanel = document.getElementById('gestion_dashboard');
    if (dbPanel) dbPanel.style.display = 'block';
    
    loadMyRequests();
}

function buyStatPoint(stat) {
    if (!confirm('¿Estás seguro de comprar +1 punto en este atributo por 5 PP?')) return;
    
    fetch(AJAX_BASE + '/purchase_attribute.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ character_id: <?= (int)$char['id'] ?>, stat: stat, amount: 1 })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) {
            alert('¡Atributo comprado con éxito!');
            // Update PP displays
            var valPP = document.getElementById('val_available_pp');
            if(valPP) valPP.textContent = res.data.new_pp;
            var otherPp = document.getElementById('rpg-pp-available');
            if (otherPp) otherPp.textContent = res.data.new_pp;
            
            // Reload page to automatically update all UI elements, including attributes bars, stats values, PV/PE, etc.
            window.location.reload();
        } else {
            alert('Error: ' + res.error.message);
        }
    })
    .catch(function() { alert('Error de conexión al comprar atributo.'); });
}

function submitCustomCardRequest() {
    var name = document.getElementById('req_new_name').value.trim();
    var type = document.getElementById('req_new_type').value;
    var desc = document.getElementById('req_new_desc').value.trim();
    var rank = document.getElementById('req_new_rank').value;
    var activation = document.getElementById('req_new_activation').value;
    var cost_pe = document.getElementById('req_new_cost').value.trim() || '—';
    var execution_stat = document.getElementById('req_new_stat').value;
    var dice = document.getElementById('req_new_dice').value;
    var tags = document.getElementById('req_new_tags').value.split(',').map(function(t){ return t.trim(); }).filter(Boolean);
    var reposo = parseInt(document.getElementById('req_new_reposo').value) || 0;
    var duracion = parseInt(document.getElementById('req_new_duracion').value) || 0;
    var image_url = document.getElementById('req_new_image').value.trim();
    var notes = document.getElementById('req_new_notes').value.trim();
    
    if (name === '' || desc === '') {
        alert('Por favor, ingresa el nombre y la descripción para tu propuesta.');
        return;
    }
    
    fetch(AJAX_BASE + '/cards_request_custom.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            character_id: <?= (int)$char['id'] ?>,
            type: 'create',
            card_name: name,
            card_type: type,
            description: desc,
            rank: rank,
            activation: activation,
            cost_pe: cost_pe,
            execution_stat: execution_stat,
            dice: dice,
            tags: tags,
            reposo: reposo,
            duracion: duracion,
            image_url: image_url,
            notes: notes
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) {
            alert('Propuesta de carta enviada correctamente al staff.');
            document.getElementById('req_new_name').value = '';
            document.getElementById('req_new_desc').value = '';
            document.getElementById('req_new_cost').value = '—';
            document.getElementById('req_new_stat').value = '';
            document.getElementById('req_new_reposo').value = '0';
            document.getElementById('req_new_duracion').value = '0';
            document.getElementById('req_new_image').value = '';
            document.getElementById('req_new_notes').value = '';
            
            // Reset tags and dice builder
            if (typeof resetReqNewTags === 'function') resetReqNewTags();
            if (typeof resetReqNewDiceBuilder === 'function') resetReqNewDiceBuilder();
            
            // Switch tab to historial
            switchGestionSubtab('historial');
        } else {
            alert('Error: ' + res.error.message);
        }
    })
    .catch(function() { alert('Error de conexión.'); });
}

// ======= CUSTOM CARD PROPOSAL: TAG SELECTOR & DICE BUILDER =======
var reqNewSelectedTags = new Set();
function initReqNewTagSelector() {
    var TAG_CATEGORIES = [
        { name: 'Activación y temporalidad', tags: ['ACTIVA','PASIVA','REACTIVA','CONTINUA','INSTANTÁNEA','CARGA','CANAL','RETRASADA','ENCADENABLE','UNA VEZ','COOLDOWN X'] },
        { name: 'Alcance y geometría', tags: ['CONTACTO','CUERPO A CUERPO','DISTANCIA CORTA','DISTANCIA MEDIA','DISTANCIA LARGA','AUTOPERSONAL','ALIADOS','ÁREA PEQUEÑA','ÁREA MEDIA','ÁREA GRANDE','LÍNEA','CONO','ANILLO','TRAYECTORIA','TOQUE','GLOBAL'] },
        { name: 'Función de combate', tags: ['OFENSIVA','DEFENSIVA','CONTROL','SOPORTE','MOVILIDAD','CURACIÓN','UTILIDAD','INTERRUPCIÓN','PENETRACIÓN','DESVÍO','ABSORCIÓN','SEÑUELO','ESCUDO'] },
        { name: 'Ejecución', tags: ['EJECUCIÓN: FUE','EJECUCIÓN: AGI','EJECUCIÓN: DES','EJECUCIÓN: INST','EJECUCIÓN: ESP','EJECUCIÓN: INT'] },
        { name: 'Tipo de daño', tags: ['DAÑO FÍSICO','DAÑO CORTANTE','DAÑO CONTUNDENTE','DAÑO PERFORANTE','DAÑO ÍGNEO','DAÑO CRIOGÉNICO','DAÑO ELÉCTRICO','DAÑO TÓXICO','DAÑO EXPLOSIVO','DAÑO INTERNO','DAÑO ESPIRITUAL','DAÑO ESTRUCTURAL','DAÑO OSCURO'] },
        { name: 'Interacción especial', tags: ['ANTI-LOGIA','ANTI-HAKI','KAIROSEKI','IGNORA ARMADURA','DOBLE DAÑO EMPAPADO','VULNERABILIDAD AGUA','ESCALA CON DAÑO RECIBIDO','ESCALA CON PE RESTANTE','ESCALA CON ALIADOS','BONUS VS DERRIBADO','BONUS VS ESTADO','ENCADENADO CON','ROMPE CONCENTRACIÓN'] },
        { name: 'Elemento / naturaleza', tags: ['FUEGO','HIELO','RAYO','VENENO','OSCURIDAD','LUZ','VIENTO','TIERRA','AGUA','HUMO','ARENA','VIBRACIÓN','SONIDO','GRAVEDAD','VACÍO'] },
        { name: 'Akuma no Mi', tags: ['LOGIA','PARAMECIA-PRODUCTOR','PARAMECIA-TRANSFORMADOR','PARAMECIA-MANIPULADOR','ZOAN','ZOAN MÍTICO','ZOAN ANTIGUO','DESPERTAR'] },
        { name: 'Haki', tags: ['HAKI ARMAMENTO','HAKI OBSERVACIÓN','HAKI REY','FLUJO AVANZADO','VISIÓN DE FUTURO','EMISIÓN DE REY'] },
        { name: 'Equipo', tags: ['ARMA','ARMA SECUNDARIA','ARMA ARROJADIZA','ARMADURA','ARMADURA PARCIAL','ACCESORIO','CONSUMIBLE','NAVE','KAIROSEKI INTEGRADO','GRADO MEITO','MODIFICABLE'] },
        { name: 'NPC', tags: ['PIRATA','MARINO','REVOLUCIONARIO','CIVIL','AGENTE CIPHER POL','BOUNTY HUNTER','ALIADO TEMPORAL','OBSTÁCULO','JEFE DE ESCENA'] },
        { name: 'Condición y restricción', tags: ['REQUIERE ARMA','REQUIERE AKUMA NO MI','REQUIERE HAKI','REQUIERE ESTADO PROPIO','REQUIERE ESTADO OBJETIVO','SOLO EN AGUA','SOLO EN TIERRA','SOLO FORMA HÍBRIDA','SOLO FORMA BESTIAL','CONSUMO DOBLE EMPAPADO','AUTO-DAÑO'] }
    ];

    var dropdown = document.getElementById('req_new_tag-dropdown');
    var selectedDiv = document.getElementById('req_new_tag-selected');
    var toggleBtn = document.getElementById('req_new_tag-toggle-btn');
    var tagsInput = document.getElementById('req_new_tags');

    if (!dropdown || !selectedDiv || !toggleBtn || !tagsInput) return;

    dropdown.innerHTML = '';
    TAG_CATEGORIES.forEach(function(cat) {
        var group = document.createElement('div');
        group.style.borderBottom = '1px solid var(--border-color)';
        
        var header = document.createElement('div');
        header.style.padding = '8px 12px';
        header.style.fontWeight = 'bold';
        header.style.fontSize = '0.85em';
        header.style.background = 'var(--bg-main)';
        header.style.cursor = 'pointer';
        header.style.userSelect = 'none';
        header.style.display = 'flex';
        header.style.alignItems = 'center';
        header.style.gap = '6px';
        header.innerHTML = '<span style="font-size: 0.7em; opacity: 0.5;">▸</span> ' + cat.name;
        
        var body = document.createElement('div');
        body.style.display = 'none';
        body.style.flexWrap = 'wrap';
        body.style.gap = '3px';
        body.style.padding = '6px 12px 10px';

        header.addEventListener('click', function() {
            var isHidden = body.style.display === 'none';
            body.style.display = isHidden ? 'flex' : 'none';
            header.querySelector('span').textContent = isHidden ? '▾' : '▸';
        });

        cat.tags.forEach(function(tag) {
            var label = document.createElement('label');
            label.style.display = 'flex';
            label.style.alignItems = 'center';
            label.style.gap = '3px';
            label.style.padding = '2px 7px';
            label.style.fontSize = '0.8em';
            label.style.cursor = 'pointer';
            label.style.borderRadius = '4px';
            label.style.background = 'var(--bg-surface)';
            label.style.border = '1px solid var(--border-color)';
            label.style.color = 'var(--text-primary)';

            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = tag;
            cb.addEventListener('change', function() {
                if (cb.checked) {
                    reqNewSelectedTags.add(tag);
                } else {
                    reqNewSelectedTags.delete(tag);
                }
                updateReqNewTagDisplay();
            });

            label.appendChild(cb);
            label.appendChild(document.createTextNode(tag));
            body.appendChild(label);
        });

        group.appendChild(header);
        group.appendChild(body);
        dropdown.appendChild(group);
    });

    toggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        var isHidden = dropdown.style.display === 'none';
        dropdown.style.display = isHidden ? 'block' : 'none';
    });
}

function updateReqNewTagDisplay() {
    var selectedDiv = document.getElementById('req_new_tag-selected');
    var tagsInput = document.getElementById('req_new_tags');
    var dropdown = document.getElementById('req_new_tag-dropdown');
    
    if (!selectedDiv || !tagsInput) return;

    selectedDiv.innerHTML = '';
    reqNewSelectedTags.forEach(function(tag) {
        var pill = document.createElement('span');
        pill.style.display = 'inline-flex';
        pill.style.alignItems = 'center';
        pill.style.gap = '3px';
        pill.style.padding = '2px 8px';
        pill.style.borderRadius = '12px';
        pill.style.fontSize = '0.8em';
        pill.style.background = 'var(--accent-indigo)';
        pill.style.color = '#fff';
        pill.textContent = tag;

        var remove = document.createElement('span');
        remove.textContent = '×';
        remove.style.cursor = 'pointer';
        remove.style.marginLeft = '2px';
        remove.style.fontWeight = 'bold';
        remove.style.fontSize = '1.1em';
        remove.addEventListener('click', function(e) {
            e.stopPropagation();
            reqNewSelectedTags.delete(tag);
            if (dropdown) {
                var cbs = dropdown.querySelectorAll('input[type="checkbox"]');
                cbs.forEach(function(cb) {
                    if (cb.value === tag) cb.checked = false;
                });
            }
            updateReqNewTagDisplay();
        });

        pill.appendChild(remove);
        selectedDiv.appendChild(pill);
    });

    tagsInput.value = Array.from(reqNewSelectedTags).join(', ');
}

function resetReqNewTags() {
    reqNewSelectedTags.clear();
    var dropdown = document.getElementById('req_new_tag-dropdown');
    if (dropdown) {
        var cbs = dropdown.querySelectorAll('input[type="checkbox"]');
        cbs.forEach(function(cb) { cb.checked = false; });
    }
    updateReqNewTagDisplay();
}

function buildReqNewDiceFormula() {
    var groups = document.querySelectorAll('#req_new_dice-groups > div');
    var parts = [];
    groups.forEach(function(g) {
        if (g.classList.contains('dice-group')) {
            var qty = parseInt(g.querySelector('.dice-qty').value) || 1;
            var type = g.querySelector('.dice-type').value;
            if (qty > 0) parts.push(qty + type);
        } else if (g.classList.contains('dice-placeholder')) {
            var type = g.querySelector('.placeholder-type').value;
            parts.push(type);
        }
    });

    var fixed = parseInt(document.getElementById('req_new_dice-fixed').value) || 0;
    var stat = document.getElementById('req_new_dice-stat').value;
    var statMod = document.getElementById('req_new_dice-stat-mod').value.trim();
    var suffix = document.getElementById('req_new_dice-suffix').value.trim();

    var formula = parts.join('+');
    if (fixed > 0) formula += (formula ? '+' : '') + fixed;
    if (stat) {
        var statPart = stat;
        if (statMod) {
            if (statMod.includes('/')) {
                var divisor = statMod.replace('/', '').trim();
                statPart = stat + '/' + divisor;
            } else if (statMod.includes('*')) {
                var mult = statMod.replace('*', '').trim();
                statPart = mult + '*' + stat;
            } else {
                if (!isNaN(parseFloat(statMod))) {
                    statPart = statMod + '*' + stat;
                } else {
                    statPart = statMod + stat;
                }
            }
        }
        formula += (formula ? '+' : '') + statPart;
    }
    if (suffix) formula += (formula ? ' ' : '') + suffix;

    document.getElementById('req_new_dice-preview').textContent = formula || '—';
    document.getElementById('req_new_dice').value = formula;
}

function addReqNewDiceGroup(qty, type) {
    var container = document.getElementById('req_new_dice-groups');
    if (!container) return;

    var group = document.createElement('div');
    group.className = 'dice-group';
    group.style.display = 'inline-flex';
    group.style.alignItems = 'center';
    group.style.gap = '6px';
    group.style.margin = '4px 8px 4px 0';
    group.style.padding = '6px 10px';
    group.style.background = 'var(--bg-surface)';
    group.style.border = '1px solid var(--border-color)';
    group.style.borderRadius = 'var(--radius-md)';

    var qtyInput = document.createElement('input');
    qtyInput.type = 'number';
    qtyInput.className = 'dice-qty';
    qtyInput.min = 1;
    qtyInput.max = 100;
    qtyInput.value = qty || 2;
    qtyInput.style.width = '60px';
    qtyInput.style.padding = '4px 6px !important';
    qtyInput.style.height = '28px';
    qtyInput.style.fontSize = '12px';
    qtyInput.style.borderRadius = '4px';
    qtyInput.style.lineHeight = '20px';
    qtyInput.style.border = '1px solid var(--border-color)';
    qtyInput.style.background = 'var(--bg-main)';
    qtyInput.style.color = 'var(--text-primary)';
    qtyInput.addEventListener('input', buildReqNewDiceFormula);

    var typeSelect = document.createElement('select');
    typeSelect.className = 'dice-type';
    typeSelect.style.width = '80px';
    typeSelect.style.padding = '4px 20px 4px 8px !important';
    typeSelect.style.height = '28px';
    typeSelect.style.fontSize = '12px';
    typeSelect.style.borderRadius = '4px';
    typeSelect.style.border = '1px solid var(--border-color)';
    typeSelect.style.background = 'var(--bg-main) url("data:image/svg+xml;charset=utf8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 4 5\'%3E%3Cpath fill=\'%23a3a3a3\' d=\'M2 0L0 2h4zm0 5L0 3h4z\'/%3E%3C/svg%3E") no-repeat right 6px center';
    typeSelect.style.backgroundSize = '8px auto';
    typeSelect.style.color = 'var(--text-primary)';
    typeSelect.style.webkitAppearance = 'none';
    typeSelect.style.mozAppearance = 'none';
    typeSelect.style.appearance = 'none';

    ['d4', 'd6', 'd8', 'd10', 'd12', 'd20', 'd100'].forEach(function(d) {
        var opt = document.createElement('option');
        opt.value = d;
        opt.textContent = d;
        if (d === (type || 'd20')) opt.selected = true;
        typeSelect.appendChild(opt);
    });
    typeSelect.addEventListener('change', buildReqNewDiceFormula);

    var removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.textContent = '×';
    removeBtn.title = 'Quitar grupo';
    removeBtn.style.background = 'none';
    removeBtn.style.border = 'none';
    removeBtn.style.color = 'var(--accent-rose)';
    removeBtn.style.cursor = 'pointer';
    removeBtn.style.fontSize = '16px';
    removeBtn.style.padding = '0 2px';
    removeBtn.style.lineHeight = '1';
    removeBtn.addEventListener('click', function() {
        container.removeChild(group);
        buildReqNewDiceFormula();
    });

    group.appendChild(qtyInput);
    group.appendChild(typeSelect);
    group.appendChild(removeBtn);
    container.appendChild(group);
    buildReqNewDiceFormula();
}

function addReqNewPlaceholderGroup(type) {
    var container = document.getElementById('req_new_dice-groups');
    if (!container) return;

    var group = document.createElement('div');
    group.className = 'dice-placeholder';
    group.style.display = 'inline-flex';
    group.style.alignItems = 'center';
    group.style.gap = '6px';
    group.style.margin = '4px 8px 4px 0';
    group.style.padding = '6px 10px';
    group.style.background = 'var(--bg-surface)';
    group.style.border = '1px solid var(--border-color)';
    group.style.borderRadius = 'var(--radius-md)';
    group.style.fontWeight = 'bold';
    group.style.color = 'var(--accent-indigo)';
    group.style.fontSize = '12px';

    var textSpan = document.createElement('span');
    textSpan.textContent = type;

    var typeInput = document.createElement('input');
    typeInput.type = 'hidden';
    typeInput.className = 'placeholder-type';
    typeInput.value = type;

    var removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.textContent = '×';
    removeBtn.title = 'Quitar';
    removeBtn.style.background = 'none';
    removeBtn.style.border = 'none';
    removeBtn.style.color = 'var(--accent-rose)';
    removeBtn.style.cursor = 'pointer';
    removeBtn.style.fontSize = '16px';
    removeBtn.style.padding = '0 2px';
    removeBtn.style.lineHeight = '1';
    removeBtn.addEventListener('click', function() {
        container.removeChild(group);
        buildReqNewDiceFormula();
    });

    group.appendChild(textSpan);
    group.appendChild(typeInput);
    group.appendChild(removeBtn);
    container.appendChild(group);
    buildReqNewDiceFormula();
}

function resetReqNewDiceBuilder() {
    var container = document.getElementById('req_new_dice-groups');
    if (container) {
        container.innerHTML = '';
    }
    addReqNewDiceGroup(2, 'd20');
    document.getElementById('req_new_dice-fixed').value = '0';
    document.getElementById('req_new_dice-stat').value = '';
    document.getElementById('req_new_dice-stat-mod').value = '';
    document.getElementById('req_new_dice-suffix').value = '';
    buildReqNewDiceFormula();
}

function submitCatalogCardRequest() {
    var cardId = document.getElementById('req_existing_id').value;
    var note = document.getElementById('req_existing_note').value.trim();
    
    if (!cardId) {
        alert('Por favor, selecciona una carta del catálogo.');
        return;
    }
    
    fetch(AJAX_BASE + '/cards_request_custom.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ character_id: <?= (int)$char['id'] ?>, type: 'add_existing', card_id: cardId, note: note })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) {
            alert('Solicitud de adición de carta enviada correctamente.');
            document.getElementById('req_existing_id').value = '';
            document.getElementById('req_existing_note').value = '';
            
            // Switch tab to historial
            switchGestionSubtab('historial');
        } else {
            alert('Error: ' + res.error.message);
        }
    })
    .catch(function() { alert('Error de conexión.'); });
}

var currentRequestsList = [];
var activeReqId = null;

function loadMyRequests() {
    fetch(AJAX_BASE + '/cards_request_list_mine.php?character_id=<?= (int)$char['id'] ?>')
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) {
            currentRequestsList = res.data;
            renderMyRequestsList(res.data);
            
            var count = res.data.filter(function(r) { return r.status === 'pendiente' || r.status === 'conforme'; }).length;
            
            // Update badge count in history subtab header if present
            var badge = document.getElementById('requests-badge-count');
            if(badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
            
            // Update dashboard card badge count
            var dBadge = document.getElementById('dashboard-requests-badge');
            if(dBadge) {
                if (count > 0) {
                    dBadge.textContent = count + ' activa' + (count > 1 ? 's' : '');
                    dBadge.style.display = 'inline-block';
                } else {
                    dBadge.style.display = 'none';
                }
            }
        }
    });
}

function renderMyRequestsList(list) {
    var container = document.getElementById('my-requests-list-items');
    if(!container) return;
    if (list.length === 0) {
        container.innerHTML = '<div style="padding:40px 20px; color:var(--text-muted); text-align:center;"><i class="fas fa-check-circle" style="font-size:24px; color:var(--accent-emerald); display:block; margin-bottom:8px; opacity:0.6;"></i>No tienes solicitudes activas.</div>';
        return;
    }
    
    var html = '';
    list.forEach(function(req) {
        var statusLabel = req.status.toUpperCase();
        var statusColor = 'var(--text-muted)';
        if (req.status === 'aprobada') statusColor = '#10b981';
        else if (req.status === 'rechazada') statusColor = '#ef4444';
        else if (req.status === 'pendiente') statusColor = '#f59e0b';
        else if (req.status === 'conforme') statusColor = '#6366f1';
        
        var typeLabel = 'MEJORA';
        if (req.request_type === 'delete') typeLabel = 'BORRADO';
        else if (req.request_type === 'create') typeLabel = 'CREACIÓN';
        else if (req.request_type === 'add_existing') typeLabel = 'ADICIÓN';
        
        var isActive = (parseInt(req.id) === activeReqId) ? 'active' : '';
        
        html += '<div class="rpg-req-item ' + isActive + '" onclick="selectMyRequest(' + req.id + ')">';
        html += '  <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">';
        html += '    <strong style="font-size:12px; color:var(--text-primary); text-overflow:ellipsis; overflow:hidden; white-space:nowrap; max-width:140px;">' + escapeHtml(req.resolved_card_name) + '</strong>';
        html += '    <span style="font-size:9px; font-weight:800; color:' + statusColor + '; flex-shrink:0;">' + statusLabel + '</span>';
        html += '  </div>';
        html += '  <div style="font-size:10px; color:var(--text-muted); margin-top:4px;">Tipo: ' + typeLabel + ' &bull; ' + req.created_at.split(' ')[0] + '</div>';
        html += '</div>';
    });
    container.innerHTML = html;
}

function selectMyRequest(reqId) {
    activeReqId = parseInt(reqId);
    renderMyRequestsList(currentRequestsList);
    
    var req = currentRequestsList.find(function(r) { return parseInt(r.id) === reqId; });
    var panel = document.getElementById('my-request-detail-panel');
    if (!req || !panel) return;
    
    var isPending = (req.status === 'pendiente');
    var isConforme = (req.status === 'conforme');
    
    var typeLabel = 'Mejora de Carta';
    if (req.request_type === 'delete') typeLabel = 'Borrado de Carta';
    else if (req.request_type === 'create') typeLabel = 'Creación de Carta';
    else if (req.request_type === 'add_existing') typeLabel = 'Adición de Carta';
    
    var html = '';
    html += '<div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:10px; margin-bottom:15px;">';
    html += '  <h3 style="margin:0; font-size:15px; color:var(--text-primary); font-family:var(--font-heading); font-weight:800;">' + typeLabel + ': ' + escapeHtml(req.resolved_card_name) + '</h3>';
    html += '  <span style="font-size:10px; font-weight:800; background:rgba(255,255,255,0.05); padding:3px 10px; border-radius:12px; color:var(--text-muted);">' + req.status.toUpperCase() + '</span>';
    html += '</div>';
    
    html += '<div style="display:flex; gap:15px; flex-wrap:wrap; flex:1; min-height:0;">';
    
    // Discussion Thread
    html += '  <div style="flex:1; display:flex; flex-direction:column; gap:10px; min-width:250px;">';
    html += '    <div class="rpg-chat-container">';
    html += '      <div class="rpg-chat-messages" id="rpg-chat-messages-container">';
    
    if (req.discussion && req.discussion.length > 0) {
        req.discussion.forEach(function(msg) {
            var bubbleClass = (msg.sender === 'player') ? 'player' : 'staff';
            var senderLabel = (msg.sender === 'player') ? 'TÚ' : 'STAFF';
            var senderColor = (msg.sender === 'player') ? 'var(--accent-indigo)' : 'var(--accent-purple)';
            var msgTime = msg.timestamp ? msg.timestamp.split(' ')[1] : '';
            
            html += '        <div class="rpg-chat-bubble ' + bubbleClass + '">';
            html += '          <div class="rpg-chat-bubble-meta">';
            html += '            <span style="color:' + senderColor + '; font-weight:700;">' + escapeHtml(msg.sender_name) + ' (' + senderLabel + ')</span>';
            html += '            <span style="margin-left:10px;">' + escapeHtml(msgTime) + '</span>';
            html += '          </div>';
            html += '          <div style="white-space:pre-wrap;">' + escapeHtml(msg.message) + '</div>';
            html += '        </div>';
        });
    } else {
        html += '        <div style="padding:20px; color:var(--text-muted); text-align:center;">No hay mensajes en esta conversación.</div>';
    }
    
    html += '      </div>';
    
    // If pending, allow reply
    if (isPending) {
        html += '      <div class="rpg-chat-input-bar">';
        html += '        <input type="text" id="rpg-chat-reply-input" class="rpg-chat-input" placeholder="Escribe un mensaje para el staff...">';
        html += '        <button class="rpg-chat-send" onclick="replyToMyRequest(' + req.id + ')"><i class="fas fa-paper-plane"></i></button>';
        html += '      </div>';
    }
    
    html += '    </div>';
    
    // Actions panel
    if (isPending && req.request_type === 'create') {
        html += '    <div style="margin-top:10px; display:flex; gap:10px;">';
        html += '      <button class="pj-btn-add" style="flex:1; justify-content:center; background:linear-gradient(135deg, #10b981, #059669) !important; box-shadow: 0 4px 15px rgba(16,185,129,0.3) !important;" onclick="conformeMyRequest(' + req.id + ')"><i class="fas fa-check-double"></i> Estoy Conforme con la Carta</button>';
        html += '    </div>';
    }
    
    html += '  </div>';
    
    // Dynamic Moderated Card Preview (For custom card creations only)
    if (req.request_type === 'create' && req.card_details) {
        var card = req.card_details;
        var tagsHtml = '';
        if (card.tags && Array.isArray(card.tags)) {
            card.tags.forEach(function(t) {
                tagsHtml += '<span style="display:inline-block; font-size:8px; font-weight:700; padding:1px 6px; border:1px solid var(--border-color); border-radius:8px; color:var(--text-muted); text-transform:uppercase;">' + escapeHtml(t) + '</span>';
            });
        }
        
        var statRow = '';
        if ((card.cost_pe && card.cost_pe !== '—') || card.execution_stat || card.dice) {
            statRow = '<div style="display:flex; gap:8px; margin:10px 0; background:var(--bg-main); padding:6px 10px; border-radius:6px; border:1px solid var(--border-color); font-size:10px;">';
            if (card.cost_pe && card.cost_pe !== '—') statRow += '<div><span style="display:block; font-size:8px; color:var(--text-muted); font-weight:700;">PE</span><strong style="color:var(--text-primary);">' + escapeHtml(card.cost_pe) + '</strong></div>';
            if (card.execution_stat) statRow += '<div><span style="display:block; font-size:8px; color:var(--text-muted); font-weight:700;">STAT</span><strong style="color:var(--text-primary);">' + escapeHtml(card.execution_stat) + '</strong></div>';
            if (card.dice) statRow += '<div><span style="display:block; font-size:8px; color:var(--text-muted); font-weight:700;">DADOS</span><strong style="color:var(--text-primary);">' + escapeHtml(card.dice) + '</strong></div>';
            statRow += '</div>';
        }
        
        var cardImg = card.image_url ? '<div style="width:100%; height:90px; background-image:url(\'' + escapeHtml(card.image_url) + '\'); background-size:cover; background-position:center; border-radius:4px; margin-bottom:8px;"></div>' : '';
        
        html += '  <div style="display:flex; flex-direction:column; align-items:center; gap:8px; flex-shrink:0;">';
        html += '    <div style="font-size:10px; font-weight:800; color:var(--accent-indigo); text-transform:uppercase; letter-spacing:0.5px;">Carta Propuesta</div>';
        html += '    <div class="rpg-card-preview-mini">';
        html += '      <div style="padding:8px 12px; background:var(--bg-surface); border-bottom:1px solid var(--border-color); font-family:var(--font-heading);">';
        html += '        <div style="font-weight:900; color:var(--text-primary); font-size:12px;">' + escapeHtml(card.name) + '</div>';
        html += '        <div style="font-size:9px; color:var(--text-muted); text-transform:uppercase; margin-top:2px;">[' + escapeHtml(card.rank) + '] ' + escapeHtml(card.card_type.toUpperCase()) + '</div>';
        html += '      </div>';
        html += '      ' + cardImg;
        html += '      <div style="padding:10px;">';
        html += '        <div style="display:flex; gap:3px; flex-wrap:wrap; margin-bottom:8px;">' + tagsHtml + '</div>';
        html += '        ' + statRow;
        html += '        <div style="font-size:10px; color:var(--text-secondary); line-height:1.4; height:100px; overflow-y:auto; padding-right:3px; white-space:pre-wrap;">' + escapeHtml(card.description) + '</div>';
        html += '      </div>';
        html += '    </div>';
        html += '  </div>';
    }
    
    html += '</div>';
    
    panel.innerHTML = html;
    
    // Scroll chat to bottom
    setTimeout(function() {
        var chatBox = document.getElementById('rpg-chat-messages-container');
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    }, 50);
}

function replyToMyRequest(reqId) {
    var input = document.getElementById('rpg-chat-reply-input');
    var msg = input.value.trim();
    if (msg === '') return;
    
    fetch(AJAX_BASE + '/cards_request_reply.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ request_id: reqId, message: msg })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) {
            input.value = '';
            loadMyRequests();
            setTimeout(function() { selectMyRequest(reqId); }, 300);
        } else {
            alert('Error: ' + res.error.message);
        }
    })
    .catch(function() { alert('Error de conexión.'); });
}

function conformeMyRequest(reqId) {
    if (!confirm('¿Estás seguro de marcar esta propuesta como CONFORME? Una vez lo hagas, no podrás seguir enviando mensajes y quedará pendiente de que el staff la cree oficialmente.')) return;
    
    fetch(AJAX_BASE + '/cards_request_conforme.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ request_id: reqId })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) {
            alert('¡Has expresado tu conformidad con éxito! El staff procederá a la creación de la carta.');
            loadMyRequests();
            setTimeout(function() { selectMyRequest(reqId); }, 300);
        } else {
            alert('Error: ' + res.error.message);
        }
    })
    .catch(function() { alert('Error de conexión.'); });
}

// Auto-run list loading on DOM ready
document.addEventListener("DOMContentLoaded", function() {
    loadMyRequests();
    
    // Initialize custom card proposal tag selector and dice builder
    if (document.getElementById('req_new_tag-toggle-btn')) {
        initReqNewTagSelector();
        
        document.getElementById('req_new_dice-add-group').addEventListener('click', function(e) { e.preventDefault(); addReqNewDiceGroup(1, 'd6'); });
        document.getElementById('req_new_dice-add-arma').addEventListener('click', function(e) { e.preventDefault(); addReqNewPlaceholderGroup('[ARMA]'); });
        document.getElementById('req_new_dice-add-municion').addEventListener('click', function(e) { e.preventDefault(); addReqNewPlaceholderGroup('[MUNICION]'); });
        document.getElementById('req_new_dice-fixed').addEventListener('input', buildReqNewDiceFormula);
        document.getElementById('req_new_dice-stat').addEventListener('change', buildReqNewDiceFormula);
        document.getElementById('req_new_dice-stat-mod').addEventListener('input', buildReqNewDiceFormula);
        document.getElementById('req_new_dice-suffix').addEventListener('input', buildReqNewDiceFormula);
        
        resetReqNewDiceBuilder();
    }
});
// ==============================

<?php endif; ?>
</script>
<?php
$content = ob_get_clean();
game_render_page('Mi Personaje', $content);

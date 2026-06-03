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


          <div id="pjTab_linaje" class="pj-preview-tab-content">
              <?php
              $linaje_v = $char['linaje']['version'] ?? 1;
              $pasiva_ids   = $char['linaje']['pasivas']          ?? [];
              $racial_ids   = $char['linaje']['elegidos_racial']  ?? [];
              $general_ids  = $char['linaje']['elegidos_general'] ?? [];
              $has_perks_v2 = ($linaje_v >= 2);

              $catalog_path = dirname(__DIR__, 2) . '/data/linaje_system.json';
              $linaje_catalog = [];
              if (file_exists($catalog_path)) {
                  $linaje_catalog = json_decode(file_get_contents($catalog_path), true);
              }

              // Helper to find and enrich perk by id in the new catalog
              if (!function_exists('enrich_perk_in_php')) {
                  function enrich_perk_in_php(array $p): array {
                      if (isset($p['icon']) && isset($p['iconColor'])) return $p;
                      $icon = 'fa-dna';
                      $iconColor = '#C62828';
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
                      elseif (strpos($id, 'g_linaje_viento') === 0) { $icon = 'fa-wind'; $iconColor = '#4A148C'; }
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
                      elseif (strpos($id, 'g_voluntad_ferrea') === 0) { $icon = 'fa-fingerprint'; $iconColor = '#C62828'; }
                      elseif (strpos($id, 'g_instinto') === 0) { $icon = 'fa-compass'; $iconColor = '#8b5cf6'; }
                      elseif (strpos($id, 'g_paso') === 0 || strpos($id, 'g_sombra') === 0) { $icon = 'fa-user-ninja'; $iconColor = '#475569'; }
                      elseif (strpos($id, 'g_agilidad') === 0) { $icon = 'fa-running'; $iconColor = '#10b981'; }
                      elseif (strpos($id, 'g_evasion') === 0) { $icon = 'fa-wind'; $iconColor = '#06b6d4'; }
                      elseif (strpos($id, 'g_parkour') === 0) { $icon = 'fa-shoe-prints'; $iconColor = '#f59e0b'; }
                      elseif (strpos($id, 'g_nen_ten') === 0) { $icon = 'fa-shield-alt'; $iconColor = '#10b981'; }
                      elseif (strpos($id, 'g_nen_zetsu') === 0) { $icon = 'fa-eye-slash'; $iconColor = '#64748b'; }
                      elseif (strpos($id, 'g_nen_ren') === 0) { $icon = 'fa-fire'; $iconColor = '#f97316'; }
                      elseif (strpos($id, 'g_nen_hatsu') === 0) { $icon = 'fa-bahai'; $iconColor = '#a855f7'; }
                      elseif (strpos($id, 'g_suerte') === 0 || strpos($id, 'g_golpe') === 0 || strpos($id, 'g_fortuna') === 0) { $icon = 'fa-dice-d20'; $iconColor = '#f59e0b'; }
                      elseif (strpos($id, 'g_carisma') === 0 || strpos($id, 'g_presencia') === 0 || strpos($id, 'g_inspiracion') === 0 || strpos($id, 'g_nombre_temido') === 0 || strpos($id, 'g_voz_rey') === 0) { $icon = 'fa-comments'; $iconColor = '#ec4899'; }
                      elseif (strpos($id, 'g_manos_') === 0 || strpos($id, 'g_dedos_') === 0 || strpos($id, 'g_ojo_') === 0 || strpos($id, 'g_genio_') === 0 || strpos($id, 'g_cocinero_') === 0) { $icon = 'fa-tools'; $iconColor = '#06b6d4'; }
                      elseif (strpos($id, 'g_cuatro_brazos') === 0) { $icon = 'fa-hand-paper'; $iconColor = '#3b82f6'; }
                      elseif (strpos($id, 'g_tercer_ojo') === 0) { $icon = 'fa-eye'; $iconColor = '#4A148C'; }
                      elseif (strpos($id, 'g_sangre_fria') === 0) { $icon = 'fa-snowflake'; $iconColor = '#06b6d4'; }
                      elseif (strpos($id, 'g_linaje_marino') === 0) { $icon = 'fa-anchor'; $iconColor = '#3b82f6'; }
                      elseif (strpos($id, 'g_gula') === 0) { $icon = 'fa-cookie-bite'; $iconColor = '#b45309'; }
                      elseif (strpos($id, 'g_pelo') === 0) { $icon = 'fa-magic'; $iconColor = '#db2777'; }
                      elseif (strpos($id, 'g_piel_color') === 0) { $icon = 'fa-palette'; $iconColor = '#10b981'; }
                      elseif (strpos($id, 'g_no_dormir') === 0) { $icon = 'fa-eye-slash'; $iconColor = '#64748b'; }
                      elseif (strpos($id, 'g_sangre_de_gigante') === 0) { $icon = 'fa-expand-arrows-alt'; $iconColor = '#ef4444'; }
                      elseif (strpos($id, 'g_cuerpo_elastico') === 0) { $icon = 'fa-dumbbell'; $iconColor = '#10b981'; }
                      elseif (strpos($id, 'rh_') === 0) { $icon = 'fa-user'; $iconColor = '#C62828'; }
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
                  function render_perk_card(array $p, string $type_class, string $icon_modifier, string $badge_label, string $badge_color): string {
                      $cost_html = '';
                      if (isset($p['cost']) && $p['cost'] > 0) {
                          $cost_html = '<div class="pj-linaje-perk-cost">' . (int)$p['cost'] . ' PTS</div>';
                      }
                      $iconColor = htmlspecialchars($p['iconColor'] ?? '#C62828');
                      return '<div class="gene-card pj-linaje-perk-card ' . $type_class . '">' .
                          $cost_html .
                          '<div class="gene-card-icon pj-linaje-perk-icon ' . $icon_modifier . '">' .
                              '<i class="fas ' . htmlspecialchars($p['icon'] ?? 'fa-dna') . '" data-icon-color="' . $iconColor . '"></i>' .
                          '</div>' .
                          '<div class="gene-card-info">' .
                              '<div class="gene-card-name">' . htmlspecialchars($p['name'] ?? '') . '</div>' .
                              '<div class="gene-card-desc">' . htmlspecialchars($p['desc'] ?? '') . '</div>' .
                          '</div>' .
                          '<div class="gene-card-badge pj-linaje-perk-badge" data-color="' . $badge_color . '">' . $badge_label . '</div>' .
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
                  $bonus_pp = $sobrante * 2;
                  ?>

                  <div class="linaje-slots-bar">
                      <div class="linaje-slots-group">
                          <span class="linaje-slots-label"><i class="fas fa-gem"></i> Puntos de Linaje:</span>
                          <?php if ($max_points <= 10): ?>
                              <div class="linaje-slots-dots">
                                  <?php for ($i = 0; $i < $max_points; $i++): ?>
                                      <div class="linaje-slot-dot <?= ($i < $spent_points) ? 'filled' : '' ?>"></div>
                                  <?php endfor; ?>
                              </div>
                          <?php endif; ?>
                          <span class="linaje-slots-count"><?= $spent_points ?>/<?= $max_points ?></span>
                      </div>
                      <div id="linajeSobranteBonus">
                          Puntos Sobrantes: <?= $sobrante ?> PL = <?= $bonus_pp ?> PP de Bonus
                      </div>
                  </div>

                  <?php if (!empty($displayed_pasivas)): ?>
                  <div class="pj-linaje-section-title pj-linaje-section-title--green">
                      <i class="fas fa-shield-alt"></i> Pasivas Innatas
                  </div>
                  <div class="gene-cards-grid">
                  <?php foreach ($displayed_pasivas as $p):
                      $is_prim = ($p['type'] === 'primaria');
                      echo render_perk_card($p,
                          $is_prim ? 'passive-primary' : 'passive-secondary',
                          $is_prim ? 'gene-card-icon--primary' : 'gene-card-icon--secondary',
                          $is_prim ? 'PRIMARIA' : 'SECUNDARIA',
                          $is_prim ? '#10b981' : '#f59e0b'
                      );
                  endforeach; ?>
                  </div>
                  <?php endif; ?>

                  <?php if (!empty($racial_display)): ?>
                  <div class="pj-linaje-section-title pj-linaje-section-title--indigo">
                      <i class="fas fa-dna"></i> Linaje Racial
                  </div>
                  <div class="gene-cards-grid">
                  <?php foreach ($racial_display as $p):
                      echo render_perk_card($p, 'perk-racial',
                          'gene-card-icon--racial',
                          'RACIAL', '#C62828');
                  endforeach; ?>
                  </div>
                  <?php endif; ?>

                  <?php if (!empty($general_display)): ?>
                  <div class="pj-linaje-section-title pj-linaje-section-title--purple">
                      <i class="fas fa-star"></i> Linaje General
                  </div>
                  <div class="gene-cards-grid">
                  <?php foreach ($general_display as $p):
                      echo render_perk_card($p, 'perk-general',
                          'gene-card-icon--general',
                          'GENERAL', '#4A148C');
                  endforeach; ?>
                  </div>
                  <?php endif; ?>

                  <?php if (empty($displayed_pasivas) && empty($racial_display) && empty($general_display)): ?>
                  <div class="pj-linaje-empty">
                      <i class="fas fa-scroll pj-linaje-empty__icon--indigo"></i>
                      <h4>Sin Perks de Linaje</h4>
                      <p>Este personaje no tiene perks de linaje asignados todavía.</p>
                  </div>
                  <?php endif; ?>

              <?php else: ?>
                  <!-- Legacy v1: show banner + old gene names -->
                  <div class="pj-linaje-legacy-notice">
                      <i class="fas fa-info-circle"></i>
                      <div>
                          <div class="pj-linaje-legacy-notice__title">Ficha en formato antiguo</div>
                          <div class="pj-linaje-legacy-notice__text">El sistema de Linaje de este personaje será actualizado en la próxima revisión de ficha.</div>
                      </div>
                  </div>
                  <?php if (empty($char['linaje']['geneNames'])): ?>
                  <div class="pj-linaje-empty">
                      <i class="fas fa-dna pj-linaje-empty__icon--purple"></i>
                      <h4>Sin datos de Linaje</h4>
                      <p>Este personaje no tiene genes registrados en el sistema antiguo.</p>
                  </div>
                  <?php else: ?>
                  <div class="gene-cards-container">
                      <?php foreach ($char['linaje']['geneNames'] as $geneName): ?>
                      <div class="gene-card perk-racial">
                          <div class="gene-card-icon pj-linaje-perk-icon gene-card-icon--racial"><i class="fas fa-dna" data-icon-color="var(--accent-indigo)"></i></div>
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


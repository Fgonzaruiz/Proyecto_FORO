<div id="pjTab_haki" class="pj-preview-tab-content">
  <?php
  global $db;
  $prefix = TABLE_PREFIX;
  $char_id = (int)$char['id'];
  $haki_rows = [];
  $haki_res = $db->query("SELECT * FROM {$prefix}game_haki_progress WHERE character_id = {$char_id}");
  while ($row = $db->fetch_array($haki_res)) {
      $haki_rows[$row['haki_type']] = $row;
  }

  $haki_obs = $haki_rows['kenbunshoku'] ?? ['nivel' => 0, 'usos_total' => 0, 'status' => 'activo', 'pp_reservados' => 0];
  $haki_arm = $haki_rows['busoshoku'] ?? ['nivel' => 0, 'usos_total' => 0, 'status' => 'activo', 'pp_reservados' => 0];
  $haki_con = $haki_rows['haoshoku'] ?? ['nivel' => 0, 'usos_total' => 0, 'status' => 'activo', 'pp_reservados' => 0];

  // Cargar stats y ESP efectivo
  $raw_stats_json = $row['stats_json'] ?? $char['stats_json'] ?? '';
  $char_stats = !empty($raw_stats_json) ? json_decode($raw_stats_json, true) : [];
  if (!is_array($char_stats)) { $char_stats = []; }
  $char_stat_ctx = game_build_stat_context(Game\Shared\StatScale::sanitizeRanks($char_stats), (string)($char['race_name'] ?? ''));
  $char_esp = (int)($char_stat_ctx['effective_ranks']['esp'] ?? 1);

  // Cargar nivel y PP
  $raw_data_json = $row['data_json'] ?? $char['data_json'] ?? '';
  $char_data = !empty($raw_data_json) ? json_decode($raw_data_json, true) : [];
  if (!is_array($char_data)) { $char_data = []; }
  Game\Application\Services\CharacterProgression::syncLinajeBonusPp($char_data, (string)($char['race_name'] ?? ''));
  Game\Application\Services\CharacterProgression::normalize($char_data);
  $char_nivel = game_get_character_nivel($char_data);
  $char_pp = (int)($char_data['pp'] ?? 0);

  // Mapeos de nombres de niveles
  $labels_obs = [
      0 => 'No manifestado',
      1 => 'Latente (obs_latente)',
      2 => 'Básico (obs_basico)',
      3 => 'Medio (obs_medio)',
      4 => 'Avanzado (obs_avanzado)',
      5 => 'Futuro (obs_futuro)'
  ];
  $labels_arm = [
      0 => 'No manifestado',
      1 => 'Latente (arm_latente)',
      2 => 'Básico (arm_basico)',
      3 => 'Medio (arm_medio)',
      4 => 'Interno (arm_interno)',
      5 => 'Supremo (arm_supremo)'
  ];
  $labels_con = [
      0 => 'No manifestado',
      1 => 'Latente (rey_latente)',
      2 => 'Básico (rey_basico)',
      3 => 'Medio (rey_medio)',
      4 => 'Avanzado (rey_avanzado)',
      5 => 'Supremo (rey_supremo)'
  ];

  $reqs_normal = [
      1 => ['esp' => 2, 'nivel' => 1, 'usos' => 0, 'coste' => 100],
      2 => ['esp' => 3, 'nivel' => 2, 'usos' => 5, 'coste' => 300],
      3 => ['esp' => 4, 'nivel' => 3, 'usos' => 15, 'coste' => 700],
      4 => ['esp' => 5, 'nivel' => 4, 'usos' => 35, 'coste' => 1500],
      5 => ['esp' => 6, 'nivel' => 5, 'usos' => 60, 'coste' => 3000]
  ];

  $reqs_conq = [
      2 => ['esp' => 4, 'nivel' => 4, 'usos' => 10, 'coste' => 500],
      3 => ['esp' => 5, 'nivel' => 5, 'usos' => 25, 'coste' => 1200],
      4 => ['esp' => 6, 'nivel' => 5, 'usos' => 45, 'coste' => 2500],
      5 => ['esp' => 6, 'nivel' => 6, 'usos' => 70, 'coste' => 5000]
  ];
  ?>

  <div class="haki-slots-bar">
      <div class="haki-slots-title">
          <i class="fas fa-bahai"></i> Progresión de Haki
      </div>
      <div class="haki-slots-pp">
          Nivel: <strong>Nivel <?= $char_nivel ?></strong> | Saldo: <strong><?= $char_pp ?> PP</strong>
      </div>
  </div>

  <div class="haki-grid">

      <!-- 1. HAKI DE OBSERVACIÓN -->
      <?php
      $obs_level = (int)$haki_obs['nivel'];
      $obs_usos = (int)$haki_obs['usos_total'];
      $obs_status = $haki_obs['status'];
      $obs_target = $obs_level + 1;
      $obs_req = $reqs_normal[$obs_target] ?? null;
      ?>
      <div class="haki-card haki-kenbunshoku">
          <div class="haki-card-header">
              <div class="haki-card-icon">
                  <i class="fas fa-eye"></i>
              </div>
              <div class="haki-card-title-group">
                  <span class="haki-card-name">Kenbunshoku</span>
                  <span class="haki-card-level">Haki de Observación</span>
              </div>
          </div>

          <div class="haki-desc-section">
              <strong>Nivel actual:</strong> <?= $labels_obs[$obs_level] ?><br>
              <em>Permite percibir presencias, intenciones y anticipar movimientos a su alrededor.</em>
          </div>

          <?php if ($obs_level > 0): ?>
          <div class="haki-progress-section">
              <div class="haki-progress-labels">
                  <span>Usos acumulados</span>
                  <span><?= $obs_usos ?><?= ($obs_req) ? ' / ' . $obs_req['usos'] : '' ?></span>
              </div>
              <div class="haki-progress-bar-bg">
                  <?php
                  $percent = 100;
                  if ($obs_req && $obs_req['usos'] > 0) {
                      $percent = min(100, (int)floor(($obs_usos / $obs_req['usos']) * 100));
                  }
                  ?>
                  <div class="haki-progress-bar-fill" data-width="<?= $percent ?>"></div>
              </div>
          </div>
          <?php endif; ?>

          <?php if ($obs_req): ?>
              <div class="haki-reqs-section">
                  <span class="haki-req-title"><i class="fas fa-list-check"></i> Requisitos Nivel <?= $obs_target ?></span>
                  <?php
                  $esp_ok = ($char_esp >= $obs_req['esp']);
                  $lvl_ok = ($char_nivel >= $obs_req['nivel']);
                  $use_ok = ($obs_usos >= $obs_req['usos']);
                  $pp_ok = ($char_pp >= $obs_req['coste']);
                  $all_ok = ($esp_ok && $lvl_ok && $use_ok && $pp_ok);
                  ?>
                  <div class="haki-req-item <?= $esp_ok ? 'req-ok' : 'req-fail' ?>">
                      <span>Espíritu: <?= Game\Shared\StatScale::rankDisplayLabel($obs_req['esp']) ?></span>
                      <i class="fas <?= $esp_ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                  </div>
                  <div class="haki-req-item <?= $lvl_ok ? 'req-ok' : 'req-fail' ?>">
                      <span>Nivel del PJ: Nivel <?= $obs_req['nivel'] ?></span>
                      <i class="fas <?= $lvl_ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                  </div>
                  <?php if ($obs_req['usos'] > 0): ?>
                  <div class="haki-req-item <?= $use_ok ? 'req-ok' : 'req-fail' ?>">
                      <span>Usos de Haki: <?= $obs_req['usos'] ?> usos</span>
                      <i class="fas <?= $use_ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                  </div>
                  <?php endif; ?>
                  <div class="haki-req-item <?= $pp_ok ? 'req-ok' : 'req-fail' ?>">
                      <span>Coste PP: <?= $obs_req['coste'] ?> PP</span>
                      <i class="fas <?= $pp_ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                  </div>
              </div>
          <?php endif; ?>

          <div class="haki-action-btn-container">
              <?php if ($obs_status === 'pendiente_subida'): ?>
                  <div class="haki-pending-banner">
                      <span><i class="fas fa-hourglass-half"></i> Petición en revisión</span>
                      <span>Reservados: <?= (int)$haki_obs['pp_reservados'] ?> PP</span>
                      <?php if ($active_char_is_staff): ?>
                          <div class="haki-pending-actions">
                              <button class="rpg-btn rpg-btn--primary" onclick="resolveHakiUpgrade(<?= $char_id ?>, 'kenbunshoku', 'aprobar')">Aprobar</button>
                              <button class="rpg-btn" onclick="resolveHakiUpgrade(<?= $char_id ?>, 'kenbunshoku', 'rechazar')">Rechazar</button>
                          </div>
                      <?php endif; ?>
                  </div>
              <?php elseif ($obs_level >= 5): ?>
                  <button class="haki-action-btn rpg-btn" disabled>Nivel máximo alcanzado</button>
              <?php elseif ($is_active_pj): ?>
                  <button class="haki-action-btn rpg-btn <?= $all_ok ? 'rpg-btn--primary' : '' ?>"
                          <?= $all_ok ? '' : 'disabled' ?>
                          onclick="requestHakiUpgrade(<?= $char_id ?>, 'kenbunshoku')">
                      Solicitar Nivel <?= $obs_target ?>
                  </button>
              <?php endif; ?>
          </div>
      </div>


      <!-- 2. HAKI DE ARMAMENTO -->
      <?php
      $arm_level = (int)$haki_arm['nivel'];
      $arm_usos = (int)$haki_arm['usos_total'];
      $arm_status = $haki_arm['status'];
      $arm_target = $arm_level + 1;
      $arm_req = $reqs_normal[$arm_target] ?? null;
      ?>
      <div class="haki-card haki-busoshoku">
          <div class="haki-card-header">
              <div class="haki-card-icon">
                  <i class="fas fa-shield-halved"></i>
              </div>
              <div class="haki-card-title-group">
                  <span class="haki-card-name">Busoshoku</span>
                  <span class="haki-card-level">Haki de Armamento</span>
              </div>
          </div>

          <div class="haki-desc-section">
              <strong>Nivel actual:</strong> <?= $labels_arm[$arm_level] ?><br>
              <em>Permite endurecer el cuerpo o imbuir armas para golpear con contundencia y dañar Logias.</em>
          </div>

          <?php if ($arm_level > 0): ?>
          <div class="haki-progress-section">
              <div class="haki-progress-labels">
                  <span>Usos acumulados</span>
                  <span><?= $arm_usos ?><?= ($arm_req) ? ' / ' . $arm_req['usos'] : '' ?></span>
              </div>
              <div class="haki-progress-bar-bg">
                  <?php
                  $percent = 100;
                  if ($arm_req && $arm_req['usos'] > 0) {
                      $percent = min(100, (int)floor(($arm_usos / $arm_req['usos']) * 100));
                  }
                  ?>
                  <div class="haki-progress-bar-fill" data-width="<?= $percent ?>"></div>
              </div>
          </div>
          <?php endif; ?>

          <?php if ($arm_req): ?>
              <div class="haki-reqs-section">
                  <span class="haki-req-title"><i class="fas fa-list-check"></i> Requisitos Nivel <?= $arm_target ?></span>
                  <?php
                  $esp_ok = ($char_esp >= $arm_req['esp']);
                  $lvl_ok = ($char_nivel >= $arm_req['nivel']);
                  $use_ok = ($arm_usos >= $arm_req['usos']);
                  $pp_ok = ($char_pp >= $arm_req['coste']);
                  $all_ok = ($esp_ok && $lvl_ok && $use_ok && $pp_ok);
                  ?>
                  <div class="haki-req-item <?= $esp_ok ? 'req-ok' : 'req-fail' ?>">
                      <span>Espíritu: <?= Game\Shared\StatScale::rankDisplayLabel($arm_req['esp']) ?></span>
                      <i class="fas <?= $esp_ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                  </div>
                  <div class="haki-req-item <?= $lvl_ok ? 'req-ok' : 'req-fail' ?>">
                      <span>Nivel del PJ: Nivel <?= $arm_req['nivel'] ?></span>
                      <i class="fas <?= $lvl_ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                  </div>
                  <?php if ($arm_req['usos'] > 0): ?>
                  <div class="haki-req-item <?= $use_ok ? 'req-ok' : 'req-fail' ?>">
                      <span>Usos de Haki: <?= $arm_req['usos'] ?> usos</span>
                      <i class="fas <?= $use_ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                  </div>
                  <?php endif; ?>
                  <div class="haki-req-item <?= $pp_ok ? 'req-ok' : 'req-fail' ?>">
                      <span>Coste PP: <?= $arm_req['coste'] ?> PP</span>
                      <i class="fas <?= $pp_ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                  </div>
              </div>
          <?php endif; ?>

          <div class="haki-action-btn-container">
              <?php if ($arm_status === 'pendiente_subida'): ?>
                  <div class="haki-pending-banner">
                      <span><i class="fas fa-hourglass-half"></i> Petición en revisión</span>
                      <span>Reservados: <?= (int)$haki_arm['pp_reservados'] ?> PP</span>
                      <?php if ($active_char_is_staff): ?>
                          <div class="haki-pending-actions">
                              <button class="rpg-btn rpg-btn--primary" onclick="resolveHakiUpgrade(<?= $char_id ?>, 'busoshoku', 'aprobar')">Aprobar</button>
                              <button class="rpg-btn" onclick="resolveHakiUpgrade(<?= $char_id ?>, 'busoshoku', 'rechazar')">Rechazar</button>
                          </div>
                      <?php endif; ?>
                  </div>
              <?php elseif ($arm_level >= 5): ?>
                  <button class="haki-action-btn rpg-btn" disabled>Nivel máximo alcanzado</button>
              <?php elseif ($is_active_pj): ?>
                  <button class="haki-action-btn rpg-btn <?= $all_ok ? 'rpg-btn--primary' : '' ?>"
                          <?= $all_ok ? '' : 'disabled' ?>
                          onclick="requestHakiUpgrade(<?= $char_id ?>, 'busoshoku')">
                      Solicitar Nivel <?= $arm_target ?>
                  </button>
              <?php endif; ?>
          </div>
      </div>


      <!-- 3. HAKI DE CONQUISTADOR -->
      <?php
      $con_level = (int)$haki_con['nivel'];
      $con_usos = (int)$haki_con['usos_total'];
      $con_status = $haki_con['status'];
      $con_target = $con_level + 1;
      $con_req = $reqs_conq[$con_target] ?? null;
      ?>
      <div class="haki-card haki-haoshoku">
          <div class="haki-card-header">
              <div class="haki-card-icon">
                  <i class="fas fa-crown"></i>
              </div>
              <div class="haki-card-title-group">
                  <span class="haki-card-name">Haoshoku</span>
                  <span class="haki-card-level">Haki del Conquistador</span>
              </div>
          </div>

          <div class="haki-desc-section">
              <strong>Nivel actual:</strong> <?= $labels_con[$con_level] ?><br>
              <em>La cualidad de imponer la propia voluntad sobre el oponente, desmayando o amedrentando masas.</em>
          </div>

          <?php if ($con_level > 0): ?>
          <div class="haki-progress-section">
              <div class="haki-progress-labels">
                  <span>Usos acumulados</span>
                  <span><?= $con_usos ?><?= ($con_req) ? ' / ' . $con_req['usos'] : '' ?></span>
              </div>
              <div class="haki-progress-bar-bg">
                  <?php
                  $percent = 100;
                  if ($con_req && $con_req['usos'] > 0) {
                      $percent = min(100, (int)floor(($con_usos / $con_req['usos']) * 100));
                  }
                  ?>
                  <div class="haki-progress-bar-fill" data-width="<?= $percent ?>"></div>
              </div>
          </div>
          <?php endif; ?>

          <?php if ($con_level === 0): ?>
              <!-- No despertado -->
              <div class="haki-reqs-section">
                  <span class="haki-req-title"><i class="fas fa-wand-magic-sparkles"></i> Despertar del Conquistador</span>
                  <p class="smalltext haki-reqs-hint">
                      El Haki del Conquistador es una cualidad única e innata. No se puede entrenar ni solicitar directamente.
                      Requiere un Espíritu de rango <strong>A</strong>, Nivel <strong>4</strong> y <strong>500 PP</strong>.
                      Tú mismo o el staff podéis ejecutar una tirada especial de despertar para manifestarlo.
                  </p>
                  <?php
                  $can_roll_esp = ($char_esp >= 4);
                  $can_roll_lvl = ($char_nivel >= 4);
                  $can_roll_pp = ($char_pp >= 500);
                  $can_roll_all = ($can_roll_esp && $can_roll_lvl && $can_roll_pp);
                  ?>
              </div>
              
              <div class="haki-action-btn-container">
                  <?php if ($active_char_is_staff || $is_active_pj): ?>
                      <button class="haki-action-btn rpg-btn <?= $can_roll_all ? 'rpg-btn--primary' : '' ?>"
                              <?= $can_roll_all ? '' : 'disabled' ?>
                              onclick="rollHaoshokuAwakening(<?= $char_id ?>)">
                          Lanzar Despertar (500 PP)
                      </button>
                      <?php if (!$can_roll_all): ?>
                          <span class="smalltext haki-error-text">El personaje no cumple los requisitos para tirar (ESP >= A, Nivel >= 4, PP >= 500).</span>
                      <?php endif; ?>
                  <?php else: ?>
                      <button class="haki-action-btn rpg-btn" disabled>No manifestado</button>
                  <?php endif; ?>
              </div>

          <?php else: ?>
              <!-- Despertado y progresando -->
              <?php if ($con_req): ?>
                  <div class="haki-reqs-section">
                      <span class="haki-req-title"><i class="fas fa-list-check"></i> Requisitos Nivel <?= $con_target ?></span>
                      <?php
                      $esp_ok = ($char_esp >= $con_req['esp']);
                      $lvl_ok = ($char_nivel >= $con_req['nivel']);
                      $use_ok = ($con_usos >= $con_req['usos']);
                      $pp_ok = ($char_pp >= $con_req['coste']);
                      $all_ok = ($esp_ok && $lvl_ok && $use_ok && $pp_ok);
                      ?>
                      <div class="haki-req-item <?= $esp_ok ? 'req-ok' : 'req-fail' ?>">
                          <span>Espíritu: <?= Game\Shared\StatScale::rankDisplayLabel($con_req['esp']) ?></span>
                          <i class="fas <?= $esp_ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                      </div>
                      <div class="haki-req-item <?= $lvl_ok ? 'req-ok' : 'req-fail' ?>">
                          <span>Nivel del PJ: Nivel <?= $con_req['nivel'] ?></span>
                          <i class="fas <?= $lvl_ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                      </div>
                      <div class="haki-req-item <?= $use_ok ? 'req-ok' : 'req-fail' ?>">
                          <span>Usos de Haki: <?= $con_req['usos'] ?> usos</span>
                          <i class="fas <?= $use_ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                      </div>
                      <div class="haki-req-item <?= $pp_ok ? 'req-ok' : 'req-fail' ?>">
                          <span>Coste PP: <?= $con_req['coste'] ?> PP</span>
                          <i class="fas <?= $pp_ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                      </div>
                  </div>
              <?php endif; ?>

              <div class="haki-action-btn-container">
                  <?php if ($con_status === 'pendiente_subida'): ?>
                      <div class="haki-pending-banner">
                          <span><i class="fas fa-hourglass-half"></i> Petición en revisión</span>
                          <span>Reservados: <?= (int)$haki_con['pp_reservados'] ?> PP</span>
                          <?php if ($active_char_is_staff): ?>
                              <div class="haki-pending-actions">
                                  <button class="rpg-btn rpg-btn--primary" onclick="resolveHakiUpgrade(<?= $char_id ?>, 'haoshoku', 'aprobar')">Aprobar</button>
                                  <button class="rpg-btn" onclick="resolveHakiUpgrade(<?= $char_id ?>, 'haoshoku', 'rechazar')">Rechazar</button>
                              </div>
                          <?php endif; ?>
                      </div>
                  <?php elseif ($con_level >= 5): ?>
                      <button class="haki-action-btn rpg-btn" disabled>Nivel máximo alcanzado</button>
                  <?php elseif ($is_active_pj): ?>
                      <button class="haki-action-btn rpg-btn <?= $all_ok ? 'rpg-btn--primary' : '' ?>"
                              <?= $all_ok ? '' : 'disabled' ?>
                              onclick="requestHakiUpgrade(<?= $char_id ?>, 'haoshoku')">
                          Solicitar Nivel <?= $con_target ?>
                      </button>
                  <?php endif; ?>
              </div>
          <?php endif; ?>
      </div>

  </div>
</div>

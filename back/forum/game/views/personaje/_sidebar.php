      <!-- LEFT COLUMN (Avatar & Stats) -->
      <div class="pj-sidebar">
          <div class="pj-sidebar-avatar" style="--sidebar-avatar:url('<?= htmlspecialchars($char['avatar'] ?: 'https://placehold.co/320x450') ?>')"></div>
          
          <div class="pj-sidebar-body">
              <h2 class="pj-sidebar-name"><?= htmlspecialchars($char['name']) ?></h2>
              
              <div class="pj-sidebar-badges">
                  <?php if ($char['status'] === 'aprobada'): ?>
                      <span class="pj-badge pj-badge--ok"><i class="fas fa-check-circle"></i> Aprobada</span>
                  <?php elseif ($char['status'] === 'revision'): ?>
                      <span class="pj-badge pj-badge--warn"><i class="fas fa-sync-alt"></i> En Revisión</span>
                  <?php elseif ($char['status'] === 'rechazada'): ?>
                      <span class="pj-badge pj-badge--err"><i class="fas fa-times-circle"></i> Rechazada</span>
                  <?php else: ?>
                      <span class="pj-badge pj-badge--err"><i class="fas fa-clock"></i> Pendiente</span>
                  <?php endif; ?>
                  <span class="pj-badge pj-badge--faction"><i class="fas fa-flag"></i> Facci&oacute;n</span>
                  <span class="pj-badge pj-badge--rank"><i class="fas fa-medal"></i> <?= htmlspecialchars($char['rango'] ?: 'Sin Rango') ?></span>
                  <?php if ($char['is_staff']): ?>
                    <span class="pj-badge pj-badge--staff"><i class="fas fa-star"></i> Staff</span>
                  <?php endif; ?>
                  <span class="pj-badge pj-badge--level"><i class="fas fa-level-up-alt"></i> Nivel <?= (int)($pj_progression['nivel'] ?? 1) ?></span>
              </div>
              
              <div class="pj-sidebar-info">
                  <div class="pj-info-row pj-info-row--border">
                      <i class="fas fa-shield-alt pj-info-icon"></i>
                      <div>
                          <div class="pj-info-label">Arquetipo B&eacute;lico</div>
                          <div class="pj-info-value"><?= htmlspecialchars($char['arquetipo']) ?></div>
                      </div>
                  </div>
                  <div class="pj-info-row">
                      <i class="fas fa-anchor pj-info-icon"></i>
                      <div>
                          <div class="pj-info-label">Oficio</div>
                          <div class="pj-info-value"><?= htmlspecialchars($char['job_name'] ?: 'Ninguno') ?></div>
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
              
              <div class="pj-vitals-row">
                  <div class="pj-vital pj-vital--pv">
                      <div class="pj-vital__label">Puntos de Vida (PV)</div>
                      <div class="pj-vital__value"><?= $pv ?></div>
                  </div>
                  <div class="pj-vital pj-vital--pe">
                      <div class="pj-vital__label">Puntos de Energía (PE)</div>
                      <div class="pj-vital__value"><?= $pe ?></div>
                  </div>
              </div>

              <h3 class="pj-stats-heading">Atributos Base</h3>
              <div class="rpg-preview-stat-row">
                  <div class="rpg-preview-stat-label"><span>FUERZA (FUE)</span><span><?= $fue ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill rpg-preview-stat-fill--fue" style="--stat-pct:<?= min(100, $fue * 10) ?>%"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div class="rpg-preview-stat-label"><span>AGILIDAD (AGI)</span><span><?= $agi ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill rpg-preview-stat-fill--agi" style="--stat-pct:<?= min(100, $agi * 10) ?>%"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div class="rpg-preview-stat-label"><span>DESTREZA (DES)</span><span><?= $des ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill rpg-preview-stat-fill--des" style="--stat-pct:<?= min(100, $des * 10) ?>%"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div class="rpg-preview-stat-label"><span>INSTINTO (INST)</span><span><?= $inst ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill rpg-preview-stat-fill--inst" style="--stat-pct:<?= min(100, $inst * 10) ?>%"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div class="rpg-preview-stat-label"><span>ESPÍRITU (ESP)</span><span><?= $esp ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill rpg-preview-stat-fill--esp" style="--stat-pct:<?= min(100, $esp * 10) ?>%"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div class="rpg-preview-stat-label"><span>INTELECTO (INT)</span><span><?= $int ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill rpg-preview-stat-fill--int" style="--stat-pct:<?= min(100, $int * 10) ?>%"></div></div>
              </div>
          </div>
      </div>
      

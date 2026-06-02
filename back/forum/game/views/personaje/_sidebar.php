      <!-- LEFT COLUMN (Avatar & Stats) -->
      <div style="width: 320px; background: var(--bg-surface); border-right: 1px solid var(--border-color); display:flex; flex-direction:column; flex-shrink: 0;">
          <div style="width:100%; height:450px; min-height:450px; background-size:cover; background-position:center; background-image:url('<?= htmlspecialchars($char['avatar'] ?: 'https://placehold.co/320x450') ?>'); border-bottom: 2px solid var(--accent-indigo);"></div>
          
          <div style="padding: 20px;">
              <h2 style="font-family:var(--font-heading); font-size:24px; color:var(--text-primary); margin-bottom:10px; text-align:center;"><?= htmlspecialchars($char['name']) ?></h2>
              
              <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap; margin-bottom: 15px;">
                  <?php if ($char['status'] === 'aprobada'): ?>
                      <span style="background:rgba(16, 185, 129, 0.1); color:#10b981; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-check-circle"></i> Aprobada</span>
                  <?php elseif ($char['status'] === 'revision'): ?>
                      <span style="background:rgba(245, 158, 11, 0.1); color:#f59e0b; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-sync-alt"></i> En Revisi├│n</span>
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
                  <span style="background:rgba(245,158,11,0.12); color:#f59e0b; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-level-up-alt"></i> Nivel <?= (int)($pj_progression['nivel'] ?? 1) ?></span>
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
              
              <!-- Puntos de Vida y Energ├¡a -->
              <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                  <div style="flex: 1; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: var(--radius-md); padding: 10px; text-align: center;">
                      <div style="font-size: 10px; color: #f87171; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px;">Puntos de Vida (PV)</div>
                      <div style="font-size: 20px; font-weight: 800; color: #ef4444; margin-top: 4px;"><?= $pv ?></div>
                  </div>
                  <div style="flex: 1; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: var(--radius-md); padding: 10px; text-align: center;">
                      <div style="font-size: 10px; color: #60a5fa; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px;">Puntos de Energ├¡a (PE)</div>
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
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>ESP├ìRITU (ESP)</span><span><?= $esp ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="style=width:0%; width:<?= min(100, $esp * 10) ?>%; background:linear-gradient(90deg,#ec4899,#db2777);"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>INTELECTO (INT)</span><span><?= $int ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="width:<?= min(100, $int * 10) ?>%; background:linear-gradient(90deg,#f59e0b,#d97706);"></div></div>
              </div>
          </div>
      </div>
      

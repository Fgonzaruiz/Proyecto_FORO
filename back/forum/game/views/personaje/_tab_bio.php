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


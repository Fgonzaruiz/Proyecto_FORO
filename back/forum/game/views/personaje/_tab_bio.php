          <div id="pjTab_bio" class="pj-preview-tab-content active">
              <div class="pj-bio-meta-grid">
                  <div class="pj-bio-meta-item"><strong>Edad:</strong> <?= htmlspecialchars($char['age']) ?></div>
                  <div class="pj-bio-meta-item"><strong>Origen:</strong> <?= htmlspecialchars($char['origin']) ?></div>
                  <div class="pj-bio-meta-item"><strong>Raza:</strong> <?= htmlspecialchars($char['race_name']) ?></div>
                  <div class="pj-bio-meta-item"><strong>PB:</strong> <?= htmlspecialchars($char['pb']) ?></div>
              </div>
              
              <h3 class="pj-tab-section-heading">Apariencia F&iacute;sica</h3>
              <div class="pj-scroll-box pj-scroll-box--bio">
                  <?= nl2br(htmlspecialchars($char['physique'] ?: 'Sin registrar.')) ?>
              </div>
              
              <h3 class="pj-tab-section-heading">Perfil Psicol&oacute;gico</h3>
              <div class="pj-scroll-box pj-scroll-box--bio">
                  <?= nl2br(htmlspecialchars($char['psychology'] ?: ($char['desc'] ?: 'Sin historia registrada.'))) ?>
              </div>
              
              <h3 class="pj-tab-section-heading">Extras y Notas</h3>
              <div class="pj-scroll-box pj-scroll-box--bio">
                  <?= nl2br(htmlspecialchars($char['extras'] ?: ($char['details'] ?: 'Sin notas extras.'))) ?>
              </div>
          </div>

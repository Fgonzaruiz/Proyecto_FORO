          <div class="pj-preview-tabs hxh-tabs">
              <div class="pj-preview-tab active" onclick="switchPjTab('portada', this)"><i class="fas fa-id-card"></i> Portada</div>
              <div class="pj-preview-tab" onclick="switchPjTab('expediente', this)"><i class="fas fa-book-open"></i> Expediente</div>
              <div class="pj-preview-tab" onclick="switchPjTab('combate', this)"><i class="fas fa-fist-raised"></i> Combate</div>
              <div class="pj-preview-tab" onclick="switchPjTab('linaje', this)"><i class="fas fa-dna"></i> Factor Linaje</div>
              <div class="pj-preview-tab" onclick="switchPjTab('cronologia', this)"><i class="fas fa-calendar-alt"></i> Bit&aacute;cora</div>
              <?php if (game_has_nen_despierto((int)$char['id'])): ?>
              <div class="pj-preview-tab" onclick="switchPjTab('nen', this)"><i class="fas fa-hand-sparkles"></i> Nen</div>
              <?php endif; ?>
              <?php if ($can_view_private): ?>
              <div class="pj-preview-tab" onclick="switchPjTab('deck', this)"><i class="fas fa-layer-group"></i> Deck</div>
              <div class="pj-preview-tab" onclick="switchPjTab('gestion', this)"><i class="fas fa-cogs"></i> Gesti&oacute;n</div>
              <?php endif; ?>
          </div>

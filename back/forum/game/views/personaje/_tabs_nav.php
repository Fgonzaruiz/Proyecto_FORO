          <div class="pj-preview-tabs">
              <div class="pj-preview-tab active" onclick="switchPjTab('bio', this)"><i class="fas fa-file-alt"></i> Biograf&iacute;a</div>
              <div class="pj-preview-tab" onclick="switchPjTab('historia', this)"><i class="fas fa-book-open"></i> Historia</div>
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

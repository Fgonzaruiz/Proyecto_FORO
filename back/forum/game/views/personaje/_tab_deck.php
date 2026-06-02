          <div id="pjTab_deck" class="pj-preview-tab-content">
              <div id="rpg-character-deck-container" data-char-id="<?= $char['id'] ?>" data-is-owner="<?= (int)($char['user_id'] == $mybb->user['uid']) ?>">
                  <div style="text-align:center; padding: 40px; color: var(--text-muted);">
                      <i class="fas fa-circle-notch fa-spin" style="font-size: 24px; margin-bottom: 10px;"></i><br>
                      Cargando Deck...
                  </div>
              </div>
          </div>

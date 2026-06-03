          <div id="pjTab_deck" class="pj-preview-tab-content">
              <div id="rpg-character-deck-container" data-char-id="<?= $char['id'] ?>" data-is-owner="<?= (int)($char['user_id'] == $mybb->user['uid']) ?>">
                  <div class="rpg-deck-empty">
                      <i class="fas fa-circle-notch fa-spin rpg-deck-empty__icon"></i>
                      Cargando Deck...
                  </div>
              </div>
          </div>

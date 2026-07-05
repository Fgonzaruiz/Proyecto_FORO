<script>
window.PERSONAJE_PAGE_CONFIG = <?= json_encode([
  'bburl' => rtrim($bb ?? $mybb->settings['bburl'] ?? '', '/'),
  'canEdit' => !empty($can_edit),
  'characterId' => (int)($char['id'] ?? 0),
  'tagColors' => $tag_colors ?? [],
  'catColors' => $cat_list_display ?? ['Pasado'=>'#8b5cf6','Presente'=>'#10b981','Mision'=>'#f59e0b','Evento'=>'#3b82f6','Trama'=>'#ef4444','Fic'=>'#ec4899','Off_Rol'=>'#6b7280'],
  'cronologia' => [
    'relaciones' => $char['cronologia']['relaciones'] ?? [],
    'groups' => $char['cronologia']['groups'] ?? [],
    'connections' => $char['cronologia']['connections'] ?? [],
    'diario' => $char['cronologia']['diario'] ?? [],
  ],
  'progression' => $pj_progression ?? null,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= rtrim($bb ?? $mybb->settings['bburl'], '/') ?>/jscripts/game/personaje_page.js?v=5"></script>
<script src="<?= rtrim($bb ?? $mybb->settings['bburl'], '/') ?>/jscripts/game/personaje_inventory.js?v=2"></script>

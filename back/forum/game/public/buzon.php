<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    header('Location: ' . $mybb->settings['bburl'] . '/member.php?action=login');
    exit;
}

$bb = $mybb->settings['bburl'];
$activePjId = game_get_active_pj_id($uid);
$activePjName = '';
if ($activePjId > 0) {
    $prefix = TABLE_PREFIX;
    $pjQ = $db->query("SELECT name FROM {$prefix}game_personajes WHERE id = {$activePjId} LIMIT 1");
    $pjRow = $db->fetch_array($pjQ);
    $activePjName = $pjRow ? (string)$pjRow['name'] : '';
}

$initialTab = 'inbox';
if (isset($_GET['compose'])) {
    $initialTab = 'compose';
} elseif (isset($_GET['sent'])) {
    $initialTab = 'sent';
}

$readId = (int)($_GET['read'] ?? 0);
$threadId = (int)($_GET['thread'] ?? 0);
$toCharacterId = (int)($_GET['to'] ?? 0);
$toCharacterName = '';
if ($toCharacterId > 0) {
    $prefix = TABLE_PREFIX;
    $toQ = $db->query("SELECT name FROM {$prefix}game_personajes WHERE id = {$toCharacterId} LIMIT 1");
    $toRow = $db->fetch_array($toQ);
    $toCharacterName = $toRow ? (string)$toRow['name'] : '';
}

ob_start();
?>
<div class="buzon-page">
    <div class="buzon-page-header">
        <div>
            <h1 class="buzon-page-title"><i class="fas fa-inbox"></i> Buzón</h1>
            <?php if ($activePjName !== ''): ?>
            <p class="buzon-page-sub">Mensajes del personaje <strong><?= htmlspecialchars($activePjName) ?></strong></p>
            <?php else: ?>
            <p class="buzon-page-sub buzon-page-sub--warn">Selecciona un personaje activo para usar el buzón.</p>
            <?php endif; ?>
        </div>
        <button type="button" class="rpg-btn--primary buzon-compose-btn" id="buzon-open-compose" <?= $activePjId <= 0 ? 'disabled' : '' ?>>
            <i class="fas fa-pen"></i> Redactar
        </button>
    </div>

    <?php if ($activePjId <= 0): ?>
    <div class="buzon-empty">
        <i class="fas fa-user-slash"></i>
        <p>No tienes un personaje activo. Ve a <a href="<?= htmlspecialchars($bb) ?>/game/public/mis_personajes.php">Mis Personajes</a> y activa uno.</p>
    </div>
    <?php else: ?>
    <div class="buzon-layout">
        <aside class="buzon-sidebar">
            <nav class="buzon-nav">
                <button type="button" class="buzon-nav-btn<?= $initialTab === 'inbox' ? ' is-active' : '' ?>" data-tab="inbox">
                    <i class="fas fa-inbox"></i> Recibidos
                    <span class="buzon-nav-badge is-hidden" id="buzon-unread-badge">0</span>
                </button>
                <button type="button" class="buzon-nav-btn<?= $initialTab === 'sent' ? ' is-active' : '' ?>" data-tab="sent">
                    <i class="fas fa-paper-plane"></i> Enviados
                </button>
                <button type="button" class="buzon-nav-btn<?= $initialTab === 'compose' ? ' is-active' : '' ?>" data-tab="compose">
                    <i class="fas fa-pen"></i> Redactar
                </button>
            </nav>
        </aside>

        <main class="buzon-main">
            <section id="buzon-panel-inbox" class="buzon-panel<?= $initialTab === 'inbox' ? '' : ' is-hidden' ?>">
                <div id="buzon-inbox-list" class="buzon-list">
                    <div class="buzon-loading"><i class="fas fa-spinner fa-spin"></i> Cargando mensajes...</div>
                </div>
                <div id="buzon-inbox-pagination" class="buzon-pagination"></div>
            </section>

            <section id="buzon-panel-sent" class="buzon-panel<?= $initialTab === 'sent' ? '' : ' is-hidden' ?>">
                <div id="buzon-sent-list" class="buzon-list">
                    <div class="buzon-loading"><i class="fas fa-spinner fa-spin"></i> Cargando enviados...</div>
                </div>
                <div id="buzon-sent-pagination" class="buzon-pagination"></div>
            </section>

            <section id="buzon-panel-compose" class="buzon-panel<?= $initialTab === 'compose' ? '' : ' is-hidden' ?>">
                <form id="buzon-compose-form" class="buzon-compose-form">
                    <div class="rpg-form-group">
                        <label class="rpg-form-label" for="buzon-to-search">Destinatario (personaje)</label>
                        <input type="text" id="buzon-to-search" class="rpg-form-input" placeholder="Buscar por nombre..." autocomplete="off" value="<?= $toCharacterId > 0 ? '' : '' ?>">
                        <input type="hidden" id="buzon-to-id" name="to_character_id" value="<?= $toCharacterId > 0 ? $toCharacterId : '' ?>">
                        <div id="buzon-to-results" class="buzon-search-results is-hidden"></div>
                        <div id="buzon-to-selected" class="buzon-selected-recipient is-hidden"></div>
                    </div>
                    <div class="rpg-form-group">
                        <label class="rpg-form-label" for="buzon-subject">Asunto</label>
                        <input type="text" id="buzon-subject" name="subject" class="rpg-form-input" maxlength="255" required>
                    </div>
                    <div class="rpg-form-group">
                        <label class="rpg-form-label" for="buzon-body">Mensaje</label>
                        <textarea id="buzon-body" name="body" class="rpg-form-input rpg-form-input--resize" rows="8" required></textarea>
                    </div>
                    <div class="buzon-compose-actions">
                        <button type="submit" class="rpg-btn--primary"><i class="fas fa-paper-plane"></i> Enviar</button>
                    </div>
                    <div id="buzon-compose-msg" class="buzon-form-msg is-hidden"></div>
                </form>
            </section>

            <section id="buzon-panel-thread" class="buzon-panel is-hidden">
                <button type="button" class="rpg-back-btn buzon-back-btn" id="buzon-back-list"><i class="fas fa-arrow-left"></i> Volver</button>
                <article id="buzon-thread-content" class="buzon-read-card"></article>
                <form id="buzon-reply-form" class="buzon-reply-form">
                    <textarea id="buzon-reply-body" class="rpg-form-input rpg-form-input--resize" rows="4" placeholder="Escribe tu respuesta..." required></textarea>
                    <button type="submit" class="rpg-btn--primary"><i class="fas fa-reply"></i> Enviar respuesta</button>
                </form>
            </section>
        </main>
    </div>
    <?php endif; ?>
</div>

<script>
window.BUZON_CONFIG = {
    bburl: '<?= $bb ?>',
    ajaxBase: '<?= $bb ?>/game/ajax',
    activePjId: <?= (int)$activePjId ?>,
    initialTab: '<?= htmlspecialchars($initialTab) ?>',
    readId: <?= $readId ?>,
    threadId: <?= $threadId ?>,
    toCharacterId: <?= $toCharacterId ?>,
    toCharacterName: <?= json_encode($toCharacterName, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= rtrim($bb, '/') ?>/jscripts/game/buzon.js?v=2"></script>
<?php
$content = ob_get_clean();
game_render_page('Buzón', $content);

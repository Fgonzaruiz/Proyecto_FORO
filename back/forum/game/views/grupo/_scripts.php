<script>
window.CREW_CONFIG = {
    crewId: <?= $crew_id ?>,
    isLeader: <?= $is_leader ? 'true' : 'false' ?>,
    myPjId: <?= $my_pj_id ?>,
    ajaxUrl: '<?= htmlspecialchars($bburl) ?>/game/ajax/group_manage.php',
    tagColors: <?= json_encode($tag_colors) ?>
};
window.__PJ_NETWORK_DATA = <?= json_encode($crew_relations_data, JSON_UNESCAPED_UNICODE) ?>;
window.draftNetworkData = JSON.parse(JSON.stringify(window.__PJ_NETWORK_DATA));

function submitJoinRequest() {
    if (!confirm('¿Deseas solicitar unirte a este grupo?')) return;
    var fd = new FormData();
    fd.append('action', 'request_join');
    fd.append('crew_id', CFG.crewId || <?= $crew_id ?>);
    fetch('<?= htmlspecialchars($bburl) ?>/game/ajax/group_join.php', {
        method: 'POST',
        body: fd
    }).then(r => r.json()).then(res => {
        if (res.ok) {
            alert(res.message);
            location.reload();
        } else {
            alert(res.message || 'Error');
        }
    }).catch(e => alert("Error de conexión"));
}
</script>
<script src="<?= htmlspecialchars($bburl) ?>/jscripts/game/grupo_page.js?v=<?= time() ?>"></script>
<script src="<?= htmlspecialchars($bburl) ?>/jscripts/game/game_network.js?v=<?= time() ?>"></script>

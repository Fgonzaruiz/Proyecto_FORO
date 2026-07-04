function submitCreateCrew() {
    var name = document.getElementById('crew_name').value.trim();
    if (!name) {
        alert("El nombre es obligatorio.");
        return;
    }
    
    var fd = new FormData();
    fd.append('action', 'create');
    fd.append('name', name);
    fd.append('motto', document.getElementById('crew_motto').value.trim());
    fd.append('image_url', document.getElementById('crew_image').value.trim());
    
    var bburl = window.CREW_CONFIG ? window.CREW_CONFIG.bburl : '';
    fetch(bburl + '/game/ajax/crew_create.php', {
        method: 'POST',
        body: fd
    }).then(r => r.json()).then(res => {
        if (res.ok) {
            window.location.href = bburl + '/game/public/grupo.php?id=' + res.crew_id;
        } else {
            alert(res.message || 'Error');
        }
    }).catch(e => {
        alert("Error de conexión");
    });
}

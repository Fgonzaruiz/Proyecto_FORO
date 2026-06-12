async function enviarSuceso(e) {
    e.preventDefault();

    const url = document.getElementById('s_url').value;
    const title = document.getElementById('s_title').value;
    const desc = document.getElementById('s_desc').value;
    const btn = document.getElementById('sucesos-btn');
    const msg = document.getElementById('sucesos-msg');
    const envelope = document.getElementById('flying-envelope');

    if (!url || !title || !desc) return;

    btn.disabled = true;
    btn.innerHTML = 'Preparando... <i class="fas fa-spinner fa-spin"></i>';

    try {
        const formData = new FormData();
        formData.append('action', 'submit_suceso');
        formData.append('url', url);
        formData.append('title', title);
        formData.append('desc', desc);

        const response = await fetch(window.SUCESOS_CONFIG.bburl + '/game/ajax/sucesos.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Trigger Animation
            btn.innerHTML = '¡Enviado!';
            envelope.classList.add('fly-away');
            msg.style.display = 'block';
            msg.style.color = '#B89742';
            msg.innerHTML = '<i class="fas fa-check-circle"></i> La gaviota ha salido volando. ¡El Staff la leerá pronto!';
            
            setTimeout(() => {
                document.getElementById('sucesos-form').reset();
                envelope.classList.remove('fly-away');
                btn.disabled = false;
                btn.innerHTML = '<span>Enviar Otra Gaviota</span> <i class="fas fa-paper-plane"></i>';
                setTimeout(() => msg.style.display = 'none', 5000);
            }, 3000);

        } else {
            throw new Error(data.error || 'Error desconocido');
        }
    } catch (err) {
        msg.style.display = 'block';
        msg.style.color = '#D32F2F';
        msg.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + err.message;
        btn.disabled = false;
        btn.innerHTML = '<span>Reintentar</span> <i class="fas fa-paper-plane"></i>';
    }
}

(function () {
    'use strict';

    // Lista de imágenes para el banner rotativo (se selecciona una aleatoria al actualizar)
    var bannerImages = [
        'https://images.unsplash.com/photo-1578632767115-351597cf2477?q=80&w=1200&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1542838132-92c53300491e?q=80&w=1200&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1509198397868-475647b2a1e5?q=80&w=1200&auto=format&fit=crop'
    ];

    var root = document.querySelector('.rpg-tablon-container[data-bburl], .hxh-tablon[data-bburl]');
    if (!root) {
        return;
    }
    var bburl = (root.getAttribute('data-bburl') || '').replace(/\/$/, '');
    var getRelativeUrl = function (url) {
        if (!url) return '';
        if (url.indexOf('http://') === 0 || url.indexOf('https://') === 0) {
            try {
                var urlObj = new URL(url);
                return urlObj.pathname.replace(/\/$/, '');
            } catch (e) {
                var match = url.match(/^https?:\/\/[^\/]+(\/.*)/);
                if (match && match[1]) {
                    return match[1].replace(/\/$/, '');
                }
            }
        }
        return url.replace(/\/$/, '');
    };
    var bbRelative = getRelativeUrl(bburl);
    var loggedPjId = null;

    function fetchJson(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) {
                throw new Error('HTTP ' + r.status);
            }
            return r.json();
        });
    }

    function postJson(url, fd) {
        if (window.gamePostForm) {
            return window.gamePostForm(url, fd);
        }
        if (window.GAME_CSRF) {
            fd.append('my_post_key', window.GAME_CSRF);
        }
        return fetch(url, {
            method: 'POST',
            headers: { 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
            credentials: 'same-origin',
            body: fd
        }).then(function (r) { return r.json(); });
    }

    function showTablonError(el, msg) {
        if (!el) {
            return;
        }
        el.innerHTML = '<div class="rpg-tablon-empty">' + msg + '</div>';
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Seleccionar y aplicar imagen aleatoria al banner
        var bannerEl = document.getElementById('hxh-index-banner');
        if (bannerEl && bannerImages && bannerImages.length > 0) {
            var randomIndex = Math.floor(Math.random() * bannerImages.length);
            bannerEl.style.backgroundImage = "url('" + bannerImages[randomIndex] + "')";
        }

        fetchJson(bbRelative + '/game/ajax/my_personajes.php').then(function (res) {
            if (res.ok && res.data) {
                loggedPjId = res.data.active_pj_id;
            }
        }).catch(function () {});

        var numEl = document.getElementById('tablon-fecha-num');
        var containerEl = document.getElementById('tablon-fecha-container');

        if (numEl) {
            fetchJson(bbRelative + '/game/ajax/get_calendar.php').then(function (res) {
                if (!res.ok || !res.data || !res.data.current) {
                    return;
                }
                var d = res.data.current;
                var seasonMap = { 'Primavera': 'primavera', 'Verano': 'verano', 'Otoño': 'otono', 'Invierno': 'invierno' };
                var seasonKey = seasonMap[d.season_name] || '';

                if (containerEl) {
                    containerEl.className = 'rpg-tablon-fecha-container' + (seasonKey ? ' rpg-tablon-fecha-container--' + seasonKey : '');
                }
                numEl.textContent = d.day;

                var estEl = document.getElementById('tablon-fecha-estacion');
                if (estEl) {
                    estEl.textContent = d.season_name;
                }
                var anioEl = document.getElementById('tablon-fecha-anio');
                if (anioEl) {
                    anioEl.textContent = 'Año ' + d.year;
                }
            }).catch(function () {
                console.error('Error al actualizar el calendario via AJAX');
            });
        }

        fetchJson(bbRelative + '/game/ajax/announcements_list.php').then(function (res) {
            var list = document.getElementById('tablon-anuncios-v2');
            if (!list) {
                return;
            }
            if (res.ok && res.data && res.data.length > 0) {
                var html = '';
                res.data.forEach(function (a) {
                    var dateParts = a.date.split(' ')[0].split('-');
                    var shortDate = dateParts.length === 3 ? dateParts[2] + '&bull;' + dateParts[1] : a.date;
                    html += '<div class="tb-item"><div><span class="tb-date">' + shortDate + '</span> <span class="tb-title tb-title--inline">' + a.title + '</span></div><div class="tb-desc">' + a.content + '</div></div>';
                });
                list.innerHTML = html;
            } else {
                list.innerHTML = '<div class="rpg-tablon-empty">No hay anuncios recientes.</div>';
            }
        }).catch(function () {
            showTablonError(document.getElementById('tablon-anuncios-v2'), 'Error al cargar novedades.');
        });

        fetchJson(bbRelative + '/game/ajax/latest_activity.php').then(function (res) {
            if (!res.ok || !res.data) {
                showTablonError(document.getElementById('tablon-staff-v2'), 'Staff no disponible.');
                showTablonError(document.getElementById('tablon-temas-v2'), 'Actividad no disponible.');
                return;
            }
            var staffList = document.getElementById('tablon-staff-v2');
            if (staffList) {
                if (res.data.staff && res.data.staff.length > 0) {
                    var htmlStaff = '';
                    res.data.staff.forEach(function (s) {
                        htmlStaff += '<a href="' + s.link + '" class="staff-vcard" title="' + s.name + '">';
                        htmlStaff += '  <img src="' + s.avatar + '" alt="' + s.name + '">';
                        htmlStaff += '  <div class="staff-vcard-label">';
                        htmlStaff += '    <div class="staff-vcard-text">' + s.name + '</div>';
                        htmlStaff += '    <div class="staff-vcard-rank">' + (s.rank || 'Staff') + '</div>';
                        htmlStaff += '  </div>';
                        htmlStaff += '</a>';
                    });
                    staffList.innerHTML = htmlStaff;
                } else {
                    staffList.innerHTML = '<div class="rpg-tablon-empty">Sin equipo staff.</div>';
                }
            }
            var actList = document.getElementById('tablon-temas-v2');
            if (actList) {
                if (res.data.latest_posts && res.data.latest_posts.length > 0) {
                    var htmlAct = '';
                    res.data.latest_posts.forEach(function (p) {
                        var title = p.subject;
                        var type = 'Tema';
                        var match = title.match(/^\[(.*?)\]/);
                        if (match) {
                            type = match[1];
                            title = title.replace(/^\[.*?\]\s*/, '');
                        }
                        htmlAct += '<div class="tb-item"><a href="' + p.link + '" class="tb-title">' + title + '</a><div class="tb-meta"><span>' + type + '</span> &bull; ' + p.time + '</div><div class="tb-author"><i class="fas fa-user"></i> ' + p.character_name + '</div></div>';
                    });
                    actList.innerHTML = htmlAct;
                } else {
                    actList.innerHTML = '<div class="rpg-tablon-empty">Sin temas recientes.</div>';
                }
            }
        }).catch(function () {
            showTablonError(document.getElementById('tablon-staff-v2'), 'Error al cargar staff.');
            showTablonError(document.getElementById('tablon-temas-v2'), 'Error al cargar actividad.');
        });

        fetchJson(bbRelative + '/game/ajax/busquedas_list.php').then(function (res) {
            var bList = document.getElementById('tablon-busquedas-list');
            if (!bList) {
                return;
            }
            if (!res.ok || !res.data || res.data.length === 0) {
                bList.innerHTML = '<div class="rpg-tablon-empty"><i class="fas fa-search"></i> No hay búsquedas publicadas aún.</div>';
                return;
            }
            var html = '';
            res.data.forEach(function (b) {
                var hasImg = !!b.imagen_url;
                var imgExtraClass = hasImg ? ' bsq-card-img--custom' : ' bsq-card-img--gradient';
                html += '<div class="bsq-card" data-busqueda="' + encodeURIComponent(JSON.stringify(b)) + '"><div class="bsq-card-img' + imgExtraClass + '"' + (hasImg ? ' data-bg="' + b.imagen_url + '"' : '') + '><div class="bsq-card-overlay"></div>' + (hasImg ? '' : '<i class="fas fa-search bsq-card-icon"></i>') + '<div class="bsq-card-footer"><img src="' + b.pj_avatar + '" class="bsq-avatar" alt=""><span class="bsq-pj-name">' + b.pj_name + '</span></div></div><div class="bsq-card-body"><div class="bsq-title">' + b.titulo + '</div><div class="bsq-desc">' + b.descripcion.substring(0, 80) + (b.descripcion.length > 80 ? '...' : '') + '</div></div></div>';
            });
            bList.innerHTML = html;
            bList.querySelectorAll('.bsq-card-img--custom[data-bg]').forEach(function (el) {
                el.style.setProperty('--bsq-bg', 'url(' + el.getAttribute('data-bg') + ')');
            });
            bList.querySelectorAll('.bsq-card[data-busqueda]').forEach(function (card) {
                card.addEventListener('click', function () {
                    try {
                        openBusquedaDetalle(JSON.parse(decodeURIComponent(card.getAttribute('data-busqueda'))));
                    } catch (e) { /* ignore */ }
                });
            });
        }).catch(function () {
            showTablonError(document.getElementById('tablon-busquedas-list'), 'Error al cargar búsquedas.');
        });

        document.addEventListener('click', function (e) {
            var m = document.getElementById('busqueda-detalle-modal');
            if (m && e.target === m) {
                closeBusquedaDetalle();
            }
            if (e.target.closest && e.target.closest('#bdm-close-btn')) {
                closeBusquedaDetalle();
            }
            if (e.target.closest && e.target.closest('#bdm-contact-btn')) {
                contactarPropuestaTrama();
            }
            var fechaWidget = document.getElementById('tablon-fecha-widget');
            if (fechaWidget && e.target.closest && e.target.closest('#tablon-fecha-widget')) {
                var href = fechaWidget.getAttribute('data-href');
                if (href) {
                    window.location.href = href;
                }
            }
        });
    });

    var bsqData = {};

    window.openBusquedaDetalle = function (b) {
        bsqData = b;
        document.getElementById('bdm-titulo').textContent = b.titulo;
        document.getElementById('bdm-desc').textContent = b.descripcion;
        document.getElementById('bdm-pj').textContent = b.pj_name;
        document.getElementById('bdm-date').textContent = b.date;
        document.getElementById('bdm-avatar').src = b.pj_avatar;
        var imgWrap = document.getElementById('bdm-img-wrap');
        if (b.imagen_url) {
            document.getElementById('bdm-img').src = b.imagen_url;
            imgWrap.classList.add('is-visible');
        } else {
            imgWrap.classList.remove('is-visible');
        }
        var contactBtn = document.getElementById('bdm-contact-btn');
        if (contactBtn) {
            if (!loggedPjId || parseInt(loggedPjId, 10) === parseInt(b.pj_id, 10)) {
                contactBtn.classList.add('is-hidden');
                contactBtn.classList.remove('is-visible-inline');
            } else {
                contactBtn.classList.remove('is-hidden');
                contactBtn.classList.add('is-visible-inline');
            }
            contactBtn.disabled = false;
            contactBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Contactar por Trama';
        }
        document.getElementById('busqueda-detalle-modal').classList.add('is-open');
    };

    window.closeBusquedaDetalle = function () {
        document.getElementById('busqueda-detalle-modal').classList.remove('is-open');
    };

    window.contactarPropuestaTrama = function () {
        if (!bsqData || !bsqData.id) {
            return;
        }
        var btn = document.getElementById('bdm-contact-btn');
        if (!btn || btn.disabled) {
            return;
        }
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
        var fd = new FormData();
        fd.append('busqueda_id', bsqData.id);
        postJson(bbRelative + '/game/ajax/busquedas_contact.php', fd).then(function (res) {
            if (res.ok) {
                btn.innerHTML = '<i class="fas fa-check"></i> Solicitud enviada';
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Contactar por Trama';
                alert('Error: ' + (window.gameFormatError ? window.gameFormatError(res) : ((res.error && res.error.message) || 'Error desconocido')));
            }
        }).catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Contactar por Trama';
            alert('Error de conexión.');
        });
    };
})();

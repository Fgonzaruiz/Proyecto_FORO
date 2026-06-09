/**
 * Sistema de Navegación — tab posting + panel bajo posts
 */
const RpgNavigation = {
    _baseUrl: '',
    _ctx: null,
    _islands: [],
    _initialized: false,

    _resolveBaseUrl: function() {
        if (window.RpgCards && RpgCards.config && RpgCards.config.baseUrl) {
            return RpgCards.config.baseUrl;
        }
        var nav = document.getElementById('pj-nav-submenu');
        if (nav && nav.dataset.base) {
            return nav.dataset.base;
        }
        var path = window.location.pathname || '';
        var gameIdx = path.toLowerCase().indexOf('/game/');
        if (gameIdx !== -1) {
            return window.location.origin + path.substring(0, gameIdx);
        }
        var firstFolder = path.split('/')[1] || '';
        if (firstFolder.toLowerCase() === 'foro') {
            return window.location.origin + '/' + firstFolder;
        }
        return window.location.origin;
    },

    init: function() {
        var panel = document.getElementById('rpg-nav-panel');
        if (!panel) return;
        if (this._initialized && this._ctx) return;

        this._baseUrl = this._resolveBaseUrl();
        this._setLoading(true);
        this._loadContext(panel);
    },

    _setLoading: function(on) {
        var loading = document.getElementById('rpg-nav-loading');
        var unavail = document.getElementById('rpg-nav-unavailable');
        var formWrap = document.getElementById('rpg-nav-form-wrap');
        if (loading) loading.classList.toggle('is-hidden', !on);
        if (on) {
            if (unavail) unavail.classList.add('is-hidden');
            if (formWrap) formWrap.classList.add('is-hidden');
        }
    },

    _showUnavailable: function(message) {
        var unavail = document.getElementById('rpg-nav-unavailable');
        var formWrap = document.getElementById('rpg-nav-form-wrap');
        var loading = document.getElementById('rpg-nav-loading');
        if (loading) loading.classList.add('is-hidden');
        if (formWrap) formWrap.classList.add('is-hidden');
        if (unavail) {
            unavail.classList.remove('is-hidden');
            var msg = document.getElementById('rpg-nav-unavailable-msg');
            if (msg) msg.textContent = message;
        }
    },

    _loadContext: function(panel) {
        var self = this;
        var fid = panel.dataset.currentIslandFid || '0';
        var charId = panel.dataset.characterId || '0';
        var container = document.querySelector('.rpg-system-container');
        var forumFid = (container && container.dataset.forumFid) ? container.dataset.forumFid : fid;
        if (!forumFid || forumFid === '0') forumFid = fid;

        var params = new URLSearchParams({ fid: forumFid, character_id: charId });
        var tidMatch = /[?&]tid=(\d+)/.exec(window.location.search || '');
        if (tidMatch) params.set('tid', tidMatch[1]);

        var url = self._api('/game/ajax/navigation_context.php?' + params);
        fetch(url, { credentials: 'same-origin' })
            .then(function(r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            })
            .then(function(res) {
                self._setLoading(false);
                if (!res.ok) {
                    self._showUnavailable((res.error && res.error.message) || 'No se pudo cargar la navegación.');
                    return;
                }
                self._initialized = true;
                var d = res.data || {};
                self._ctx = d;
                panel.dataset.currentIslandFid = String(d.island_fid || fid);
                panel.dataset.characterId = String(d.character_id || charId);

                var unavail = document.getElementById('rpg-nav-unavailable');
                var formWrap = document.getElementById('rpg-nav-form-wrap');

                if (!d.can_navigate) {
                    var reason = 'Navegación no disponible.';
                    if (!d.has_island) reason = 'Este foro no es un puerto de partida.';
                    else if (!d.has_ship) reason = 'Necesitas un barco equipado para navegar.';
                    self._showUnavailable(reason);
                    return;
                }

                if (unavail) unavail.classList.add('is-hidden');
                if (formWrap) formWrap.classList.remove('is-hidden');
                self._updateNavigatorInfo(d);
                self._renderShipCard(d.ships || []);
                self._renderInstrumentCards(d.instruments || []);
                self._bindForm(panel, d);
                self._loadIslands(panel);
            })
            .catch(function(err) {
                self._setLoading(false);
                self._showUnavailable('Error al cargar navegación. Recarga la página. (' + (err.message || 'conexión') + ')');
            });
    },

    _updateNavigatorInfo: function(ctx) {
        var el = document.getElementById('nav-navigator-info');
        if (!el) return;
        if (ctx.navegante_rank > 0) {
            el.innerHTML = '<i class="fas fa-star"></i> Navegante grado <strong>' + ctx.navegante_label + '</strong> · +' + (ctx.navegante_bonus || 0).toFixed(1) + ' velocidad';
            el.classList.remove('rpg-nav-navigator-info--none');
        } else {
            el.textContent = 'Sin oficio Navegante — sin bonus de velocidad.';
            el.classList.add('rpg-nav-navigator-info--none');
        }
    },

    _cardImage: function(url, icon, alt) {
        if (url) {
            return '<img src="' + this._escape(url) + '" alt="' + this._escape(alt) + '" class="rpg-nav-pick-card__img" loading="lazy" />';
        }
        return '<div class="rpg-nav-pick-card__icon"><i class="fas ' + (icon || 'fa-circle') + '"></i></div>';
    },

    _renderShipCard: function(ships) {
        var wrap = document.getElementById('nav-ship-cards');
        var hidden = document.getElementById('nav_ship_card_id');
        if (!wrap || !hidden) return;

        if (!ships.length) {
            wrap.innerHTML = '<p class="rpg-nav-empty">Sin barco equipado.</p>';
            hidden.value = '';
            return;
        }

        var ship = ships[0];
        hidden.value = String(ship.card_id);
        wrap.innerHTML =
            '<div class="rpg-nav-pick-card rpg-nav-pick-card--ship is-selected" data-id="' + ship.card_id + '">' +
            this._cardImage(ship.image_url, 'fa-ship', ship.name) +
            '<div class="rpg-nav-pick-card__body">' +
            '<div class="rpg-nav-pick-card__title">' + this._escape(ship.name) + '</div>' +
            '<div class="rpg-nav-pick-card__meta">Vel. ' + (ship.velocidad || '—') + ' · Equipado</div>' +
            '</div></div>';
    },

    _renderInstrumentCards: function(instruments) {
        var section = document.getElementById('nav-instruments-section');
        var wrap = document.getElementById('nav-instrument-cards');
        var hidden = document.getElementById('nav_instrument');
        if (!section || !wrap || !hidden) return;

        if (!instruments.length) {
            section.classList.add('is-hidden');
            hidden.value = 'none';
            wrap.innerHTML = '';
            return;
        }

        section.classList.remove('is-hidden');
        var self = this;
        var html = '<button type="button" class="rpg-nav-pick-card rpg-nav-pick-card--instrument is-selected" data-instrument="none">' +
            '<div class="rpg-nav-pick-card__icon"><i class="fas fa-ban"></i></div>' +
            '<div class="rpg-nav-pick-card__body"><div class="rpg-nav-pick-card__title">Ninguno</div>' +
            '<div class="rpg-nav-pick-card__meta">Sin objeto</div></div></button>';

        instruments.forEach(function(inst) {
            html += '<button type="button" class="rpg-nav-pick-card rpg-nav-pick-card--instrument" data-instrument="' + self._escape(inst.instrument_key) + '">' +
                self._cardImage(inst.image_url, inst.icon || 'fa-location-arrow', inst.name) +
                '<div class="rpg-nav-pick-card__body">' +
                '<div class="rpg-nav-pick-card__title">' + self._escape(inst.label || inst.name) + '</div>' +
                '<div class="rpg-nav-pick-card__meta">' + self._escape(inst.subtitle || inst.name) + '</div>' +
                '</div></button>';
        });
        wrap.innerHTML = html;
        hidden.value = 'none';

        wrap.querySelectorAll('.rpg-nav-pick-card--instrument').forEach(function(btn) {
            btn.addEventListener('click', function() {
                wrap.querySelectorAll('.rpg-nav-pick-card--instrument').forEach(function(b) { b.classList.remove('is-selected'); });
                btn.classList.add('is-selected');
                hidden.value = btn.dataset.instrument || 'none';
                var panel = document.getElementById('rpg-nav-panel');
                if (panel && self._ctx) self._updatePreview(panel, self._ctx);
            });
        });
    },

    _renderDestinationCards: function() {
        var wrap = document.getElementById('nav-destination-cards');
        var sel = document.getElementById('nav_destination_island_id');
        if (!wrap || !sel) return;

        var self = this;
        if (!this._islands.length) {
            wrap.innerHTML = '<p class="rpg-nav-empty">No hay islas disponibles.</p>';
            return;
        }

        wrap.innerHTML = this._islands.map(function(island) {
            return '<button type="button" class="rpg-nav-pick-card rpg-nav-pick-card--island" data-fid="' + island.fid + '" data-zone="' + self._escape(island.sea_zone) + '" data-logpose="' + island.requires_log_pose + '">' +
                (island.image_url ? self._cardImage(island.image_url, 'fa-island-tropical', island.name) : '<div class="rpg-nav-pick-card__icon"><i class="fas fa-island-tropical"></i></div>') +
                '<div class="rpg-nav-pick-card__body">' +
                '<div class="rpg-nav-pick-card__title">' + self._escape(island.name) + '</div>' +
                '<div class="rpg-nav-pick-card__meta">' + self._escape(self._seaZoneLabel(island.sea_zone)) + '</div>' +
                '</div></button>';
        }).join('');

        wrap.querySelectorAll('.rpg-nav-pick-card--island').forEach(function(btn) {
            btn.addEventListener('click', function() {
                wrap.querySelectorAll('.rpg-nav-pick-card--island').forEach(function(b) { b.classList.remove('is-selected'); });
                btn.classList.add('is-selected');
                sel.value = btn.dataset.fid || '';
                var panel = document.getElementById('rpg-nav-panel');
                if (panel && self._ctx) self._updatePreview(panel, self._ctx);
            });
        });
    },

    _bindForm: function(panel, ctx) {
        var self = this;
        var toggle = document.getElementById('nav_enabled');
        var body = document.getElementById('nav-form-body');
        if (!toggle || !body) return;

        toggle.addEventListener('change', function() {
            body.classList.toggle('is-hidden', !toggle.checked);
            document.getElementById('rpg_nav_enabled_hidden').value = toggle.checked ? '1' : '0';
            if (toggle.checked) self._updatePreview(panel, ctx);
        });
    },

    _loadIslands: function(panel) {
        var self = this;
        var exclude = panel.dataset.currentIslandFid || '0';
        fetch(this._api('/game/ajax/navigation_islands_list.php?exclude=' + exclude))
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.ok) return;
                self._islands = res.data.islands || [];
                var sel = document.getElementById('nav_destination_island_id');
                if (sel) {
                    sel.innerHTML = '<option value="">—</option>';
                    self._islands.forEach(function(island) {
                        var opt = document.createElement('option');
                        opt.value = island.fid;
                        opt.textContent = island.name;
                        sel.appendChild(opt);
                    });
                }
                self._renderDestinationCards();
            });
    },

    _updatePreview: function(panel, ctx) {
        var destSel = document.getElementById('nav_destination_island_id');
        var shipInput = document.getElementById('nav_ship_card_id');
        var instrument = document.getElementById('nav_instrument');
        var preview = document.getElementById('nav-preview');
        if (!destSel || !shipInput || !instrument) return;

        document.getElementById('rpg_nav_destination_hidden').value = destSel.value;
        document.getElementById('rpg_nav_ship_hidden').value = shipInput.value;
        document.getElementById('rpg_nav_instrument_hidden').value = instrument.value;

        if (!destSel.value || !shipInput.value) {
            if (preview) preview.classList.add('is-hidden');
            return;
        }

        var params = new URLSearchParams({
            island_from: panel.dataset.currentIslandFid || '0',
            island_to: destSel.value,
            ship_card_id: shipInput.value,
            character_id: panel.dataset.characterId || '0',
            instrument: instrument.value
        });

        var self = this;
        var selectedIsland = this._islands.find(function(i) { return String(i.fid) === String(destSel.value); });

        fetch(this._api('/game/ajax/navigation_preview.php?' + params))
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.ok || !res.data) return;
                var d = res.data;
                document.getElementById('prev-distance').textContent = d.distance + ' leguas';
                document.getElementById('prev-speed').textContent = d.effective_speed;
                document.getElementById('prev-duration').textContent = d.duration_days + ' días';
                document.getElementById('prev-danger').textContent = d.danger_label || '—';
                document.getElementById('prev-events').textContent = d.events_min + '–' + d.events_max;
                self._checkInstrumentWarning(selectedIsland, instrument.value);
                if (preview) preview.classList.remove('is-hidden');
            });
    },

    _checkInstrumentWarning: function(island, instrument) {
        var warning = document.getElementById('nav-instrument-warning');
        if (!warning) return;
        var zone = island ? island.sea_zone : '';
        var requiresLogPose = island && parseInt(island.requires_log_pose, 10) === 1;
        var msg = '';
        if ((zone === 'grand_line' || zone === 'new_world') && instrument === 'compass') {
            msg = 'En la Grand Line una brújula normal no funciona. Necesitas Log Pose o Eternal Pose.';
        } else if (instrument === 'none' && requiresLogPose) {
            msg = 'Esta ruta requiere instrumento de navegación. Sin él sufrirás penalizaciones.';
        }
        warning.textContent = msg;
        warning.classList.toggle('is-hidden', !msg);
    },

    _seaZoneLabel: function(zone) {
        var labels = {
            east_blue: 'East Blue', west_blue: 'West Blue',
            north_blue: 'North Blue', south_blue: 'South Blue',
            grand_line: 'Grand Line', new_world: 'New World',
            calm_belt: 'Calm Belt', florian_triangle: 'Triángulo de Florian'
        };
        return labels[zone] || zone;
    },

    _api: function(path) {
        var base = this._baseUrl || '';
        if (base && path.indexOf('/') === 0) return base.replace(/\/$/, '') + path;
        return path;
    },

    _escape: function(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    },

    renderPostVoyageHtml: function(postId, voyage) {
        if (window.RpgCards && typeof RpgCards.renderPostVoyageHtml === 'function') {
            return RpgCards.renderPostVoyageHtml(postId, voyage);
        }
        return '';
    },

    renderVoyagePanel: function(voyage) {
        if (window.RpgCards && typeof RpgCards.renderVoyagePanel === 'function') {
            return RpgCards.renderVoyagePanel(voyage);
        }
        return '';
    }
};

if (typeof window !== 'undefined') {
    window.RpgNavigation = RpgNavigation;
}

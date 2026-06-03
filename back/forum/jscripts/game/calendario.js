/**
 * Auto-extracted from back/forum/game/public/calendario.php
 * Config: window.CALENDARIO_CONFIG
 */
(function () {
  "use strict";
  var cfg = window.CALENDARIO_CONFIG || {};
  var bburl = cfg.bburl || (window.GAME_BBURL || '');
document.addEventListener('DOMContentLoaded', function() {
    var fixedLegend = {'Evento':'#3b82f6', 'Trama':'#ef4444', 'Noticia':'#10b981'};
    var seasonColors = { 'Primavera': '#10b981', 'Verano': '#f59e0b', 'Otoño': '#f97316', 'Invierno': '#06b6d4' };

    fetch(bburl + '/game/ajax/get_calendar.php')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.ok && res.data) {
                var d = res.data.current;
                var evs = res.data.events || [];
                
                document.getElementById('calendar-page-title').innerText = "Estación de " + d.season_name + ", Año " + d.year;
                
                var grid = document.getElementById('calendar-grid');
                grid.innerHTML = '';
                
                var evByDay = {};
                evs.forEach(function(ev) {
                    if (!evByDay[ev.day]) evByDay[ev.day] = [];
                    evByDay[ev.day].push(ev);
                });
                
                var gridContainer = document.getElementById('calendar-grid-container');
                var baseSeasonColor = seasonColors[d.season_name] || '#C62828';
                gridContainer.style.setProperty('--season-color', baseSeasonColor);
                gridContainer.classList.add('pj-cal-grid-wrap--season');

                for (var i = 1; i <= 65; i++) {
                    var isToday = (i === d.day);
                    var dayEvs = evByDay[i] || [];
                    var hasEv = dayEvs.length > 0;
                    
                    var html = '<div class="pj-cal-day' + (isToday ? ' is-today' : '') + '" onclick="showCalendarEvents('+i+')">';
                    html += '<div class="pj-cal-day__num">' + i;
                    if (i % 25 === 0) {
                        html += ' <i class="fas fa-moon" title="Luna Llena"></i>';
                    }
                    html += '</div>';
                    
                    if (hasEv) {
                        html += '<div class="pj-cal-day__events">';
                        dayEvs.slice(0, 2).forEach(function(e) {
                            var evCol = e.color || fixedLegend[e.type] || 'var(--text-primary)';
                            html += '<div class="pj-cal-day__ev" data-ev-color="'+evCol+'">'+e.title+'</div>';
                        });
                        if (dayEvs.length > 2) {
                            html += '<div class="pj-cal-day__more">+'+(dayEvs.length-2)+' más</div>';
                        }
                        html += '</div>';
                    }
                    
                    html += '</div>';
                    grid.innerHTML += html;
                }
                if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(grid);
                
                window.showCalendarEvents = function(day) {
                    var panel = document.getElementById('calendar-details');
                    var dayEvs = evByDay[day] || [];
                    var isToday = (day === d.day);
                    
                    var html = '<h3 class="pj-cal-detail-title">Día ' + day;
                    if (isToday) html += ' <span class="pj-cal-detail-today">HOY</span>';
                    if (day % 25 === 0) html += ' <i class="fas fa-moon" title="Luna Llena"></i>';
                    html += '</h3>';
                    
                    if (dayEvs.length === 0) {
                        html += '<p class="pj-cal-detail-empty">No hay eventos registrados para este día.</p>';
                    } else {
                        dayEvs.forEach(function(e) {
                            var evCol = e.color || fixedLegend[e.type] || 'var(--text-primary)';
                            html += '<div class="pj-cal-event" data-ev-color="'+evCol+'">';
                            html += '<div class="pj-cal-event__type">'+e.type+'</div>';
                            html += '<div class="pj-cal-event__title">'+e.title+'</div>';
                            html += '<div class="pj-cal-event__desc">'+e.desc+'</div>';
                            html += '</div>';
                        });
                    }
                    panel.innerHTML = html;
                    if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(panel);
                };
            }
        });
});

})();

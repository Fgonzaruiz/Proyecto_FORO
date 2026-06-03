<?php
declare(strict_types=1);

define('THIS_SCRIPT', 'calendario.php');

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $headerinclude, $header, $footer, $theme, $templates;
$bburl = $mybb->settings['bburl'];

// Ensure headerinclude is evaluated if it's missing (failsafe)
if (empty($headerinclude) && isset($templates)) {
    eval('$headerinclude = "'.$templates->get('headerinclude').'";');
    eval('$header = "'.$templates->get('header').'";');
    eval('$footer = "'.$templates->get('footer').'";');
}

ob_start();
?>
<div class="pj-container" style="max-width: 1200px; margin: 40px auto; padding: 20px;">
    <h1 id="calendar-page-title" style="font-family:var(--font-heading); font-size:32px; color:var(--text-primary); margin-bottom:30px; text-align:center; font-weight:800;">Calendario On-Rol</h1>
    
    <div style="display:flex; gap:30px; align-items:flex-start; flex-wrap:wrap;">
        <div id="calendar-grid-container" style="flex:2; min-width:300px; padding:30px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:12px; box-shadow:var(--shadow-card);">
            <div id="calendar-grid" style="display:grid; grid-template-columns:repeat(7, minmax(0, 1fr)); gap:12px; list-style:none;">
                <!-- 65 days injected by JS -->
                <div style="text-align:center; grid-column: 1 / -1; color: var(--text-muted); padding: 40px;">Cargando calendario...</div>
            </div>
        </div>
        
        <!-- Details Panel -->
        <div id="calendar-details" style="flex:1; min-width:280px; padding:30px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:12px; position:sticky; top:30px; box-shadow:var(--shadow-card);">
            <div style="text-align:center; color:var(--text-muted); margin-top:80px; font-size:14px;">
                <i class="fas fa-hand-pointer" style="font-size:24px; margin-bottom:10px; opacity:0.5;"></i><br>
                Selecciona un día para ver sus eventos
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var fixedLegend = {'Evento':'#3b82f6', 'Trama':'#ef4444', 'Noticia':'#10b981'};
    var seasonColors = { 'Primavera': '#10b981', 'Verano': '#f59e0b', 'Otoño': '#f97316', 'Invierno': '#06b6d4' };

    fetch('<?= $bburl ?>/game/ajax/get_calendar.php')
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
                // Add a subtle top border to identify season
                gridContainer.style.borderTop = '4px solid ' + baseSeasonColor;

                for (var i = 1; i <= 65; i++) {
                    var isToday = (i === d.day);
                    var dayEvs = evByDay[i] || [];
                    var hasEv = dayEvs.length > 0;
                    
                    var bgBase = isToday ? 'var(--bg-main)' : 'var(--bg-surface)';
                    var borderColor = isToday ? baseSeasonColor : 'var(--border-color)';
                    var shadow = isToday ? '0 0 0 1px '+baseSeasonColor : 'none';
                    
                    var html = '<div onclick="showCalendarEvents('+i+')" style="background:'+bgBase+'; border:1px solid '+borderColor+'; border-radius:8px; min-height:80px; padding:8px; display:flex; flex-direction:column; position:relative; cursor:pointer; transition:all 0.2s; box-shadow:'+shadow+'; min-width:0;" onmouseenter="this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'var(--shadow-hover)\';" onmouseleave="this.style.transform=\'none\'; this.style.boxShadow=\''+shadow+'\';">';
                    
                    // Number top-left
                    html += '<div style="font-family:var(--font-heading); font-size:16px; font-weight:800; color:var(--text-primary); margin-bottom:5px;">' + i;
                    if (i % 25 === 0) {
                        html += ' <i class="fas fa-moon" style="color:var(--text-muted); font-size:12px; margin-left:4px;" title="Luna Llena"></i>';
                    }
                    html += '</div>';
                    
                    // Event titles
                    if (hasEv) {
                        html += '<div style="flex:1; display:flex; flex-direction:column; gap:4px; overflow:hidden;">';
                        dayEvs.slice(0, 2).forEach(function(e) {
                            var evCol = e.color || fixedLegend[e.type] || 'var(--text-primary)';
                            html += '<div style="font-size:9px; line-height:1.2; color:var(--text-primary); opacity:0.9; text-transform:uppercase; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; border-left:2px solid '+evCol+'; padding-left:4px;">'+e.title+'</div>';
                        });
                        if (dayEvs.length > 2) {
                            html += '<div style="font-size:9px; color:var(--text-muted); font-weight:600;">+'+(dayEvs.length-2)+' más</div>';
                        }
                        html += '</div>';
                    }
                    
                    html += '</div>';
                    grid.innerHTML += html;
                }
                
                window.showCalendarEvents = function(day) {
                    var panel = document.getElementById('calendar-details');
                    var dayEvs = evByDay[day] || [];
                    var isToday = (day === d.day);
                    
                    var html = '<h3 style="font-family:var(--font-heading); color:var(--text-primary); font-size:20px; border-bottom:1px solid var(--border-color); padding-bottom:10px; margin-top:0;">Día ' + day + (isToday ? ' <span style="font-size:12px; color:var(--accent-indigo); background:rgba(198,40,40,0.1); padding:2px 6px; border-radius:4px; vertical-align:middle; margin-left:10px;">HOY</span>' : '');
                    if (day % 25 === 0) html += ' <i class="fas fa-moon" style="color:var(--text-muted); margin-left:10px;" title="Luna Llena"></i>';
                    html += '</h3>';
                    
                    if (dayEvs.length === 0) {
                        html += '<p style="color:var(--text-muted); font-size:14px; margin-top:20px;">No hay eventos registrados para este día.</p>';
                    } else {
                        dayEvs.forEach(function(e) {
                            var evCol = e.color || fixedLegend[e.type] || 'var(--text-primary)';
                            html += '<div style="background:var(--bg-main); border-left:4px solid '+evCol+'; padding:15px; border-radius:6px; margin-bottom:15px; box-shadow:var(--shadow-sm);">';
                            html += '<div style="font-size:11px; font-weight:800; text-transform:uppercase; color:'+evCol+'; margin-bottom:5px;">'+e.type+'</div>';
                            html += '<div style="font-size:16px; font-weight:800; color:var(--text-primary); margin-bottom:8px;">'+e.title+'</div>';
                            html += '<div style="font-size:13px; color:var(--text-secondary); line-height:1.5;">'+e.desc+'</div>';
                            html += '</div>';
                        });
                    }
                    panel.innerHTML = html;
                };
            }
        });
});
</script>
<?php
$content = ob_get_clean();
game_render_page('Calendario On-Rol', $content);


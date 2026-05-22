<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer, $headerinclude, $theme, $templates;

$bburl = $mybb->settings['bburl'];

// Output page header
eval("\$html = \"".$templates->get("header")."\";");
echo $html;

?>
<div class="pj-container" style="max-width: 1200px; margin: 40px auto; padding: 20px;">
    <h1 id="calendar-page-title" style="font-family:var(--font-heading); font-size:32px; color:#fff; margin-bottom:30px; text-align:center; font-weight:800;">Calendario On-Rol</h1>
    
    <div style="display:flex; gap:30px; align-items:flex-start;">
        <div id="calendar-grid-container" style="flex:2; padding:30px; border:1px solid rgba(255,255,255,0.1); border-radius:12px;">
            <div id="calendar-grid" style="display:grid; grid-template-columns:repeat(7, 1fr); gap:12px; list-style:none;">
                <!-- 100 days injected by JS -->
                <div style="text-align:center; grid-column: 1 / -1; color: var(--text-muted); padding: 40px;">Cargando calendario...</div>
            </div>
        </div>
        
        <!-- Details Panel -->
        <div id="calendar-details" style="flex:1; padding:30px; background:rgba(0,0,0,0.2); border:1px solid var(--border-color); border-radius:12px; position:sticky; top:30px;">
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
                var baseSeasonColor = seasonColors[d.season_name] || '#6366f1';
                gridContainer.style.background = 'radial-gradient(circle, ' + baseSeasonColor + '22 0%, var(--bg-main) 100%)';

                for (var i = 1; i <= 100; i++) {
                    var isToday = (i === d.day);
                    var dayEvs = evByDay[i] || [];
                    var hasEv = dayEvs.length > 0;
                    
                    var bgBase = isToday ? 'rgba(255, 255, 255, 0.2)' : 'rgba(255, 255, 255, 0.05)';
                    var bgHover = isToday ? 'rgba(255, 255, 255, 0.25)' : 'rgba(255, 255, 255, 0.12)';
                    var borderColor = isToday ? 'rgba(255,255,255,0.5)' : 'rgba(255,255,255,0.1)';
                    
                    var html = '<div onclick="showCalendarEvents('+i+')" style="background:'+bgBase+'; border:1px solid '+borderColor+'; border-radius:8px; min-height:80px; padding:8px; display:flex; flex-direction:column; position:relative; cursor:pointer; transition:all 0.2s; box-shadow:0 4px 15px rgba(0,0,0,0.1); backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px);" onmouseenter="this.style.background=\''+bgHover+'\'" onmouseleave="this.style.background=\''+bgBase+'\'">';
                    
                    // Number top-left
                    html += '<div style="font-family:var(--font-heading); font-size:16px; font-weight:800; color:#fff; margin-bottom:5px;">' + i;
                    if (i % 25 === 0) {
                        html += ' <i class="fas fa-moon" style="color:rgba(255,255,255,0.6); font-size:12px; margin-left:4px;" title="Luna Llena"></i>';
                    }
                    html += '</div>';
                    
                    // Event titles
                    if (hasEv) {
                        html += '<div style="flex:1; display:flex; flex-direction:column; gap:4px; overflow:hidden;">';
                        dayEvs.slice(0, 2).forEach(function(e) {
                            var evCol = e.color || fixedLegend[e.type] || '#fff';
                            html += '<div style="font-size:9px; line-height:1.2; color:var(--text-primary); opacity:0.9; text-transform:uppercase; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; border-left:2px solid '+evCol+'; padding-left:4px;">'+e.title+'</div>';
                        });
                        if (dayEvs.length > 2) {
                            html += '<div style="font-size:9px; color:var(--text-muted);">+'+(dayEvs.length-2)+' más</div>';
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
                    
                    var html = '<h3 style="font-family:var(--font-heading); color:#fff; font-size:20px; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px; margin-top:0;">Día ' + day + (isToday ? ' <span style="font-size:12px; color:var(--accent-indigo); background:rgba(99,102,241,0.2); padding:2px 6px; border-radius:4px; vertical-align:middle; margin-left:10px;">HOY</span>' : '');
                    if (day % 25 === 0) html += ' <i class="fas fa-moon" style="color:rgba(255,255,255,0.7); margin-left:10px;" title="Luna Llena"></i>';
                    html += '</h3>';
                    
                    if (dayEvs.length === 0) {
                        html += '<p style="color:var(--text-muted); font-size:14px; margin-top:20px;">No hay eventos registrados para este día.</p>';
                    } else {
                        dayEvs.forEach(function(e) {
                            var evCol = e.color || fixedLegend[e.type] || '#fff';
                            html += '<div style="background:rgba(0,0,0,0.3); border-left:4px solid '+evCol+'; padding:15px; border-radius:6px; margin-bottom:15px;">';
                            html += '<div style="font-size:11px; font-weight:700; text-transform:uppercase; color:'+evCol+'; margin-bottom:5px;">'+e.type+'</div>';
                            html += '<div style="font-size:16px; font-weight:700; color:#fff; margin-bottom:8px;">'+e.title+'</div>';
                            html += '<div style="font-size:13px; color:var(--text-muted); line-height:1.5;">'+e.desc+'</div>';
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
eval("\$html = \"".$templates->get("footer")."\";");
echo $html;

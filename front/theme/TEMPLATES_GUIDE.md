# Guía de Plantillas RPG — Carta Náutica v2 (pergamino light-glass)

Tema visual **pergamino náutico** con glass moderado en capas flotantes (nav, tablón). Fuente de verdad del CSS: `back/forum/rpg_custom.css`. Plantillas HTML fuente: `front/templates/mybb/**`.

**Sincronización obligatoria** (ver `AGENTS.md`):
1. Editar fuentes en `front/templates/mybb/`
2. `python front/sync_theme_full.py` → actualiza `front/Default-theme.xml`
3. `powershell -NoProfile -File front/diff_theme_source.ps1` → validar 18/18 OK
4. Importar tema en MyBB o desplegar docroot

**Botones:** usar `.rpg-btn--primary|secondary|ghost|staff|danger` (no inline ni indigo).

---

### Pasos Generales
1. Sube los archivos `rpg_custom.css` y `rpg_custom.js` por FTP a la carpeta `htdocs/` en tu servidor.
2. Entra a tu Panel de Administración (`http://fororolprueba.infinityfree.me/admin/`).
3. Ve a **Templates & Style** > **Templates** > Tu Set de Plantillas.
4. Edita cada plantilla indicada reemplazando todo su contenido actual por el código de abajo.

---

## 1. Cabecera y Recursos Globales

### Plantilla: `headerinclude` (en "Headerinclude Templates")
*Carga las fuentes elegantes, iconos de Font Awesome y los archivos CSS/JS externos.*

**Código completo:**
```html
{$stylesheets}
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="{$mybb->settings['bburl']}/rpg_custom.css?v=1">
<script type="text/javascript" src="{$mybb->asset_url}/jscripts/jquery.js?v=1806"></script>
<script type="text/javascript" src="{$mybb->asset_url}/jscripts/jquery.plugins.min.js?v=1806"></script>
<script type="text/javascript" src="{$mybb->asset_url}/jscripts/general.js?v=1806"></script>
<script type="text/javascript" src="{$mybb->settings['bburl']}/rpg_custom.js?v=1"></script>
```

### Plantilla: `header` (en "Header Templates")
*Define la barra superior de navegación y usuario.*

**Código completo:**
```html
<div id="header">
    <!-- Bloque de Usuario (Avatar y Nombre del Personaje) -->
    <div class="welcome-block">
        <div class="welcome-avatar"></div>
        <div class="welcome-text">
            {$welcomeblock}
        </div>
    </div>
    
    <!-- Pestañas centrales de Navegación -->
    <ul class="header-menu">
        <li class="active"><a href="index.php"><i class="fas fa-home"></i> Inicio</a></li>
        <li><a href="misc.php?action=library"><i class="fas fa-book"></i> Biblioteca</a></li>
        <li><a href="misc.php?action=requests"><i class="fas fa-scroll"></i> Peticiones</a></li>
    </ul>
    
    <!-- Acciones Rápidas (Alertas, Mensajes y Buscador) -->
    <div class="header-actions">
        <a href="usercp.php?action=alerts" class="action-icon" title="Notificaciones">
            <i class="far fa-bell"></i>
        </a>
        <a href="private.php" class="action-icon" title="Mensajes Privados">
            <i class="far fa-envelope"></i>
        </a>
        <a href="search.php" class="search-trigger" title="Buscar en el Foro">
            <i class="fas fa-search"></i>
        </a>
    </div>
</div>
```

---

## 2. Página de Inicio (Index)

### Plantilla: `index` (en "Index Page Templates")
*Cuerpo de la página principal, banner héroe con buscador y botón de subir.*

**Código completo:**
```html
<html>
<head>
<title>{$mybb->settings['bbname']}</title>
{$headerinclude}
</head>
<body>
{$header}

<!-- Banner RPG Gigante con Imagen Rotatoria -->
<div class="roleplay-hero">
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <span class="hero-welcome">Bienvenido a</span>
        <h1 class="hero-title">{$mybb->settings['bbname']}</h1>
        <p class="hero-subtitle">Un lugar para compartir, crear y vivir historias inolvidables.</p>
        
        <!-- Formulario de búsqueda estilo cápsula -->
        <form action="search.php" method="post" class="hero-search-form">
            <input type="hidden" name="action" value="do_search" />
            <input type="text" name="keywords" class="hero-search-input" placeholder="Buscar en el foro..." />
            <button type="submit" class="hero-search-btn"><i class="fas fa-search"></i></button>
        </form>
    </div>
</div>

<!-- Barra de Título de Categorías -->
<div class="forums-section-header">
    <div class="forums-section-title">
        <i class="fas fa-th-large"></i> Categorías del foro
    </div>
    <a href="misc.php?action=markread&amp;mykey={$mybb->post_code}" class="mark-read-btn">
        <i class="fas fa-check-double"></i> Marcar todos como leídos
    </a>
</div>

{$forums}

{$boardstats}

{$footer}

<!-- Botón flotante para subir (controlado por rpg_custom.js) -->
<div class="scroll-top" style="display: none;">
    <i class="fas fa-chevron-up"></i>
</div>

</body>
</html>
```

### Plantilla: `index_boardstats` (en "Index Page Templates")
*Barra horizontal de estadísticas a 4 columnas.*

**Código completo:**
```html
<div class="rpg-stats-container">
    <div class="rpg-stats-row">
        <!-- Usuarios Registrados -->
        <div class="rpg-stat-col">
            <div class="rpg-stat-icon-wrapper">
                <i class="fas fa-user-friends"></i>
            </div>
            <div class="rpg-stat-text">
                <span class="rpg-stat-number">{$stats['numusers']}</span>
                <span class="rpg-stat-label">Usuarios registrados</span>
            </div>
        </div>
        
        <!-- Temas Creados -->
        <div class="rpg-stat-col">
            <div class="rpg-stat-icon-wrapper">
                <i class="fas fa-comment-alt"></i>
            </div>
            <div class="rpg-stat-text">
                <span class="rpg-stat-number">{$stats['numthreads']}</span>
                <span class="rpg-stat-label">Temas creados</span>
            </div>
        </div>
        
        <!-- Mensajes Enviados -->
        <div class="rpg-stat-col">
            <div class="rpg-stat-icon-wrapper">
                <i class="fas fa-envelope-open-text"></i>
            </div>
            <div class="rpg-stat-text">
                <span class="rpg-stat-number">{$stats['numposts']}</span>
                <span class="rpg-stat-label">Mensajes enviados</span>
            </div>
        </div>
        
        <!-- Top / Último Usuario -->
        <div class="rpg-stat-col">
            <div class="rpg-stat-icon-wrapper">
                <i class="fas fa-crown"></i>
            </div>
            <div class="rpg-stat-text">
                <span class="rpg-stat-number">{$newestmember}</span>
                <span class="rpg-stat-label">Top Usuario</span>
            </div>
        </div>
    </div>
</div>
```

---

## 3. Listados de Categorías y Foros (Forumbit)

### Plantilla: `forumbit_depth1_cat` (en "Forum Bit Templates")
*Contenedor de categoría con título, descripción y rejilla de 4 columnas.*

**Código completo:**
```html
<div class="rpg-category" id="cat_{$forum['fid']}">
    <div class="rpg-category-header">
        <div class="rpg-category-icon">
            <i class="fas fa-folder-open"></i>
        </div>
        <div class="rpg-category-info">
            <h2 class="rpg-category-title">
                <a href="{$forum_url}">{$forum['name']}</a>
            </h2>
            <p class="rpg-category-desc">{$forum['description']}</p>
        </div>
    </div>
    <div class="rpg-forums-grid">
        {$sub_forums}
    </div>
</div>
```

### Plantilla: `forumbit_depth2_forum` (en "Forum Bit Templates")
*Cada foro individual en formato tarjeta.*

**Código completo:**
```html
<div class="rpg-forum-card {$lightbulb['folder']}">
    <!-- Icono circular superior (se personaliza dinámicamente vía rpg_custom.js) -->
    <div class="rpg-forum-icon">
        <i class="far fa-compass"></i>
    </div>
    
    <div class="rpg-forum-main">
        <h3 class="rpg-forum-name">
            <a href="{$forum_url}">{$forum['name']}</a>
        </h3>
        <p class="rpg-forum-desc">{$forum['description']}</p>
        {$subforums}
    </div>
    
    <div class="rpg-forum-footer-meta">
        <div class="card-stat-item" title="Temas creados">
            <i class="far fa-comment"></i> <span>{$threads} Temas</span>
        </div>
        <div class="card-stat-item" title="Mensajes escritos">
            <i class="far fa-envelope"></i> <span>{$posts} Mensajes</span>
        </div>
    </div>
</div>
```

### Plantilla: `forumbit_subforums` (en "Forum Bit Templates")
*Contenedor de la lista de subforos.*

**Código completo:**
```html
<div class="rpg-subforums">
    <span class="rpg-subforums-title">Subforos:</span>
    {$subforums}
</div>
```

### Plantilla: `forumbit_depth3_subforum` (en "Forum Bit Templates")
*Cada subforo individual como una píldora estética.*

**Código completo:**
```html
<a href="{$forum_url}" class="rpg-subforum-link" title="{$forum['description']}">
    <i class="fas fa-bookmark"></i> {$forum['name']}
</a>
```

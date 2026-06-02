<?php
require_once __DIR__ . '/../../global.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>UI Playground - One Piece RPG</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Pixel Font -->
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=VT323&display=swap" rel="stylesheet">
    <!-- Manga Font -->
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;500;900&display=swap" rel="stylesheet">
    <!-- Parchment Font -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Crimson+Text:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">

    <style>
        /* BASE STYLES */
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background 0.3s ease, color 0.3s ease;
        }

        .controls {
            background: #111;
            color: white;
            padding: 15px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 100;
            font-family: sans-serif;
            border-bottom: 2px solid #000;
        }

        .controls button {
            background: #333;
            color: white;
            border: 1px solid #555;
            padding: 10px 20px;
            margin: 0 5px;
            cursor: pointer;
            border-radius: 4px;
            font-size: 14px;
            transition: 0.2s;
        }

        .controls button:hover, .controls button.active {
            background: #ff003c;
            border-color: #ff003c;
            font-weight: bold;
        }

        .playground-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            flex-grow: 1;
            width: 100%;
            box-sizing: border-box;
        }

        /* COMPONENT STRUCTURES */
        .card {
            padding: 30px;
            margin-bottom: 40px;
            display: flex;
            gap: 30px;
        }
        .card-avatar {
            width: 180px;
            height: 180px;
            background: #ccc;
            object-fit: cover;
            display: block;
        }
        .card-content {
            flex-grow: 1;
        }
        .tags {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tag {
            padding: 6px 12px;
            font-size: 0.85em;
        }
        .post-content {
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .button-group {
            display: flex;
            gap: 15px;
        }
        .btn {
            padding: 12px 25px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        /* =========================================
           THEME 1: PIXEL ART (16-BIT RETRO)
           ========================================= */
        body.theme-pixel {
            background-color: #0B131F;
            color: #e0e0ff;
            font-family: 'VT323', monospace;
            font-size: 22px;
        }
        .theme-pixel h1, .theme-pixel h2, .theme-pixel h3 {
            font-family: 'Press Start 2P', cursive;
            color: #f7d51d;
            text-shadow: 2px 2px 0 #000;
            line-height: 1.4;
        }
        .theme-pixel h1 { font-size: 24px; }
        .theme-pixel h2 { font-size: 16px; margin-top: 0;}
        .theme-pixel .card {
            background-color: #1a2942;
            border: 4px solid #457b9dff;
            box-shadow: inset -4px -4px 0 rgba(0,0,0,0.5), inset 4px 4px 0 rgba(255,255,255,0.2), 6px 6px 0 #000;
        }
        .theme-pixel .card-avatar {
            border: 4px solid #f7d51d;
            image-rendering: pixelated;
        }
        .theme-pixel .tag {
            background: #e63946ff;
            color: white;
            border: 2px solid #fff;
            text-transform: uppercase;
        }
        .theme-pixel .btn {
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
            background: #a8dadcff;
            color: #0B131F;
            border: 4px solid #fff;
            box-shadow: inset -4px -4px 0 rgba(0,0,0,0.3), 4px 4px 0 #000;
            transition: transform 0.1s;
        }
        .theme-pixel .btn:active {
            transform: translate(4px, 4px);
            box-shadow: inset -4px -4px 0 rgba(0,0,0,0.3), 0px 0px 0 #000;
        }
        .theme-pixel .btn.secondary {
            background: #1d3557ff;
            color: #fff;
        }
        .theme-pixel .post-content {
            border-top: 2px dashed #457b9dff;
            padding-top: 15px;
        }

        /* =========================================
           THEME 2: MANGA BRUTALISM
           ========================================= */
        body.theme-manga {
            background-color: #f4f4f4;
            /* Halftone dots effect */
            background-image: radial-gradient(#d5d5d5 15%, transparent 16%), radial-gradient(#d5d5d5 15%, transparent 16%);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
            color: #000;
            font-family: 'Inter', sans-serif;
        }
        .theme-manga h1, .theme-manga h2, .theme-manga h3 {
            font-family: 'Bangers', cursive;
            letter-spacing: 2px;
            color: #000;
            font-size: 45px;
            margin-top: 0;
            text-shadow: 2px 2px 0px #fff;
        }
        .theme-manga h2 { font-size: 36px; }
        .theme-manga .card {
            background-color: #fff;
            border: 4px solid #000;
            box-shadow: 10px 10px 0 #000;
            border-radius: 0;
        }
        .theme-manga .card-avatar {
            border: 4px solid #000;
            filter: grayscale(100%) contrast(120%);
        }
        .theme-manga .tag {
            background: #ffe600; /* Bright yellow */
            color: #000;
            border: 2px solid #000;
            font-weight: 900;
            text-transform: uppercase;
            transform: skew(-10deg);
        }
        .theme-manga .btn {
            font-family: 'Inter', sans-serif;
            font-weight: 900;
            font-size: 16px;
            text-transform: uppercase;
            background: #ff003c; /* Bright red */
            color: #fff;
            border: 4px solid #000;
            box-shadow: 6px 6px 0 #000;
            transition: all 0.1s;
        }
        .theme-manga .btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 8px 8px 0 #000;
        }
        .theme-manga .btn:active {
            transform: translate(6px, 6px);
            box-shadow: 0px 0px 0 #000;
        }
        .theme-manga .btn.secondary {
            background: #fff;
            color: #000;
        }
        .theme-manga .post-content {
            font-weight: 500;
            font-size: 1.1em;
            position: relative;
            padding: 20px;
            border: 3px solid #000;
            background: #fff;
        }
        .theme-manga .post-content::before {
            content: '';
            position: absolute;
            top: -15px;
            left: 20px;
            width: 0;
            height: 0;
            border-left: 15px solid transparent;
            border-right: 15px solid transparent;
            border-bottom: 15px solid #000;
        }
        .theme-manga .post-content::after {
            content: '';
            position: absolute;
            top: -10px;
            left: 22px;
            width: 0;
            height: 0;
            border-left: 12px solid transparent;
            border-right: 12px solid transparent;
            border-bottom: 12px solid #fff;
        }

        /* =========================================
           THEME 3: NAUTICAL PARCHMENT
           ========================================= */
        body.theme-parchment {
            background-color: #f1e5d1;
            background-image: url('https://www.transparenttextures.com/patterns/old-wall.png');
            color: #2c1e16;
            font-family: 'Crimson Text', serif;
            font-size: 20px;
        }
        .theme-parchment h1, .theme-parchment h2, .theme-parchment h3 {
            font-family: 'Playfair Display', serif;
            color: #5c1818;
            border-bottom: 1px solid #c9b18f;
            padding-bottom: 10px;
            margin-top: 0;
        }
        .theme-parchment h1 { font-size: 40px; }
        .theme-parchment .card {
            background-color: #fdfbf7;
            border: 1px solid #d4c2a5;
            box-shadow: 0 10px 30px rgba(100, 80, 60, 0.1);
            border-radius: 4px;
            position: relative;
        }
        .theme-parchment .card::before {
            content: '';
            position: absolute;
            top: 5px; left: 5px; right: 5px; bottom: 5px;
            border: 1px solid #eae0cd;
            pointer-events: none;
        }
        .theme-parchment .card-avatar {
            border: 2px solid #8c7355;
            border-radius: 50%;
            padding: 4px;
            background: #fff;
            filter: sepia(0.4) contrast(1.1);
        }
        .theme-parchment .tag {
            background: transparent;
            color: #8c7355;
            border: 1px solid #8c7355;
            font-style: italic;
            border-radius: 20px;
            padding: 4px 14px;
            font-family: 'Playfair Display', serif;
        }
        .theme-parchment .btn {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            background: #5c1818;
            color: #fdfbf7;
            border: none;
            border-radius: 3px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
            transition: background 0.3s, transform 0.2s;
        }
        .theme-parchment .btn:hover {
            background: #3d0f0f;
            transform: translateY(-2px);
        }
        .theme-parchment .btn.secondary {
            background: transparent;
            color: #5c1818;
            border: 1px solid #5c1818;
            box-shadow: none;
        }
        .theme-parchment .btn.secondary:hover {
            background: #f1e5d1;
        }
        .theme-parchment .post-content {
            font-size: 1.1em;
            line-height: 1.8;
            text-align: justify;
        }
        .theme-parchment .post-content::first-letter {
            float: left;
            font-size: 3.5em;
            line-height: 0.8;
            margin-right: 10px;
            font-family: 'Playfair Display', serif;
            color: #5c1818;
        }
    </style>
</head>
<body class="theme-manga">

    <div class="controls">
        <button onclick="setTheme('theme-pixel')" id="btn-pixel">🕹️ Retro Pixel Art</button>
        <button onclick="setTheme('theme-manga')" id="btn-manga" class="active">💥 Manga Brutalism</button>
        <button onclick="setTheme('theme-parchment')" id="btn-parchment">🗺️ Nautical Parchment</button>
    </div>

    <div class="playground-container">
        <h1>Centro de Pruebas Visuales</h1>
        
        <div class="card">
            <img src="https://i.imgur.com/gKnjVwP.jpeg" alt="Avatar" class="card-avatar">
            <div class="card-content">
                <h2>Roronoa Zoro</h2>
                <div class="tags">
                    <span class="tag">Espadachín</span>
                    <span class="tag">Nivel 45</span>
                    <span class="tag">Wanted: 320M</span>
                </div>
                <div class="post-content">
                    El viento soplaba fuerte en la cubierta. Zoro ajustó su bandana verde y desenvainó sus espadas con un brillo asesino en los ojos. No había vuelta atrás. Las olas chocaban contra el casco mientras la flota Marine se acercaba peligrosamente. "Si no podéis ni detener a un simple espadachín... ¿cómo pretendéis atrapar al futuro Rey de los Piratas?", murmuró con una sonrisa confiada.
                </div>
                <div class="button-group">
                    <a href="#" class="btn">Responder Tema</a>
                    <a href="#" class="btn secondary">Ver Ficha</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-content">
                <h2>Panel de Sistema</h2>
                <p>Las recompensas han sido actualizadas. Revisa el tablón de anuncios en la plaza principal de Loguetown para más detalles de las misiones globales activas. La tripulación del Sombrero de Paja ha sido avistada cerca del archipiélago Sabaody.</p>
                <div class="button-group">
                    <a href="#" class="btn secondary">Descartar Notificación</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setTheme(themeClass) {
            document.body.className = themeClass;
            
            // Update buttons
            document.querySelectorAll('.controls button').forEach(btn => btn.classList.remove('active'));
            document.getElementById('btn-' + themeClass.split('-')[1]).classList.add('active');
        }
    </script>
</body>
</html>

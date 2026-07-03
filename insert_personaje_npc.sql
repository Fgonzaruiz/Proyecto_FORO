INSERT INTO mybb_game_personajes (
    user_id, name, race, race_name, occupation, occupation_name,
    `desc`, details, rango, tripulacion, recompensa, banner, avatar,
    is_npc, status, stats_json, faction, approved
) VALUES (
    1, -- Asignado al admin (user_id 1) o NULL
    'Ryuken D. Maren',
    'humano',
    'Humano',
    'lider',
    'Líder Supremo',
    'El Guardián de las Sílabas / El Fantasma de Baltigo',
    'Líder Supremo de la Tercera Red del Ejército Revolucionario.',
    'Líder Supremo',
    'La Tercera Red (Ejército Revolucionario)',
    '700.000.000 Berries',
    '',
    '',
    1,
    'aprobado',
    '{"fue":7,"res":8,"agi":6,"des":7,"int":8,"inst":9,"esp":8}',
    'Revolucionario',
    1
);

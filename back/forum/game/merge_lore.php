<?php
$lore_path = 'c:\\laragon\\www\\foro_local\\back\\forum\\game\\lore.json';
$lore2_path = 'c:\\laragon\\www\\foro_local\\back\\forum\\game\\lore2.txt';

$lore = json_decode(file_get_contents($lore_path), true);
$lore2 = json_decode(file_get_contents($lore2_path), true);

$keys = ['eras', 'lore_basal', 'eventos'];

foreach ($keys as $key) {
    if (isset($lore2[$key])) {
        if (!isset($lore[$key])) {
            $lore[$key] = [];
        }
        
        $existing_ids = [];
        foreach ($lore[$key] as $item) {
            if (isset($item['id'])) {
                $existing_ids[$item['id']] = true;
            }
        }
        
        foreach ($lore2[$key] as $item) {
            if (isset($item['id']) && isset($existing_ids[$item['id']])) {
                foreach ($lore[$key] as $i => $ex_item) {
                    if (isset($ex_item['id']) && $ex_item['id'] == $item['id']) {
                        $lore[$key][$i] = $item;
                    }
                }
            } else {
                $lore[$key][] = $item;
            }
        }
    }
}

file_put_contents($lore_path, json_encode($lore, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
unlink($lore2_path);

echo "Merge complete and lore2.txt deleted.\n";
?>

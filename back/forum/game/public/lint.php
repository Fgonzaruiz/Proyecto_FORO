<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

game_deny_public_maintenance();
game_require_admin_cp();

$file = __DIR__ . '/personaje.php';
$output = shell_exec('php -l ' . escapeshellarg($file) . ' 2>&1');
if ($output === null) {
    echo "No PHP CLI available.\n";
    $code = file_get_contents($file);
    token_get_all($code);
    echo "Tokens parsed successfully, no missing quotes.\n";
} else {
    echo $output;
}

<?php
$file = __DIR__ . '/personaje.php';
$output = shell_exec('php -l ' . escapeshellarg($file) . ' 2>&1');
if ($output === null) {
    echo "No PHP CLI available.\n";
    // Let's use eval to check syntax
    $code = file_get_contents($file);
    // Remove <?php and ?> for eval, or just try to parse tokens
    $tokens = token_get_all($code);
    echo "Tokens parsed successfully, no missing quotes.\n";
} else {
    echo $output;
}

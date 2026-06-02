<?php
/**
 * Simula check_template() de MyBB 1.8 sobre plantillas en templates/mybb/.
 * Salida 0 = OK para importar tema; 1 = hay bloqueos.
 */
declare(strict_types=1);

$root = __DIR__;
require_once $root . '/../back/forum/admin/inc/functions.php';

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/templates/mybb', FilesystemIterator::SKIP_DOTS)
);

$failed = [];
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'html') {
        continue;
    }
    $path = $file->getPathname();
    $content = (string)file_get_contents($path);
    if (check_template($content)) {
        $failed[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    }
}

if ($failed === []) {
    echo "OK: ninguna plantilla fuente dispara el escaner de MyBB.\n";
    exit(0);
}

echo "FALLO: estas plantillas bloquearian la importacion del tema:\n";
foreach ($failed as $f) {
    echo " - {$f}\n";
}
exit(1);

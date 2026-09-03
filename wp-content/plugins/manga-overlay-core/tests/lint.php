<?php

declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS)
);
$failed = false;

foreach ($iterator as $file) {
    if (! $file instanceof SplFileInfo || 'php' !== $file->getExtension()) {
        continue;
    }

    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
        continue;
    }

    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
    passthru($command, $exitCode);
    if (0 !== $exitCode) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);

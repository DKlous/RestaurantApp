#!/usr/bin/env php
<?php

// Eenvoudige syntax-linter zonder extra package: runt `php -l` op
// elke PHP-bestand in de bron-mappen. Ziet syntax-fouten vóór runtime.
// Geeft exit-code 1 terug zodra er één bestand faalt.

$root = dirname(__DIR__);
$directories = ['src', 'tests', 'config', 'bin', 'public'];
$phpBinary = PHP_BINARY;

$files = [];
foreach ($directories as $directory) {
    $path = "{$root}/{$directory}";
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getPathname(), '.php')) {
            $files[] = $file->getPathname();
        }
    }
}
sort($files);

$errors = 0;
foreach ($files as $file) {
    $command = escapeshellarg($phpBinary) . ' -l ' . escapeshellarg($file);
    $output = [];
    exec($command, $output, $status);

    $relative = str_replace("{$root}/", '', $file);
    if ($status !== 0) {
        $errors++;
        echo "[FAIL] {$relative}\n";
        foreach (array_slice($output, 1) as $line) {
            echo "       {$line}\n";
        }
        continue;
    }
    echo "[ OK ] {$relative}\n";
}

if ($errors > 0) {
    fwrite(STDERR, "\n{$errors} bestand(en) met een syntax-fout.\n");
    exit(1);
}

echo "\nAlle " . count($files) . " PHP-bestand(en) zijn syntax-geldig.\n";
exit(0);

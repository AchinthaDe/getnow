<?php

declare(strict_types=1);

test('filament does not perform direct model writes', function () {
    $path = __DIR__.'/../../app/Filament';
    if (! file_exists($path)) {
        $this->markTestSkipped('Filament directory does not exist yet.');
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    $files = [];
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file;
        }
    }

    $offendingFiles = [];

    $patterns = [
        '::create(',
        '->update(',
        '->delete(',
        '->save(',
        '->forceDelete(',
    ];

    foreach ($files as $file) {
        $content = file_get_contents($file->getPathname());

        foreach ($patterns as $pattern) {
            if (str_contains($content, $pattern)) {
                $offendingFiles[] = $file->getPathname();
                break;
            }
        }
    }

    expect($offendingFiles)->toBeEmpty('Direct model writes found in Filament files: '.implode(', ', $offendingFiles));
});

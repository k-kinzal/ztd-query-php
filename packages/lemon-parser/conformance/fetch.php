<?php

/**
 * Fetches the grammars listed in conformance/corpus.txt into a directory, with their define sets.
 *
 * Usage:
 *   php conformance/fetch.php DIRECTORY
 *
 * Each line of corpus.txt names a file and its URL. A file already present is
 * not fetched again. A file defines/NAME.defines is copied next to NAME.y.
 * Exit status 1 when a fetch fails, 2 on usage errors.
 */

declare(strict_types=1);

$arguments = $_SERVER['argv'] ?? [];
$directory = is_array($arguments) && isset($arguments[1]) && is_string($arguments[1]) ? $arguments[1] : null;
if ($directory === null) {
    fwrite(STDERR, "Usage: php conformance/fetch.php DIRECTORY\n");
    exit(2);
}
if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
    fwrite(STDERR, "Cannot create {$directory}\n");
    exit(1);
}
$manifest = file_get_contents(__DIR__ . '/corpus.txt');
$failed = false;
foreach (explode("\n", $manifest === false ? '' : $manifest) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    $parts = preg_split('/\s+/', $line, 2);
    [$name, $url] = array_pad($parts === false ? [] : $parts, 2, '');
    $target = "{$directory}/{$name}";
    $defines = __DIR__ . '/defines/' . preg_replace('/\.y$/', '.defines', $name);
    if (is_file($defines)) {
        copy($defines, preg_replace('/\.y$/', '.defines', $target) ?? $target);
    }
    if (is_file($target)) {
        fwrite(STDOUT, "kept {$target}\n");
        continue;
    }
    $contents = file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'lemon-parser-conformance']]));
    if ($contents === false || file_put_contents($target, $contents) === false) {
        fwrite(STDERR, "failed {$url}\n");
        $failed = true;
        continue;
    }
    fwrite(STDOUT, "fetched {$target}\n");
}
exit($failed ? 1 : 0);

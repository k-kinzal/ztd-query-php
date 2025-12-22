#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SqlFaker\Sqlite\Lemon\LemonParser;

/**
 * Build script for generating AST cache from SQLite's parse.y (Lemon grammar)
 *
 * Usage:
 *   php bin/build-sqlite.php                      # Use default version (sqlite-3.47.2)
 *   php bin/build-sqlite.php --tag version-3.47.2 # Use specific tag
 *   php bin/build-sqlite.php --all                # Build all supported versions
 */

const SQLITE_SUPPORTED_VERSIONS = [
    'sqlite-3.47.2' => 'version-3.47.2',
];

const SQLITE_DEFAULT_VERSION = 'sqlite-3.47.2';

function sqliteParseArguments(array $argv): array
{
    $versions = [];
    $buildAll = false;

    for ($i = 1; $i < count($argv); $i++) {
        if ($argv[$i] === '--all') {
            $buildAll = true;
        } elseif ($argv[$i] === '--tag' && isset($argv[$i + 1])) {
            $versions[] = $argv[$i + 1];
            $i++;
        }
    }

    if ($buildAll) {
        return ['versions' => array_keys(SQLITE_SUPPORTED_VERSIONS)];
    }

    if (empty($versions)) {
        $versions = [SQLITE_DEFAULT_VERSION];
    }

    return ['versions' => $versions];
}

function sqliteBuildUrl(string $version): string
{
    $tag = SQLITE_SUPPORTED_VERSIONS[$version] ?? $version;

    return "https://raw.githubusercontent.com/sqlite/sqlite/refs/tags/{$tag}/src/parse.y";
}

function sqliteFetchGramFile(string $url): string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 120,
            'user_agent' => 'sql-faker/1.0',
        ],
    ]);

    set_error_handler(static function (int $severity, string $message): never {
        throw new \RuntimeException($message);
    });
    try {
        $contents = file_get_contents($url, false, $context);
    } catch (\RuntimeException $e) {
        fwrite(STDERR, "Error: Failed to fetch {$url}: {$e->getMessage()}\n");
        exit(1);
    } finally {
        restore_error_handler();
    }

    if ($contents === false) {
        fwrite(STDERR, "Error: Failed to fetch {$url}\n");
        exit(1);
    }

    return $contents;
}

function sqliteBuildVersion(string $version, LemonParser $parser): bool
{
    $url = sqliteBuildUrl($version);

    fwrite(STDOUT, "\n" . str_repeat('=', 60) . "\n");
    fwrite(STDOUT, "Fetching parse.y for {$version}...\n");
    fwrite(STDOUT, "URL: {$url}\n");

    $contents = sqliteFetchGramFile($url);
    $hash = hash('sha256', $contents);

    fwrite(STDOUT, "File hash: {$hash}\n");
    fwrite(STDOUT, "Parsing grammar...\n");

    try {
        $grammar = $parser->parse($contents);
    } catch (\Throwable $e) {
        fwrite(STDERR, "Error parsing {$version}: {$e->getMessage()}\n");
        fwrite(STDERR, "Trace: {$e->getTraceAsString()}\n");
        return false;
    }

    fwrite(STDOUT, "Rules: " . count($grammar->ruleMap) . "\n");
    fwrite(STDOUT, "Start symbol: {$grammar->startSymbol}\n");
    fwrite(STDOUT, "Serializing AST...\n");

    $serialized = serialize($grammar);

    $outputDir = __DIR__ . '/../resources/ast';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $outputPath = $outputDir . '/' . $version . '.php';

    $output = <<<PHP
<?php

declare(strict_types=1);

/**
 * Auto-generated AST cache for SQLite parse.y (Lemon grammar)
 *
 * Source: {$url}
 * Version: {$version}
 * Generated: %s
 *
 * @return array<string, string>
 */
return [
    '{$hash}' => '%s',
];

PHP;

    $output = sprintf(
        $output,
        date('Y-m-d H:i:s T'),
        addcslashes($serialized, "'\\")
    );

    if (file_put_contents($outputPath, $output) === false) {
        fwrite(STDERR, "Error: Failed to write {$outputPath}\n");
        return false;
    }

    fwrite(STDOUT, "Generated: {$outputPath}\n");

    return true;
}

function sqliteMain(array $argv): int
{
    $args = sqliteParseArguments($argv);
    $versions = $args['versions'];

    fwrite(STDOUT, "Building " . count($versions) . " SQLite version(s): " . implode(', ', $versions) . "\n");

    $parser = new LemonParser();

    $success = 0;
    $failed = 0;
    $failedVersions = [];

    foreach ($versions as $version) {
        if (sqliteBuildVersion($version, $parser)) {
            $success++;
        } else {
            $failed++;
            $failedVersions[] = $version;
        }
    }

    fwrite(STDOUT, "\n" . str_repeat('=', 60) . "\n");
    fwrite(STDOUT, "Build complete: {$success} succeeded, {$failed} failed\n");

    if ($failed > 0) {
        fwrite(STDERR, "Failed versions: " . implode(', ', $failedVersions) . "\n");
        return 1;
    }

    fwrite(STDOUT, "Done.\n");

    return 0;
}

exit(sqliteMain($argv));

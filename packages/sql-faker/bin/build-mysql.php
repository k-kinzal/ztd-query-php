#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SqlFaker\MySql\SnapshotCompiler;

/**
 * Build script for generating AST cache from MySQL's sql_yacc.yy
 *
 * Usage:
 *   php bin/build-mysql.php                    # Use default version (mysql-8.4.7)
 *   php bin/build-mysql.php --tag trunk        # Use trunk branch
 *   php bin/build-mysql.php --tag mysql-8.4.7  # Use specific tag
 *   php bin/build-mysql.php --all              # Build all supported versions
 *   php bin/build-mysql.php --all --verify     # Verify snapshots against rebuilt grammars
 */

/**
 * Supported MySQL versions (latest patch for each minor version)
 */
const SUPPORTED_VERSIONS = [
    'mysql-5.6.51',
    'mysql-5.7.44',
    'mysql-8.0.44',
    'mysql-8.1.0',
    'mysql-8.2.0',
    'mysql-8.3.0',
    'mysql-8.4.7',
    'mysql-9.0.1',
    'mysql-9.1.0',
];

const DEFAULT_VERSION = 'mysql-8.4.7';

function parseArguments(array $argv): array
{
    $tags = [];
    $buildAll = false;
    $verify = false;

    for ($i = 1; $i < count($argv); $i++) {
        if ($argv[$i] === '--all') {
            $buildAll = true;
        } elseif ($argv[$i] === '--verify') {
            $verify = true;
        } elseif ($argv[$i] === '--tag' && isset($argv[$i + 1])) {
            $tags[] = $argv[$i + 1];
            $i++;
        }
    }

    if ($buildAll) {
        return ['tags' => SUPPORTED_VERSIONS, 'verify' => $verify];
    }

    if (empty($tags)) {
        $tags = [DEFAULT_VERSION];
    }

    return ['tags' => $tags, 'verify' => $verify];
}

function buildUrl(string $tag): string
{
    $baseUrl = 'https://raw.githubusercontent.com/mysql/mysql-server';

    if ($tag === 'trunk') {
        return "{$baseUrl}/refs/heads/trunk/sql/sql_yacc.yy";
    }

    return "{$baseUrl}/refs/tags/{$tag}/sql/sql_yacc.yy";
}

function fetchYaccFile(string $url): string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 60,
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

function verifyExistingSnapshot(string $outputPath, string $serialized): bool
{
    if (!file_exists($outputPath)) {
        fwrite(STDERR, "Error: Snapshot file not found for verification: {$outputPath}\n");
        return false;
    }

    /** @var mixed $data */
    $data = require $outputPath;
    if (!is_array($data) || $data === []) {
        fwrite(STDERR, "Error: Snapshot file is invalid: {$outputPath}\n");
        return false;
    }

    $existing = $data[array_key_first($data)] ?? null;
    if (!is_string($existing)) {
        fwrite(STDERR, "Error: Snapshot payload is invalid: {$outputPath}\n");
        return false;
    }

    if ($existing !== $serialized) {
        fwrite(STDERR, "Error: Snapshot drift detected: {$outputPath}\n");
        return false;
    }

    fwrite(STDOUT, "Verified: {$outputPath}\n");

    return true;
}

function buildVersion(string $tag, SnapshotCompiler $compiler, bool $verify): bool
{
    $url = buildUrl($tag);

    fwrite(STDOUT, "\n" . str_repeat('=', 60) . "\n");
    fwrite(STDOUT, "Fetching sql_yacc.yy for {$tag}...\n");
    fwrite(STDOUT, "URL: {$url}\n");

    $contents = fetchYaccFile($url);
    $hash = hash('sha256', $contents);

    fwrite(STDOUT, "File hash: {$hash}\n");
    fwrite(STDOUT, "Parsing grammar...\n");

    try {
        $grammar = $compiler->compile($contents);
    } catch (\Throwable $e) {
        fwrite(STDERR, "Error parsing {$tag}: {$e->getMessage()}\n");
        return false;
    }

    fwrite(STDOUT, "Serializing AST...\n");

    $serialized = serialize($grammar);

    $outputDir = __DIR__ . '/../resources/ast';
    $outputPath = $outputDir . '/' . $tag . '.php';

    if ($verify) {
        return verifyExistingSnapshot($outputPath, $serialized);
    }

    // Ensure output directory exists
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $output = <<<PHP
<?php

declare(strict_types=1);

/**
 * Auto-generated AST cache for MySQL sql_yacc.yy
 *
 * Source: {$url}
 * Version: {$tag}
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

function main(array $argv): int
{
    $args = parseArguments($argv);
    $tags = $args['tags'];
    $verify = $args['verify'];

    fwrite(STDOUT, ($verify ? 'Verifying ' : 'Building ') . count($tags) . " version(s): " . implode(', ', $tags) . "\n");

    $compiler = new SnapshotCompiler();

    $success = 0;
    $failed = 0;
    $failedTags = [];

    foreach ($tags as $tag) {
        if (buildVersion($tag, $compiler, $verify)) {
            $success++;
        } else {
            $failed++;
            $failedTags[] = $tag;
        }
    }

    fwrite(STDOUT, "\n" . str_repeat('=', 60) . "\n");
    fwrite(STDOUT, "Build complete: {$success} succeeded, {$failed} failed\n");

    if ($failed > 0) {
        fwrite(STDERR, "Failed versions: " . implode(', ', $failedTags) . "\n");
        return 1;
    }

    fwrite(STDOUT, "Done.\n");

    return 0;
}

exit(main($argv));

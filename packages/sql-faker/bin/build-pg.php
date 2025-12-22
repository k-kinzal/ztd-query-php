#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SqlFaker\Grammar\GrammarCompiler;
use SqlFaker\MySql\Bison\BisonParser;

/**
 * Build script for generating AST cache from PostgreSQL's gram.y
 *
 * Usage:
 *   php bin/build-pg.php                      # Use default version (pg-17.2)
 *   php bin/build-pg.php --tag REL_17_STABLE  # Use specific tag
 *   php bin/build-pg.php --all                # Build all supported versions
 */

const PG_SUPPORTED_VERSIONS = [
    'pg-16.8' => 'REL_16_STABLE',
    'pg-17.2' => 'REL_17_STABLE',
];

const PG_DEFAULT_VERSION = 'pg-17.2';

function pgParseArguments(array $argv): array
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
        return ['versions' => array_keys(PG_SUPPORTED_VERSIONS)];
    }

    if (empty($versions)) {
        $versions = [PG_DEFAULT_VERSION];
    }

    return ['versions' => $versions];
}

function pgBuildUrl(string $version): string
{
    $branch = PG_SUPPORTED_VERSIONS[$version] ?? $version;

    return "https://raw.githubusercontent.com/postgres/postgres/refs/heads/{$branch}/src/backend/parser/gram.y";
}

function pgFetchGramFile(string $url): string
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

function pgBuildVersion(string $version, BisonParser $parser, GrammarCompiler $compiler): bool
{
    $url = pgBuildUrl($version);

    fwrite(STDOUT, "\n" . str_repeat('=', 60) . "\n");
    fwrite(STDOUT, "Fetching gram.y for {$version}...\n");
    fwrite(STDOUT, "URL: {$url}\n");

    $contents = pgFetchGramFile($url);
    $hash = hash('sha256', $contents);

    fwrite(STDOUT, "File hash: {$hash}\n");
    fwrite(STDOUT, "Parsing grammar...\n");

    try {
        $ast = $parser->parse($contents);
        $grammar = $compiler->compile($ast);
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
 * Auto-generated AST cache for PostgreSQL gram.y
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

function pgMain(array $argv): int
{
    $args = pgParseArguments($argv);
    $versions = $args['versions'];

    fwrite(STDOUT, "Building " . count($versions) . " PostgreSQL version(s): " . implode(', ', $versions) . "\n");

    $parser = new BisonParser();
    $compiler = new GrammarCompiler();

    $success = 0;
    $failed = 0;
    $failedVersions = [];

    foreach ($versions as $version) {
        if (pgBuildVersion($version, $parser, $compiler)) {
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

exit(pgMain($argv));

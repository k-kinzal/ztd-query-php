#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SqlFaker\Grammar\LexicalProfileBuilder;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\TerminalInventory;
use SqlFaker\Sqlite\Lemon\LemonParser;

/**
 * Build script for generating a versioned grammar and lexical profile from SQLite sources.
 *
 * Usage:
 *   php bin/build-sqlite.php                     # Use the default supported version
 *   php bin/build-sqlite.php --tag sqlite-3.47.2 # Use specific version
 *   php bin/build-sqlite.php --all                # Build all supported versions
 */

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
        return ['versions' => SqlVersion::names('sqlite')];
    }

    if (empty($versions)) {
        $versions = [SqlVersion::resolve('sqlite')->name];
    }

    return ['versions' => $versions];
}

function sqliteBuildUrl(string $version): string
{
    $tag = 'version-' . substr($version, strlen('sqlite-'));

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

function sqliteBuildVersion(
    string $version,
    LemonParser $parser,
    LexicalProfileBuilder $lexical,
): bool {
    try {
        $sqlVersion = SqlVersion::resolve('sqlite', $version);
    } catch (Throwable $throwable) {
        fwrite(STDERR, $throwable->getMessage() . "\n");

        return false;
    }
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
        fwrite(STDOUT, "Building lexical profile...\n");
        $profile = $lexical->sqlite($version);
        $lexical->assertCompatible($profile, 'sqlite', $version, TerminalInventory::fromGrammar($grammar));
    } catch (\Throwable $e) {
        fwrite(STDERR, "Error building {$version}: {$e->getMessage()}\n");
        fwrite(STDERR, "Trace: {$e->getTraceAsString()}\n");
        return false;
    }

    fwrite(STDOUT, "Rules: " . count($grammar->ruleMap) . "\n");
    fwrite(STDOUT, "Start symbol: {$grammar->startSymbol}\n");
    fwrite(STDOUT, "Serializing AST...\n");

    $serialized = serialize($grammar);

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

    try {
        $lexical->publishVersion($sqlVersion, $output, $profile);
    } catch (Throwable $throwable) {
        fwrite(STDERR, "Error publishing {$version}: {$throwable->getMessage()}\n");

        return false;
    }

    return true;
}

function sqliteMain(array $argv): int
{
    $args = sqliteParseArguments($argv);
    $versions = $args['versions'];

    fwrite(STDOUT, "Building " . count($versions) . " SQLite version(s): " . implode(', ', $versions) . "\n");

    $parser = new LemonParser();
    $lexical = new LexicalProfileBuilder();

    $success = 0;
    $failed = 0;
    $failedVersions = [];

    foreach ($versions as $version) {
        if (sqliteBuildVersion($version, $parser, $lexical)) {
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

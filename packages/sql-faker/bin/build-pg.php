#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SqlFaker\Compiler\Bison\BisonParser;
use SqlFaker\Compiler\Bison\GrammarCompiler;
use SqlFaker\Grammar\LexicalProfileCheck;
use SqlFaker\Grammar\LexicalProfileWriter;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\TerminalInventory;
use SqlFaker\PostgreSql\PgProfileBuilder;

/**
 * Build script for generating a versioned grammar and lexical profile from PostgreSQL sources.
 *
 * Usage:
 *   php bin/build-pg.php                 # Use the default supported version
 *   php bin/build-pg.php --tag pg-17.2   # Use specific version
 *   php bin/build-pg.php --all                # Build all supported versions
 */

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
        return ['versions' => SqlVersion::names('postgresql')];
    }

    if (empty($versions)) {
        $versions = [SqlVersion::resolve('postgresql')->name];
    }

    return ['versions' => $versions];
}

function pgBuildUrl(string $version): string
{
    $tag = strtoupper(str_replace(['pg-', '.'], ['REL_', '_'], $version));

    return "https://raw.githubusercontent.com/postgres/postgres/refs/tags/{$tag}/src/backend/parser/gram.y";
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
        throw new RuntimeException($message);
    });
    try {
        $contents = file_get_contents($url, false, $context);
    } catch (RuntimeException $e) {
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

function pgBuildVersion(
    string $version,
    BisonParser $parser,
    GrammarCompiler $compiler,
    PgProfileBuilder $lexical,
): bool {
    try {
        $sqlVersion = SqlVersion::resolve('postgresql', $version);
    } catch (Throwable $throwable) {
        fwrite(STDERR, $throwable->getMessage() . "\n");

        return false;
    }
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
        fwrite(STDOUT, "Building lexical profile...\n");
        $profile = $lexical->build($version);
        (new LexicalProfileCheck())->assertCompatible($profile, 'postgresql', $version, TerminalInventory::fromGrammar($grammar));
    } catch (Throwable $e) {
        fwrite(STDERR, "Error building {$version}: {$e->getMessage()}\n");
        fwrite(STDERR, "Trace: {$e->getTraceAsString()}\n");
        return false;
    }

    fwrite(STDOUT, 'Rules: ' . count($grammar->ruleMap) . "\n");
    fwrite(STDOUT, "Start symbol: {$grammar->startSymbol}\n");
    fwrite(STDOUT, "Serializing AST...\n");

    $serialized = serialize($grammar);

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

    try {
        (new LexicalProfileWriter())->publishVersion($sqlVersion, $output, $profile);
    } catch (Throwable $throwable) {
        fwrite(STDERR, "Error publishing {$version}: {$throwable->getMessage()}\n");

        return false;
    }

    return true;
}

function pgMain(array $argv): int
{
    $args = pgParseArguments($argv);
    $versions = $args['versions'];

    fwrite(STDOUT, 'Building ' . count($versions) . ' PostgreSQL version(s): ' . implode(', ', $versions) . "\n");

    $parser = new BisonParser();
    $compiler = new GrammarCompiler();
    $lexical = new PgProfileBuilder();

    $success = 0;
    $failed = 0;
    $failedVersions = [];

    foreach ($versions as $version) {
        if (pgBuildVersion($version, $parser, $compiler, $lexical)) {
            $success++;
        } else {
            $failed++;
            $failedVersions[] = $version;
        }
    }

    fwrite(STDOUT, "\n" . str_repeat('=', 60) . "\n");
    fwrite(STDOUT, "Build complete: {$success} succeeded, {$failed} failed\n");

    if ($failed > 0) {
        fwrite(STDERR, 'Failed versions: ' . implode(', ', $failedVersions) . "\n");
        return 1;
    }

    fwrite(STDOUT, "Done.\n");

    return 0;
}

exit(pgMain($argv));

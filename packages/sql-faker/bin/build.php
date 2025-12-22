#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SqlFaker\MySql\Bison\BisonParser;
use SqlFaker\MySql\Grammar\GrammarCompiler;

/**
 * Build script for generating AST cache from MySQL's sql_yacc.yy
 *
 * Usage:
 *   php bin/build.php                    # Use default version (mysql-8.4.7)
 *   php bin/build.php --tag trunk        # Use trunk branch
 *   php bin/build.php --tag mysql-8.4.7  # Use specific tag
 *   php bin/build.php --all              # Build all supported versions
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

    for ($i = 1; $i < count($argv); $i++) {
        if ($argv[$i] === '--all') {
            $buildAll = true;
        } elseif ($argv[$i] === '--tag' && isset($argv[$i + 1])) {
            $tags[] = $argv[$i + 1];
            $i++;
        }
    }

    if ($buildAll) {
        return ['tags' => SUPPORTED_VERSIONS];
    }

    if (empty($tags)) {
        $tags = [DEFAULT_VERSION];
    }

    return ['tags' => $tags];
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

    $contents = @file_get_contents($url, false, $context);

    if ($contents === false) {
        fwrite(STDERR, "Error: Failed to fetch {$url}\n");
        exit(1);
    }

    return $contents;
}

function buildVersion(string $tag, BisonParser $parser, GrammarCompiler $compiler): bool
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
        $ast = $parser->parse($contents);
        $grammar = $compiler->compile($ast);
    } catch (\Throwable $e) {
        fwrite(STDERR, "Error parsing {$tag}: {$e->getMessage()}\n");
        return false;
    }

    fwrite(STDOUT, "Serializing AST...\n");

    $serialized = serialize($grammar);

    // Ensure output directory exists
    $outputDir = __DIR__ . '/../src/ast';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $outputPath = $outputDir . '/' . $tag . '.php';

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

    fwrite(STDOUT, "Building " . count($tags) . " version(s): " . implode(', ', $tags) . "\n");

    $parser = new BisonParser();
    $compiler = new GrammarCompiler();

    $success = 0;
    $failed = 0;
    $failedTags = [];

    foreach ($tags as $tag) {
        if (buildVersion($tag, $parser, $compiler)) {
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

#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SqlFaker\Compiler\Bison\BisonParser;
use SqlFaker\Compiler\Bison\GrammarCompiler;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\TerminalInventory;
use SqlFaker\Tooling\LexicalProfileBuilder;

/**
 * Build script for generating a versioned grammar and lexical profile from MySQL sources.
 *
 * Usage:
 *   php bin/build-mysql.php                    # Use the default supported version
 *   php bin/build-mysql.php --tag mysql-8.4.7  # Use specific tag
 *   php bin/build-mysql.php --all              # Build all supported versions
 */

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
        return ['tags' => SqlVersion::names('mysql')];
    }

    if (empty($tags)) {
        $tags = [SqlVersion::resolve('mysql')->name];
    }

    return ['tags' => $tags];
}

function buildUrl(string $tag): string
{
    $baseUrl = 'https://raw.githubusercontent.com/mysql/mysql-server';

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

function buildVersion(
    string $tag,
    BisonParser $parser,
    GrammarCompiler $compiler,
    LexicalProfileBuilder $lexical,
): bool {
    try {
        $version = SqlVersion::resolve('mysql', $tag);
    } catch (Throwable $throwable) {
        fwrite(STDERR, $throwable->getMessage() . "\n");

        return false;
    }
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
        fwrite(STDOUT, "Building lexical profile...\n");
        $profile = $lexical->mysql($tag, $grammar);
        $lexical->assertCompatible($profile, 'mysql', $tag, TerminalInventory::fromGrammar($grammar));
    } catch (Throwable $e) {
        fwrite(STDERR, "Error building {$tag}: {$e->getMessage()}\n");
        return false;
    }

    fwrite(STDOUT, "Serializing AST...\n");

    $serialized = serialize($grammar);

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

    try {
        $lexical->publishVersion($version, $output, $profile);
    } catch (Throwable $throwable) {
        fwrite(STDERR, "Error publishing {$tag}: {$throwable->getMessage()}\n");

        return false;
    }

    return true;
}

function main(array $argv): int
{
    $args = parseArguments($argv);
    $tags = $args['tags'];

    fwrite(STDOUT, 'Building ' . count($tags) . ' version(s): ' . implode(', ', $tags) . "\n");

    $parser = new BisonParser();
    $compiler = new GrammarCompiler();
    $lexical = new LexicalProfileBuilder();

    $success = 0;
    $failed = 0;
    $failedTags = [];

    foreach ($tags as $tag) {
        if (buildVersion($tag, $parser, $compiler, $lexical)) {
            $success++;
        } else {
            $failed++;
            $failedTags[] = $tag;
        }
    }

    fwrite(STDOUT, "\n" . str_repeat('=', 60) . "\n");
    fwrite(STDOUT, "Build complete: {$success} succeeded, {$failed} failed\n");

    if ($failed > 0) {
        fwrite(STDERR, 'Failed versions: ' . implode(', ', $failedTags) . "\n");
        return 1;
    }

    fwrite(STDOUT, "Done.\n");

    return 0;
}

exit(main($argv));

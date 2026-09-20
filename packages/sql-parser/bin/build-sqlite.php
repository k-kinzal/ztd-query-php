#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SqlParser\Automaton\ParseTableBuilder;
use SqlParser\Compiler\LemonGrammarReader;
use SqlParser\Resource\ResourceWriter;
use SqlParser\Resource\SourceFetcher;
use SqlParser\Resource\VersionRegistry;
use SqlParser\Sqlite\Source\KeywordHash;

/**
 * Builds the SQLite parse tables and keyword tables from the library sources.
 *
 * Usage:
 *   php bin/build-sqlite.php                          Build the default release
 *   php bin/build-sqlite.php --tag sqlite-3.47.2      Build one release
 *   php bin/build-sqlite.php --all                    Build every release
 *   php bin/build-sqlite.php -D SQLITE_OMIT_WINDOWFUNC  Build with a compile-time option
 *
 * Without options the grammar is built as the amalgamation is, with every
 * feature compiled in except the ones an `SQLITE_ENABLE_*` option adds.
 * Downloaded sources are kept under build/sources so a rebuild is offline.
 */

/**
 * Reads the release tags and defines from the arguments.
 *
 * @param list<string> $arguments Command-line arguments after the script name
 * @param VersionRegistry $registry Record of shipped releases
 *
 * @return array{list<string>, list<string>} Release tags and defines
 */
function sqliteOptions(array $arguments, VersionRegistry $registry): array
{
    $tags = [];
    $defines = [];
    $all = false;
    for ($index = 0; $index < count($arguments); $index++) {
        if ($arguments[$index] === '--all') {
            $all = true;
        } elseif ($arguments[$index] === '--tag' && isset($arguments[$index + 1])) {
            $tags[] = $arguments[++$index];
        } elseif ($arguments[$index] === '-D' && isset($arguments[$index + 1])) {
            $defines[] = $arguments[++$index];
        }
    }
    if ($all) {
        $tags = $registry->names('sqlite');
    }

    return [$tags === [] ? [$registry->resolve('sqlite')->name] : $tags, $defines];
}

$registry = new VersionRegistry();
[$tags, $defines] = sqliteOptions(array_slice($argv, 1), $registry);
$fetcher = new SourceFetcher(__DIR__ . '/../build/sources');
$writer = new ResourceWriter();
$reader = new LemonGrammarReader();
$builder = new ParseTableBuilder();
$failed = false;
foreach ($tags as $tag) {
    $version = $registry->resolve('sqlite', $tag);
    $base = 'https://raw.githubusercontent.com/sqlite/sqlite/refs/tags/version-' . substr($tag, strlen('sqlite-'));
    fwrite(STDOUT, "Building {$tag}\n");
    $grammar = $reader->read($fetcher->fetch("{$base}/src/parse.y"), $defines);
    $result = $builder->build($grammar);
    $conflicts = $result->conflicts;
    fwrite(STDOUT, sprintf("  %d states, %d shift/reduce and %d reduce/reduce conflicts\n", $result->stateCount, $conflicts->shiftReduce, $conflicts->reduceReduce));
    if (!$conflicts->isExpected()) {
        fwrite(STDERR, "  Lemon accepts no conflict; the table is not written.\n");
        $failed = true;
        continue;
    }
    $writer->writeTable($version, $result->table);
    $writer->writeKeywords($version, (new KeywordHash($defines))->parse($fetcher->fetch("{$base}/tool/mkkeywordhash.c")), "{$base}/tool/mkkeywordhash.c");
    fwrite(STDOUT, "  Wrote {$version->tablePath}\n  Wrote {$version->keywordPath}\n");
}
exit($failed ? 1 : 0);

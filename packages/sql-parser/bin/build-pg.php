#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SqlParser\Automaton\ParseTableBuilder;
use SqlParser\Compiler\Bison\BisonReader;
use SqlParser\PostgreSql\Source\KeywordList;
use SqlParser\Resource\ResourceWriter;
use SqlParser\Resource\SourceFetcher;
use SqlParser\Resource\VersionRegistry;

/**
 * Builds the PostgreSQL parse tables and keyword tables from the server sources.
 *
 * Usage:
 *   php bin/build-pg.php                 Build the default release
 *   php bin/build-pg.php --tag pg-17.2   Build one release
 *   php bin/build-pg.php --all           Build every release
 *
 * Downloaded sources are kept under build/sources so a rebuild is offline.
 */

/**
 * Reads the release tags to build from the arguments.
 *
 * @param list<string> $arguments Command-line arguments after the script name
 * @param VersionRegistry $registry Record of shipped releases
 *
 * @return list<string> Release tags
 */
function pgTagsToBuild(array $arguments, VersionRegistry $registry): array
{
    $tags = [];
    for ($index = 0; $index < count($arguments); $index++) {
        if ($arguments[$index] === '--all') {
            return $registry->names('postgresql');
        }
        if ($arguments[$index] === '--tag' && isset($arguments[$index + 1])) {
            $tags[] = $arguments[++$index];
        }
    }

    return $tags === [] ? [$registry->resolve('postgresql')->name] : $tags;
}

/**
 * Names the git tag of a release, `pg-17.2` being `REL_17_2`.
 *
 * @param string $tag Release tag
 *
 * @return string Git tag
 */
function pgGitTag(string $tag): string
{
    return 'REL_' . str_replace('.', '_', substr($tag, strlen('pg-')));
}

$registry = new VersionRegistry();
$fetcher = new SourceFetcher(__DIR__ . '/../build/sources');
$writer = new ResourceWriter();
$reader = new BisonReader();
$builder = new ParseTableBuilder();
$failed = false;
foreach (pgTagsToBuild(array_slice($argv, 1), $registry) as $tag) {
    $version = $registry->resolve('postgresql', $tag);
    $base = 'https://raw.githubusercontent.com/postgres/postgres/refs/tags/' . pgGitTag($tag) . '/src';
    fwrite(STDOUT, "Building {$tag}\n");
    $grammar = $reader->read($fetcher->fetch("{$base}/backend/parser/gram.y"));
    $result = $builder->build($grammar);
    $conflicts = $result->conflicts;
    fwrite(STDOUT, sprintf(
        "  %d states, %d shift/reduce and %d reduce/reduce conflicts, %s expected\n",
        $result->stateCount,
        $conflicts->shiftReduce,
        $conflicts->reduceReduce,
        $conflicts->expected === null ? 'none' : (string) $conflicts->expected,
    ));
    if (!$conflicts->isExpected()) {
        fwrite(STDERR, "  The conflicts differ from what the grammar expects; the table is not written.\n");
        $failed = true;
        continue;
    }
    $writer->writeTable($version, $result->table);
    $writer->writeKeywords($version, (new KeywordList())->parse($fetcher->fetch("{$base}/include/parser/kwlist.h")), "{$base}/include/parser/kwlist.h");
    fwrite(STDOUT, "  Wrote {$version->tablePath}\n  Wrote {$version->keywordPath}\n");
}
exit($failed ? 1 : 0);

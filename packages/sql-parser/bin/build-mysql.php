#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SqlParser\Automaton\ParseTableBuilder;
use SqlParser\Compiler\BisonGrammarReader;
use SqlParser\MySql\Source\LexHeader;
use SqlParser\Resource\ResourceWriter;
use SqlParser\Resource\SourceFetcher;
use SqlParser\Resource\VersionRegistry;

/**
 * Builds the MySQL parse tables and keyword tables from the server sources.
 *
 * Usage:
 *   php bin/build-mysql.php                    Build the default release
 *   php bin/build-mysql.php --tag mysql-8.4.7  Build one release
 *   php bin/build-mysql.php --all              Build every release
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
function mysqlTagsToBuild(array $arguments, VersionRegistry $registry): array
{
    $tags = [];
    for ($index = 0; $index < count($arguments); $index++) {
        if ($arguments[$index] === '--all') {
            return $registry->names('mysql');
        }
        if ($arguments[$index] === '--tag' && isset($arguments[$index + 1])) {
            $tags[] = $arguments[++$index];
        }
    }

    return $tags === [] ? [$registry->resolve('mysql')->name] : $tags;
}

$registry = new VersionRegistry();
$fetcher = new SourceFetcher(__DIR__ . '/../build/sources');
$writer = new ResourceWriter();
$reader = new BisonGrammarReader();
$builder = new ParseTableBuilder();
$failed = false;
foreach (mysqlTagsToBuild(array_slice($argv, 1), $registry) as $tag) {
    $version = $registry->resolve('mysql', $tag);
    $base = "https://raw.githubusercontent.com/mysql/mysql-server/refs/tags/{$tag}/sql";
    fwrite(STDOUT, "Building {$tag}\n");
    $grammar = $reader->read($fetcher->fetch("{$base}/sql_yacc.yy"));
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
    $writer->writeKeywords($version, (new LexHeader())->parse($fetcher->fetch("{$base}/lex.h")), "{$base}/lex.h");
    fwrite(STDOUT, "  Wrote {$version->tablePath}\n  Wrote {$version->keywordPath}\n");
}
exit($failed ? 1 : 0);

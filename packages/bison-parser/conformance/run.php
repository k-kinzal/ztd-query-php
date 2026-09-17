<?php

/**
 * Checks this package against GNU Bison on a corpus of grammar files.
 *
 * Usage:
 *   php conformance/run.php [--bison=PATH] DIRECTORY|FILE...
 *
 * Every `.y` and `.yy` file under the directories is read by Bison and by this
 * package. A file Bison accepts must parse, and printing the tree must give a
 * file from which Bison produces the same XML report. A file Bison's scanner
 * or parser refuses must raise a SyntaxException. Exit status 1 on any
 * difference, 2 on usage errors.
 */

declare(strict_types=1);

ini_set('memory_limit', '1G');

require_once __DIR__ . '/../vendor/autoload.php';

use Conformance\Checker;
use Conformance\Corpus;
use Conformance\Reference;
use Conformance\Runner;

$bison = 'bison';
$roots = [];
$arguments = $_SERVER['argv'] ?? [];
foreach (array_slice(is_array($arguments) ? $arguments : [], 1) as $argument) {
    if (!is_string($argument)) {
        continue;
    }
    if (str_starts_with($argument, '--bison=')) {
        $bison = substr($argument, strlen('--bison='));
    } else {
        $roots[] = $argument;
    }
}
if ($roots === []) {
    fwrite(STDERR, "Usage: php conformance/run.php [--bison=PATH] DIRECTORY|FILE...\n");
    exit(2);
}
$reference = new Reference($bison);
fwrite(STDOUT, 'Reference: ' . $reference->version() . "\n");
$files = (new Corpus())->files($roots);
$ties = [];
foreach (explode("\n", (string) file_get_contents(__DIR__ . '/known-ties.txt')) as $line) {
    if (preg_match('/^([0-9a-f]{64})\b/', $line, $match) === 1) {
        $ties[] = $match[1];
    }
}
$passed = (new Runner(new Checker($reference, knownTies: $ties), STDOUT))->run($files);
exit($passed ? 0 : 1);

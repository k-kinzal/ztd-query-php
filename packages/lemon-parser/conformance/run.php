<?php

/**
 * Checks this package against Lemon on a corpus of grammar files.
 *
 * Usage:
 *   php conformance/run.php [--lemon=PATH] DIRECTORY|FILE...
 *
 * Every `.y` file under the directories is read by Lemon and by this package,
 * under each set of defines its `.defines` file lists. The preprocessed text
 * must equal `lemon -E`, a file Lemon accepts must parse and print to a file
 * from which Lemon produces the same report, and a file Lemon refuses must
 * raise a SyntaxException. Exit status 1 on any difference, 2 on usage errors.
 */

declare(strict_types=1);

ini_set('memory_limit', '1G');

require_once __DIR__ . '/../vendor/autoload.php';

use Conformance\Checker;
use Conformance\Corpus;
use Conformance\Reference;
use Conformance\Runner;

$lemon = 'lemon';
$roots = [];
$arguments = $_SERVER['argv'] ?? [];
foreach (array_slice(is_array($arguments) ? $arguments : [], 1) as $argument) {
    if (!is_string($argument)) {
        continue;
    }
    if (str_starts_with($argument, '--lemon=')) {
        $lemon = substr($argument, strlen('--lemon='));
    } else {
        $roots[] = $argument;
    }
}
if ($roots === []) {
    fwrite(STDERR, "Usage: php conformance/run.php [--lemon=PATH] DIRECTORY|FILE...\n");
    exit(2);
}
$reference = new Reference($lemon);
fwrite(STDOUT, 'Reference: ' . $reference->version() . "\n");
$corpus = new Corpus();
$passed = (new Runner(new Checker($reference), $corpus, STDOUT))->run($corpus->files($roots));
exit($passed ? 0 : 1);

<?php

/**
 * Direct semantic-structure campaign: php fuzz/structures.php COUNT [SEED] [CLASS|-] [RELEASE] [START].
 *
 * Every non-success is retained. A SQL error is an unresolved property failure until its
 * state dependence is established, not an automatic success. Exit 2 means generation was
 * incomplete; exit 1 means a generated structure failed; exit 0 requires every trial to run.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Execution;
use Fuzz\Structure\Exhausted;
use Fuzz\Structure\Metadata;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Scalar;

$count = filter_var($argv[1] ?? '100', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$seed = $argv[2] ?? bin2hex(random_bytes(16));
$root = ($argv[3] ?? '-') === '-' ? null : $argv[3];
$release = $argv[4] ?? '8.4.7';
$start = filter_var($argv[5] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($count === false || $start === false) {
    fwrite(STDERR, "Usage: php fuzz/structures.php COUNT [SEED] [CLASS|-] [RELEASE] [START]\n");
    exit(2);
}
$catalogue = new Catalogue();
$metadata = new Metadata();
$roots = $catalogue->roots();
if ($root !== null && !in_array($root, $roots, true)) {
    fwrite(STDERR, "CLASS must be an inventoried Statement or Scalar.\n");
    exit(2);
}
$counts = ['passed' => 0, 'exhausted' => 0, 'generation' => 0, 'sql_error' => 0, 'structure' => 0];
echo json_encode(['seed' => $seed, 'release' => $release, 'start' => $start, 'trials' => $count, 'rootTypes' => count($roots)], JSON_THROW_ON_ERROR) . "\n";
for ($trial = 0; $trial < $count; $trial++) {
    $index = $start + $trial;
    $choices = new Choices($seed . ':' . $index);
    $selected = $choices->one($roots);
    $class = $root ?? $selected;
    $construction = new Construction($choices, $catalogue, $metadata);
    $stage = 'generation';
    try {
        $node = $construction->object($class, 6);
        if (!$node instanceof Statement && !$node instanceof Scalar) {
            throw new RuntimeException('A selected root does not implement its declared interface.');
        }
        $stage = 'structure';
        (new Execution())->run($node, $release);
        $counts['passed']++;
    } catch (Throwable $failure) {
        $kind = $failure instanceof Exhausted ? 'exhausted' : ($failure instanceof SqlError ? 'sql_error' : $stage);
        $counts[$kind]++;
        echo json_encode(['index' => $index, 'class' => $class, 'kind' => $kind, 'failure' => $failure::class, 'message' => $failure->getMessage(), 'code' => $failure->getCode(), 'constructorAttempts' => $construction->attempts, 'rejections' => $construction->rejections], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n";
    }
}
echo json_encode(['seed' => $seed, 'release' => $release, 'counts' => $counts], JSON_THROW_ON_ERROR) . "\n";
exit($counts['generation'] + $counts['exhausted'] > 0 ? 2 : ($counts['passed'] === $count ? 0 : 1));

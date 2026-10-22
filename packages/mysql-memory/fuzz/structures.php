<?php

/**
 * Direct semantic-structure campaign: php fuzz/structures.php COUNT [SEED] [CLASS|-|@each] [RELEASE] [START] [native|compare].
 *
 * Local runs distinguish explicit runtime state conditions from unresolved SQL errors.
 * In compare mode every complete outcome must match
 * a fresh native server, including rows, metadata, errors and warnings. No SQL error is
 * accepted merely because it was caught. Generation and internal failures remain failures.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Fuzz\Structure\Bindings;
use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Execution;
use Fuzz\Structure\Exhausted;
use Fuzz\Structure\Metadata;
use Fuzz\Structure\Profiles;
use Fuzz\Structure\Reference;
use Fuzz\Structure\Rejections;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Scalar;

$count = filter_var($argv[1] ?? '100', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$seed = $argv[2] ?? bin2hex(random_bytes(16));
$selection = $argv[3] ?? '-';
$root = in_array($selection, ['-', '@each'], true) ? null : $selection;
$release = $argv[4] ?? 'auto';
$start = filter_var($argv[5] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$audit = ($argv[6] ?? '') === 'native';
$compare = ($argv[6] ?? '') === 'compare';
$auditCounts = ['matchingError' => 0, 'different' => 0, 'failed' => 0];
$comparisons = ['matchedSuccess' => 0, 'matchedError' => 0, 'different' => 0, 'failed' => 0];
$transitions = ['structures' => 0, 'observations' => 0, 'different' => 0];
if ($count === false || $start === false) {
    fwrite(STDERR, "Usage: php fuzz/structures.php COUNT [SEED] [CLASS|-|@each] [RELEASE] [START] [native|compare]\n");
    exit(2);
}
$catalogue = new Catalogue();
$metadata = new Metadata();
$roots = $catalogue->roots();
if ($root !== null && !in_array($root, $roots, true)) {
    fwrite(STDERR, "CLASS must be an inventoried Statement or Scalar.\n");
    exit(2);
}
$counts = ['passed' => 0, 'runtime_condition' => 0, 'exhausted' => 0, 'generation' => 0, 'profile' => 0, 'sql_error' => 0, 'structure' => 0];
echo json_encode(['seed' => $seed, 'release' => $release, 'start' => $start, 'trials' => $count, 'rootTypes' => count($roots), 'selection' => $selection, 'nativeAudit' => $audit, 'compare' => $compare], JSON_THROW_ON_ERROR) . "\n";
for ($trial = 0; $trial < $count; $trial++) {
    $index = $start + $trial;
    $choices = new Choices($seed . ':' . $index);
    $selected = $choices->one($roots);
    $class = $selection === '@each' ? $roots[$index % count($roots)] : ($root ?? $selected);
    $construction = new Construction($choices, $catalogue, $metadata);
    $stage = 'generation';
    $node = null;
    $profile = null;
    $mode = null;
    try {
        $node = $construction->object($class, 6);
        if (!$node instanceof Statement && !$node instanceof Scalar) {
            throw new RuntimeException('A selected root does not implement its declared interface.');
        }
        $version = $release;
        if ($release === 'auto') {
            $stage = 'profile';
            $profiles = new Profiles();
            $profile = $profiles->select($node);
            $version = substr($profile->grammar->value, 6);
            $mode = $profiles->mode($profile);
        }
        if (($audit || $compare) && $mode === null) {
            $mode = (string) (new MySqlMemory\Instance($version))->connect()->variables->read('sql_mode');
        }
        $stage = 'structure';
        if ($compare) {
            $profile ??= new LanguageProfile(GrammarRelease::from('mysql-' . $release), parameters: (new Execution())->parameters($node));
            $port = filter_var(getenv('MYSQL_MEMORY_STRUCTURE_NATIVE_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            $observed = (new Fuzz\Structure\Audit\Comparison())->compare($node, $profile, $mode, $port === false ? null : $port);
            $history = $observed['transitions'];
            $transitions['structures'] += $history === [] ? 0 : 1;
            $transitions['observations'] += count($history);
            $transitions['different'] += count(array_filter($history, static fn (array $step): bool => !$step['matches']));
            $error = isset($observed['memory']['error']);
            $counts[$error ? 'sql_error' : 'passed']++;
            $comparisons[$observed['matches'] === true ? ($error ? 'matchedError' : 'matchedSuccess') : 'different']++;
            echo json_encode(['index' => $index, 'class' => $class, 'comparison' => $observed], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n";
            continue;
        }
        (new Execution())->run($node, $version, (new Bindings())->nulls($node), $mode);
        $counts['passed']++;
    } catch (Throwable $failure) {
        $kind = $failure instanceof Exhausted ? 'exhausted' : ($failure instanceof SqlError ? 'sql_error' : $stage);
        $reason = $failure instanceof SqlError && $stage === 'structure' && ($node instanceof Statement || $node instanceof Scalar) ? (new Rejections())->reason($failure, $node) : null;
        if (!$compare && $reason !== null) {
            $kind = 'runtime_condition';
        }
        $counts[$kind]++;
        if ($compare && $stage === 'structure') {
            $comparisons['failed']++;
        }
        echo json_encode(['index' => $index, 'class' => $class, 'kind' => $kind, 'reason' => $reason, 'failure' => $failure::class, 'message' => $failure->getMessage(), 'code' => $failure->getCode(), 'constructorAttempts' => $construction->attempts, 'rejections' => $construction->rejections], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n";
        if ($audit && $failure instanceof SqlError && ($node instanceof Statement || $node instanceof Scalar)) {
            try {
                $profile ??= new LanguageProfile(GrammarRelease::from('mysql-' . $release), parameters: (new Execution())->parameters($node));
                $port = filter_var(getenv('MYSQL_MEMORY_STRUCTURE_NATIVE_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
                $observed = (new Reference())->rejection($node, $profile, $failure, $port === false ? null : $port, $mode);
                $auditCounts[$observed['matches'] === true ? 'matchingError' : 'different']++;
                echo json_encode(['index' => $index, 'audit' => $observed], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n";
            } catch (Throwable $auditFailure) {
                $auditCounts['failed']++;
                echo json_encode(['index' => $index, 'auditFailure' => $auditFailure::class, 'message' => $auditFailure->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n";
            }
        }
    }
}
echo json_encode(['seed' => $seed, 'release' => $release, 'counts' => $counts, 'nativeAudit' => $auditCounts, 'comparisons' => $comparisons, 'databaseTransitions' => $transitions], JSON_THROW_ON_ERROR) . "\n";
$success = $compare ? $comparisons['matchedSuccess'] + $comparisons['matchedError'] === $count : $counts['passed'] + $counts['runtime_condition'] === $count;
exit($counts['generation'] + $counts['exhausted'] > 0 ? 2 : ($success ? 0 : 1));

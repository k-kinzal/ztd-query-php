<?php

/**
 * Runs a differential campaign outside the fuzzer and reports the differences grouped by kind.
 *
 * Usage: php fuzz/campaign.php MODE COUNT [SEED]
 * Each kind is printed with its count and its shortest statement, followed by the difference of
 * that statement. MYSQL_MEMORY_VERBOSE=1 prints every differing statement.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Faker\Factory;
use Fuzz\Target\Plans;
use Fuzz\Target\Servers;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\MySql\MySqlProvider;

$mode = $argv[1] ?? 'expression';
$count = filter_var($argv[2] ?? 100, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$seed = filter_var($argv[3] ?? 1, FILTER_VALIDATE_INT);
if (!in_array($mode, ['expression', 'query', 'select', 'write', 'statement'], true) || $count === false || $seed === false) {
    fwrite(STDERR, "Usage: php fuzz/campaign.php {expression|query|select|write|statement} COUNT [SEED]; COUNT must be positive.\n");
    exit(2);
}
mt_srand($seed);
[$target, $grammar, $server] = (new Servers())->start(getenv('MYSQL_MEMORY_EMULATE') !== '0');
$provider = new MySqlProvider(Factory::create(), $grammar);
$planner = $provider->planner();
$plans = new Plans();
$constraints = $plans->plan($mode, $grammar);
$compiler = new BytePlanCompiler();
$kinds = [];
$compared = 0;
$volatile = 0;
$generationFailures = 0;
$findings = [];
echo "Campaign: {$grammar}, {$mode}, {$count} inputs, seed {$seed}\n";
for ($i = 0; $i < $count; $i++) {
    $input = '';
    for ($b = 0, $length = mt_rand(8, 200); $b < $length; $b++) {
        $input .= chr(mt_rand(0, 255));
    }
    try {
        $sql = $plans->statement($mode, $provider->generate($compiler->compile($input, $planner, $constraints)));
    } catch (Throwable $failure) {
        $generationFailures++;
        fwrite(STDERR, "Generation failed at input {$i} (hex " . bin2hex($input) . "): {$failure->getMessage()}\n");
        continue;
    }
    $comparison = $target->compare($sql);
    if ($comparison->volatile) {
        $volatile++;
        continue;
    }
    $compared++;
    $difference = $comparison->difference;
    if ($difference === null) {
        continue;
    }
    $findings[] = ['index' => $i, 'input' => bin2hex($input), 'sql' => $sql, 'sqlHex' => bin2hex($sql), 'difference' => $difference];
    $kind = signature($difference);
    if (!isset($kinds[$kind]) || strlen($sql) < strlen($kinds[$kind][1])) {
        $kinds[$kind] = [($kinds[$kind][0] ?? 0) + 1, $sql, $difference];
    } else {
        $kinds[$kind][0]++;
    }
    if (getenv('MYSQL_MEMORY_VERBOSE') === '1') {
        echo "=== {$sql}\n{$difference}\n";
    }
}
uasort($kinds, static fn (array $left, array $right): int => $right[0] <=> $left[0]);
$failures = array_sum(array_column($kinds, 0));
foreach ($kinds as $kind => [$number, $sql, $difference]) {
    echo "### {$number} × {$kind}\n    {$sql}\n" . preg_replace('/^/m', '    ', substr($difference, 0, (int) ((new Servers())->environment('MYSQL_MEMORY_DIFF_BYTES', '900')))) . "\n";
}
echo "--- {$failures} of {$compared} differ\n";
echo "--- {$volatile} volatile; {$generationFailures} generation failures; {$count} attempted\n";
$report = getenv('MYSQL_MEMORY_REPORT');
if (is_string($report) && $report !== '') {
    $written = file_put_contents($report, json_encode(['grammar' => $grammar, 'mode' => $mode, 'emulate' => $target->emulate, 'foundRows' => $target->foundRows, 'seed' => $seed, 'attempted' => $count, 'compared' => $compared, 'volatile' => $volatile, 'generationFailures' => $generationFailures, 'differences' => $failures, 'findings' => $findings], JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n");
    if ($written === false) {
        exit(2);
    }
}
exit($generationFailures > 0 || $compared === 0 ? 2 : ($failures > 0 ? 1 : 0));

/**
 * Names the kind of a difference: the unsupported feature, the two errors, or the keys that differ.
 */
function signature(string $difference): string
{
    if (preg_match("/doesn't yet support '([^']+)'/", $difference, $match) === 1) {
        return 'unsupported ' . $match[1];
    }
    if (preg_match('/internal error: ([\\w\\\\]+): (.{0,60})/', $difference, $match) === 1) {
        return 'crash ' . $match[1] . ': ' . $match[2];
    }
    preg_match_all('/^(\w+)\n  expected: ([^\n]*)\n  actual:   ([^\n]*)/m', $difference, $matches, PREG_SET_ORDER);
    $parts = [];
    foreach ($matches as [, $key, $expected, $actual]) {
        if ($key === 'error') {
            preg_match('/\[(\d+)/', $expected, $left);
            preg_match('/\[(\d+)/', $actual, $right);
            $parts[] = 'error ' . ($left[1] ?? 'none') . '/' . ($right[1] ?? 'none');
        } elseif ($key !== 'warnings' || count($matches) === 1) {
            $parts[] = $key;
        }
    }

    return implode(' ', $parts);
}

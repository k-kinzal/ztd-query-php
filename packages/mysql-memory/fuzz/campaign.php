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
$count = (int) ($argv[2] ?? 100);
mt_srand((int) ($argv[3] ?? 1));
[$target, $grammar, $server] = (new Servers())->start(getenv('MYSQL_MEMORY_EMULATE') !== '0');
$provider = new MySqlProvider(Factory::create(), $grammar);
$planner = $provider->planner();
$plans = new Plans();
$constraints = $plans->plan($mode, $grammar);
$compiler = new BytePlanCompiler();
$kinds = [];
$compared = 0;
for ($i = 0; $i < $count; $i++) {
    $input = '';
    for ($b = 0, $length = mt_rand(8, 200); $b < $length; $b++) {
        $input .= chr(mt_rand(0, 255));
    }
    try {
        $sql = $plans->statement($mode, $provider->generate($compiler->compile($input, $planner, $constraints)));
    } catch (Throwable $failure) {
        continue;
    }
    $compared++;
    $difference = $target->difference($sql);
    if ($difference === null) {
        continue;
    }
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
    preg_match_all('/^(\w+)\n  expected: (.{0,40})\n  actual:   (.{0,40})/m', $difference, $matches, PREG_SET_ORDER);
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

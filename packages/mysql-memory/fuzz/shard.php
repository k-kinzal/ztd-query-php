<?php

/**
 * Partitions PHPUnit's complete class listing into three deterministic shards.
 * Each case has a fixed cost plus the statement separators in its SQL input.
 * This balances multi-statement scenarios without excluding tests or changing
 * time limits. Semicolons inside literals only increase the workload estimate.
 * Usage: php fuzz/shard.php build/integration-tests.xml 0
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;

require dirname(__DIR__) . '/vendor/autoload.php';

$argv = $argv ?? [];
if (count($argv) !== 3 || !in_array($argv[2], ['0', '1', '2'], true)) {
    fwrite(STDERR, "Usage: php fuzz/shard.php <test-list.xml> <0|1|2>\n");
    exit(2);
}
$listing = simplexml_load_file($argv[1]);
if ($listing === false) {
    throw new RuntimeException('The PHPUnit class listing could not be read.');
}
$classes = [];
foreach ($listing->testCaseClass as $node) {
    $name = (string) $node['name'];
    $methods = [];
    foreach ($node->testCaseMethod as $method) {
        $methods[(string) $method['name']] = true;
    }
    $count = count($node->testCaseMethod);
    $weight = 0;
    foreach (array_keys($methods) as $method) {
        $reflection = new ReflectionMethod($name, $method);
        $providers = [...$reflection->getAttributes(DataProvider::class), ...$reflection->getAttributes(DataProviderExternal::class)];
        foreach ($providers as $attribute) {
            $provider = $attribute->newInstance();
            $class = $provider instanceof DataProviderExternal ? $provider->className() : $name;
            $rows = (new ReflectionMethod($class, $provider->methodName()))->invoke(null);
            if (!is_iterable($rows)) {
                throw new RuntimeException('A data provider must return iterable rows.');
            }
            foreach ($rows as $data) {
                if (!is_array($data)) {
                    throw new RuntimeException('Each data-provider row must be an argument array.');
                }
                $weight += 2 + (is_string($data[0] ?? null) ? substr_count($data[0], ';') : 0);
            }
        }
    }
    $classes[] = [$name, $count, max($count * 2, $weight)];
}
usort($classes, static fn (array $a, array $b): int => $a[2] === $b[2] ? strcmp($a[0], $b[0]) : $b[2] <=> $a[2]);
$groups = [[], [], []];
$counts = $weights = [0, 0, 0];
foreach ($classes as [$name, $count, $weight]) {
    $shard = (int) array_search(min($weights), $weights, true);
    $groups[$shard][] = $name;
    $counts[$shard] += $count;
    $weights[$shard] += $weight;
}
$assigned = array_merge(...$groups);
if (count(array_unique($assigned)) !== count($classes) || in_array([], $groups, true)) {
    throw new RuntimeException('Every listed test class must belong to exactly one nonempty shard.');
}
$selected = $groups[(int) $argv[2]];
fwrite(STDERR, 'Cases per shard: ' . json_encode($counts) . "\nWork per shard: " . json_encode($weights) . "\n" . implode("\n", $selected) . "\n");
echo '^(?:' . implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), $selected)) . ')::', "\n";

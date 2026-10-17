<?php

/**
 * Partitions PHPUnit's complete class listing into three deterministic shards.
 * Recorded CI seconds per case balance classes with different setup costs.
 * New classes use their case count and SQL statement separators as an estimate.
 * The optional final argument reserves seconds for shard zero's other checks.
 * Every listed case remains assigned exactly once; time limits are unchanged.
 * Usage: php fuzz/shard.php build/integration-tests.xml 0 35
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;

require dirname(__DIR__) . '/vendor/autoload.php';

$argv = $argv ?? [];
if (!in_array(count($argv), [3, 4], true) || !in_array($argv[2], ['0', '1', '2'], true) || preg_match('/\A[0-9]+\z/', $argv[3] ?? '0') !== 1) {
    fwrite(STDERR, "Usage: php fuzz/shard.php <test-list.xml> <0|1|2> [reserved-seconds]\n");
    exit(2);
}
$text = file_get_contents(__DIR__ . '/shard-timings.json');
if ($text === false) {
    throw new RuntimeException('The recorded CI timings could not be read.');
}
$recorded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
if (!is_array($recorded) || !is_array($recorded['secondsPerCase'] ?? null)) {
    throw new RuntimeException('The recorded CI timings must contain secondsPerCase.');
}
$timings = [];
foreach ($recorded['secondsPerCase'] as $name => $seconds) {
    if (!is_string($name) || (!is_int($seconds) && !is_float($seconds)) || $seconds <= 0) {
        throw new RuntimeException('Each recorded class timing must be positive.');
    }
    $timings[$name] = (int) ceil($seconds * 1000);
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
    $estimated = $count * 360 + max(0, $weight - $count * 2) * 25;
    $classes[] = [$name, $count, isset($timings[$name]) ? $count * $timings[$name] : $estimated];
}
usort($classes, static fn (array $a, array $b): int => $a[2] === $b[2] ? strcmp($a[0], $b[0]) : $b[2] <=> $a[2]);
$groups = [[], [], []];
$counts = [0, 0, 0];
$weights = [(int) ($argv[3] ?? '0') * 1000, 0, 0];
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
fwrite(STDERR, 'Cases per shard: ' . json_encode($counts) . "\nEstimated milliseconds per shard: " . json_encode($weights) . "\n" . implode("\n", $selected) . "\n");
echo '^(?:' . implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), $selected)) . ')::', "\n";

<?php

/**
 * Replays sql-faker's complete canonical seed corpus against MySQL and mysql-memory.
 *
 * Usage: php fuzz/seeds.php [SEED_DIRECTORY] [REPORT_PREFIX]
 * MYSQL_VERSION selects the release. Reports contain every outcome and exact input/SQL bytes.
 * Exit 0 requires all inputs to match and every reachable statement production to be covered;
 * volatility, differences, incomplete coverage and infrastructure failures never pass.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Fuzz\Target\Lifecycle;
use Fuzz\Target\SeedCorpus;
use Fuzz\Target\Servers;

[$target, $grammar, $server] = (new Servers())->start(getenv('MYSQL_MEMORY_EMULATE') !== '0', true);
$directory = $argv[1] ?? dirname(__DIR__) . '/vendor/k-kinzal/sql-faker/seeds/mysql/' . $grammar;
$prefix = $argv[2] ?? dirname(__DIR__) . '/build/fuzz/seeds-' . $grammar;
if (!is_dir(dirname($prefix)) && !mkdir(dirname($prefix), 0777, true)) {
    throw new RuntimeException('Cannot create report directory ' . dirname($prefix));
}
$report = fopen($prefix . '.jsonl', 'w');
if ($report === false) {
    throw new RuntimeException('Cannot write report ' . $prefix);
}
$corpus = new SeedCorpus($grammar);
foreach ($corpus->inputs($directory) as $index => $seed) {
    $comparison = Lifecycle::handles($seed->sql) ? (new Lifecycle())->compare($seed->sql, $target->version) : $target->compare($seed->sql);
    $status = $corpus->record($seed, $comparison);
    $row = ['index' => $index, 'file' => $seed->file, 'inputHex' => bin2hex($seed->input), 'sqlHex' => bin2hex($seed->sql), 'status' => $status, 'difference' => $comparison->difference, 'reached' => $seed->reached, 'emitted' => $seed->emitted];
    if (fwrite($report, json_encode($row, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n") === false || !fflush($report)) {
        throw new RuntimeException('Cannot append to report ' . $prefix);
    }
    if (($index + 1) % 50 === 0) {
        echo ($index + 1) . ' inputs: ' . json_encode($corpus->counts, JSON_THROW_ON_ERROR) . "\n";
    }
}
fclose($report);
$summary = $corpus->summary();
if (file_put_contents($prefix . '.coverage.json', json_encode($corpus->coverage->snapshot(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n") === false
    || file_put_contents($prefix . '.summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n") === false) {
    throw new RuntimeException('Cannot write coverage reports for ' . $prefix);
}
echo json_encode(array_diff_key($summary, ['unreached' => true, 'unverified' => true]), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
exit($summary['total'] > 0 && $summary['unreached'] === [] && $summary['unverified'] === [] && $corpus->counts['different'] === 0 && $corpus->counts['volatile'] === 0 ? 0 : 1);

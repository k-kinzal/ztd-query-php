<?php

declare(strict_types=1);

// Run each scenario in a fresh process; an alternate installed version can supply its own autoloader.
// Example: php -d xdebug.mode=off -d opcache.enable_cli=0 -d memory_limit=1G bench/Practical.php tags 8192
require getenv('DERIVER_BENCH_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';

use Deriver\Analyzer;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Query\ValueQuery;

$scenario = $argv[1] ?? 'array';
$size = (int) ($argv[2] ?? 8192);
$seconds = (float) ($argv[3] ?? 0);
if (!in_array($scenario, ['array', 'negative-key', 'negative-string-key', 'tags', 'loop', 'shared', 'separate', 'batch'], true) || $size < 1 || $size > 16384) {
    throw new InvalidArgumentException('Usage: Practical.php array|negative-key|negative-string-key|tags|loop|shared|separate|batch [size:1..16384] [querySeconds:0]');
}
$source = match ($scenario) {
    'array', 'tags' => '<?php function target(){return [' . implode(',', array_map(static fn (int $i): string => $scenario === 'tags' ? '"tag' . $i . '"' : (string) $i, range(0, $size - 1))) . '];}',
    'negative-key', 'negative-string-key' => '<?php function target(){return [' . ($scenario === 'negative-key' ? '-1' : '"-1"') . '=>0,' . implode(',', array_slice(range(0, $size - 1), 1)) . '];}',
    'separate', 'batch' => '<?php function sink($x){} function target(){$n=0;' . str_repeat('$n+=1;', 100) . str_repeat('sink($n);', $size) . '}',
    'loop' => '<?php function target(PDO $pdo, array $input): void {$ids=[];foreach($input as $id){if(!check($id)){continue;}$ids[]=$id;}$pdo->query("SELECT 1");}',
    'shared' => '<?php function helper($n){' . str_repeat('$n+=1;', 100) . 'return $n;} function sink($x){} function target(){$n=helper(0);' . str_repeat('sink($n);', $size) . '}',
};
$start = hrtime(true);
$session = (new Analyzer())->open(new ProjectInput([new SourceFile('benchmark.php', $source)]), new Configuration(resources: new ResourceLimits(seconds: $seconds)));
$opened = hrtime(true);
$queries = in_array($scenario, ['array', 'negative-key', 'negative-string-key', 'tags'], true)
    ? [new ReturnQuery('target')]
    : array_map(static fn ($site): ValueQuery => new ValueQuery($site->argument(0)), $session->callsTo($scenario === 'loop' ? 'query' : 'sink'));
$selected = hrtime(true);
$runs = [];
$batchStart = hrtime(true);
$batch = $scenario === 'batch' ? $session->deriveTogether($queries)->results : null;
$batchElapsed = (hrtime(true) - $batchStart) / 1e9;
foreach ($queries as $index => $query) {
    $before = hrtime(true);
    $result = $batch[$index] ?? $session->derive($query);
    $elapsed = (hrtime(true) - $before) / 1e9;
    $definite = $result->definite();
    $expected = match ($scenario) {
        'array' => range(0, $size - 1),
        'tags' => array_map(static fn (int $i): string => 'tag' . $i, range(0, $size - 1)),
        'loop' => 'SELECT 1',
        'shared', 'separate', 'batch' => 100,
        'negative-key', 'negative-string-key' => array_combine(range(-1, $size - 2), range(0, $size - 1)),
    };
    if ($definite !== null && ($definite->values['return'] ?? $definite->values['value'])->native() !== $expected) {
        throw new RuntimeException('The benchmark produced an incorrect definite value.');
    }
    $runs[] = [
        'seconds' => $elapsed,
        'transfers' => $result->statistics->transfers,
        'cacheHits' => $result->statistics->cacheHits,
        'definite' => $definite !== null,
        'normalOutcomes' => count($result->normalOutcomes),
        'frontiers' => array_count_values(array_column($result->frontiers, 'code')),
        'valueHash' => $definite === null ? null : hash('sha256', serialize(array_map(static fn ($value) => $value->native(), $definite->values))),
    ];
}
echo json_encode([
    'runtime' => PHP_VERSION,
    'scenario' => $scenario,
    'size' => $size,
    'querySeconds' => $seconds,
    'sourceHash' => hash('sha256', $source),
    'openSeconds' => ($opened - $start) / 1e9,
    'selectionSeconds' => ($selected - $opened) / 1e9,
    'deriveSeconds' => $scenario === 'batch' ? $batchElapsed : array_sum(array_column($runs, 'seconds')),
    'peakBytes' => memory_get_peak_usage(true),
    'runs' => $runs,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;

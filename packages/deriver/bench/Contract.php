<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// An isolated source checkout permits exactly the same fixture to measure the baseline.
$baseline = getenv('DERIVER_BASELINE_SOURCE');
if (is_string($baseline) && $baseline !== '') {
    spl_autoload_register(static function (string $class) use ($baseline): void {
        if (str_starts_with($class, 'Deriver\\')) {
            require $baseline . '/' . str_replace('\\', '/', substr($class, 8)) . '.php';
        }
    }, true, true);
}

$count = (int) ($argv[1] ?? 0);
$shape = $argv[2] ?? 'local';
$branches = in_array($shape, ['array', 'product'], true) ? '' : str_repeat('if($unrelated){$unused=1;}else{$unused=2;}', $count);
$array = '[' . implode(',', array_fill(0, $count, '42')) . ']';
$product = '';
$productEntries = ['"head"'];
for ($index = 0; $index < $count; ++$index) {
    $product .= '$v' . $index . '=$unrelated[' . $index . ']?0:1;';
    $productEntries[] = '$v' . $index;
}
$product .= 'observe([' . implode(',', $productEntries) . ',"tail"]);';
$body = match ($shape) {
    'array' => 'observe(' . $array . ');',
    'product' => $product,
    'receiver' => '$pdo=new PDO;' . $branches . '$pdo->query("SELECT 1");',
    'receiver-choice' => '$pdo=$unrelated?new A:new B;$pdo->query("SELECT 1");',
    'tuple' => '$a=$unrelated?1:2;observe([$a,$a]);',
    'loop' => '$sql="SELECT 1";while($unrelated){$unrelated--;}observe($sql);',
    'shared' => '$x=helper();observe($x);observe($x);',
    'literal' => $branches . 'observe("SELECT 1");',
    'return' => 'observe(helper());',
    default => '$sql="SELECT 1";' . $branches . 'observe($sql);',
};
$source = '<?php function observe($value){} function helper(){' . $branches . 'return "SELECT 1";} function target($unrelated){' . $body . '}';
if ($shape === 'receiver-choice') {
    $source .= 'class A{function query($sql){}}class B extends A{}';
} elseif ($shape === 'callers') {
    $source = '<?php function observe($value){} function helper($ttl){observe($ttl+5);}function wrap($ttl){return helper($ttl);}function a(){return wrap(30);}function b(){return wrap(60);}';
}
$cpuStart = getrusage();
$start = microtime(true);
$session = (new Deriver\Analyzer())->open(new Deriver\Project\ProjectInput([new Deriver\Project\SourceFile('fixture.php', $source)]));
$capture = microtime(true);
$receiver = in_array($shape, ['receiver', 'receiver-choice'], true);
$sites = $session->callsTo($receiver ? 'query' : 'observe');
$site = $sites[0];
$discovery = microtime(true);
$budget = new Deriver\Query\Budget(nodes:200000, transfers:1000000);
$query = $receiver ? new Deriver\Query\TupleQuery($site->beforeInvocation(), ['receiver' => $site->receiver,'sql' => $site->argument(0)], budget:$budget) : new Deriver\Query\ValueQuery($site->argument(0), budget:$budget);
$result = $session->derive($query);
$derive = microtime(true);
$json = $result->toJson();
$export = microtime(true);
$values = $result instanceof Deriver\Result\DerivationResult
    ? array_map(static fn ($item) => $item->values, $result->normalOutcomes)
    : array_map(static fn ($item) => [$item->term], $result->candidates);
$quality = [];
foreach ($values as $row) {
    foreach ($row as $value) {
        $quality[] = ['kind' => $value->kind,'concrete' => $value->isConcrete(),'value_hash' => (new Deriver\Value\Identity())->key($value),'entries' => count($value->operands)];
    }
}
$warmStart = microtime(true);
$warm = $session->derive($query);
$warmEnd = microtime(true);
$queries = array_map(static fn ($site) => new Deriver\Query\ValueQuery($site->argument(0), budget:$budget), $sites);
$batchStart = microtime(true);
$session->deriveTogether($queries);
$batchEnd = microtime(true);
$cpuEnd = getrusage();
$cpuSeconds = ($cpuEnd['ru_utime.tv_sec'] + $cpuEnd['ru_stime.tv_sec'] - $cpuStart['ru_utime.tv_sec'] - $cpuStart['ru_stime.tv_sec']) + ($cpuEnd['ru_utime.tv_usec'] + $cpuEnd['ru_stime.tv_usec'] - $cpuStart['ru_utime.tv_usec'] - $cpuStart['ru_stime.tv_usec']) / 1000000;
echo json_encode([
    'php' => PHP_VERSION, 'xdebug' => getenv('XDEBUG_MODE'), 'opcache_cli' => ini_get('opcache.enable_cli'),
    'baseline' => is_string($baseline) && $baseline !== '' ? 'fed0725f6220c06d8eebc5d349d61c5c2a588178' : null,
    'lock_hash' => hash_file('sha256', dirname(__DIR__) . '/composer.lock'),
    'fixture_hash' => hash('sha256', $source), 'target' => $session->snapshot()->target->id(),
    'budget' => $budget, 'branches' => $count, 'shape' => $shape,
    'capture_seconds' => $capture - $start, 'discovery_seconds' => $discovery - $capture,
    'derive_seconds' => $derive - $discovery, 'export_seconds' => $export - $derive,
    'peak_memory_bytes' => memory_get_peak_usage(true), 'statistics' => $result->statistics,
    'quality' => $quality, 'cpu_seconds' => $cpuSeconds, 'graph_count' => $session->program->graphCount(), 'warm_seconds' => $warmEnd - $warmStart, 'batch_seconds' => $batchEnd - $batchStart, 'batch_size' => count($queries), 'warm_equal' => $warm->toJson() === $json, 'evidence_hash' => hash('sha256', $json), 'export_bytes' => strlen($json),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), PHP_EOL;

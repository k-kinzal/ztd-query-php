<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Deriver\Analyzer;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\Budget;
use Deriver\Query\ReturnQuery;
use Deriver\Result\Candidates\CandidateCollection;

if (($argv[1] ?? '') !== '--worker') {
    $measurements = [];
    $sizes = array_slice($argv, 1);
    $sizes = $sizes === [] ? ['1000', '10000', '100000'] : $sizes;
    foreach ($sizes as $size) {
        if (!ctype_digit($size) || (int) $size < 40 || (int) $size > 100000) {
            throw new InvalidArgumentException('Benchmark sizes must be integers between 40 and 100000.');
        }
        $process = proc_open([PHP_BINARY, '-d', 'memory_limit=2G', '-d', 'xdebug.mode=off', __FILE__, '--worker', $size], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start isolated benchmark process.');
        }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || $output === false) {
            throw new RuntimeException('Benchmark failed: ' . $error);
        }
        $measurements[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    echo json_encode(['schemaVersion' => 1, 'runs' => $measurements], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    exit(0);
}

/** @return array<string, mixed> Comparable result quality and logical resource measures. */
function quality(CandidateCollection $result): array
{
    return [
        'candidates' => count($result),
        'analyzed' => count(array_filter($result->candidates, static fn ($candidate): bool => $candidate->type === 'analyzed')),
        'partials' => count(array_filter($result->candidates, static fn ($candidate): bool => $candidate->type === 'partials')),
        'interrupted' => $result->interrupted,
        'statistics' => $result->statistics,
    ];
}

$size = (int) ($argv[2] ?? '1000');
$source = '<?php function fixture0(){return fixture1();}';
for ($i = 1; $i < $size; $i++) {
    $source .= "\nfunction fixture" . $i . '(){return ' . $i . ';}';
}
$input = new ProjectInput([new SourceFile('generated.php', $source)]);
$analyzer = new Analyzer();
$largeProject = new Configuration(sourceLimits: new Deriver\Project\SourceLimits(bytes: 134217728, fileBytes: 16777216, nodes: 1500000));
$start = hrtime(true);
$session = $analyzer->open($input, $largeProject);
$coldOpen = (hrtime(true) - $start) / 1e9;
$query = new ReturnQuery('fixture0');
$start = hrtime(true);
$cold = $session->derive($query);
$coldQuery = (hrtime(true) - $start) / 1e9;
if ($cold->candidates[0]->result !== 1) {
    throw new RuntimeException('The benchmark produced an incorrect definite value.');
}
$queries = [];
$times = [];
for ($i = 2; $i < 34; $i++) {
    $queries[] = new ReturnQuery('fixture' . $i);
    $start = hrtime(true);
    $result = $session->derive($queries[array_key_last($queries)]);
    $times[] = (hrtime(true) - $start) / 1e9;
    if ($result->candidates[0]->result !== $i) {
        throw new RuntimeException('A benchmark query produced an incorrect definite value.');
    }
}
sort($times);
$start = hrtime(true);
$warm = $session->derive($query);
$warmQuery = (hrtime(true) - $start) / 1e9;
if ($warm !== $cold) {
    throw new RuntimeException('An unchanged query did not reuse its result.');
}
unset($session);
$start = hrtime(true);
$session = $analyzer->open($input, $largeProject);
$warmOpen = (hrtime(true) - $start) / 1e9;
$start = hrtime(true);
$session->deriveMany($queries);
$warmBatch = (hrtime(true) - $start) / 1e9;
unset($session);
$start = hrtime(true);
$coldBatchSession = (new Analyzer())->open($input, $largeProject);
$coldBatchOpen = (hrtime(true) - $start) / 1e9;
$start = hrtime(true);
$coldBatchSession->deriveMany($queries);
$coldBatch = (hrtime(true) - $start) / 1e9;
unset($coldBatchSession);

$deep = '<?php function target(){return helper0(1);}';
for ($i = 0; $i < 100; $i++) {
    $deep .= 'function helper' . $i . '($x){return ' . ($i === 99 ? '$x+1' : 'helper' . ($i + 1) . '($x)') . ';}';
}
$scenarios = [
    'deep-helpers' => $deep,
    'repeated-helper' => '<?php function increment($x){return $x+1;}function target(){$sum=0;for($i=0;$i<200;$i++){$sum+=increment(1);}return $sum;}',
    'wide-branches' => '<?php function target(bool $a,bool $b,bool $c,bool $d,bool $e,bool $f){$x=0;if($a)$x+=1;if($b)$x+=2;if($c)$x+=4;if($d)$x+=8;if($e)$x+=16;if($f)$x+=32;return $x;}',
    'mutable-builder' => '<?php class Builder{public array $items=[];function add($v){$this->items[]=$v;return $this;}}function target(){$b=new Builder();$c=clone $b;$b->add("one")->add("two");$c->add("three");return [$b->items,$c->items];}',
    'dynamic-dispatch' => '<?php interface Service{function value():int;}class First implements Service{function value():int{return 1;}}class Second implements Service{function value():int{return 2;}}function target(Service $s){return $s->value();}',
];
$scenarioResults = [];
foreach ($scenarios as $name => $program) {
    $start = hrtime(true);
    $result = (new Analyzer())->open(new ProjectInput([new SourceFile($name . '.php', $program)]), new Configuration(closedWorld: true))->derive(new ReturnQuery('target', budget: new Budget(iterations: 256)));
    $expected = ['deep-helpers' => 2, 'repeated-helper' => 400, 'mutable-builder' => [['one', 'two'], ['three']]];
    if (isset($expected[$name]) && $result->candidates[0]->type === 'analyzed' && $result->candidates[0]->result !== $expected[$name]) {
        throw new RuntimeException('A benchmark scenario produced an incorrect definite value: ' . $name);
    }
    $scenarioResults[$name] = ['seconds' => (hrtime(true) - $start) / 1e9, ...quality($result)];
}
echo json_encode([
    'recordedAtUtc' => gmdate('c'),
    'runtime' => PHP_VERSION,
    'target' => 'PHP 8.3, 64-bit',
    'platform' => php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m'),
    'standardModels' => true,
    'customModels' => [],
    'callables' => $size,
    'sourceBytes' => strlen($source),
    'coldOpenSeconds' => $coldOpen,
    'warmOpenSeconds' => $warmOpen,
    'coldQuerySeconds' => $coldQuery,
    'warmResultSeconds' => $warmQuery,
    'queryP50Seconds' => $times[15],
    'queryP95Seconds' => $times[30],
    'batchSize' => count($queries),
    'coldBatchOpenSeconds' => $coldBatchOpen,
    'coldBatchSeconds' => $coldBatch,
    'warmBatchSeconds' => $warmBatch,
    'peakMemoryBytes' => memory_get_peak_usage(true),
    'selectedQuery' => quality($cold),
    'scenarios' => $scenarioResults,
], JSON_THROW_ON_ERROR), PHP_EOL;

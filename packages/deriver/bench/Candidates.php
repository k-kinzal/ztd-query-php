<?php

declare(strict_types=1);

$autoload = getenv('DERIVER_BENCH_AUTOLOAD');
require is_string($autoload) && $autoload !== '' ? $autoload : dirname(__DIR__) . '/vendor/autoload.php';

use Deriver\Analyzer;
use Deriver\Model\CallDescription;
use Deriver\Model\CallModel;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;
use Deriver\Query\ValueQuery;
use Deriver\Value\Term;

$scenario = $argv[1] ?? 'general-key';
$size = (int) ($argv[2] ?? 8192);
if ($size < 1 || $size > 16384 || !in_array($scenario, ['array', 'negative-key', 'negative-string-key', 'general-key', 'heavy', 'model', 'shared'], true)) {
    throw new InvalidArgumentException('Candidates.php array|negative-key|negative-string-key|general-key|heavy|model|shared [size:1..16384]');
}
$values = $size === 1 ? '' : implode(',', range(1, $size - 1));
$source = match ($scenario) {
    'array' => 'function target(){return [0,' . $values . '];}',
    'negative-key', 'negative-string-key', 'general-key' => 'function target(){return [' . match ($scenario) {
        'negative-key' => '-1', 'negative-string-key' => '"-1"', 'general-key' => '-(1+0)'
    } . '=>0,' . $values . '];}',
    'heavy' => 'function heavy(){for($i=0;$i<100000;$i++){}return 1;}function target(){$unused=heavy();observe("SELECT 1");}',
    'model' => 'function heavy($input){return missing($input);}function target($input){observe(heavy($input)+5);}',
    'shared' => 'function helper(){return 3+4;}function target($input){$x=helper();observe($x+$input);' . str_repeat('observe($x+1);', $size) . '}',
};
$model = new class () implements CallModel {
    public function descriptor(): ModelDescriptor
    {
        return new ModelDescriptor('bench.heavy', '1', 'heavy', new Signature([new Parameter('input')]));
    }
    public function describe(CallDescription $call): ModelDecision
    {
        return ModelDecision::handled(new SemanticPlan([Action::returns(Expression::literal(Term::constant(10)))]));
    }
};
$start = hrtime(true);
$session = (new Analyzer())->open(new ProjectInput([new SourceFile('benchmark.php', '<?php ' . $source)]), new Configuration(models: $scenario === 'model' ? [$model] : []));
$opened = hrtime(true);
$queries = in_array($scenario, ['heavy', 'model', 'shared'], true) ? array_map(static fn ($site) => new ValueQuery($site->argument(0)), $session->callsTo('observe')) : [new ReturnQuery('target')];
$selected = hrtime(true);
$results = [];
$deriveNanoseconds = 0;
$outputNanoseconds = 0;
$counts = ['bodyExpansions' => 0,'referenceExpansions' => 0,'sharedNodeHits' => 0,'constructedNodes' => 0];
foreach ($queries as $query) {
    $queryStart = hrtime(true);
    $result = $session->derive($query);
    $queryEnd = hrtime(true);
    $deriveNanoseconds += $queryEnd - $queryStart;
    foreach ($counts as $name => $_) {
        $counts[$name] += $result->statistics->$name ?? 0;
    }
    $concrete = [];
    foreach ($result->normalOutcomes as $outcome) {
        foreach ($outcome->values as $value) {
            if ($value->isConcrete()) {
                $concrete[] = $value->native();
            }
        }
    }
    $results[] = ['concreteHash' => hash('sha256', serialize($concrete)), 'concreteCount' => count($concrete), 'frontiers' => array_count_values(array_column($result->frontiers, 'code'))];
    $outputNanoseconds += hrtime(true) - $queryEnd;
}
$derived = hrtime(true);
$session->derive($queries[count($queries) - 1]);
$warm = hrtime(true);
$beforeRelease = memory_get_usage();
$weak = WeakReference::create($result);
unset($result);
if (method_exists($session, 'release')) {
    $session->release();
}
gc_collect_cycles();
echo json_encode(['runtime' => PHP_VERSION,'scenario' => $scenario,'size' => $size,'sourceHash' => hash('sha256', $source),'openSeconds' => ($opened - $start) / 1e9,'selectionSeconds' => ($selected - $opened) / 1e9,'deriveSeconds' => $deriveNanoseconds / 1e9,'enumerateAndHashSeconds' => $outputNanoseconds / 1e9,'warmSeconds' => ($warm - $derived) / 1e9,'peakBytes' => memory_get_peak_usage(true),'beforeReleaseBytes' => $beforeRelease,'afterReleaseBytes' => memory_get_usage(),'releasedResult' => $weak->get() === null,'statistics' => $counts,'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),PHP_EOL;

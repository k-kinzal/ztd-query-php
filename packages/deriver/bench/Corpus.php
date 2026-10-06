<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Deriver\Analyzer;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ValueQuery;

if (count($argv) < 2) {
    throw new InvalidArgumentException('Corpus.php captured-fixtures.json [more-fixtures.json ...]');
}
$summary = ['sourceSets' => 0, 'observations' => 0, 'concrete' => 0, 'symbolic' => 0, 'exceptionOnly' => 0, 'empty' => 0, 'errors' => [], 'frontiers' => []];
foreach (array_slice($argv, 1) as $path) {
    foreach (json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) as $case => $fixture) {
        $summary['sourceSets']++;
        try {
            $files = [];
            foreach ($fixture['sources'] as $name => $source) {
                $files[] = new SourceFile($name, $source);
            }
            $session = (new Analyzer())->open(new ProjectInput($files), new Configuration(resources: new ResourceLimits(seconds: 0.2)));
            foreach (['query', 'prepare', 'exec', 'mysqli_query', 'mysqli_prepare'] as $name) {
                foreach ($session->callsTo($name) as $site) {
                    if ($site->arguments === []) {
                        continue;
                    }
                    $result = $session->derive(new ValueQuery($site->argument(str_starts_with($name, 'mysqli_') ? 1 : 0)));
                    $summary['observations']++;
                    $concrete = false;
                    foreach ($result->normalOutcomes as $outcome) {
                        $concrete = $concrete || $outcome->values['value']->isConcrete();
                    }
                    $category = $result->normalOutcomes === [] ? ($result->exceptionalOutcomes === [] ? 'empty' : 'exceptionOnly') : ($concrete ? 'concrete' : 'symbolic');
                    $summary[$category]++;
                    foreach ($result->frontiers as $frontier) {
                        $summary['frontiers'][$frontier->code] = ($summary['frontiers'][$frontier->code] ?? 0) + 1;
                    }
                }
            }
            $session->release();
            unset($session, $result);
            gc_collect_cycles();
        } catch (Throwable $error) {
            $summary['errors'][] = ['group' => basename($path), 'case' => $case, 'type' => $error::class, 'message' => $error->getMessage()];
        }
    }
}
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;

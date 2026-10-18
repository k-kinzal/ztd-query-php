<?php

declare(strict_types=1);

namespace Bench;

use Deriver\Deriver;
use Deriver\Deriver\BranchEvaluators;
use Deriver\Deriver\Candidate\CandidateCollection;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\MicroEvaluator;
use Deriver\Finder;
use PhpBench\Attributes as Bench;
use RuntimeException;

#[Bench\Iterations(5)]
#[Bench\Revs(1)]
#[Bench\Warmup(0)]
#[Bench\OutputTimeUnit('milliseconds')]
final class SymfonyBench
{
    private const QUERIES = [
        'App\Service\PublishingRules::initialState#return',
        'App\Service\PublishingRules::notificationMessage#return',
        'App\Service\PublishingRules::reportDeadlineHours#return',
        'App\Service\Publishing::create#return',
    ];

    /** @var list<string> */
    private array $files;
    private DeriverConfig $configuration;
    private Deriver $prepared;
    /** @var list<CandidateCollection> */
    private array $results = [];

    public function locate(): void
    {
        $this->locateWithEvaluators([]);
    }

    public function locateWithBranchPruning(): void
    {
        $this->locateWithEvaluators(BranchEvaluators::truth());
    }

    /** @param list<MicroEvaluator> $evaluators */
    public function locateWithEvaluators(array $evaluators): void
    {
        $root = __DIR__ . '/target/symfony';
        $this->files = (new Finder())->in($root)
            ->include('**/*.php', 'bin/console')
            ->exclude('vendor', 'tests', 'var', 'templates', 'config/reference.php')
            ->files();
        // Include the framework traits used by these application sources.
        $this->files = [
            ...$this->files,
            $root . '/vendor/symfony/dependency-injection/Kernel/KernelTrait.php',
            $root . '/vendor/symfony/framework-bundle/Kernel/MicroKernelTrait.php',
        ];
        sort($this->files, SORT_STRING);
        $this->configuration = new DeriverConfig(phpVersion: '8.4', evaluators: $evaluators);
    }

    public function prepare(): void
    {
        $this->locate();
        $this->prepared = Deriver::prepare($this->files, $this->configuration);
    }

    public function prepareWithBranchPruning(): void
    {
        $this->locateWithBranchPruning();
        $this->prepared = Deriver::prepare($this->files, $this->configuration);
    }

    #[Bench\BeforeMethods('locate')]
    #[Bench\Groups(['preparation'])]
    public function benchPrepare(): void
    {
        $this->prepared = Deriver::prepare($this->files, $this->configuration);
    }

    #[Bench\BeforeMethods('prepare')]
    #[Bench\Groups(['first-query'])]
    #[Bench\AfterMethods('verifyResults')]
    public function benchFirstQuery(): void
    {
        $this->results = [$this->prepared->derive(self::QUERIES[0])];
    }

    #[Bench\BeforeMethods('prepare')]
    #[Bench\Groups(['query-batch'])]
    #[Bench\AfterMethods('verifyResults')]
    public function benchQueryBatch(): void
    {
        $this->results = [];
        foreach (self::QUERIES as $query) {
            $this->results[] = $this->prepared->derive($query);
        }
    }

    #[Bench\BeforeMethods('locateWithBranchPruning')]
    #[Bench\Groups(['preparation', 'branch-pruning'])]
    public function benchPrepareWithBranchPruning(): void
    {
        $this->benchPrepare();
    }

    #[Bench\BeforeMethods('prepareWithBranchPruning')]
    #[Bench\Groups(['first-query', 'branch-pruning'])]
    #[Bench\AfterMethods('verifyResults')]
    public function benchFirstQueryWithBranchPruning(): void
    {
        $this->benchFirstQuery();
    }

    #[Bench\BeforeMethods('prepareWithBranchPruning')]
    #[Bench\Groups(['query-batch', 'branch-pruning'])]
    #[Bench\AfterMethods('verifyResults')]
    public function benchQueryBatchWithBranchPruning(): void
    {
        $this->benchQueryBatch();
    }

    public function verifyResults(): void
    {
        foreach ($this->results as $collection) {
            if (count($collection) === 0) {
                throw new RuntimeException('A benchmark query returned no candidates.');
            }
        }
    }
}

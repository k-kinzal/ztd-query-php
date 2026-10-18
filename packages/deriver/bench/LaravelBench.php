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
final class LaravelBench
{
    private const QUERIES = [
        'App\Services\PublishingRules::initialState#return',
        'App\Services\PublishingRules::nextState#return',
        'App\Services\PublishingRules::escalationHours#return',
        'App\Services\Publishing::create#return',
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
        $root = __DIR__ . '/target/laravel';
        $this->files = (new Finder())->in($root)
            ->include('**/*.php', 'artisan')
            ->exclude('vendor', 'tests', 'storage', 'bootstrap/cache', 'resources/views', 'database/factories', 'database/seeders')
            ->files();
        // Include the framework traits used by these application sources.
        $this->files = [
            ...$this->files,
            $root . '/vendor/laravel/framework/src/Illuminate/Bus/Queueable.php',
            $root . '/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Factories/HasFactory.php',
            $root . '/vendor/laravel/framework/src/Illuminate/Database/Eloquent/SoftDeletes.php',
            $root . '/vendor/laravel/framework/src/Illuminate/Foundation/Bus/Dispatchable.php',
            $root . '/vendor/laravel/framework/src/Illuminate/Foundation/Events/Dispatchable.php',
            $root . '/vendor/laravel/framework/src/Illuminate/Foundation/Queue/Queueable.php',
            $root . '/vendor/laravel/framework/src/Illuminate/Notifications/HasDatabaseNotifications.php',
            $root . '/vendor/laravel/framework/src/Illuminate/Notifications/Notifiable.php',
            $root . '/vendor/laravel/framework/src/Illuminate/Notifications/RoutesNotifications.php',
            $root . '/vendor/laravel/framework/src/Illuminate/Queue/InteractsWithQueue.php',
            $root . '/vendor/laravel/framework/src/Illuminate/Queue/SerializesAndRestoresModelIdentifiers.php',
            $root . '/vendor/laravel/framework/src/Illuminate/Queue/SerializesModels.php',
            $root . '/vendor/laravel/framework/src/Illuminate/Support/InteractsWithTime.php',
            $root . '/vendor/laravel/sanctum/src/HasApiTokens.php',
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

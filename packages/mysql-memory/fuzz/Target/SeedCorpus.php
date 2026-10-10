<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Faker\Factory;
use Generator;
use RuntimeException;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySql\MySqlProvider;

/**
 * Replays the canonical sql-faker corpus with exactly the constraints used by its seeds checker.
 *
 * Every production reachable from statement (5.6 and 5.7) or simple_statement_or_begin is in the
 * denominator. No statement is excluded or rewritten by this target. Reached, emitted and
 * successfully compared coverage are measured separately: a volatile oracle is not a match.
 */
final class SeedCorpus
{
    /**
     * Records the generator's derivations and emitted productions.
     */
    public readonly GrammarCoverage $coverage;

    /**
     * The generator for the selected MySQL grammar.
     */
    public readonly MySqlProvider $provider;

    /**
     * The grammar rule the canonical corpus starts from.
     */
    public readonly string $root;

    /**
     * @var array<string, true> Reached alternatives of statements whose comparison matched
     */
    public array $matched = [];

    /**
     * @var array<string, int> Counts by comparison outcome
     */
    public array $counts = ['matched' => 0, 'different' => 0, 'volatile' => 0];

    /**
     * Creates the same generator as sql-faker's bin/seeds.php check command.
     */
    public function __construct(public readonly string $grammar)
    {
        $this->coverage = new GrammarCoverage();
        $this->provider = new MySqlProvider(Factory::create(), $grammar, $this->coverage);
        $this->root = str_starts_with($grammar, 'mysql-5.') ? 'statement' : 'simple_statement_or_begin';
    }

    /**
     * Generates every seed in filename order, without changing its bytes or generation plan.
     *
     * @return Generator<int, Seed>
     * @throws RuntimeException When the corpus is missing or an input cannot be read
     */
    public function inputs(string $directory): Generator
    {
        $files = glob($directory . '/*.txt');
        if ($files === false || $files === []) {
            throw new RuntimeException('No sql-faker seeds found in ' . $directory);
        }
        sort($files);
        $planner = $this->provider->planner();
        $constraints = GenerationPlan::fromRule($this->root)->requiringNonEmpty();
        $compiler = new BytePlanCompiler();
        foreach ($files as $file) {
            $input = file_get_contents($file);
            if ($input === false) {
                throw new RuntimeException('Cannot read seed ' . $file);
            }
            $sql = $this->provider->generate($compiler->compile($input, $planner, $constraints));
            $trace = $this->coverage->lastGeneration();
            assert($trace !== null);

            yield new Seed(basename($file), $input, $sql, $trace['reachedIds'], $trace['emittedIds']);
        }
    }

    /**
     * Records a comparison and answers its status for the per-seed report.
     */
    public function record(Seed $seed, Comparison $comparison): string
    {
        $status = $comparison->volatile ? 'volatile' : ($comparison->difference === null ? 'matched' : 'different');
        $this->counts[$status]++;
        if ($status === 'matched') {
            $this->matched += array_fill_keys($seed->reached, true);
        }

        return $status;
    }

    /**
     * Lists exactly the statement-root denominator used by sql-faker's canonical corpus.
     *
     * @return list<string>
     */
    public function denominator(): array
    {
        $inventory = $this->coverage->inventory();
        $rules = $inventory->reachableRules($this->root);

        return array_keys(array_filter($inventory->entries, static fn (array $entry): bool => isset($rules[$entry['rule']])));
    }

    /**
     * Reports coverage counts and the alternatives that remain unverified, without rounding a partial rate to 100%.
     *
     * @return array{grammar: string, root: string, counts: array<string, int>, total: int, reached: int, emitted: int, matched: int, unreached: list<string>, unverified: list<string>}
     */
    public function summary(): array
    {
        $target = $this->denominator();
        $current = $this->coverage->snapshot()['current'];

        return [
            'grammar' => $this->grammar, 'root' => $this->root, 'counts' => $this->counts,
            'total' => count($target),
            'reached' => count(array_intersect($target, $current['reachedIds'])),
            'emitted' => count(array_intersect($target, $current['emittedIds'])),
            'matched' => count(array_intersect($target, array_keys($this->matched))),
            'unreached' => array_values(array_diff($target, $current['reachedIds'])),
            'unverified' => array_values(array_diff($target, array_keys($this->matched))),
        ];
    }
}

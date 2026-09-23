<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Seed;

use Closure;
use InvalidArgumentException;
use LogicException;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Choice\BytePlanEncoder;
use SqlFaker\Generation\Choice\PlanBuilder;
use SqlFaker\Generation\Coverage\CoverageException;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Exception\GenerationException;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Grammar\Model\Production;

/**
 * Synthesizes byte inputs that together select every production reachable from a root, one target at a time.
 *
 * Every seed decodes under the same contract: the plan starts at the root, requires output, and carries no other
 * constraint, so any fuzz target compiling its input that way replays the corpus unchanged.
 */
final class SeedCorpusBuilder
{
    /**
     * Largest budget the compiler decodes for a plan without an explicit expansion budget.
     */
    public const MAXIMUM_BUDGET = 5000;

    /**
     * @param GrammarCoverage $coverage Recorder attached to the generator behind $generate
     * @param PlanBuilder $planner Planner of that same generator
     * @param Closure(GenerationPlan<bool>): string $generate Generates SQL from a compiled plan
     */
    public function __construct(
        private readonly GrammarCoverage $coverage,
        private readonly PlanBuilder $planner,
        private readonly Closure $generate,
    ) {
    }

    /**
     * Visits productions in grammar order and keeps a seed only when it reaches a production no earlier seed did.
     *
     * @param int $cap Expansions a guided walk may spend before giving up on its target
     * @throws CoverageException When the coverage recorder is not attached to a generator
     * @throws InvalidArgumentException When the cap exceeds what the compiler can decode
     */
    public function build(string $root, int $cap = self::MAXIMUM_BUDGET): SeedCorpus
    {
        if ($cap < 1 || $cap > self::MAXIMUM_BUDGET) {
            throw new InvalidArgumentException('A seed budget cap must lie between 1 and ' . self::MAXIMUM_BUDGET . '.');
        }
        $inventory = $this->coverage->inventory();
        $graph = new ProductionGraph($inventory->grammar);
        $constraints = GenerationPlan::fromRule($root)->requiringNonEmpty();
        $targets = $this->targets($root);
        $covered = [];
        $seeds = [];
        $failures = [];
        $reachable = $inventory->reachableRules($root);
        foreach ($inventory->grammar->ruleMap as $rule => $definition) {
            foreach (isset($reachable[$rule]) ? $definition->alternatives : [] as $index => $production) {
                $id = $inventory->id($rule, $production, $index);
                if (isset($covered[$id])) {
                    continue;
                }
                $ordinal = $production->ordinal ?? $index;
                try {
                    $seed = $this->seed($constraints, $graph, $rule, $ordinal, $production, $cap);
                } catch (GenerationException | LexicalException $failure) {
                    $failures[$targets[$id]] = $failure->getMessage();
                    continue;
                }
                if ($seed === null || !in_array($id, $seed->reached, true)) {
                    $failures[$targets[$id]] = $seed === null ? "No walk of at most $cap expansions reached the production." : 'The replayed input selected other productions.';
                    continue;
                }
                $seeds[] = $seed;
                $covered += array_fill_keys($seed->reached, true);
            }
        }
        return new SeedCorpus($root, $seeds, $targets, $failures);
    }

    /**
     * Guides one derivation to the production, encodes its decisions, and replays the bytes through the generator.
     *
     * @param GenerationPlan<bool> $constraints
     * @return CoverageSeed|null The replayed seed, or null when the guided walk never reached the production
     * @throws GenerationException When the guided walk or its replay cannot complete
     * @throws LexicalException When a terminal has no realization
     * @throws LogicException When the replay does not offer a production the guided walk chose
     */
    public function seed(GenerationPlan $constraints, ProductionGraph $graph, string $rule, int $ordinal, Production $production, int $cap): ?CoverageSeed
    {
        $guide = new ProductionGuide($graph, $this->planner->root($constraints), $rule, $production);
        $this->planner->build($constraints, $cap, $guide->choose(...), static fn (): ?int => null);
        if (!$guide->reached()) {
            return null;
        }
        $productions = $guide->productions();
        $step = 0;
        $replay = static function (int $count, array $candidates) use ($productions, &$step): int {
            $index = array_search($productions[$step++] ?? null, $candidates, true);
            return is_int($index) ? $index : throw new LogicException('The replay must offer every production the guided walk chose.');
        };
        $input = (new BytePlanEncoder())->encode($this->planner, $constraints, count($productions), $replay);
        return $this->replay($constraints, $input, $rule, $ordinal);
    }

    /**
     * Decodes one input under the corpus contract, generates from it, and reads what the generation exercised.
     *
     * @param GenerationPlan<bool> $constraints
     */
    public function replay(GenerationPlan $constraints, string $input, ?string $rule = null, ?int $ordinal = null): CoverageSeed
    {
        $plan = (new BytePlanCompiler())->compile($input, $this->planner, $constraints);
        $this->coverage->reset();
        $sql = ($this->generate)($plan);
        $trace = $this->coverage->lastGeneration();
        return new CoverageSeed($input, $rule, $ordinal, $plan->expansionBudget() ?? 0, $sql, $trace['reachedIds'] ?? [], $trace['emittedIds'] ?? []);
    }

    /**
     * Labels every production reachable from the root by its coverage ID, in grammar order.
     *
     * @return array<string, string>
     * @throws CoverageException When the coverage recorder is not attached to a generator
     */
    public function targets(string $root): array
    {
        $inventory = $this->coverage->inventory();
        $targets = [];
        $reachable = $inventory->reachableRules($root);
        foreach ($inventory->grammar->ruleMap as $rule => $definition) {
            foreach (isset($reachable[$rule]) ? $definition->alternatives : [] as $index => $production) {
                $targets[$inventory->id($rule, $production, $index)] = $rule . '#' . ($production->ordinal ?? $index);
            }
        }
        return $targets;
    }
}

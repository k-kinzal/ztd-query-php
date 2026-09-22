<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use Faker\Factory;
use Faker\Generator;
use SqlCatalog\Analyzer;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Choice\PlanBuilder;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySqlProvider;

/**
 * Checks that a statement written into PHP comes back out of the catalog.
 *
 * The input picks both the statement, through sql-faker's grammar, and the way
 * it is written into the source: as one literal, as a concatenation, through a
 * constant, through a variable, through a call. Every one of those has to
 * produce the same catalogued statement, character for character, because the
 * whole point of the analysis is that how a query is assembled does not change
 * what it is.
 */
final class RecoverTarget
{
    private readonly Generator $faker;

    private readonly MySqlProvider $sql;

    private readonly PlanBuilder $planner;

    /**
     * @var GenerationPlan<bool>
     */
    private readonly GenerationPlan $plan;

    private readonly Analyzer $analyzer;

    /**
     * Builds the target over one grammar version and one expansion budget.
     */
    public function __construct(private readonly string $grammarVersion, int $maxExpansions = 64)
    {
        $this->faker = Factory::create();
        $this->sql = new MySqlProvider($this->faker, $grammarVersion);
        $this->planner = $this->sql->planner();
        $this->plan = GenerationPlan::fromRule('select_stmt')->requiringNonEmpty()->withExpansionBudget($maxExpansions);
        $this->analyzer = new Analyzer();
    }

    /**
     * Writes a generated statement into PHP and checks that it comes back.
     *
     * @throws Error When the catalog does not hold exactly the statement that was written
     */
    public function __invoke(string $input): void
    {
        $plan = (new BytePlanCompiler())->compile($input, $this->planner, $this->plan);
        $statement = $this->sql->generate($plan);
        if ($statement === '') {
            return;
        }

        $shape = strlen($input) === 0 ? 0 : ord($input[0]) % 6;
        $catalog = $this->analyzer->analyzeSource(['fuzz.php' => $this->program($shape, $statement)]);

        if ($catalog->count() !== 1) {
            throw new Error(sprintf(
                'Expected one statement, got %d; shape=%d; input=%s%sSQL: %s',
                $catalog->count(),
                $shape,
                bin2hex($input),
                PHP_EOL,
                $statement,
            ));
        }

        $recovered = $catalog->entries()[0]->pattern->text();
        if ($recovered !== $statement) {
            throw new Error(sprintf(
                'Recovered a different statement; shape=%d; input=%s%sWritten:   %s%sRecovered: %s',
                $shape,
                bin2hex($input),
                PHP_EOL,
                $statement,
                PHP_EOL,
                $recovered ?? '(unresolved)',
            ));
        }
    }

    /**
     * A PHP program that issues the statement, written the way the shape says.
     */
    public function program(int $shape, string $statement): string
    {
        $literal = var_export($statement, true);
        $head = var_export(substr($statement, 0, (int) (strlen($statement) / 2)), true);
        $tail = var_export(substr($statement, (int) (strlen($statement) / 2)), true);

        return '<?php ' . match ($shape) {
            0 => 'function f(\\PDO $d): void { $d->query(' . $literal . '); }',
            1 => 'function f(\\PDO $d): void { $sql = ' . $literal . '; $d->query($sql); }',
            2 => 'function f(\\PDO $d): void { $d->query(' . $head . ' . ' . $tail . '); }',
            3 => 'const Q = ' . $literal . '; function f(\\PDO $d): void { $d->query(Q); }',
            4 => 'class C { public const Q = ' . $literal . '; } function f(\\PDO $d): void { $d->query(C::Q); }',
            default => 'function q(): string { return ' . $literal . '; } function f(\\PDO $d): void { $d->query(q()); }',
        };
    }
}

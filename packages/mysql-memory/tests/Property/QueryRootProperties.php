<?php

declare(strict_types=1);

namespace Tests\Property;

use Eris\TestTrait;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use Tests\Generator\MySql\Statement\QueryRootGenerator;

/**
 * Searches for QueryRoot values that post-analysis execution cannot consume.
 */
#[CoversNothing]
#[Large]
final class QueryRootProperties extends TestCase
{
    use TestTrait;

    /**
     * With no generated prerequisite state, execution may complete or refuse the
     * statement as SQL. Any representation or implementation failure escapes.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesExecutionConsumesTheAnalyzedValue(GrammarRelease $release): void
    {
        $this->forAll(new QueryRootGenerator($release))->withMaxSize(10)->then(static function (QueryRoot $statement) use ($release): void {
            $instance = new Instance(substr($release->value, 6), databases: ['pbt']);
            $session = $instance->connect(database: 'pbt');
            $execution = new Execution($session);
            try {
                $execution->statement($statement);
            } catch (SqlError) {
                // SQL refusal is an allowed outcome without prerequisite state.
            }
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        yield GrammarRelease::MySql847->value => [GrammarRelease::MySql847];
        yield GrammarRelease::MySql5651->value => [GrammarRelease::MySql5651];
    }
    /**
     * With generated prerequisites, every statement must execute successfully.
     * SQL errors are counterexamples in this property as well.
     */
    #[Test]
    #[DataProvider('providerDependentReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesExecutionCompletesWithItsPrerequisites(GrammarRelease $release): void
    {
        $this->forAll(QueryRootGenerator::withPrerequisites($release))->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            $instance = new Instance(substr($release->value, 6), databases: ['pbt']);
            $session = $instance->connect(database: 'pbt');
            $execution = new Execution($session);
            [$create, $insert, $query] = $sequence;
            $execution->statement($create);
            $execution->statement($insert);
            $execution->statement($query);
        });
    }

    /**
     * Generated numeric projections execute without additional state.
     */
    #[Test]
    #[DataProvider('providerDependentReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesNumericProjectionsComplete(GrammarRelease $release): void
    {
        $this->forAll(QueryRootGenerator::integerExpressions($release))->withMaxSize(10)->then(static function (QueryRoot $statement) use ($release): void {
            $instance = new Instance(substr($release->value, 6), databases: ['pbt']);
            $session = $instance->connect(database: 'pbt');
            $execution = new Execution($session);
            $execution->statement($statement);
        });
    }

    /**
     * Complete projections execute with the generated types and notices unchanged.
     */
    #[Test]
    #[DoesNotPerformAssertions]
    public function satisfiesCompleteProjectionsExecuteWithTheirGeneratedFacts(): void
    {
        $this->forAll(QueryRootGenerator::completeProjections())->withMaxSize(10)->then(static function (QueryRoot $statement): void {
            $instance = new Instance('8.4.7', databases: ['pbt']);
            $session = $instance->connect(database: 'pbt');
            $execution = new Execution($session);
            $execution->statement($statement);
        });
    }

    /**
     * A generated assignment supplies the session state required by its read.
     */
    #[Test]
    #[DoesNotPerformAssertions]
    public function satisfiesAssignedVariablesCanBeRead(): void
    {
        $this->forAll(QueryRootGenerator::assignedVariableReads())->withMaxSize(10)->then(static function (array $sequence): void {
            $instance = new Instance('8.4.7', databases: ['pbt']);
            $session = $instance->connect(database: 'pbt');
            $execution = new Execution($session);
            [$assign, $read] = $sequence;
            $execution->statement($assign);
            $execution->statement($read);
        });
    }

    /**
     * Deferred query operands execute or produce a SQL refusal.
     */
    #[Test]
    #[DoesNotPerformAssertions]
    public function satisfiesExecutionConsumesDeferredOperands(): void
    {
        $this->forAll(QueryRootGenerator::deferredSelections())->withMaxSize(10)->then(static function (QueryRoot $statement): void {
            $instance = new Instance('8.4.7', databases: ['pbt']);
            $session = $instance->connect(database: 'pbt');
            $execution = new Execution($session);
            try {
                $execution->statement($statement);
            } catch (SqlError) {
                // SQL refusal is an allowed outcome without prerequisite state.
            }
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerDependentReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }
}

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
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use Tests\Generator\MySql\Statement\Utility\Explain\ExplainGenerator;

/**
 * Searches for Explain values that post-analysis execution cannot consume.
 */
#[CoversNothing]
#[Large]
final class ExplainProperties extends TestCase
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
        $this->forAll(new ExplainGenerator($release))->withMaxSize(10)->then(static function (Explain $statement) use ($release): void {
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
     * Executes an explanation in its generated prerequisite database.
     */
    #[Test]
    #[DataProvider('providerDatabaseReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesExecutionCompletesWithItsPrerequisites(GrammarRelease $release): void
    {
        $this->forAll(ExplainGenerator::withPrerequisites($release))->withMaxSize(10)->then(static function (array $statements) use ($release): void {
            [$create, $explain] = $statements;
            $instance = new Instance(substr($release->value, 6), databases: ['pbt']);
            $session = $instance->connect(database: 'pbt');
            $execution = new Execution($session);
            $execution->statement($create);
            $execution->statement($explain);
        });
    }

    /**
     * Explains a generated write after creating and locking its destination.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesExecutionExplainsItsGeneratedLockedWrite(GrammarRelease $release): void
    {
        $this->forAll(ExplainGenerator::withLockedWrite($release))->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            [$table, $lock, $explain] = $sequence;
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $execution = new Execution($session);
            $execution->statement($table);
            $execution->statement($lock);
            $execution->statement($explain);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerDatabaseReleases(): iterable
    {
        foreach ([GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910] as $release) {
            yield $release->value => [$release];
        }
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910] as $release) {
            yield $release->value => [$release];
        }
    }
}

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
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use Tests\Generator\MySql\Statement\Table\CreateTableGenerator;

/**
 * Searches for CreateTable values that post-analysis execution cannot consume.
 */
#[CoversNothing]
#[Large]
final class CreateTableProperties extends TestCase
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
        $this->forAll(new CreateTableGenerator($release))->withMaxSize(10)->then(static function (CreateTable $statement) use ($release): void {
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
        yield from self::providerDependentReleases();
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
        $this->forAll(new CreateTableGenerator($release, CreateTableGenerator::integers()))->withMaxSize(10)->then(static function (CreateTable $statement) use ($release): void {
            $instance = new Instance(substr($release->value, 6), databases: ['pbt']);
            $session = $instance->connect(database: 'pbt');
            $execution = new Execution($session);
            $execution->statement($statement);
        });
    }

    /**
     * Creates a temporary shadow or accepts an existing table under generated explicit locks.
     * All prerequisites and the final creation must execute successfully.
     */
    #[Test]
    #[DataProvider('providerDependentReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesExecutionCompletesWithItsLockedPrerequisites(GrammarRelease $release): void
    {
        $this->forAll(CreateTableGenerator::withLockedPrerequisites($release))->withMaxSize(10)->then(static function (array $statements) use ($release): void {
            [$create, $lock, $target] = $statements;
            $instance = new Instance(substr($release->value, 6), databases: ['pbt']);
            $session = $instance->connect(database: 'pbt');
            $execution = new Execution($session);
            $execution->statement($create);
            $execution->statement($lock);
            $execution->statement($target);
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

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
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use Tests\Generator\MySql\Statement\Dml\Load\LoadTableGenerator;

/**
 * Searches for LoadTable values that post-analysis execution cannot consume.
 */
#[CoversNothing]
#[Large]
final class LoadTableProperties extends TestCase
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
        $this->forAll(new LoadTableGenerator($release))->withMaxSize(10)->then(static function (LoadTable $statement) use ($release): void {
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
     * Executes the generated table, lock and local load with its matching input file.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesExecutionCompletesWithItsPrerequisites(GrammarRelease $release): void
    {
        $this->forAll(LoadTableGenerator::withPrerequisites($release))->withMaxSize(10)->then(static function (array $input) use ($release): void {
            [$statements, $contents] = $input;
            $session = (new Instance(substr($release->value, 6), ['local_infile' => 1], databases: ['pbt']))->connect(database: 'pbt');
            $session->running->readFile = static fn (string $file): string => $contents;
            $execution = new Execution($session);
            foreach ($statements as $statement) {
                $execution->statement($statement);
            }
        });
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

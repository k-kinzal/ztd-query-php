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
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable;
use Tests\Generator\MySql\Statement\Alter\RenameTableGenerator;

/**
 * Searches for RenameTable values that post-analysis execution cannot consume.
 */
#[CoversNothing]
#[Large]
final class RenameTableProperties extends TestCase
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
        $this->forAll(new RenameTableGenerator($release))->withMaxSize(10)->then(static function (RenameTable $statement) use ($release): void {
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
     * Executes the complete generated rename sequence against its prerequisite table.
     */
    #[Test]
    #[DataProvider('providerPrerequisites')]
    #[DoesNotPerformAssertions]
    public function satisfiesExecutionCompletesWithItsPrerequisites(GrammarRelease $release, bool $locked): void
    {
        $this->forAll(RenameTableGenerator::withPrerequisites($release, $locked))->withMaxSize(10)->then(static function (array $statements) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $execution = new Execution($session);
            foreach ($statements as $statement) {
                $execution->statement($statement);
            }
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease, bool}>
     */
    public static function providerPrerequisites(): iterable
    {
        foreach (self::providerReleases() as [$release]) {
            yield $release->value . '/unlocked' => [$release, false];
            if (!in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                yield $release->value . '/locked' => [$release, true];
            }
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

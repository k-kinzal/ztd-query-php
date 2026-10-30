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
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use Tests\Generator\MySql\Statement\Alter\AlterTableGenerator;

/**
 * Searches for AlterTable values that post-analysis execution cannot consume.
 */
#[CoversNothing]
#[Large]
final class AlterTableProperties extends TestCase
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
        $this->forAll(new AlterTableGenerator($release))->withMaxSize(10)->then(static function (AlterTable $statement) use ($release): void {
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
     * Generated prerequisite definitions and rows make DISCARD executable.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesTablespaceDiscardCompletesWithItsPrerequisites(GrammarRelease $release): void
    {
        $this->forAll(AlterTableGenerator::tablespaceSequences($release))->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $execution = new Execution($session);
            [$create, $insert, $alter] = $sequence;
            $execution->statement($create);
            $execution->statement($insert);
            $execution->statement($alter);
        });
    }

    /**
     * Generated table definitions and rows satisfy index-alteration dependencies.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesIndexAlterationCompletesWithItsPrerequisites(GrammarRelease $release): void
    {
        $this->forAll(AlterTableGenerator::indexSequences($release))->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $execution = new Execution($session);
            [$create, $insert, $alter] = $sequence;
            $execution->statement($create);
            $execution->statement($insert);
            $execution->statement($alter);
        });
    }

    /**
     * Generated columns and functional indexes execute against their generated table.
     */
    #[Test]
    #[DataProvider('providerFunctionalReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesFunctionalAlterationCompletesWithItsPrerequisites(GrammarRelease $release): void
    {
        $this->forAll(AlterTableGenerator::functionalSequences($release))->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $execution = new Execution($session);
            [$create, $insert, $alter] = $sequence;
            $execution->statement($create);
            $execution->statement($insert);
            $execution->statement($alter);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerFunctionalReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql' && !in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                yield $release->value => [$release];
            }
        }
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }
}

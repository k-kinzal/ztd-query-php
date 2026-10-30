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
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowKeysGenerator;

/**
 * Searches for ShowKeys values that post-analysis execution cannot consume.
 */
#[CoversNothing]
#[Large]
final class ShowKeysProperties extends TestCase
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
        $this->forAll(new ShowKeysGenerator($release))->withMaxSize(10)->then(static function (ShowKeys $statement) use ($release): void {
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
     * Index inspection completes after its generated schema and table are installed.
     */
    #[Test]
    #[DataProvider('providerDependentReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesIndexInspectionCompletesWithItsTable(GrammarRelease $release): void
    {
        $this->forAll(ShowKeysGenerator::withTable($release))->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            $instance = new Instance(substr($release->value, 6), databases: ['pbt']);
            $session = $instance->connect(database: 'pbt');
            $execution = new Execution($session);
            [$schema, $create, $show] = $sequence;
            $execution->statement($schema);
            $execution->statement($create);
            $execution->statement($show);
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

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        yield GrammarRelease::MySql847->value => [GrammarRelease::MySql847];
    }
}

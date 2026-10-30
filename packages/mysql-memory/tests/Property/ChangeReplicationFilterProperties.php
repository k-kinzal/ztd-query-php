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
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter;
use Tests\Generator\MySql\Statement\Replication\Filter\ChangeReplicationFilterGenerator;

/**
 * Searches for ChangeReplicationFilter values that post-analysis execution cannot consume.
 */
#[CoversNothing]
#[Large]
final class ChangeReplicationFilterProperties extends TestCase
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
        $this->forAll(new ChangeReplicationFilterGenerator($release))->withMaxSize(10)->then(static function (ChangeReplicationFilter $statement) use ($release): void {
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
     * A generated source and stopped applier satisfy the filter change's prerequisites.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesFilterChangeCompletesWithItsPrerequisites(GrammarRelease $release): void
    {
        $this->forAll(ChangeReplicationFilterGenerator::sequences($release))->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            $session = (new Instance(substr($release->value, 6), globals: ['server_id' => 1], databases: ['pbt']))->connect(database: 'pbt');
            $execution = new Execution($session);
            [$configure, $start, $stop, $filter] = $sequence;
            $execution->statement($configure);
            $execution->statement($start);
            $execution->statement($stop);
            $execution->statement($filter);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql' && $release !== GrammarRelease::MySql5651) {
                yield $release->value => [$release];
            }
        }
    }
}

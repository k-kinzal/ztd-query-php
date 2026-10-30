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
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore;
use Tests\Generator\MySql\Statement\Replication\Log\PurgeLogsBeforeGenerator;

/**
 * Searches for PurgeLogsBefore values that post-analysis execution cannot consume.
 */
#[CoversNothing]
#[Large]
final class PurgeLogsBeforeProperties extends TestCase
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
        $this->forAll(new PurgeLogsBeforeGenerator($release))->withMaxSize(10)->then(static function (PurgeLogsBefore $statement) use ($release): void {
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
     * Generated rotations and cutoffs remain executable after shrinking.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesPurgeCompletesWithItsRotatedLogs(GrammarRelease $release): void
    {
        $this->forAll(PurgeLogsBeforeGenerator::withRotatedLogs($release))->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $execution = new Execution($session);
            [$rotate, $purge] = $sequence;
            $execution->statement($rotate);
            $execution->statement($purge);
        });
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

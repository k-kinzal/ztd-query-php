<?php

declare(strict_types=1);

namespace Tests\Property;

use Eris\TestTrait;
use MySqlMemory\Error\Family\AdministrationError;
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
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica;
use Tests\Generator\MySql\Statement\Replication\Replica\StartReplicaGenerator;

/**
 * Generates StartReplica analysis results and executes them after analysis.
 */
#[CoversNothing]
#[Large]
final class StartReplicaProperties extends TestCase
{
    use TestTrait;

    /**
     * The server errors that report only runtime state the generated statement depends on, such as objects it names that do not exist yet.
     */
    private const MISSING_STATE = [AdministrationError::ReplicaNotConfigured, AdministrationError::ReplicaConnectionMetadata, AdministrationError::ReplicaChannelMissing];

    /**
     * Execution raises no error, except an error that depends on database state.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesExecutionRaisesNoErrorExceptForDatabaseState(GrammarRelease $release): void
    {
        $this->forAll(new StartReplicaGenerator($release))->then(static function (StartReplica $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            try {
                (new Execution($session))->statement($statement);
            } catch (SqlError $error) {
                if (!in_array($error->error, self::MISSING_STATE, true)) {
                    throw $error;
                }
            }
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        yield GrammarRelease::MySql847->value => [GrammarRelease::MySql847];
    }
}

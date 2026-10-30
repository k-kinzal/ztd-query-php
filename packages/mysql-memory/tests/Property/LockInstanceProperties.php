<?php

declare(strict_types=1);

namespace Tests\Property;

use Eris\TestTrait;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockInstance;
use Tests\Generator\MySql\Statement\Server\Lock\LockInstanceGenerator;

/**
 * Generates LockInstance analysis results and executes them after analysis.
 */
#[CoversNothing]
#[Large]
final class LockInstanceProperties extends TestCase
{
    use TestTrait;

    /**
     * Execution raises no error.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesExecutionRaisesNoErrorExceptForDatabaseState(GrammarRelease $release): void
    {
        $this->forAll(new LockInstanceGenerator($release))->then(static function (LockInstance $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            (new Execution($session))->statement($statement);
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

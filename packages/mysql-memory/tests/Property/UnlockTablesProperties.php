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
use SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables;
use Tests\Generator\MySql\Statement\Server\Lock\UnlockTablesGenerator;

/**
 * Generates UnlockTables analysis results and executes them after analysis.
 */
#[CoversNothing]
#[Large]
final class UnlockTablesProperties extends TestCase
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
        $this->forAll(new UnlockTablesGenerator($release))->then(static function (UnlockTables $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            (new Execution($session))->statement($statement);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        yield GrammarRelease::MySql5651->value => [GrammarRelease::MySql5651];
        yield GrammarRelease::MySql5744->value => [GrammarRelease::MySql5744];
        yield GrammarRelease::MySql8044->value => [GrammarRelease::MySql8044];
        yield GrammarRelease::MySql810->value => [GrammarRelease::MySql810];
        yield GrammarRelease::MySql820->value => [GrammarRelease::MySql820];
        yield GrammarRelease::MySql830->value => [GrammarRelease::MySql830];
        yield GrammarRelease::MySql847->value => [GrammarRelease::MySql847];
        yield GrammarRelease::MySql901->value => [GrammarRelease::MySql901];
        yield GrammarRelease::MySql910->value => [GrammarRelease::MySql910];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Property;

use Eris\TestTrait;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
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
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use Tests\Generator\MySql\Statement\Routine\CreateProcedureGenerator;

/**
 * Generates CreateProcedure analysis results and executes them after analysis.
 */
#[CoversNothing]
#[Large]
final class CreateProcedureProperties extends TestCase
{
    use TestTrait;

    /**
     * The server errors that report only database state the generated statement depends on.
     */
    private const MISSING_STATE = [QueryError::BadDatabase, ProgramError::UndeclaredVariable, ProgramError::UndefinedCursor, SchemaError::UnknownStorageEngine];

    /**
     * Execution raises no error, except an error that depends on database state.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    #[DoesNotPerformAssertions]
    public function satisfiesExecutionRaisesNoErrorExceptForDatabaseState(GrammarRelease $release): void
    {
        $this->forAll(new CreateProcedureGenerator($release))->then(static function (CreateProcedure $statement) use ($release): void {
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

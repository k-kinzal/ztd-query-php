<?php

declare(strict_types=1);

namespace Tests\Integration;

use Eris\Random\MersenneTwister;
use Eris\Random\RandomRange;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use Tests\Generator\MySql\Statement\QueryRootGenerator;
use Tests\Support\Analysis;

/**
 * Shrinking a generated statement yields statements that its analysis still accepts and
 * that execute with no error other than one that depends on database state.
 */
#[CoversNothing]
#[Medium]
final class GeneratedDependencyShrinkingTest extends TestCase
{
    /**
     * The server errors that report only runtime state the generated query depends on.
     */
    private const MISSING_STATE = [QueryError::NoSuchTable, QueryError::BadDatabase, QueryError::BadField, QueryError::UnknownTable, SchemaError::BadTable, ProgramError::RoutineMissing, ProgramError::UndeclaredVariable];

    #[DataProvider('providerShrunkStatements')]
    public function testShrinkingKeepsTheGeneratedStatementExecutable(QueryRoot $statement): void
    {
        self::assertTrue(Analysis::accepted($statement));
        $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
        try {
            (new Execution($session))->statement($statement);
        } catch (SqlError $error) {
            self::assertContains($error->error, self::MISSING_STATE, $error->getMessage());
        }
    }

    /**
     * @return iterable<string, array{QueryRoot}>
     */
    public static function providerShrunkStatements(): iterable
    {
        $random = new RandomRange(new MersenneTwister());
        $random->seed(1983);
        $generator = new QueryRootGenerator(GrammarRelease::MySql847);
        $original = $generator(8, $random);
        yield 'generated' => [$original->unbox()];
        foreach ($generator->shrink($original) as $index => $candidate) {
            yield 'shrunk ' . $index => [$candidate->unbox()];
        }
    }
}

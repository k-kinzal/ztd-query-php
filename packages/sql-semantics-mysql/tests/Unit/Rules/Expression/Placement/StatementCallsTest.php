<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Placement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Expression\Placement\StatementCalls;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(StatementCalls::class)]
#[Medium]
final class StatementCallsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerMisplacedCalls(): iterable
    {
        yield 'aggregate in KILL' => ['KILL SUM(1)', 'Invalid use of group function'];
        yield 'aggregate in PURGE' => ['PURGE BINARY LOGS BEFORE SUM(1)', 'Invalid use of group function'];
        yield 'aggregate in a SHOW filter' => ['SHOW TABLES WHERE SUM(1)', 'Invalid use of group function'];
        yield 'aggregate in SET' => ['SET @a = SUM(1)', 'Invalid use of group function'];
        yield 'window in KILL' => ['KILL SUM(1) OVER ()', "You cannot use the window function 'sum' in this context.'"];
        yield 'window in SET' => ['SET @a = ROW_NUMBER() OVER ()', "You cannot use the window function 'row_number' in this context.'"];
        yield 'undefined window in a SHOW filter' => ['SHOW TABLES WHERE ROW_NUMBER() OVER w', 'Window name is not defined'];
        yield 'undefined window in DO' => ['DO ROW_NUMBER() OVER w', 'Window name is not defined'];
        yield 'undefined window in a query without tables' => ['SELECT ROW_NUMBER() OVER w', 'Window name is not defined'];
        yield 'undefined window in a nested query' => ['SET @a = (SELECT SUM(1) OVER w)', 'Window name is not defined'];
    }

    #[DataProvider('providerMisplacedCalls')]
    public function testProblemFindsACallAStandaloneStatementCannotEvaluate(string $sql, string $message): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze($sql);
            self::fail('The call is misplaced.');
        } catch (RejectedInput $failure) {
            self::assertSame($message, $failure->problem->message());
            self::assertFalse($failure->aborts);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerAcceptedCalls(): iterable
    {
        yield 'aggregate in DO' => ['DO SUM(1)'];
        yield 'inline window in DO' => ['DO SUM(1) OVER ()'];
        yield 'aggregate in a subquery of a SHOW filter' => ['SHOW TABLES WHERE (SELECT SUM(1))'];
        yield 'aggregate in a SET subquery' => ['SET @a = (SELECT SUM(1))'];
        yield 'defined window' => ['SELECT ROW_NUMBER() OVER w WINDOW w AS ()'];
        yield 'aggregate over a table' => ['SELECT SUM(a) FROM t'];
    }

    #[DataProvider('providerAcceptedCalls')]
    public function testProblemAcceptsCallsInsideAQueryBlockOrDo(string $sql): void
    {
        self::assertNull((new StatementCalls())->problem((new Semantics(Dialect::MySql))->analyze($sql)));
    }

    public function testProblemKeepsAStoredStatement(): void
    {
        $stored = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() KILL SUM(1)');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $stored);
        self::assertInstanceOf(M\Server\Instance\Kill::class, $stored->body);
    }

    public function testDetachedNamesTheStatementsThatOpenNoTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $calls = new StatementCalls();

        self::assertTrue($calls->detached($semantics->analyze('KILL 1')));
        self::assertTrue($calls->detached($semantics->analyze('SHOW TABLES')));
        self::assertTrue($calls->detached($semantics->analyze('SET @a = 1')));
        self::assertFalse($calls->detached($semantics->analyze('DO 1')));
        self::assertFalse($calls->detached($semantics->analyze('SELECT 1')));
    }

    public function testNameWritesTheFunctionAsTheServerDoes(): void
    {
        $calls = new StatementCalls();

        self::assertSame('row_number', $calls->name(new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new M\Call\Window\WindowSpec())));
        self::assertSame('sum', $calls->name(new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [new M\Literal\NumberLiteral('1')])));
        self::assertSame('group_concat', $calls->name(new M\Call\Aggregate\GroupConcat([new M\Literal\NumberLiteral('1')])));
        self::assertSame('json_objectagg', $calls->name(new M\Call\Aggregate\JsonObjectAggregate(new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2'))));
    }
}

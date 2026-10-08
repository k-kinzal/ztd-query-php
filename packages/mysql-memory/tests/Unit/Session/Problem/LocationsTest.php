<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Problem\Locations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(Locations::class)]
#[Small]
final class LocationsTest extends TestCase
{
    public function testUndeclaredHoldsForACallOfAFunctionThatIsNotNative(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $operation = $session->analyze('SELECT nosuch(1), abs(1)');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SelectExpression::class, $operation->statement->items[0]);
        self::assertInstanceOf(FunctionCall::class, $operation->statement->items[0]->expression);
        self::assertInstanceOf(SelectExpression::class, $operation->statement->items[1]);
        self::assertInstanceOf(FunctionCall::class, $operation->statement->items[1]->expression);

        self::assertTrue(Locations::undeclared($operation->statement->items[0]->expression, $operation));
        self::assertFalse(Locations::undeclared($operation->statement->items[1]->expression, $operation));
    }

    public function testProblemAnswersTheProblemOfAnOrdinalAndOfACall(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $operation = $session->analyze('SELECT nosuch(1) ORDER BY 2');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SelectExpression::class, $operation->statement->items[0]);
        self::assertInstanceOf(FunctionCall::class, $operation->statement->items[0]->expression);
        self::assertInstanceOf(OutputOrdinal::class, $operation->statement->orderBy[0]->expression);

        self::assertSame($operation->statement->items[0]->expression, (new Locations())->problem($operation->statement->items[0]->expression, $operation));
        self::assertInstanceOf(OrdinalOutOfRange::class, (new Locations())->problem($operation->statement->orderBy[0]->expression, $operation));
    }

    public function testWidthFindsTheWidthOfAllBeforeItsOperandAndOfInAfterIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $all = $session->run('SELECT nope = ALL (SELECT a, b FROM t) FROM t');
        $in = $session->run('SELECT nope IN (SELECT a, b FROM t) FROM t');
        $subquery = $session->run('SELECT nope IN (SELECT nope2 FROM t) FROM t');

        self::assertInstanceOf(SqlError::class, $all[0]);
        self::assertSame([1241, 'Operand should contain 1 column(s)'], [$all[0]->getCode(), $all[0]->getMessage()]);
        self::assertInstanceOf(SqlError::class, $in[0]);
        self::assertSame([1054, "Unknown column 'nope' in 'IN/ALL/ANY subquery'"], [$in[0]->getCode(), $in[0]->getMessage()]);
        self::assertInstanceOf(SqlError::class, $subquery[0]);
        self::assertSame("Unknown column 'nope2' in 'field list'", $subquery[0]->getMessage());
    }

    public function testUndefaultedRefusesDefaultOfAColumnWithoutADefaultWhereItIsResolved(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, a INT)');
        $first = $session->run('SELECT DEFAULT(id), nope FROM t');
        $later = $session->run('SELECT nope, DEFAULT(id) FROM t');
        $result = $session->query('SELECT DEFAULT(a) FROM t')[0];

        self::assertInstanceOf(SqlError::class, $first[0]);
        self::assertSame([1364, "Field 'id' doesn't have a default value"], [$first[0]->getCode(), $first[0]->getMessage()]);
        self::assertInstanceOf(SqlError::class, $later[0]);
        self::assertSame(1054, $later[0]->getCode());
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
    }

    public function testLocatedPlacesEachProblemWhereTheServerResolvesIt(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT nope, CAST(1 AS SIGNED ARRAY) FROM t');
        [$located, $matched] = (new Locations())->located($operation, [], $operation->facts->diagnostics, $session);

        self::assertSame([[\SqlSemantics\Statement\Reference\Column\MissingColumn::class, 'field list'], [\SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet::class, 'field list']], array_map(static fn (array $entry): array => [$entry[0]::class, $entry[1][0]], array_values($located)));
        self::assertSame([], $matched);
    }

    public function testNamesMarksTheProblemOfAColumnOfMatch(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze("SELECT MATCH(nope) AGAINST ('x') FROM t");
        [$located, $matched] = (new Locations())->names($operation, (new \MySqlMemory\Session\Locator())->statement($operation->statement), [], $operation->facts->diagnostics);

        self::assertCount(1, $located);
        self::assertSame(array_keys($located), array_keys($matched));
    }

    public function testNamesLeavesOutAProblemTheStatementDoesNotReport(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT nope FROM t');
        [$located, $matched] = (new Locations())->names($operation, (new \MySqlMemory\Session\Locator())->statement($operation->statement), [], []);

        self::assertSame([[], []], [$located, $matched]);
    }

    public function testDefaultsPlacesTheRefusalOfDefaultAfterItsColumn(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, a INT)');
        $operation = $session->analyze('SELECT DEFAULT(id), DEFAULT(a) FROM t');
        $located = array_values((new Locations())->defaults($operation, (new \MySqlMemory\Session\Locator())->statement($operation->statement), $session, []));

        self::assertCount(1, $located);
        self::assertInstanceOf(SqlError::class, $located[0][0]);
        self::assertSame([1364, 'field list', 0], [$located[0][0]->getCode(), $located[0][1][0], $located[0][1][1][count($located[0][1][1]) - 1]]);
    }

    public function testWidthsPlacesTheWidthOfAllBeforeItsOperand(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $operation = $session->analyze('SELECT 1 = ALL (SELECT a, b FROM t) FROM t');
        $located = array_values((new Locations())->widths($operation, (new \MySqlMemory\Session\Locator())->statement($operation->statement), $operation->facts->diagnostics, []));

        self::assertCount(1, $located);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $located[0][0]);
        self::assertSame(['field list', [1, 0, 1]], $located[0][1]);
    }

    public function testFirstAnswersTheProblemResolvedFirstAndTheEarlierOfTwoAtOneOrder(): void
    {
        $where = new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('where');
        $order = new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('order');
        $again = new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('again');

        self::assertSame([$where, ['where clause', [2, 1]]], Locations::first([1 => [$order, ['order clause', [6, 0]]], 2 => [$where, ['where clause', [2, 1]]], 3 => [$again, ['where clause', [2, 1]]]]));
        self::assertNull(Locations::first([]));
    }
}

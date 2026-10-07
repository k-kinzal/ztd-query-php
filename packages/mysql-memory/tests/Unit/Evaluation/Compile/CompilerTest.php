<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Compiler::class)]
#[Small]
final class CompilerTest extends TestCase
{
    public function testParameterIndexReadsEachMarkerInWrittenOrder(): void
    {
        $session = (new Instance())->connect();
        $result = $session->run('SELECT ?, ?', [[5, Domain::integer()], ['x', Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'))]], true)[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['5', 'x']], $result->rows);
    }

    public function testCompileEvaluatesAnExpressionForEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2), (3)');
        $result = $session->query('SELECT a * 10 + 1 FROM t ORDER BY a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['11'], ['21'], ['31']], $result->rows);
    }

    public function testCompileReadsAGroupedExpressionAfterGrouping(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('INSERT INTO t VALUES (1, 10), (1, 20), (2, 5)');
        $result = $session->query('SELECT a + 1, SUM(b) FROM t GROUP BY a + 1 ORDER BY a + 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '30'], ['3', '5']], $result->rows);
    }

    public function testResolvedGivesTheResultTheTypeSqlSemanticsResolved(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1.50, 1 + 1.5, 1e2')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(
            [['NewDecimal', 2], ['NewDecimal', 1], ['Double', 31]],
            [[$result->columns[0]->type->name, $result->columns[0]->decimals], [$result->columns[1]->type->name, $result->columns[1]->decimals], [$result->columns[2]->type->name, $result->columns[2]->decimals]],
        );
    }

    public function testDomainGivesADivisionTheScaleOfDivPrecisionIncrement(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET div_precision_increment = 2');
        $result = $session->query('SELECT 1 / 3')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0.33']], $result->rows);
        self::assertSame(2, $result->columns[0]->decimals);
    }

    public function testTypedKeepsTheNullabilitySqlSemanticsDerived(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, v INT)');
        $session->query('INSERT INTO t VALUES (1, NULL)');
        $result = $session->query('SELECT id, v, id + 1, v + 1, COALESCE(v, 0) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', null, '2', null, '0']], $result->rows);
        self::assertSame([1, 0, 1, 0, 1], [$result->columns[0]->flags & 1, $result->columns[1]->flags & 1, $result->columns[2]->flags & 1, $result->columns[3]->flags & 1, $result->columns[4]->flags & 1]);
    }

    public function testDispatchCompilesConcatenationWithPipesAsConcat(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode = 'PIPES_AS_CONCAT'");
        $result = $session->query("SELECT 'a' || 'b' || 1")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['ab1']], $result->rows);
    }

    public function testDispatchRefusesAnAggregateOutsideTheGroupedOutput(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1111);
        $this->expectExceptionMessage('Invalid use of group function');

        $session->query('SELECT a FROM t WHERE COUNT(*) > 0');
    }
}

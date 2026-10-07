<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Subqueries;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Subqueries::class)]
#[Small]
final class SubqueriesTest extends TestCase
{
    public function testScalarReadsTheValueOfASubqueryOrNullForNoRow(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT (SELECT 5), (SELECT 1 FROM DUAL WHERE 0)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['5', null]], $result->rows);
    }

    public function testScalarRefusesASubqueryOfMoreThanOneColumn(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        $this->expectExceptionMessage('Operand should contain 1 column(s)');

        $session->query('SELECT (SELECT 1, 2)');
    }

    public function testScalarRefusesASubqueryOfMoreThanOneRow(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1242);
        $this->expectExceptionMessage('Subquery returns more than 1 row');

        $session->query('SELECT (SELECT 1 UNION SELECT 2)');
    }

    public function testExistsTellsWhetherTheSubqueryHasARow(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT EXISTS (SELECT 1), EXISTS (SELECT 1 FROM DUAL WHERE 0), NOT EXISTS (SELECT NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '0']], $result->rows);
    }

    public function testInTellsWhetherAValueIsAmongTheRowsOfTheSubquery(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 2 IN (SELECT 1 UNION SELECT 2), 3 IN (SELECT 1 UNION SELECT NULL), 3 NOT IN (SELECT 1 UNION SELECT 2), NULL IN (SELECT 1), 1 IN (SELECT 1 FROM DUAL WHERE 0)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', null, '1', null, '0']], $result->rows);
    }

    public function testQuantifiedComparisonComparesWithAnyOrAllRows(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 2 > ALL (SELECT 1 UNION SELECT 2), 2 > ANY (SELECT 1 UNION SELECT 2), 2 = SOME (SELECT 3), 1 = ALL (SELECT 1 FROM DUAL WHERE 0), 1 = ANY (SELECT 1 FROM DUAL WHERE 0)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1', '0', '1', '0']], $result->rows);
    }

    public function testQuantifiedRefusesASubqueryOfMoreThanOneColumn(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        $this->expectExceptionMessage('Operand should contain 1 column(s)');

        $session->query('SELECT 1 IN (SELECT 1, 2)');
    }
}

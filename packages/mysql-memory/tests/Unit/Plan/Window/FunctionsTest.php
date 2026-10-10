<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Window;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Window\Analytic;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\Window\Functions;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(Functions::class)]
#[Small]
final class FunctionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function providerWindowRefusals(): iterable
    {
        yield 'undefined window' => ['SELECT NTH_VALUE(1,1) IGNORE NULLS OVER missing', 3579, "Window name 'missing' is not defined."];
        yield 'unresolved field' => ['SELECT NTH_VALUE(1,1) IGNORE NULLS OVER (), missing', 1054, "Unknown column 'missing' in 'field list'"];
        yield 'null treatment' => ['SELECT NTH_VALUE(1,1) FROM LAST IGNORE NULLS OVER ()', 1235, "This version of MySQL doesn't yet support 'IGNORE NULLS'"];
        yield 'counting edge' => ['SELECT NTH_VALUE(1,0) FROM LAST OVER ()', 1235, "This version of MySQL doesn't yet support 'FROM LAST'"];
    }

    #[DataProvider('providerWindowRefusals')]
    public function testCompileRefusesUnsupportedOptionsAfterResolution(string $sql, int $code, string $message): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $this->expectExceptionMessage($message);

        $session->query($sql);
    }

    public function testCompileReadsTheOffsetAndTheDefaultOfLag(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE u (id INT)');
        $session->query('INSERT INTO u VALUES (1), (2), (3)');

        $result = $session->query('SELECT id, LAG(id, 2, -1) OVER (ORDER BY id) FROM u')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '-1'], ['2', '-1'], ['3', '1']], $result->rows);
    }

    public function testCompileRefusesTheCountOfNtileAfterEveryWindowIsChecked(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3586);

        $session->query('SELECT NTILE(0) OVER (ORDER BY 2 + 0 ROWS BETWEEN 1 FOLLOWING AND 1 PRECEDING)');
    }

    public function testCountRefusesAVariableThatHoldsNoInteger(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET @n = '2'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1210);
        $this->expectExceptionMessage('Incorrect arguments to ntile');

        $session->query('SELECT NTILE(@n) OVER ()');
    }

    public function testCountRefusesAnOffsetAboveTheLargestBigint(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1210);
        $this->expectExceptionMessage('Incorrect arguments to lead');

        $session->query('SELECT LEAD(1, 9223372036854775808) OVER ()');
    }

    public function testNthRefusesARowThatIsNotAPositiveInteger(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1210);
        $this->expectExceptionMessage('Incorrect arguments to nth_value');

        $session->query('SELECT NTH_VALUE(1, 2.0) OVER ()');
    }

    public function testCachedReadsTheArgumentsAfterThePosition(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1');
        self::assertInstanceOf(Select::class, $operation->statement);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $argument = new ColumnRead(Domain::integer(), 0);
        [$sum, $sums] = (new Functions($planner))->cached(new Analytic(null, new Accumulation(AggregateFunction::Sum, [$argument], false, Domain::decimal(33, 0)), [], 1, Domain::decimal(33, 0)), 3);
        [$lag, $lags] = (new Functions($planner))->cached(new Analytic(WindowFunctionKind::Lag, null, [$argument], 1, Domain::integer()), 4);

        self::assertSame([[$argument], [$argument]], [$sums, $lags]);
        self::assertInstanceOf(ColumnRead::class, $sum->accumulation?->arguments[0]);
        self::assertSame(3, $sum->accumulation->arguments[0]->position);
        self::assertInstanceOf(ColumnRead::class, $lag->arguments[0]);
        self::assertSame(4, $lag->arguments[0]->position);
    }

    public function testCountRefusesAParameterBoundToAString(): void
    {
        $session = (new Instance())->connect();
        $session->query('PREPARE s FROM \'SELECT NTILE(?) OVER ()\'');
        $session->query("SET @n = '2'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1210);
        $this->expectExceptionMessage('Incorrect arguments to EXECUTE');

        $session->query('EXECUTE s USING @n');
    }
}

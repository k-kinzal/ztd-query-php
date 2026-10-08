<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Expression;

use MySqlMemory\Command\Definition\Expression\ExpressionRules;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;

#[CoversClass(ExpressionRules::class)]
#[Small]
final class ExpressionRulesTest extends TestCase
{
    public function testConditionRefusesACheckConstraintThatIsNoCondition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3812);
        $this->expectExceptionMessage("An expression of non-boolean type specified to a check constraint 't1_chk_1'.");

        $session->query('CREATE TABLE t1 (a INT, CHECK (a))');
    }

    public function testForbiddenRefusesTheFirstVariableSubqueryOrFunctionInWrittenOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3772);
        $this->expectExceptionMessage("Default value expression of column 'b' cannot refer user or system variables.");

        $session->query('CREATE TABLE t1 (a INT, b INT AS (@v + (SELECT 1)))');
    }

    public function testCalledAnswersTheNameANodeCallsAFunctionBy(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('SELECT RAND()')->statement;
        $calls = (new \MySqlMemory\Evaluation\Compile\Walker())->find($statement, FunctionCall::class);

        self::assertSame(['rand'], array_map(static fn (FunctionCall $call): ?string => (new ExpressionRules())->called($call), $calls));
    }

    public function testGroupedRefusesAnAggregate(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1111);
        $this->expectExceptionMessage('Invalid use of group function');

        $session->query('CREATE TABLE t1 (a INT, b INT AS (sum(a)))');
    }

    public function testGroupedRefusesAWindowFunction(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3593);
        $this->expectExceptionMessage("You cannot use the window function 'row_number' in this context.'");

        $session->query('CREATE TABLE t1 (a INT, b INT DEFAULT (row_number() over ()))');
    }
}

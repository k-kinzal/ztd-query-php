<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\DoubleOperand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(DoubleOperand::class)]
#[Small]
final class DoubleOperandTest extends TestCase
{
    public function testEvaluateReadsAConstantOperandOnceForTheStatement(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $numeric = new DoubleOperand(new Constant(Domain::string(5, Collation::known('utf8mb4_0900_ai_ci')), '1x'), true);

        self::assertSame([1.0, 1.0], [$numeric->evaluate($frame), $numeric->evaluate($frame)]);
        self::assertSame(1, $session->diagnostics->count());
    }

    public function testEvaluateReadsAVaryingOperandEachTime(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $numeric = new DoubleOperand(new Constant(Domain::string(5, Collation::known('utf8mb4_0900_ai_ci')), '1x'), false);

        self::assertSame([1.0, 1.0], [$numeric->evaluate($frame), $numeric->evaluate($frame)]);
        self::assertSame(2, $session->diagnostics->count());
    }

    public function testEvaluateWarnsOnceForAConstantStringComparedWithEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT, s VARCHAR(5))');
        $session->query("INSERT INTO t VALUES (1, 'a'), (2, 'b'), (3, NULL)");
        $result = $session->query("SELECT 'a' = id, 'a' IS TRUE, @undefined = 'b' FROM t")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '0', null], ['0', '0', null], ['0', '0', null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"], ['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"]], $warnings->rows);
    }

    public function testDomainAnswersADoubleAsNullableAsTheOperand(): void
    {
        $numeric = new DoubleOperand(new Constant(Domain::integer()->withNullable(false), 1), true);

        self::assertSame([Kind::Double, false], [$numeric->domain()->kind, $numeric->domain()->nullable]);
    }
}

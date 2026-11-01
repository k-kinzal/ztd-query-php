<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Transcoded;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Transcoded::class)]
#[Small]
final class TranscodedTest extends TestCase
{
    public function testDomainIsTheDomainOfTheOperand(): void
    {
        $domain = Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame($domain, (new Transcoded(new Constant($domain, 'abc')))->domain());
    }

    public function testEvaluateReadsTheOperandOnceForTheStatement(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), ['a']);
        $transcoded = new Transcoded(new ColumnRead(Domain::string(1, Collation::known('utf8mb4_0900_ai_ci')), 0));
        $first = $transcoded->evaluate($frame);
        $frame->row = ['b'];

        self::assertSame(['a', 'a'], [$first, $transcoded->evaluate($frame)]);
    }

    public function testOfReadsAConstantStringInAnotherCharacterSetWhenTheStatementIsResolved(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2)');
        $result = $session->query("SELECT CONCAT('x' - INTERVAL 1 DAY, USER()) FROM t WHERE a > 5")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[], [['Warning', 1292, "Incorrect datetime value: 'x'"]]], [$result->rows, $session->diagnostics->conditions]);
    }

    public function testOfKeepsAnOperandThatVariesOrNeedsNoConversion(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $read = new ColumnRead(Domain::string(1, Collation::known('utf8mb4_0900_ai_ci')), 0);

        self::assertSame([$read, $read], [Transcoded::of($read, Collation::known('utf8mb3_general_ci'), false, $context), Transcoded::of($read, Collation::known('utf8mb4_bin'), true, $context)]);
    }
}

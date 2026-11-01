<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Aggregate;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Aggregate\RollupInput;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Assignment;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;

#[CoversClass(RollupInput::class)]
#[Small]
final class RollupInputTest extends TestCase
{
    public function testCaptureDoesNotEvaluateArgumentsAfterTheFirstNull(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @x=0');
        $domain = Domain::integer();
        $assignment = new Assignment('x', new Constant($domain, 1), $domain, $domain);
        $accumulation = new Accumulation(AggregateFunction::Count, [new Constant(Domain::null(), null), $assignment], true, $domain);
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame(['values' => [null], 'order' => []], (new RollupInput())->capture($accumulation, $frame));
        self::assertSame(0, $session->variables->user('x')[0]);
    }

    public function testCaptureSharesDistinctCountArgumentsAfterEarlierAggregatesRun(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @y=0');
        $result = $session->query('SELECT SUM(@y:=@y+1),COUNT(DISTINCT @y:=@y+10) FROM t GROUP BY n WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '1'], ['40', '2'], ['39', '3']], $result->rows);
        self::assertSame(36, $session->variables->user('y')[0]);
    }

    public function testCaptureSharesConcatenationArgumentsBeforeLaterAggregatesRun(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @y=0');
        $result = $session->query('SELECT GROUP_CONCAT(@y:=@y+10),SUM(@y:=@y+1) FROM t GROUP BY n WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['10', '12'], ['22,34', '60'], ['10,22,34', '69']], $result->rows);
        self::assertSame(36, $session->variables->user('y')[0]);
    }
}

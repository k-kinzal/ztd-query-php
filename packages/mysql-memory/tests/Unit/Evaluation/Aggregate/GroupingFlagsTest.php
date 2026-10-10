<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Aggregate;

use MySqlMemory\Evaluation\Aggregate\GroupingFlags;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(GroupingFlags::class)]
#[Small]
final class GroupingFlagsTest extends TestCase
{
    public function testDomainAnswersTheDomainOfTheResult(): void
    {
        $domain = Domain::integer();

        self::assertSame($domain, (new GroupingFlags($domain, [0], 1, 0))->domain());
    }

    public function testEvaluateSetsABitForEachArgumentTheRowRollsUp(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $flags = new GroupingFlags(Domain::integer(), [0, 1, 1], 2, 0);

        self::assertSame([0, 3, 7], [$flags->evaluate(new Frame($context, [0])), $flags->evaluate(new Frame($context, [1])), $flags->evaluate(new Frame($context, [2]))]);
    }

    public function testEvaluateAnswersTheBitsOfTheSuperAggregateRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('INSERT INTO t VALUES (1, 1), (1, 2)');
        $result = $session->query('SELECT GROUPING(a), GROUPING(b), GROUPING(a, b) FROM t GROUP BY a, b WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '0', '0'], ['0', '0', '0'], ['0', '1', '1'], ['1', '1', '3']], $result->rows);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Family;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Family\Ranges;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Ranges::class)]
#[Small]
final class RangesTest extends TestCase
{
    public function testBetweenComparesAStringAndANumberBoundAsDoubles(): void
    {
        $result = (new Instance())->connect()->query("SELECT 2 BETWEEN '1' AND 3, 'b' BETWEEN 'a' AND 'c', 3 NOT BETWEEN 1 AND 2")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '1']], $result->rows);
    }

    public function testBetweenWarnsOfAJsonValue(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('2' AS JSON) BETWEEN 1 AND 3")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[['1']], [['Warning', 1235, "This version of MySQL doesn't yet support 'comparison of JSON in the BETWEEN operator'"]]], [$result->rows, $session->diagnostics->conditions]);
    }

    public function testInListComparesAListOfOneValueAsAnEquality(): void
    {
        $result = (new Instance())->connect()->query("SELECT 1 IN ('1'), 1 NOT IN (2), 3 IN (1, 2, NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', null]], $result->rows);
    }

    public function testComparisonsAnswersTheComparisonTypeOfEachElementOfAMixedList(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $ranges = new Ranges((new Planner($session->analyze('SELECT 1')->statement, $session->analyze('SELECT 1')->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary))->compiler);
        $text = Domain::string(1, Collation::known('utf8mb4_general_ci'));

        self::assertSame(['double', 'string', null, 'double'], $ranges->comparisons($text, [Domain::integer(), $text, Domain::null(), Domain::decimal(1, 0)]));
    }

    public function testComparisonsAnswersNoTypeWhenEveryElementComparesAsOne(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $ranges = new Ranges((new Planner($session->analyze('SELECT 1')->statement, $session->analyze('SELECT 1')->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary))->compiler);

        self::assertSame([], $ranges->comparisons(Domain::integer(), [Domain::integer(), Domain::null()]));
    }
}

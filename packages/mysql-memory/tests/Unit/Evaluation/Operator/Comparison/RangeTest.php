<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator\Comparison;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Operator\Comparison\Range;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Range::class)]
#[Small]
final class RangeTest extends TestCase
{
    public function testEvaluateIncludesBothBounds(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $comparator = new Comparator(Kind::Integer, Domain::integer(), Domain::integer(), Collation::binary());
        $low = new Range(new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 3), $comparator, $comparator, false, Domain::integer());
        $high = new Range(new Constant(Domain::integer(), 3), new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 3), $comparator, $comparator, false, Domain::integer());
        $outside = new Range(new Constant(Domain::integer(), 4), new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 3), $comparator, $comparator, false, Domain::integer());
        $negated = new Range(new Constant(Domain::integer(), 4), new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 3), $comparator, $comparator, true, Domain::integer());

        self::assertSame([1, 1, 0, 1], [$low->evaluate($frame), $high->evaluate($frame), $outside->evaluate($frame), $negated->evaluate($frame)]);
    }

    public function testDomainAnswersTheDomainOfTheTruthValue(): void
    {
        $domain = Domain::integer();
        $comparator = new Comparator(Kind::Integer, Domain::integer(), Domain::integer(), Collation::binary());
        $range = new Range(new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 3), $comparator, $comparator, false, $domain);

        self::assertSame($domain, $range->domain());
    }

    public function testEvaluateAnswersBetweenAndNotBetween(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 2 BETWEEN 1 AND 3, 1 BETWEEN 1 AND 1, 4 BETWEEN 1 AND 3, 2 NOT BETWEEN 1 AND 3, 3 BETWEEN 3 AND 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0', '0', '0']], $result->rows);
    }

    public function testEvaluateAnswersFalseWhenAKnownBoundFailsDespiteANullBound(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT NULL BETWEEN 1 AND 3, 2 BETWEEN NULL AND 3, 5 BETWEEN NULL AND 3, 0 BETWEEN 1 AND NULL, 0 NOT BETWEEN 1 AND NULL, 2 NOT BETWEEN NULL AND 3')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, '0', '0', '1', null]], $result->rows);
    }

    public function testEvaluateComparesEachBoundAsItsTypesCompare(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'b' BETWEEN 'A' AND 'C', '10' BETWEEN 9 AND 11, '10' BETWEEN '9' AND '91', DATE '2024-05-01' BETWEEN '2024-01-01' AND '2024-12-31'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0', '1']], $result->rows);
    }

    public function testEvaluateTestsTheColumnOfEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, v INT, lo INT, hi INT)');
        $session->query('INSERT INTO t VALUES (1, 5, 1, 10), (2, 5, 6, 10), (3, 5, NULL, 10), (4, 5, NULL, 4)');
        $result = $session->query('SELECT id, v BETWEEN lo AND hi, v NOT BETWEEN lo AND hi FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0'], ['2', '0', '1'], ['3', null, null], ['4', '0', '1']], $result->rows);
    }
}

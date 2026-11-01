<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator\Comparison;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Operator\Comparison\RowCompare;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(RowCompare::class)]
#[Small]
final class RowCompareTest extends TestCase
{
    public function testEvaluateDecidesByTheFirstPairThatDiffers(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $comparator = new Comparator(Kind::Integer, Domain::integer(), Domain::integer(), Collation::binary());
        $pairs = [
            [new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 1), $comparator],
            [new Constant(Domain::integer(), 2), new Constant(Domain::integer(), 3), $comparator],
        ];

        self::assertSame(
            [1, 0, 0, 1],
            [
                (new RowCompare(ComparisonOperator::Less, $pairs, Domain::integer()))->evaluate($frame),
                (new RowCompare(ComparisonOperator::Greater, $pairs, Domain::integer()))->evaluate($frame),
                (new RowCompare(ComparisonOperator::Equal, $pairs, Domain::integer()))->evaluate($frame),
                (new RowCompare(ComparisonOperator::NotEqual, $pairs, Domain::integer()))->evaluate($frame),
            ],
        );
    }

    public function testEvaluateAnswersTheOperatorForEqualRows(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $comparator = new Comparator(Kind::Integer, Domain::integer(), Domain::integer(), Collation::binary());
        $pairs = [[new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 1), $comparator]];

        self::assertSame(
            [1, 1, 0],
            [
                (new RowCompare(ComparisonOperator::Equal, $pairs, Domain::integer()))->evaluate($frame),
                (new RowCompare(ComparisonOperator::GreaterOrEqual, $pairs, Domain::integer()))->evaluate($frame),
                (new RowCompare(ComparisonOperator::Less, $pairs, Domain::integer()))->evaluate($frame),
            ],
        );
    }

    public function testDomainAnswersTheDomainOfTheTruthValue(): void
    {
        $domain = Domain::integer();

        self::assertSame($domain, (new RowCompare(ComparisonOperator::Equal, [], $domain))->domain());
    }

    public function testEvaluateComparesRowsForEquality(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT (1, 2) = (1, 2), (1, 2) = (1, 3), (1, NULL) = (1, 2), (1, NULL) = (2, 2), (1, 2) <> (1, 2), (1, NULL) <> (2, 1), (1, NULL) <> (1, 2)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', null, '0', '0', '1', null]], $result->rows);
    }

    public function testEvaluateOrdersRowsByTheFirstUnequalPair(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT (1, 2) < (1, 3), (1, 2) < (2, 0), (1, NULL) < (1, 3), (NULL, 1) < (2, 3), (1, 1) < (NULL, 2), (2, 1) >= (2, 1), (1, 2) > (1, 2)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', null, null, null, '1', '0']], $result->rows);
    }

    public function testEvaluateComparesNullsAsEqualForTheNullSafeEquality(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT (1, NULL) <=> (1, NULL), (1, NULL) <=> (1, 2), (NULL, NULL) <=> (NULL, NULL), (1, 2) <=> (1, 3)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1', '0']], $result->rows);
    }

    public function testEvaluateComparesStringElementsInTheirCollation(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT ('a', 'B') = ('A', 'b'), ('a', 1) < ('B', 0)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1']], $result->rows);
    }

    public function testEvaluateRejectsRowsOfDifferentDegrees(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Operand should contain 2 column(s)');

        $session->query('SELECT (1, 2) = (1, 2, 3)');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator\Comparison;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Operator\Comparison\Compare;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Compare::class)]
#[Small]
final class CompareTest extends TestCase
{
    public function testHoldsTellsEachOperatorForAnOrder(): void
    {
        self::assertSame(
            [true, false, true, true, false, true, false, true, false, true],
            [
                Compare::holds(ComparisonOperator::Equal, 0),
                Compare::holds(ComparisonOperator::Equal, 1),
                Compare::holds(ComparisonOperator::NullSafeEqual, 0),
                Compare::holds(ComparisonOperator::NotEqual, -1),
                Compare::holds(ComparisonOperator::Less, 0),
                Compare::holds(ComparisonOperator::LessOrEqual, 0),
                Compare::holds(ComparisonOperator::Greater, -1),
                Compare::holds(ComparisonOperator::Greater, 1),
                Compare::holds(ComparisonOperator::GreaterOrEqual, -1),
                Compare::holds(ComparisonOperator::GreaterOrEqual, 0),
            ],
        );
    }

    public function testEvaluateAnswersNullWhenAnOperandIsNull(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $comparator = new Comparator(Kind::Integer, Domain::integer(), Domain::null(), Collation::binary());
        $compare = new Compare(ComparisonOperator::Equal, new Constant(Domain::integer(), 1), new Constant(Domain::null(), null), $comparator, Domain::integer());

        self::assertNull($compare->evaluate($frame));
    }

    public function testEvaluateNeverAnswersNullForTheNullSafeEquality(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $comparator = new Comparator(Kind::Integer, Domain::null(), Domain::null(), Collation::binary());
        $both = new Compare(ComparisonOperator::NullSafeEqual, new Constant(Domain::null(), null), new Constant(Domain::null(), null), $comparator, Domain::integer());
        $one = new Compare(ComparisonOperator::NullSafeEqual, new Constant(Domain::integer(), 1), new Constant(Domain::null(), null), $comparator, Domain::integer());

        self::assertSame([1, 0], [$both->evaluate($frame), $one->evaluate($frame)]);
    }

    public function testEvaluateComparesTheOperandsWithTheComparator(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $comparator = new Comparator(Kind::Integer, Domain::integer(), Domain::integer(), Collation::binary());
        $less = new Compare(ComparisonOperator::Less, new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 2), $comparator, Domain::integer());
        $greater = new Compare(ComparisonOperator::Greater, new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 2), $comparator, Domain::integer());

        self::assertSame([1, 0], [$less->evaluate($frame), $greater->evaluate($frame)]);
    }

    public function testDomainAnswersTheDomainOfTheTruthValue(): void
    {
        $domain = Domain::integer();
        $comparator = new Comparator(Kind::Integer, Domain::integer(), Domain::integer(), Collation::binary());
        $compare = new Compare(ComparisonOperator::Equal, new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 1), $comparator, $domain);

        self::assertSame($domain, $compare->domain());
    }

    public function testEvaluateAnswersEachOperatorThroughASession(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 2 > 1, 2 <> 2, 3 >= 3, 3 <= 2, 1 < 2, 1 = 1, 1 != 2, 1 = NULL, NULL <=> NULL, 1 <=> NULL')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1', '0', '1', '1', '1', null, '1', '0']], $result->rows);
    }

    public function testEvaluateComparesStringsInTheCollationOfTheOperation(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'a' = 'A', 'a' < 'b', 'a' = 'a ', 'a' COLLATE utf8mb4_bin = 'A', 'B' COLLATE utf8mb4_bin < 'a'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0', '0', '1']], $result->rows);
    }

    public function testEvaluateComparesColumnsOfEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, a DECIMAL(10,2), b DOUBLE)');
        $session->query('INSERT INTO t VALUES (1, 1.50, 1.5), (2, 2.00, 3), (3, NULL, 1)');
        $result = $session->query('SELECT id, a = b, a < b, a <=> b FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0', '1'], ['2', '0', '1', '0'], ['3', null, null, '0']], $result->rows);
    }

    public function testEvaluateRejectsAnIllegalMixOfCollations(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Illegal mix of collations (utf8mb4_bin,EXPLICIT) and (utf8mb4_0900_ai_ci,EXPLICIT) for operation '='");

        $session->query("SELECT 'a' COLLATE utf8mb4_bin = 'A' COLLATE utf8mb4_0900_ai_ci");
    }
}

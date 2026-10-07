<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Operator\Coerce;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Coerce::class)]
#[Small]
final class CoerceTest extends TestCase
{
    public function testToKeepsAValueOfTheSameKind(): void
    {
        $instance = new Instance();
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0);

        self::assertSame([5, '1.25', null], [Coerce::to(5, Domain::integer(), Domain::integer(), $context), Coerce::to('1.25', Domain::decimal(3, 2), Domain::decimal(4, 2), $context), Coerce::to(null, Domain::integer(), Domain::double(), $context)]);
    }

    public function testToConvertsToADecimalOfTheTargetScale(): void
    {
        $instance = new Instance();
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0);

        self::assertSame(['7.00', '1.26'], [Coerce::to(7, Domain::integer(), Domain::decimal(5, 2), $context), Coerce::to('1.255', Domain::decimal(4, 3), Domain::decimal(4, 2), $context)]);
    }

    public function testToConvertsToAnIntegerOrADouble(): void
    {
        $instance = new Instance();
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0);

        self::assertSame([3, 2.5], [Coerce::to('2.5', Domain::decimal(2, 1), Domain::integer(), $context), Coerce::to('2.5', Domain::decimal(2, 1), Domain::double(), $context)]);
    }

    public function testToConvertsANumberToText(): void
    {
        $instance = new Instance();
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0);

        self::assertSame('12', Coerce::to(12, Domain::integer(), Domain::string(2, Collation::known('utf8mb4_0900_ai_ci')), $context));
    }

    public function testToWarnsForAStringThatIsNoInteger(): void
    {
        $instance = new Instance();
        $diagnostics = new Diagnostics();
        $context = new Context(new SqlModes([]), $diagnostics, new Variables($instance->catalog, $instance->globals), 0.0);

        self::assertSame(12, Coerce::to('12abc', Domain::string(5, Collation::known('utf8mb4_0900_ai_ci')), Domain::integer(), $context));
        self::assertSame([['Warning', 1292, "Truncated incorrect INTEGER value: '12abc'"]], $diagnostics->conditions);
    }

    public function testToAlignsTheScaleOfCoalesceArguments(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT COALESCE(1.5, 2.25), COALESCE(NULL, 2, 2.5), COALESCE(1, 'a'), COALESCE(NULL, 2, 1e0)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1.50', '2.0', '1', '2']], $result->rows);
    }

    public function testBranchKeepsTheScaleOfADecimalBranch(): void
    {
        $instance = new Instance();
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0);

        self::assertSame('2.5', Coerce::branch('2.5', Domain::decimal(2, 1), Domain::decimal(5, 2), $context));
    }

    public function testBranchConvertsToAKindOtherThanDecimal(): void
    {
        $instance = new Instance();
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0);

        self::assertSame([3.0, '4'], [Coerce::branch(3, Domain::integer(), Domain::double(), $context), Coerce::branch(4, Domain::integer(), Domain::string(2, Collation::known('utf8mb4_0900_ai_ci')), $context)]);
    }
}

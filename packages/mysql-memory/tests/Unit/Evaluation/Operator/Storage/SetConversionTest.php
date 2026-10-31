<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator\Storage;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Storage\SetConversion;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(SetConversion::class)]
#[Small]
final class SetConversionTest extends TestCase
{
    public function testDomainRetainsTheResolvedStorageField(): void
    {
        $target = Domain::decimal(11, 2);
        $value = new SetConversion(new Constant(Domain::integer(), 1), $target);

        self::assertSame($target, $value->domain());
    }

    public function testEvaluateKeepsNullAndRoundsTheStoredDecimalWithoutCastWarnings(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $value = new SetConversion(new Constant(Domain::integer(), 2147483648), Domain::decimal(11, 2));
        $null = new SetConversion(new Constant(Domain::integer(), null), Domain::decimal(11, 2));

        self::assertSame('999999999.99', $value->evaluate($frame));
        self::assertNull($null->evaluate($frame));
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testIntegerSaturatesBothDirectionsWithoutReinterpretingTheBits(): void
    {
        $signed = Domain::integer();
        $unsigned = Domain::integer(Field::LongLong, 20, true);
        $value = new Constant($signed, 0);

        self::assertSame(PHP_INT_MAX, (new SetConversion($value, $signed))->integer(-1, $unsigned));
        self::assertSame(0, (new SetConversion($value, $unsigned))->integer(-1, $signed));
        self::assertSame(-1, (new SetConversion($value, $signed))->integer(-1, $signed));
        self::assertSame(-1, (new SetConversion($value, $unsigned))->integer(-1, $unsigned));
    }

    public function testIntegerCopiesTheBitsOfTheSameStoredFieldType(): void
    {
        $signed = Domain::integer();
        $unsigned = Domain::integer(Field::LongLong, 20, true);
        $value = new Constant($unsigned, -1);

        self::assertSame(-1, (new SetConversion($value, $signed, true))->integer(-1, $unsigned));
        self::assertSame(-1, (new SetConversion($value, $unsigned, true))->integer(-1, $signed));
        self::assertSame(2147483647, (new SetConversion($value, Domain::integer(Field::Long, 11), true))->integer(-1, $unsigned));
    }

    public function testDecimalRoundsWithinTheBoundAndSaturatesAtEitherEnd(): void
    {
        $value = new SetConversion(new Constant(Domain::integer(), 0), Domain::decimal(3, 2));

        self::assertSame('1.24', $value->decimal('1.235'));
        self::assertSame('9.99', $value->decimal('10'));
        self::assertSame('-9.99', $value->decimal('-10'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRangeLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Between;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(IntegerRangeLowering::class)]
#[Small]
final class IntegerRangeLoweringTest extends TestCase
{
    public function testLowerKeepsTheBoundOrderAndIndependentFlags(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE 2 NOT BETWEEN SYMMETRIC 3 AND NULL');
        self::assertInstanceOf(Select::class, $operation->statement);
        $parsed = $operation->statement->where;
        self::assertInstanceOf(Between::class, $parsed);

        $test = (new IntegerRangeLowering())->lower($parsed, new SourceBinding($operation->binding(), null), true);
        self::assertInstanceOf(IntegerLiteral::class, $test->lower);
        self::assertSame('3', $test->lower->decimal);
        self::assertInstanceOf(NullLiteral::class, $test->upper);
        self::assertTrue($test->negated);
        self::assertTrue($test->symmetric);
    }

    public function testLowerInfersTheSubjectTypeFromTwoKnownBounds(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE NULL BETWEEN 3 AND 4');
        self::assertInstanceOf(Select::class, $operation->statement);
        $parsed = $operation->statement->where;
        self::assertInstanceOf(Between::class, $parsed);

        $test = (new IntegerRangeLowering())->lower($parsed, new SourceBinding($operation->binding(), null), true);
        self::assertInstanceOf(NullLiteral::class, $test->subject);
        self::assertFalse($test->symmetric);
        self::assertFalse($test->negated);
    }
}

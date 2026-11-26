<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCaseArm;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;

#[CoversClass(IntegerCase::class)]
#[Small]
final class IntegerCaseTest extends TestCase
{
    public function testKeepsOwnedOrderedArmsAndTypedFallback(): void
    {
        $first = new IntegerCaseArm(new BooleanLiteral(true), new IntegerLiteral('1'));
        $second = new IntegerCaseArm(new BooleanLiteral(true), new IntegerLiteral('2'));
        $source = [$first, $second];
        $fallback = new NullLiteral();
        $value = new IntegerCase($source, $fallback);
        $source[0] = $second;

        self::assertSame([$first, $second], $value->arms);
        self::assertSame($fallback, $value->otherwise);
    }

    public function testAdmitsThirtyTwoDecisions(): void
    {
        $arm = new IntegerCaseArm(new BooleanLiteral(false), new NullLiteral());
        $arms = array_fill(0, 32, $arm);

        self::assertSame($arms, (new IntegerCase($arms))->arms);
    }
}

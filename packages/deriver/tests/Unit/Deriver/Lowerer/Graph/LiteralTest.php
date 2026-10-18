<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Lowerer\Graph;

use Deriver\Deriver\Lowerer\Graph\Literal;
use Deriver\Deriver\Lowerer\Graph\LiteralKind;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class LiteralTest extends TestCase
{
    public function testConstantPreservesTheScalarToken(): void
    {
        self::assertSame(LiteralKind::Constant, Literal::constant(1)->kind);
        self::assertSame(1, Literal::constant(1)->literal);
    }
    public function testArrayPreservesElementKeys(): void
    {
        $item = Literal::constant('x');
        self::assertSame(['name' => $item], Literal::array(['name' => $item])->operands);
    }
    public function testOpaqueRetainsTheInitializerReason(): void
    {
        self::assertSame(LiteralKind::Unresolved, Literal::opaque('UNEVALUATED_INITIALIZER')->kind);
    }
    public function testNativeReadsOnlyScalarTokens(): void
    {
        self::assertNull(Literal::constant(null)->native());
        self::assertSame(4, Literal::constant(4)->native());
    }
}

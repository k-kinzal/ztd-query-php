<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;

#[CoversClass(Concatenation::class)]
#[Small]
final class ConcatenationTest extends TestCase
{
    public function testKeepsTheOrderedTypedInputs(): void
    {
        $left = new NullLiteral();
        $right = new TextLiteral('tail');
        $value = new Concatenation($left, $right);

        self::assertSame($left, $value->left);
        self::assertSame($right, $value->right);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Scalar::class)]
#[Medium]
final class ScalarTest extends TestCase
{
    public function testDeriveScalarRecordsAFactForEveryOperand(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("SELECT 1 + 'a'");
        $expression = $operation->field(0)->expression;

        self::assertInstanceOf(Binary::class, $expression);
        self::assertTrue($operation->facts->covers($expression));
        $left = $operation->facts->scalar($expression->left)->type;
        $right = $operation->facts->scalar($expression->right)->type;
        self::assertInstanceOf(Known::class, $left);
        self::assertInstanceOf(Known::class, $right);
        self::assertSame('INTEGER', $left->descriptor->name());
        self::assertSame('TEXT', $right->descriptor->name());
        self::assertSame(Nullability::NotNull, $operation->facts->scalar($expression)->nullability);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Scalar::class)]
#[Medium]
final class ScalarTest extends TestCase
{
    public function testDeriveScalarRecordsAFactForEveryOperand(): void
    {
        $operation = (new Inspection())->inspect("SELECT 1 + 'a'");
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

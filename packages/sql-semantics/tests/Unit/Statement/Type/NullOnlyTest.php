<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(NullOnly::class)]
#[Medium]
final class NullOnlyTest extends TestCase
{
    public function testABareNullAndAnExpressionThatIsAlwaysNullHaveNoOtherType(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT NULL, NULL + 1');

        self::assertInstanceOf(NullOnly::class, $operation->field(0)->type);
        self::assertInstanceOf(NullOnly::class, $operation->field(1)->type);
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
    }
}

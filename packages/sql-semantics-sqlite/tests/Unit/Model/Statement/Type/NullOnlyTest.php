<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\NullOnly;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(NullOnly::class)]
#[Medium]
final class NullOnlyTest extends TestCase
{
    public function testABareNullAndAnExpressionThatIsAlwaysNullHaveNoOtherType(): void
    {
        $operation = (new Semantics())->inspect('SELECT NULL, NULL + 1');

        self::assertInstanceOf(NullOnly::class, $operation->field(0)->type);
        self::assertInstanceOf(NullOnly::class, $operation->field(1)->type);
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
    }
}

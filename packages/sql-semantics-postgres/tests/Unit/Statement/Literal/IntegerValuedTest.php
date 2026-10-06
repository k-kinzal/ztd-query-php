<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerValued;

#[CoversClass(IntegerValued::class)]
#[Small]
final class IntegerValuedTest extends TestCase
{
    public function testIntegerValueIsOfferedByAConstant(): void
    {
        self::assertContains(IntegerValued::class, class_implements(Constant::class));
    }
}

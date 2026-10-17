<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit;

#[CoversClass(TypeLimit::class)]
#[Small]
final class TypeLimitTest extends TestCase
{
    public function testFromNamesTheSizeConstraint(): void
    {
        self::assertSame(TypeLimit::ScaleExceedsPrecision, TypeLimit::from('scale-exceeds-precision'));
        self::assertSame(TypeLimit::Width, TypeLimit::from('width'));
    }
}

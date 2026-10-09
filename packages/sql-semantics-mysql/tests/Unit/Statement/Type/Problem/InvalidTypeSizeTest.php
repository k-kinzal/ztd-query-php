<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit;

#[CoversClass(InvalidTypeSize::class)]
#[Small]
final class InvalidTypeSizeTest extends TestCase
{
    public function testMessageRetainsTheBoundAndTheColumnName(): void
    {
        self::assertSame("Display width out of range for column 'a' (max = 255)", (new InvalidTypeSize(TypeLimit::Width, 'a', 256, 255))->message());
        self::assertSame("Too-big precision 66 specified for ''. Maximum is 65.", (new InvalidTypeSize(TypeLimit::Precision, '', 66, 65))->message());
        self::assertSame("Too big scale 31 specified for column 'b'. Maximum is 30.", (new InvalidTypeSize(TypeLimit::Scale, 'b', 31, 30))->message());
        self::assertSame("For float(M,D), double(M,D) or decimal(M,D), M must be >= D (column 'c').", (new InvalidTypeSize(TypeLimit::ScaleExceedsPrecision, 'c'))->message());
        self::assertSame("Invalid size for column ''.", (new InvalidTypeSize(TypeLimit::Empty, ''))->message());
        self::assertSame("Incorrect column specifier for column 'd'", (new InvalidTypeSize(TypeLimit::Specifier, 'd'))->message());
    }
}

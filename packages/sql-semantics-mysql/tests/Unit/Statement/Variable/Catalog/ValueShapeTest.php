<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\ValueShape;

#[CoversClass(ValueShape::class)]
#[Small]
final class ValueShapeTest extends TestCase
{
    public function testCasesNameTheValuesAVariableTakes(): void
    {
        self::assertSame(['Boolean', 'Integer', 'Unsigned', 'Double', 'Text'], array_map(static fn (ValueShape $shape): string => $shape->name, ValueShape::cases()));
    }
}

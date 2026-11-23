<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;

#[CoversClass(CastDestination::class)]
#[Small]
final class CastDestinationTest extends TestCase
{
    public function testEachDestinationHasItsCatalogName(): void
    {
        self::assertSame(['int4', 'int8', 'bool', 'text'], array_column(CastDestination::cases(), 'value'));
    }
}

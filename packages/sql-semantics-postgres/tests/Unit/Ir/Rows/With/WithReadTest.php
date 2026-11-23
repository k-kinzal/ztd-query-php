<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithRead;

#[CoversClass(WithRead::class)]
#[Small]
final class WithReadTest extends TestCase
{
    public function testKeepsTheDefinitionCoordinateAndColumns(): void
    {
        $field = new LocalResultColumn(1, new Identifier('n'));
        $read = new WithRead(2, [$field, $field]);

        self::assertSame(2, $read->definition);
        self::assertSame([$field, $field], $read->columns);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalResultColumn;

#[CoversClass(LocalResultColumn::class)]
#[Small]
final class LocalResultColumnTest extends TestCase
{
    public function testAddressesItsDefinitionSlot(): void
    {
        $column = new LocalResultColumn(2, new Identifier('a'));

        self::assertSame(2, $column->slot);
        self::assertSame('a', $column->name->value);
    }
}

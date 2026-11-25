<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyKeyColumn;

#[CoversClass(ForeignKeyKeyColumn::class)]
#[Small]
final class ForeignKeyKeyColumnTest extends TestCase
{
    public function testColumnRetainsThePhysicalField(): void
    {
        $column = new ForeignKeyKeyColumn(new Identifier('Owner_Id'), true);

        self::assertSame('Owner_Id', $column->field->value);
        self::assertTrue($column->descending);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn
 */
final class TableColumnTest extends TestCase
{
    public function testToStringEmitsTheDeclaredTypeAndNullability(): void
    {
        $name = new Identifier('x"');
        $untyped = new TableColumn($name);
        $typed = new TableColumn($name, DeclaredDatatypeName::Real, true);

        self::assertSame('"x"""', $untyped->toString());
        self::assertSame('"x""" REAL NOT NULL', $typed->toString());
    }

    public function testToStringRetainsAnExplicitNullDefault(): void
    {
        $column = new TableColumn(new Identifier('c'), DeclaredDatatypeName::Blob, false, new NullLiteral());

        self::assertSame('"c" BLOB DEFAULT NULL', $column->toString());
    }
}

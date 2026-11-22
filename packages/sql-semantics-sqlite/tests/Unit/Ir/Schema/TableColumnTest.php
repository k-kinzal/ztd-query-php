<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
use SqlSemantics\Platform\Sqlite\Ir\Schema\NotNullConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn
 */
final class TableColumnTest extends TestCase
{
    public function testPreservesTheDeclaredTypeAndNullability(): void
    {
        $name = new Identifier('x"');
        $untyped = new TableColumn($name);
        $typed = new TableColumn($name, DeclaredDatatypeName::Real, NotNullConflict::Abort);

        self::assertSame($name, $untyped->name);
        self::assertSame(DeclaredDatatypeName::Untyped, $untyped->type);
        self::assertSame(NotNullConflict::Abort, $typed->notNull);
        self::assertSame(DeclaredDatatypeName::Real, $typed->type);
    }
}

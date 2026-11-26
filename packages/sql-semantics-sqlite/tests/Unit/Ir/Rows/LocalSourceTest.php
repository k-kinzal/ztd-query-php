<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource
 */
final class LocalSourceTest extends TestCase
{
    public function testShapeKeepsItsDefinitionAndNamesWhenTheCallerChangesTheList(): void
    {
        $values = new ValuesQuery([new ValueRow([new IntegerLiteral('1'), new IntegerLiteral('9')])]);
        $first = new Identifier('a');
        $names = [&$first, new Identifier('b')];
        $source = new LocalSource($values, $names);
        $first = new Identifier('new');

        self::assertSame($values, $source->query);
        self::assertSame('a', $source->columns[0]->value);
        self::assertSame('b', $source->columns[1]->value);
    }
}

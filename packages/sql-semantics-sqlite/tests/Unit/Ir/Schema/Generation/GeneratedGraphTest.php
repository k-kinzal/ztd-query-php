<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Generation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\ColumnGeneration;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\GeneratedGraph;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\Storage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\GeneratedGraph
 */
final class GeneratedGraphTest extends TestCase
{
    public function testCheckAllowsAForwardReferenceThroughAnotherGeneratedColumn(): void
    {
        $columns = [
            new TableColumn(new Identifier('ordinary')),
            new TableColumn(new Identifier('a'), generated: new ColumnGeneration(Storage::Virtual, new ColumnReference(2))),
            new TableColumn(new Identifier('b'), generated: new ColumnGeneration(Storage::Stored, new ColumnReference(0))),
        ];

        (new GeneratedGraph())->check($columns, null);

        self::assertSame('a', $columns[1]->name->value);
    }
}

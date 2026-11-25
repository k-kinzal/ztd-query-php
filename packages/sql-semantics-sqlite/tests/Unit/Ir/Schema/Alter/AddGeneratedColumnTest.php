<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddGeneratedColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\ColumnGeneration;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\Storage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;

#[CoversClass(AddGeneratedColumn::class)]
#[Small]
final class AddGeneratedColumnTest extends TestCase
{
    public function testToStringUsesTheExactPriorSlotAndStorageClass(): void
    {
        $column = new TableColumn(
            new Identifier('computed'),
            DeclaredDatatypeName::Integer,
            generated: new ColumnGeneration(Storage::Stored, new ColumnReference(1)),
        );
        $peer = new Identifier('peer"slot');
        $heading = [new Identifier('id'), &$peer];
        $value = new AddGeneratedColumn(
            new Identifier('temp'),
            new Identifier('entries'),
            $heading,
            true,
            $column,
        );

        $peer = new Identifier('changed');

        self::assertSame(2, $value->slot);
        self::assertSame([1 => 'peer"slot'], array_map(static fn (Identifier $name): string => $name->value, $value->bindings));
        self::assertSame('ALTER TABLE "temp"."entries" ADD COLUMN "computed" INTEGER GENERATED ALWAYS AS ("peer""slot") STORED', $value->toString());
    }

    public function testProfileIdIdentifiesThePinnedSqliteProfile(): void
    {
        $column = new TableColumn(
            new Identifier('computed'),
            generated: new ColumnGeneration(Storage::Virtual, new ColumnReference(0)),
        );
        $value = new AddGeneratedColumn(new Identifier('main'), new Identifier('entries'), [new Identifier('id')], false, $column);

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $value->profileId());
    }
}

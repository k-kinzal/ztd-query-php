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
        $value = $this->value();

        self::assertSame('ALTER TABLE "temp"."entries" ADD COLUMN "computed" INTEGER GENERATED ALWAYS AS ("peer""slot") STORED', $value->toString());
    }

    public function testProfileIdIdentifiesThePinnedSqliteProfile(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $this->value()->profileId());
    }

    private function value(): AddGeneratedColumn
    {
        $column = new TableColumn(
            new Identifier('computed'),
            DeclaredDatatypeName::Integer,
            generated: new ColumnGeneration(Storage::Stored, new ColumnReference(1)),
        );

        return new AddGeneratedColumn(
            new Identifier('temp'),
            new Identifier('entries'),
            [new Identifier('id'), new Identifier('peer"slot')],
            true,
            $column,
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\TextCollation;
use SqlSemantics\Platform\MySql\Ir\Schema\CharacterStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\CreateColumnStorageLowering;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateColumnStorageLowering::class)]
#[Medium]
final class CreateColumnStorageLoweringTest extends TestCase
{
    public function testLowerKeepsTheExplicitNativeLengthAndCollation(): void
    {
        $query = (new Inspection())->inspect('CREATE TABLE app.a (code VARCHAR(63) COLLATE utf8mb4_bin)');
        self::assertInstanceOf(CreateTable::class, $query->statement);
        $column = $query->statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);

        $storage = (new CreateColumnStorageLowering())->lower($column, null);

        self::assertInstanceOf(CharacterStorage::class, $storage);
        self::assertSame(63, $storage->length);
        self::assertSame(TextCollation::Utf8mb4Bin, $storage->collation);
    }
}

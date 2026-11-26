<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Schema\CharacterStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\TableColumn;

#[CoversClass(TableColumn::class)]
#[Small]
final class TableColumnTest extends TestCase
{
    public function testToStringPreservesSignedDefaultAndNullability(): void
    {
        $column = new TableColumn(new Identifier('n'), IntegerStorage::Small, false, new IntegerLiteral('-7'));

        self::assertSame('`n` SMALLINT NOT NULL DEFAULT -7', $column->toString());
    }

    public function testToStringRetainsDefaultPaddingAndCharacterRole(): void
    {
        $column = new TableColumn(new Identifier('label'), new CharacterStorage(1), true, new TextLiteral('é '));

        self::assertSame("`label` VARCHAR(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL DEFAULT 'é '", $column->toString());
    }

    public function testStorageBytesChecksTheNativeLengthPrefixBoundary(): void
    {
        $name = new Identifier('label');

        self::assertSame(253, (new TableColumn($name, new CharacterStorage(63)))->storageBytes());
        self::assertSame(258, (new TableColumn($name, new CharacterStorage(64)))->storageBytes());
        self::assertSame(8, (new TableColumn($name, IntegerStorage::Wide))->storageBytes());
    }
}

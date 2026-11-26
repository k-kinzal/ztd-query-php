<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CharacterStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableColumn;

#[CoversClass(TableColumn::class)]
#[Small]
final class TableColumnTest extends TestCase
{
    public function testToStringPreservesSignedDefaultAndNullability(): void
    {
        $column = new TableColumn(new Identifier('n'), IntegerStorage::Small, false, new IntegerLiteral('-7'));

        self::assertSame('"n" SMALLINT NOT NULL DEFAULT -7', $column->toString());
    }

    public function testToStringRetainsTextCollationAndQuotedDefault(): void
    {
        $column = new TableColumn(
            new Identifier('code'),
            CharacterStorage::Text,
            false,
            new TextLiteral("a'π"),
            TextCollation::C,
        );

        self::assertSame(
            "\"code\" pg_catalog.text COLLATE pg_catalog.\"C\" NOT NULL DEFAULT CAST('a''π' AS pg_catalog.text)",
            $column->toString(),
        );
    }
}

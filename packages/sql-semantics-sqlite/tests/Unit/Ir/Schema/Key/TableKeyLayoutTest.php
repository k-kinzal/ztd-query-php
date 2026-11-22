<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyTerm;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\TableKeyLayout;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\UniqueKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;

#[CoversClass(TableKeyLayout::class)]
#[Small]
final class TableKeyLayoutTest extends TestCase
{
    public function testCheckOwnsTheOrderedKeysAndFindsThePrimary(): void
    {
        $primary = new PrimaryKey([new KeyTerm(0)], true, KeyConflict::Fail);
        $unique = new UniqueKey([new KeyTerm(0)], false, KeyConflict::Ignore);
        $column = new TableColumn(new Identifier('id'), DeclaredDatatypeName::Integer);
        $layout = (new TableKeyLayout())->check([&$primary, $unique], [$column], false);
        $previous = $primary;
        $primary = new PrimaryKey([new KeyTerm(0)]);

        self::assertSame([[$previous, $unique], $previous], $layout);
    }

    public function testTermsUsesTheOwningColumnCollation(): void
    {
        $column = new TableColumn(new Identifier('code'), collation: Collation::NoCase);
        $key = new UniqueKey([new KeyTerm(0, collation: Collation::NoCase)], true);

        (new TableKeyLayout())->terms($key, [$column]);

        self::assertSame(Collation::NoCase, $key->terms[0]->collation);
    }

    public function testRowidAliasUsesExactIntegerTypeAndPlacement(): void
    {
        $column = new TableColumn(new Identifier('id'), DeclaredDatatypeName::Integer);
        $inlineDescending = new PrimaryKey([new KeyTerm(0, true)], true);
        $tableDescending = new PrimaryKey([new KeyTerm(0, true)]);
        $check = new TableKeyLayout();

        self::assertFalse($check->rowidAlias($inlineDescending, [$column], false));
        self::assertTrue($check->rowidAlias($tableDescending, [$column], false));
        self::assertFalse($check->rowidAlias($tableDescending, [$column], true));
    }

    public function testIndexIdentityIgnoresDirection(): void
    {
        $first = new UniqueKey([new KeyTerm(0, true, Collation::NoCase)]);
        $other = new PrimaryKey([new KeyTerm(0, false, Collation::NoCase)]);
        $check = new TableKeyLayout();

        self::assertSame($check->indexIdentity($first), $check->indexIdentity($other));
    }
}

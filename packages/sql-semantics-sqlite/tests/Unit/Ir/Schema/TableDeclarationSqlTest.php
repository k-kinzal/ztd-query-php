<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyTerm;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\UniqueKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\CheckConstraint;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableDeclarationSql;

#[CoversClass(TableDeclarationSql::class)]
#[Small]
final class TableDeclarationSqlTest extends TestCase
{
    public function testDeclarationsPlaceOrderedKeysAndCheckedExpressions(): void
    {
        $column = new TableColumn(new Identifier('code'), collation: Collation::NoCase);
        $inline = new UniqueKey([new KeyTerm(0, collation: Collation::NoCase)], inline: true);
        $table = new UniqueKey([new KeyTerm(0)], conflict: KeyConflict::Ignore);
        $check = new CheckConstraint(new Identifier('a"b'), new ColumnReference(0), 0);

        self::assertSame(
            ['"code" COLLATE NOCASE UNIQUE CONSTRAINT "a""b" CHECK ("code")', 'UNIQUE ("code" COLLATE BINARY) ON CONFLICT IGNORE'],
            (new TableDeclarationSql())->declarations([$column], [$inline, $table], [$check])
        );
    }
}

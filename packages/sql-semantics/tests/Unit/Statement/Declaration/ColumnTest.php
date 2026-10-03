<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Column::class)]
#[Medium]
final class ColumnTest extends TestCase
{
    public function testADeclaredColumnIsNullableUnlessSaidOtherwise(): void
    {
        $column = new Column(new Name('a'), Storage::Integer);

        self::assertSame('a', $column->name->value);
        self::assertSame('INTEGER', $column->type->name());
        self::assertSame(Nullability::Nullable, $column->nullability);
    }

    public function testAResolvedReferenceReachesTheDeclarationObjectItself(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')->declarations()[0];

        $query = $semantics->analyze('SELECT b, a FROM t', [$table]);

        self::assertSame($table->columns[1], $query->field('b')->column());
        self::assertSame($table->columns[0], $query->field('a')->column());
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
    }
}

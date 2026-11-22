<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Testing\Sqlite\Inspection;

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
        self::assertFalse($column->generated);
    }

    public function testAGeneratedColumnSaysSo(): void
    {
        self::assertTrue((new Column(new Name('a'), Storage::Integer, Nullability::Nullable, true))->generated);
    }

    public function testAResolvedReferenceReachesTheDeclarationObjectItself(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')->declarations()[0];

        $query = $semantics->inspect('SELECT b, a FROM t', [$table]);

        self::assertSame($table->columns[1], $query->field('b')->column());
        self::assertSame($table->columns[0], $query->field('a')->column());
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
    }
}

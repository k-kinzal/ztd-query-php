<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Cast::class)]
#[Medium]
final class CastTest extends TestCase
{
    public function testDeriveScalarGivesTheStorageClassOfTheAffinity(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT CAST(a AS VARCHAR(10)), CAST(1 AS INT), CAST(1 AS BLOB), CAST(1 AS REAL), CAST(b AS DOUBLE PRECISION) FROM t', [$create]);

        self::assertEquals(new Known(Storage::Text), $operation->field(0)->type);
        self::assertEquals(new Known(Storage::Integer), $operation->field(1)->type);
        self::assertEquals(new Known(Storage::Blob), $operation->field(2)->type);
        self::assertEquals(new Known(Storage::Real), $operation->field(3)->type);
        self::assertEquals(new Known(Storage::Real), $operation->field(4)->type);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarGivesIntegerOrRealForNumericAffinity(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("SELECT CAST('1.5' AS NUMERIC), CAST('1.5' AS)", []);

        self::assertEquals(new Choice([Storage::Integer, Storage::Real]), $operation->field(0)->type);
        self::assertEquals(new Choice([Storage::Integer, Storage::Real]), $operation->field(1)->type);
        self::assertSame(Nullability::NotNull, $operation->field(1)->nullability);
    }

    public function testDeriveScalarKeepsTheNullFactOfTheOperand(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT CAST(a AS TEXT), CAST(b AS INT), CAST(? AS TEXT) FROM t', [$create]);

        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
        self::assertEquals(new Known(Storage::Text), $operation->field(2)->type);
        self::assertSame(Nullability::Dependent, $operation->field(2)->nullability);
    }

    public function testDeriveScalarLeavesANullOperandNull(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT CAST(NULL AS INT)', []);

        self::assertInstanceOf(NullOnly::class, $operation->field(0)->type);
        self::assertSame(Nullability::Nullable, $operation->field(0)->nullability);
    }

    public function testDeriveScalarReadsTheTypeNameAndItsArguments(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT CAST(a AS VARCHAR(10)), CAST(1 AS) FROM t', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[1]);
        self::assertInstanceOf(Cast::class, $statement->columns[0]->expression);
        self::assertInstanceOf(Cast::class, $statement->columns[1]->expression);
        $cast = $statement->columns[0]->expression;
        self::assertInstanceOf(ColumnUse::class, $cast->operand);
        self::assertNotNull($cast->target);
        self::assertSame('VARCHAR', $cast->target->text());
        self::assertSame(Affinity::Text, $cast->target->affinity());
        self::assertCount(1, $cast->target->arguments);
        $resolution = $operation->facts->scalar($cast->operand)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($create->declarations()[0]->columns[0], $resolution->slot->column);
        self::assertNull($statement->columns[1]->expression->target);
    }

    public function testRenderWritesTheConversionWithAndWithoutATypeName(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('select cast(a as varchar(10)), cast(1 as), cast(b as "int") from t');

        self::assertSame('SELECT CAST(a AS varchar(10)), CAST(1 AS), CAST(b AS "int") FROM t', $operation->toString());
    }
}

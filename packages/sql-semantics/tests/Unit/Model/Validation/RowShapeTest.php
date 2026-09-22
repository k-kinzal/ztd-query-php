<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\RowShape;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RowShape::class)]
final class RowShapeTest extends TestCase
{
    public function testRowsRejectsUnequalWidthsBeforeSerialization(): void
    {
        $one = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        RowShape::rows([[$one], [$one, $one]], Dialect::PostgreSql);
    }

    public function testRowsRejectsMixedDialectValues(): void
    {
        $this->expectException(InvalidStructure::class);
        RowShape::rows([[Expression::literal(1, Dialect::Sqlite)]], Dialect::MySql);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::Sqlite])]
    public function testRowsRejectsEmptyRowsOutsideMysql(Dialect $dialect): void
    {
        $this->expectException(InvalidStructure::class);
        RowShape::rows([[]], $dialect);
    }

    public function testWidthDefersAnUnresolvedWildcard(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertNull(RowShape::width($binder->bind('SELECT * FROM missing', strict: false)));
        self::assertSame(2, RowShape::width($binder->bind('SELECT 1, 2')));
    }

    public function testInsertionRejectsAnExplicitPositionWithoutAnInput(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER,b INTEGER)')))->bind('INSERT INTO t(a,b) VALUES(1,2)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $query);
        $this->expectException(InvalidStructure::class);
        RowShape::insertion($query->insertion, 1, Dialect::PostgreSql);
    }
}

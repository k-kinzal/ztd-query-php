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
#[\PHPUnit\Framework\Attributes\Medium]
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
        $open = $binder->bind('SELECT * FROM missing', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $open);
        self::assertNull(RowShape::width($open));
        $closed = $binder->bind('SELECT 1, 2');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $closed);
        self::assertSame(2, RowShape::width($closed));
    }

    public function testInsertionRejectsAnExplicitPositionWithoutAnInput(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER,b INTEGER)')))->bind('INSERT INTO t(a,b) VALUES(1,2)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $query);
        $this->expectException(InvalidStructure::class);
        RowShape::insertion($query->insertion, 1, Dialect::PostgreSql);
    }

    public function testWritesAcceptsDefaultSlotsInRowsOfEqualWidth(): void
    {
        $one = Expression::literal(1, Dialect::PostgreSql);
        RowShape::writes([[$one, \SqlSemantics\Model\Write\DefaultSource::Column], [$one, $one]], Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('Write rows must have equal widths.');
        RowShape::writes([[$one], [$one, $one]], Dialect::PostgreSql);
    }

    public function testWritesRejectsAnEmptyRowList(): void
    {
        $this->expectException(InvalidStructure::class);
        RowShape::writes([], Dialect::PostgreSql);
    }

    public function testWritesRejectsASqliteDefaultSlot(): void
    {
        $this->expectException(InvalidStructure::class);
        RowShape::writes([[\SqlSemantics\Model\Write\DefaultSource::Column]], Dialect::Sqlite);
    }

    public function testRowsAcceptsEqualWidthsOfTheStatementDialect(): void
    {
        $one = Expression::literal(1, Dialect::PostgreSql);
        $this->expectNotToPerformAssertions();
        RowShape::rows([[$one, $one], [$one, $one]], Dialect::PostgreSql);
        RowShape::rows([[], []], Dialect::MySql);
    }

    public function testRowsRejectsAnEmptyRowList(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('Row input requires a nonempty ordered list.');
        RowShape::rows([], Dialect::PostgreSql);
    }

    public function testRowsRejectsAnUnorderedRowList(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('Row input requires a nonempty ordered list.');
        RowShape::rows([1 => [Expression::literal(1, Dialect::PostgreSql)]], Dialect::PostgreSql);
    }

    public function testRowsRejectsARowThatIsNotAList(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('Each row must contain an ordered expression list.');
        RowShape::rows([Expression::literal(1, Dialect::PostgreSql)], Dialect::PostgreSql);
    }

    public function testRowsRejectsAValueThatIsNotAnExpression(): void
    {
        $this->expectException(InvalidStructure::class);
        RowShape::rows([[1]], Dialect::PostgreSql);
    }

    public function testRowsRejectsAWiderLaterRow(): void
    {
        $one = Expression::literal(1, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('Rows must have equal widths and use a valid row arity.');
        RowShape::rows([[$one], []], Dialect::MySql);
    }

    public function testInsertionSkipsUnknownWidthsEmptyMySqlRowsAndUnresolvedTargets(): void
    {
        $postgres = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER,b INTEGER)'));
        $explicit = $postgres->bind('INSERT INTO t(a,b) VALUES(1,2)');
        $unresolved = $postgres->bind('INSERT INTO missing VALUES (1, 2, 3)', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $explicit);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $unresolved);
        RowShape::insertion($explicit->insertion, null, Dialect::PostgreSql);
        RowShape::insertion($explicit->insertion, 0, Dialect::MySql);
        RowShape::insertion($explicit->insertion, 2, Dialect::PostgreSql);
        RowShape::insertion($unresolved->insertion, 5, Dialect::PostgreSql);
        self::assertCount(2, $explicit->insertion->columns);
    }

    public function testInsertionRejectsAnEmptyRowOutsideMySql(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER,b INTEGER)')))->bind('INSERT INTO t(a,b) VALUES(1,2)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $query);
        $this->expectException(InvalidStructure::class);
        RowShape::insertion($query->insertion, 0, Dialect::PostgreSql);
    }
}

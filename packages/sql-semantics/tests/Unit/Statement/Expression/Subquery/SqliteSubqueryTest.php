<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction\Expression\ColumnUse;
use SqlSemantics\Statement\Construction\Query\FieldDefinition;
use SqlSemantics\Statement\Construction\Query\ProjectionDefinition;
use SqlSemantics\Statement\Construction\Query\SelectDefinition;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\Subquery\SqliteSubquery;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query\Row;
use SqlSemantics\Statement\Query\Rows;
use SqlSemantics\Statement\Query\ScopedSelect;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(SqliteSubquery::class)]
#[Small]
final class SqliteSubqueryTest extends TestCase
{
    public function testWidthAndOutputsRetainResultPositions(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $value = new NullConstant();
        $source = new SqliteSubquery($outer, new Rows(new Row($inner, $value, $value)));
        self::assertSame(2, $source->width());
        self::assertSame([$value, $value], $source->outputs());
    }

    public function testOutputsPreservesTheFirstValuesRow(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $first = new NullConstant();
        $second = new NullConstant();
        $source = new SqliteSubquery($outer, new Rows(new Row($inner, $first), new Row($inner, $second)));
        self::assertSame([$first], $source->outputs());
    }

    public function testOperandsIncludesPredicatesEvenForExistenceTests(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $value = new NullConstant();
        $query = new ScopedSelect($outer, new SelectDefinition(new ProjectionDefinition(new FieldDefinition($value, new Name('n'))), where: new ColumnUse(new Name('missing'))));
        $source = new SqliteSubquery($outer, $query);
        $predicate = $query->where;
        self::assertSame([$value, $predicate], $source->operands());
    }

    public function testInvalidReportsUnequalRowWidthsWithoutDiscardingTheQuery(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $value = new NullConstant();
        $rows = new Rows(new Row($inner, $value), new Row($inner, $value, $value));
        $source = new SqliteSubquery($outer, $rows);
        self::assertSame($rows, $source->query);
        self::assertSame(Invalid::InconsistentRowWidth, $source->invalid());
    }

    public function testReferencesRetainsOriginalNestedLookupSites(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $reference = new ColumnReference($inner, new Name('missing'));
        $source = new SqliteSubquery($outer, new Rows(new Row($inner, $reference)));
        self::assertSame([$reference], $source->references());
        self::assertSame(Invalid::MissingColumn, $source->invalid());
    }

}

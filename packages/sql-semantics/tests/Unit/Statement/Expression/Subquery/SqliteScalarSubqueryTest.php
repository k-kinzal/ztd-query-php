<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction\Expression\ColumnUse;
use SqlSemantics\Statement\Construction\Query\FieldDefinition;
use SqlSemantics\Statement\Construction\Query\ProjectionDefinition;
use SqlSemantics\Statement\Construction\Query\RowDefinition;
use SqlSemantics\Statement\Construction\Query\RowsDefinition;
use SqlSemantics\Statement\Construction\Query\SelectDefinition;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Expression\Subquery\SqliteScalarSubquery;
use SqlSemantics\Statement\Expression\Subquery\SqliteSubquery;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Query\ScopedRows;
use SqlSemantics\Statement\Query\ScopedSelect;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteChoiceDomain;

#[CoversClass(SqliteScalarSubquery::class)]
#[Small]
final class SqliteScalarSubqueryTest extends TestCase
{
    public function testReferencesRetainsQueryDependencies(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $source = new SqliteSubquery($outer, new ScopedRows($outer, new RowsDefinition(new RowDefinition(new ColumnUse(new Name('missing'))))));
        $reference = $source->references()[0];
        self::assertSame($source->query->scope, $reference->scope);
        $expression = new SqliteScalarSubquery($source);
        self::assertSame([$reference], $expression->references());
        self::assertSame(Invalid::MissingColumn, $expression->type());
    }

    public function testTypeIncludesNullWhenTheQueryCouldReturnNoRows(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $value = new SqliteInteger(new UnsignedInteger('1'));
        $source = new SqliteSubquery($outer, new ScopedSelect($outer, new SelectDefinition(new ProjectionDefinition(new FieldDefinition($value, new Name('n'))))));
        $domain = (new SqliteScalarSubquery($source))->type();
        self::assertInstanceOf(SqliteChoiceDomain::class, $domain);
        self::assertEquals([$value->type(), NullDomain::Null], $domain->alternatives);
    }

    public function testTypeKeepsInvalidScalarWidthExplicit(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $value = new NullConstant();
        $source = new SqliteSubquery($outer, new ScopedRows($outer, new RowsDefinition(new RowDefinition($value, $value))));
        self::assertSame(Invalid::ScalarSubqueryWidth, (new SqliteScalarSubquery($source))->type());
    }

    public function testNullabilityRespectsTheQueryOperation(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $source = new SqliteSubquery($outer, new ScopedRows($outer, new RowsDefinition(new RowDefinition(new NullConstant()))));
        self::assertSame(Nullability::AlwaysNull, (new SqliteScalarSubquery($source))->nullability());
    }

    public function testToStringReconstructsTheConcreteOperation(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $source = new SqliteSubquery($outer, new ScopedRows($outer, new RowsDefinition(new RowDefinition(new NullConstant()))));
        self::assertSame('(VALUES (NULL))', (new SqliteScalarSubquery($source))->toString());
    }

}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\Subquery\SqliteExists;
use SqlSemantics\Statement\Expression\Subquery\SqliteSubquery;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query\Row;
use SqlSemantics\Statement\Query\Rows;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(SqliteExists::class)]
#[Small]
final class SqliteExistsTest extends TestCase
{
    public function testReferencesRetainsQueryDependencies(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $reference = new ColumnReference($inner, new Name('missing'));
        $source = new SqliteSubquery($outer, new Rows(new Row($inner, $reference)));
        $expression = new SqliteExists($source);
        self::assertSame([$reference], $expression->references());
        self::assertSame(Invalid::MissingColumn, $expression->type());
    }

    public function testTypeDoesNotRequireAScalarProjection(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $value = new NullConstant();
        $source = new SqliteSubquery($outer, new Rows(new Row($inner, $value, $value)));
        self::assertEquals(new TypeDescriptor(Builtin::Integer), (new SqliteExists($source))->type());
    }

    public function testNullabilityRespectsTheQueryOperation(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $source = new SqliteSubquery($outer, new Rows(new Row($inner, new NullConstant())));
        self::assertSame(Nullability::NotNull, (new SqliteExists($source))->nullability());
    }

    public function testToStringReconstructsTheConcreteOperation(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $source = new SqliteSubquery($outer, new Rows(new Row($inner, new NullConstant())));
        self::assertSame('EXISTS (VALUES (NULL))', (new SqliteExists($source))->toString());
    }

}

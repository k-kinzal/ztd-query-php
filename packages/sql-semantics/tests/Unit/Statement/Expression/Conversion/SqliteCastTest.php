<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Conversion;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\Conversion\SqliteCast;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\SqliteText;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteCastTarget;
use SqlSemantics\Statement\Type\SqliteNumericDomain;
use SqlSemantics\Statement\Type\Unresolved;

#[CoversClass(SqliteCast::class)]
#[Small]
final class SqliteCastTest extends TestCase
{
    #[TestWith(['INTEGER', Builtin::Integer])]
    #[TestWith(['REAL', Builtin::Real])]
    #[TestWith(['BLOB', Builtin::Blob])]
    #[TestWith(['VARCHAR(4)', Builtin::Text])]
    public function testTypeReflectsTheConversionDomain(string $name, Builtin $expected): void
    {
        $cast = new SqliteCast(new SqliteText(new StringLiteral('12.5')), new SqliteCastTarget($name));
        $type = $cast->type();
        self::assertInstanceOf(TypeDescriptor::class, $type);
        self::assertSame($expected, $type->name);
    }

    public function testTypeKeepsNumericAlternativesAndInvalidReferencesDistinct(): void
    {
        $cast = new SqliteCast(new SqliteText(new StringLiteral('12.5')), new SqliteCastTarget(''));
        self::assertSame(SqliteNumericDomain::IntegerOrReal, $cast->type());
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $invalid = new SqliteCast(new ColumnReference($scope, new Name('absent')), new SqliteCastTarget('TEXT'));
        self::assertSame(Invalid::MissingColumn, $invalid->type());
        self::assertSame(NullDomain::Null, (new SqliteCast(new NullConstant(), new SqliteCastTarget('TEXT')))->type());
    }

    public function testNullabilityPropagatesNullWithoutEvaluatingTheOperand(): void
    {
        self::assertSame(Nullability::AlwaysNull, (new SqliteCast(new NullConstant(), new SqliteCastTarget('INTEGER')))->nullability());
        self::assertSame(Nullability::NotNull, (new SqliteCast(new SqliteText(new StringLiteral('')), new SqliteCastTarget('INTEGER')))->nullability());
    }

    public function testReferencesRetainsTheOriginalLookupEvenWhenItsTypeIsUnknown(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $scope = new Scope($catalog, new TableReference($catalog, new QualifiedName(new Name('bar'))));
        $column = new ColumnReference($scope, new Name('foo'));
        $cast = new SqliteCast($column, new SqliteCastTarget('TEXT'));
        self::assertSame(Unresolved::MissingDeclaration, $column->type());
        self::assertSame([$column], $cast->references());
        $type = $cast->type();
        self::assertInstanceOf(TypeDescriptor::class, $type);
        self::assertSame(Builtin::Text, $type->name);
    }

    public function testToStringPreservesConversionRatherThanColumnStorageAffinity(): void
    {
        $cast = new SqliteCast(new SqliteText(new StringLiteral('12.5suffix')), new SqliteCastTarget('INTEGER'));
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $cast->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(12, $result->fetchColumn());
    }
}

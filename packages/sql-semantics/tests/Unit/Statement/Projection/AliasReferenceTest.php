<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Projection;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Projection\AliasReference;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Type\NullDomain;

#[CoversClass(AliasReference::class)]
#[Small]
final class AliasReferenceTest extends TestCase
{
    public function testTypeComesFromTheExactTargetField(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $field = new Field(new NullConstant(), new Name('answer'));
        $reference = new AliasReference(new Fields($scope, $field), $field, new Name('answer'));
        self::assertSame($field, $reference->field);
        self::assertSame(NullDomain::Null, $reference->type());
    }

    public function testNullabilityDoesNotTreatAnAliasAsAnAbsentInputColumn(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $field = new Field(new NullConstant(), new Name('answer'));
        self::assertSame(Nullability::AlwaysNull, (new AliasReference(new Fields($scope, $field), $field, new Name('answer')))->nullability());
    }

    public function testReferencesPreservesTheActualUnderlyingColumnLookup(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $scope = new Scope($catalog, new TableReference($catalog, new QualifiedName(new Name('bar'))));
        $column = new ColumnReference($scope, new Name('foo'));
        $field = new Field($column, new Name('answer'));
        self::assertSame([$column], (new AliasReference(new Fields($scope, $field), $field, new Name('answer')))->references());
    }

    public function testToStringRetainsTheAliasUseWithoutSubstitutingItsExpression(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $field = new Field(new SqliteInteger(new UnsignedInteger('1')), new Name('answer'));
        $reference = new AliasReference(new Fields($scope, $field), $field, new Name('answer'));
        self::assertSame('answer', $reference->toString());
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $field->toString() . ' WHERE ' . $reference->toString() . ' = 1');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(1, $result->fetchColumn());
    }

    public function testRejectsAnAliasTargetThatLookupWouldNotChoose(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $first = new Field(new SqliteInteger(new UnsignedInteger('1')), new Name('answer'));
        $other = new Field(new SqliteInteger(new UnsignedInteger('2')), new Name('answer'));
        $this->expectException(\SqlSemantics\Statement\Validation\Failure\InvalidConstruction::class);
        new AliasReference(new Fields($scope, $first, $other), $other, new Name('answer'));
    }
}

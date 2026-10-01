<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;

#[CoversClass(ScalarExpression::class)]
#[Small]
final class ScalarExpressionTest extends TestCase
{
    public function testTypeIsPartOfTheScalarContract(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        $expression = new ColumnReference($scope, new Name('foo'));
        self::assertSame($declared->type, $expression->type());
    }

    public function testNullabilityIsDerivedForScalarReferences(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        self::assertSame(Nullability::NotNull, (new ColumnReference($scope, new Name('foo')))->nullability());
    }

    public function testReferencesExposesTheActualColumnLookup(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        $expression = new ColumnReference($scope, new Name('foo'));
        self::assertSame([$expression], $expression->references());
    }

    public function testToStringRetainsTheExpressionMeaning(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        self::assertSame('foo', (new ColumnReference($scope, new Name('foo')))->toString());
    }
}

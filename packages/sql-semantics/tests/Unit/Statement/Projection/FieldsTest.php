<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Projection;

use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(Fields::class)]
#[Small]
final class FieldsTest extends TestCase
{
    public function testAddFieldRetainsTheScopeAndDeclarationIdentities(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        $field = new Field(new ColumnReference($scope, new Name('foo')));
        $fields = new Fields($scope, $field);
        $added = new Field(new ColumnReference($scope, new Name('foo')), new Name('copy'));
        $changed = $fields->addField($added);
        self::assertSame([$field], $fields->items);
        self::assertSame([$field, $added], $changed->items);
        self::assertSame($scope, $changed->scope);
        self::assertSame($declared->type, $changed->field('copy')->expression->type());
        self::assertTrue((new SemanticGraph())->containsOnlyValues($changed));
    }

    public function testFieldReturnsTheUniqueOutputPosition(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        $field = new Field(new ColumnReference($scope, new Name('foo')));
        self::assertSame($field, (new Fields($scope, $field))->field('foo'));
    }

    public function testFieldDoesNotChooseBetweenDuplicateLabels(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        $field = new Field(new ColumnReference($scope, new Name('foo')));
        $fields = new Fields($scope, $field, $field);
        $this->expectException(OutOfBoundsException::class);
        $fields->field('foo');
    }

    public function testFieldReportsAnAbsentLabel(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        $fields = new Fields($scope);
        $this->expectException(OutOfBoundsException::class);
        $fields->field('foo');
    }

    public function testToStringPreservesTheOrderOfFields(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        $expression = new ColumnReference($scope, new Name('foo'));
        $fields = new Fields($scope, new Field($expression, new Name('first')), new Field($expression, new Name('second')));
        self::assertSame('foo AS first, foo AS second', $fields->toString());
    }

    public function testMatchingAliasesKeepsAllMatchingFieldsInProjectionOrder(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main')), columnNames: Comparison::AsciiInsensitive));
        $first = new Field(new NullConstant(), new Name('answer'));
        $second = new Field(new NullConstant(), new Name('ANSWER'));
        $third = new Field(new NullConstant(), new Name('unrelated'));
        $fields = new Fields($scope, $first, $second, $third);
        self::assertSame([$first, $second], $fields->matchingAliases('Answer'));
    }
}

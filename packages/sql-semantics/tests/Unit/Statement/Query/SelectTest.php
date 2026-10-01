<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(Select::class)]
#[Medium]
final class SelectTest extends TestCase
{
    public function testFieldsRetainsTheActualProjectionAndDeclaration(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);
        $field = new Field(new ColumnReference($scope, new Name('foo')));
        $fields = new Fields($scope, $field);
        $query = new Select($fields);
        self::assertSame($fields, $query->fields());
        self::assertInstanceOf(ColumnReference::class, $field->expression);
        self::assertInstanceOf(ResolvedColumn::class, $field->expression->resolution);
        self::assertSame($table, $field->expression->resolution->table);
        self::assertSame($column, $field->expression->resolution->column);
    }

    public function testFieldReturnsTheNamedOutputWithItsDeclaredType(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);
        $field = new Field(new ColumnReference($scope, new Name('foo')));
        $fields = new Fields($scope, $field);
        $query = new Select($fields);
        self::assertSame($field, $query->field('foo'));
        self::assertSame($column->type, $query->field('foo')->expression->type());
    }

    public function testWithFieldsAddsAnOwnedFieldWithoutChangingTheOriginal(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);
        $field = new Field(new ColumnReference($scope, new Name('foo')));
        $fields = new Fields($scope, $field);
        $query = new Select($fields);
        $copy = new Field(new ColumnReference($scope, new Name('foo')), new Name('copy'));
        $changed = $query->withFields($query->fields()->addField($copy));
        self::assertSame([$field], $query->fields()->items);
        self::assertSame([$field, $copy], $changed->fields()->items);
        self::assertSame($query->scope, $changed->scope);
        self::assertInstanceOf(ColumnReference::class, $copy->expression);
        self::assertInstanceOf(ResolvedColumn::class, $copy->expression->resolution);
        self::assertSame($column, $copy->expression->resolution->column);
        self::assertTrue((new SemanticGraph())->isSemanticOperation($changed));
    }

    public function testWithWherePreservesTheProjectionAndChangesTheRowPredicate(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);
        $field = new Field(new ColumnReference($scope, new Name('foo')));
        $fields = new Fields($scope, $field);
        $query = new Select($fields);
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE bar(foo INTEGER NOT NULL)');
        $db->exec('INSERT INTO bar VALUES (0), (1)');
        $changed = $query->withWhere(new ColumnReference($scope, new Name('foo')));
        $originalRows = $db->query($query->toString());
        $changedRows = $db->query($changed->toString());
        self::assertInstanceOf(PDOStatement::class, $originalRows);
        self::assertInstanceOf(PDOStatement::class, $changedRows);
        self::assertSame([0, 1], $originalRows->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame([1], $changedRows->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame($fields, $changed->fields());
        self::assertNull($query->where);
    }

    public function testToStringUsesOnlyTheSemanticProjectionAndInputs(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);
        $field = new Field(new ColumnReference($scope, new Name('foo')));
        $fields = new Fields($scope, $field);
        $query = new Select($fields);
        self::assertSame('SELECT foo FROM bar', $query->toString());
        self::assertSame($relation, $query->scope->tables[0]);
        self::assertTrue((new SemanticGraph())->isSemanticOperation($query));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction\Expression\ColumnUse;
use SqlSemantics\Statement\Construction\Query\FieldDefinition;
use SqlSemantics\Statement\Construction\Query\Inputs;
use SqlSemantics\Statement\Construction\Query\NamedInput;
use SqlSemantics\Statement\Construction\Query\ProjectionDefinition;
use SqlSemantics\Statement\Construction\Query\SelectDefinition;
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
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\SemanticGraph;
use UnexpectedValueException;

#[CoversClass(Select::class)]
#[Medium]
final class SelectTest extends TestCase
{
    public function testContextRetainsItsExactDeclarationSnapshot(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $query = new Select($catalog, new SelectDefinition(new ProjectionDefinition(new FieldDefinition(new NullConstant()))));
        self::assertSame($catalog, $query->context());
    }

    public function testProfileComesFromTheFixedContext(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $query = new Select($catalog, new SelectDefinition(new ProjectionDefinition(new FieldDefinition(new NullConstant()))));
        self::assertSame($catalog->profile, $query->profile());
    }

    public function testLookupFieldRetainsAnAbsentOutputResult(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $query = new Select($scope->catalog, new SelectDefinition(new ProjectionDefinition(new FieldDefinition(new NullConstant(), new Name('answer')))));
        self::assertSame(\SqlSemantics\Statement\Projection\AbsentField::Value, $query->lookupField('missing'));
        $found = $query->lookupField('answer');
        self::assertInstanceOf(\SqlSemantics\Statement\Projection\UniqueField::class, $found);
        self::assertSame($query->field('answer'), $found->field);
    }

    public function testSingleNamedInputRequiresOneActualOccurrence(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $input = new TableReference($catalog, new QualifiedName(new Name('bar')));
        $query = new Select($catalog, new SelectDefinition(new ProjectionDefinition(new FieldDefinition(new NullConstant())), new Inputs(new NamedInput($input->name))));
        self::assertNotSame($input, $query->singleNamedInput());
        self::assertSame($input->name, $query->singleNamedInput()->name);
        $multiple = new Select($catalog, new SelectDefinition(new ProjectionDefinition(new FieldDefinition(new NullConstant())), new Inputs(new NamedInput($input->name), new NamedInput($input->name))));
        $this->expectException(UnexpectedValueException::class);
        $multiple->singleNamedInput();
    }

    public function testFieldsRetainsTheActualProjectionAndDeclaration(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);
        $field = new Field(new ColumnReference($scope, new Name('foo')));
        $fields = new Fields($scope, $field);
        $query = new Select($catalog, new SelectDefinition(new ProjectionDefinition(new FieldDefinition(new ColumnUse(new Name('foo')))), new Inputs(new NamedInput($table->name))));
        $field = $query->field('foo');
        self::assertSame([$field], $query->fields()->items);
        self::assertNotSame($scope, $query->scope);
        self::assertInstanceOf(ColumnReference::class, $field->expression);
        self::assertInstanceOf(ResolvedColumn::class, $field->expression->resolution);
        self::assertSame($table, $field->expression->resolution->table);
        self::assertSame($column, $field->expression->resolution->column);
    }

    public function testFieldReturnsTheNamedOutputWithItsDeclaredType(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);
        $field = new Field(new ColumnReference($scope, new Name('foo')));
        $fields = new Fields($scope, $field);
        $query = new Select($catalog, new SelectDefinition(new ProjectionDefinition(new FieldDefinition(new ColumnUse(new Name('foo')))), new Inputs(new NamedInput($table->name))));
        $field = $query->field('foo');
        self::assertSame($field, $query->field('foo'));
        self::assertSame($column->type, $query->field('foo')->expression->type());
    }

    public function testToStringUsesOnlyTheSemanticProjectionAndInputs(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);
        $field = new Field(new ColumnReference($scope, new Name('foo')));
        $fields = new Fields($scope, $field);
        $query = new Select($catalog, new SelectDefinition(new ProjectionDefinition(new FieldDefinition(new ColumnUse(new Name('foo')))), new Inputs(new NamedInput($table->name))));
        $field = $query->field('foo');
        self::assertSame('SELECT foo FROM bar', $query->toString());
        self::assertNotSame($relation, $query->scope->tables[0]);
        self::assertSame([$table], $query->scope->tables[0]->declarations);
        self::assertTrue((new SemanticGraph())->isSemanticOperation($query));
    }

}

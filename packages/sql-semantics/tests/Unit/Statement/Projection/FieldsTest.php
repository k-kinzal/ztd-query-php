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

#[CoversClass(Fields::class)]
#[Small]
final class FieldsTest extends TestCase
{
    public function testFieldReturnsTheUniqueOutputPosition(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        $field = new Field(new ColumnReference($scope, new Name('foo')));
        self::assertSame($field, (new Fields($scope, $field))->field('foo'));
    }

    public function testFieldDoesNotChooseBetweenDuplicateLabels(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table);
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
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        $fields = new Fields($scope);
        $this->expectException(OutOfBoundsException::class);
        $fields->field('foo');
    }

    public function testToStringPreservesTheOrderOfFields(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table);
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
    public function testLookupFieldDistinguishesAllCompetingPositions(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main')), columnNames: Comparison::AsciiInsensitive));
        $first = new Field(new NullConstant(), new Name('answer'));
        $other = new Field(new NullConstant(), new Name('other'));
        $last = new Field(new NullConstant(), new Name('ANSWER'));
        $fields = new Fields($scope, $first, $other, $last);
        $ambiguous = $fields->lookupField('Answer');
        self::assertInstanceOf(\SqlSemantics\Statement\Projection\AmbiguousFields::class, $ambiguous);
        self::assertSame([0 => $first, 2 => $last], $ambiguous->matches);
        $unique = $fields->lookupField('other');
        self::assertInstanceOf(\SqlSemantics\Statement\Projection\UniqueField::class, $unique);
        self::assertSame(1, $unique->position);
        self::assertSame($other, $unique->field);
        self::assertSame(\SqlSemantics\Statement\Projection\AbsentField::Value, $fields->lookupField('missing'));
    }

    public function testCountAndIterationRetainDuplicatesWithoutExposingTheContainer(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $field = new Field(new NullConstant(), new Name('n'));
        $fields = new Fields($scope, $field, $field);
        self::assertCount(2, $fields);
        self::assertSame([$field, $field], iterator_to_array($fields));
        self::assertSame($field, $fields->at(1));
        $iterator = $fields->getIterator();
        $iterator->offsetUnset(0);
        self::assertCount(2, $fields);
        self::assertSame($field, $fields->at(0));
    }

    public function testAtRejectsAnAbsentPosition(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $this->expectException(OutOfBoundsException::class);
        (new Fields($scope))->at(-1);
    }

    public function testGetIteratorUsesTheOriginalPositionKeys(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $first = new Field(new NullConstant(), new Name('first'));
        $last = new Field(new NullConstant(), new Name('last'));
        self::assertSame([0 => $first, 1 => $last], (new Fields($scope, $first, $last))->getIterator()->getArrayCopy());
    }

}

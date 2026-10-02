<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Projection;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;

#[CoversClass(Field::class)]
#[Small]
final class FieldTest extends TestCase
{
    public function testToStringPreservesTheDeclaredOutputNameUnderInsensitiveLookup(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE bar(foo INTEGER NOT NULL)');
        $db->exec('INSERT INTO bar VALUES (11)');
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, true, null, null, new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table);
        $scope = new Scope($catalog, new TableReference($catalog, $table->name));
        $field = new Field(new ColumnReference($scope, new Name('FOO')));
        $result = $db->query('SELECT ' . $field->toString() . ' FROM bar');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame([$field->name->value => 11], $result->fetch(PDO::FETCH_ASSOC));
        self::assertSame($column->name, $field->name);
    }

    public function testToStringRetainsTheAliasWithoutChangingTheReferencedColumn(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        $expression = new ColumnReference($scope, new Name('foo'));
        $field = new Field($expression, new Name('result'));
        self::assertSame('foo AS result', $field->toString());
        self::assertSame('result', $field->name->value);
        self::assertSame($expression, $field->expression);
        self::assertSame($declared->type, $field->expression->type());
    }

    public function testToStringAndLabelUseTheColumnNameWithoutAnAlias(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        $field = new Field(new ColumnReference($scope, new Name('foo')));
        self::assertSame('foo', $field->name->value);
        self::assertSame('foo', $field->toString());
    }
}

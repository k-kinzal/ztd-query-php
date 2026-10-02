<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\RenameColumn;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;

#[CoversClass(RenameColumn::class)]
#[Medium]
final class RenameColumnTest extends TestCase
{
    public function testToStringExecutesTheRepresentedOperation(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE users(id INTEGER, name TEXT)');
        $db->exec("INSERT INTO users VALUES (1, 'Alice')");
        $column = new Column(new Name('id'), new TypeDescriptor(Builtin::Integer));
        $table = new Table(new QualifiedName(new Name('users')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table);
        $relation = new TableReference($catalog, $table->name);
        $operation = new RenameColumn($relation, new Name('id'), new Name('key'));
        $db->exec($operation->toString());
        $result = $db->query('SELECT key FROM users');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(1, $result->fetchColumn());
        self::assertInstanceOf(ResolvedColumn::class, $operation->column->resolution);
        self::assertSame($column, $operation->column->resolution->column);
        self::assertSame('id', $table->columns[0]->name->value);
    }
}

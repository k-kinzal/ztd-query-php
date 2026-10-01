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
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\RenameTable;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;

#[CoversClass(RenameTable::class)]
#[Medium]
final class RenameTableTest extends TestCase
{
    public function testToStringExecutesTheRepresentedOperation(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE users(id INTEGER, name TEXT)');
        $db->exec("INSERT INTO users VALUES (1, 'Alice')");
        $column = new Column(new Name('id'), new TypeDescriptor(Builtin::Integer));
        $table = new Table(new QualifiedName(new Name('users')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $operation = new RenameTable($relation, new Name('people'));
        $db->exec($operation->toString());
        $result = $db->query('SELECT name FROM people');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame('Alice', $result->fetchColumn());
        self::assertSame([$table], $catalog->matchingTables(new QualifiedName(new Name('users'))));
        self::assertSame([], $catalog->matchingTables(new QualifiedName(new Name('people'))));
        self::assertSame($table, $operation->table->declarations[0]);
    }
}

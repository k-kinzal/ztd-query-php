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
use SqlSemantics\Statement\Schema\DropTrigger;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;

#[CoversClass(DropTrigger::class)]
#[Medium]
final class DropTriggerTest extends TestCase
{
    public function testToStringExecutesTheRepresentedOperation(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE users(id INTEGER, name TEXT)');
        $db->exec("INSERT INTO users VALUES (1, 'Alice')");
        $column = new Column(new Name('id'), new TypeDescriptor(Builtin::Integer));
        $table = new Table(new QualifiedName(new Name('users')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $db->exec('CREATE TABLE log(id INTEGER)');
        $db->exec('CREATE TRIGGER user_log AFTER INSERT ON users BEGIN INSERT INTO log VALUES(new.id); END');
        $db->exec("INSERT INTO users VALUES (2, 'Bob')");
        $db->exec((new DropTrigger(new QualifiedName(new Name('user_log'))))->toString());
        $db->exec("INSERT INTO users VALUES (3, 'Carol')");
        $result = $db->query('SELECT id FROM log');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame([2], $result->fetchAll(PDO::FETCH_COLUMN));
    }
}

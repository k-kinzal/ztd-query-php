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
use SqlSemantics\Statement\Schema\DropColumn;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;

#[CoversClass(DropColumn::class)]
#[Medium]
final class DropColumnTest extends TestCase
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
        $operation = new DropColumn($relation, new Name('id'));
        $db->exec($operation->toString());
        $result = $db->query('SELECT * FROM users');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(['name' => 'Alice'], $result->fetch(PDO::FETCH_ASSOC));
        self::assertInstanceOf(ResolvedColumn::class, $operation->column->resolution);
        self::assertSame($column, $operation->column->resolution->column);
        self::assertSame([$column], $table->columns);
    }
}

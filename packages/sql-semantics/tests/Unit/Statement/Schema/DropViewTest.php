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
use SqlSemantics\Statement\Schema\DropView;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;

#[CoversClass(DropView::class)]
#[Medium]
final class DropViewTest extends TestCase
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
        $db->exec('CREATE VIEW user_ids AS SELECT id FROM users');
        $operation = new DropView(new TableReference($catalog, new QualifiedName(new Name('user_ids'))));
        $db->exec($operation->toString());
        $views = $db->query("SELECT count(*) FROM sqlite_schema WHERE type = 'view'");
        $rows = $db->query('SELECT count(*) FROM users');
        self::assertInstanceOf(PDOStatement::class, $views);
        self::assertInstanceOf(PDOStatement::class, $rows);
        self::assertSame(0, $views->fetchColumn());
        self::assertSame(1, $rows->fetchColumn());
    }
}

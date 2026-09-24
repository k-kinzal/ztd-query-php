<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\TableRenaming;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql\Table\RenameTablesStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameTablesStatement::class)]
#[Medium]
final class RenameTablesStatementTest extends TestCase
{
    public function testRenamingsRetainRequestOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE a(id INT)', 'CREATE TABLE b(id INT)')))->bind('RENAME TABLE a TO c, b TO d');
        self::assertInstanceOf(RenameTablesStatement::class, $statement);
        self::assertSame(StatementKind::Rename, $statement->kind);
        self::assertSame([['c'], ['d']], array_map(static fn (TableRenaming $renaming): array => $renaming->newName->parts, $statement->renamings));
    }

    public function testWithOriginPreservesThePairs(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE a(id INT)')))->bind('RENAME TABLE a TO c');
        self::assertInstanceOf(RenameTablesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->renamings, $copy->renamings);
    }

    public function testWithRenamingsReplacesThePairsAndKeepsTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE a(id INT)')))->bind('RENAME TABLE a TO c');
        self::assertInstanceOf(RenameTablesStatement::class, $statement);
        $changed = $statement->withRenamings([new TableRenaming($statement->renamings[0]->table, new QualifiedName(['archive', 'a']))]);
        self::assertSame('RENAME TABLE `a` TO `archive`.`a`', $changed->toString());
        self::assertSame(['c'], $statement->renamings[0]->newName->parts);
    }

    public function testAnotherDialectIsRejected(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE a(id INT)')))->bind('RENAME TABLE a TO c');
        self::assertInstanceOf(RenameTablesStatement::class, $statement);
        $sqlite = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new RenameTablesStatement($sqlite->origin, $statement->renamings);
    }
}

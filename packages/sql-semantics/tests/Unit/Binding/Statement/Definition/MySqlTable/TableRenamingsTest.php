<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\TableRenamings;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\Table\RenameTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableRenamings::class)]
#[Medium]
final class TableRenamingsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindRetainsEveryPairAcrossGrammarReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE a(id INT)', 'CREATE TABLE b(id INT)'));
        $statement = $binder->bind('RENAME TABLES a TO app.c, b TO d');
        self::assertInstanceOf(RenameTablesStatement::class, $statement);
        self::assertSame(['a', 'b'], array_map(static fn ($renaming): string => $renaming->table->declaration->name, $statement->renamings));
        self::assertSame([['app', 'c'], ['d']], array_map(static fn ($renaming): array => $renaming->newName->parts, $statement->renamings));
        self::assertSame('RENAME TABLE `a` TO `app`.`c`, `b` TO `d`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testBindDiagnosesAnUnknownSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('RENAME TABLE missing TO t', strict: false);
        self::assertInstanceOf(RenameTablesStatement::class, $statement);
        self::assertFalse($statement->renamings[0]->table->declaration->resolved);
        self::assertSame('unknown-table', $statement->diagnostics[0]->reason);
    }

    public function testSourceFollowsATableRenamedByAnEarlierPair(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE a(id INT, n INT)')))->bind('RENAME TABLE a TO tmp, tmp TO b');
        self::assertInstanceOf(RenameTablesStatement::class, $statement);
        self::assertSame([], $statement->diagnostics);
        self::assertSame('tmp', $statement->renamings[1]->table->declaration->name);
        self::assertSame(['id', 'n'], array_map(static fn ($column): string => $column->name, $statement->renamings[1]->table->declaration->columns));
    }

    public function testSameReadsAnOmittedDatabaseAsTheDefault(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE a(id INT)'));
        $statement = $binder->bind('RENAME TABLE a TO x.tmp, x.tmp TO b');
        self::assertInstanceOf(RenameTablesStatement::class, $statement);
        self::assertSame('tmp', $statement->renamings[1]->table->declaration->name);
        self::assertSame('x', $statement->renamings[1]->table->declaration->schema);
    }
}

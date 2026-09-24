<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\AlterTables;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterTables::class)]
#[Medium]
final class AlterTablesTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindKeepsATrailingPartitioningChangeLast(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind('ALTER TABLE t ADD n INT, COMMENT = \'x\' REMOVE PARTITIONING');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame(TableCommand::RemovePartitioning, $statement->alterations[2]);
        self::assertSame('ALTER TABLE `t` ADD COLUMN `n` integer, COMMENT = \'x\' REMOVE PARTITIONING', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindDiagnosesInstantOnMySql57(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AlterAlgorithm->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ALGORITHM = INSTANT');
    }

    public function testBindDiagnosesAnUnknownTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER TABLE missing ADD c INT', strict: false);
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertFalse($statement->table->declaration->resolved);
    }

    public function testCommandsFindTheMySql56StandaloneCommand(): void
    {
        $statement = (new DialectParser(Dialect::MySql, 'mysql-5.6.51'))->parse('ALTER TABLE t DROP PARTITION p');
        self::assertSame(['alter_commands'], array_map(static fn ($command): string => $command->name, AlterTables::commands($statement)));
    }

    public function testCommandBindsAPartitionClause(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY HASH (id)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        self::assertNull($alteration->partitioning->partitionCount);
    }

    public function testScopeResolvesColumnsTheStatementAdds(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD c INT, ADD CHECK (c > 0)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame([], $statement->diagnostics);
    }
}

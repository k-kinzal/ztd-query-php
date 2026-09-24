<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\AlterItems;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\IndexLock;
use SqlSemantics\Model\Definition\MySqlTable\Column\DropColumn;
use SqlSemantics\Model\Definition\MySqlTable\Column\RenameColumn;
use SqlSemantics\Model\Definition\MySqlTable\Key\DropKey;
use SqlSemantics\Model\Definition\MySqlTable\Key\RenameIndex;
use SqlSemantics\Model\Definition\MySqlTable\Table\RenameTable;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand;
use SqlSemantics\Model\Definition\MySqlTable\TableAlgorithm;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterItems::class)]
#[Medium]
final class AlterItemsTest extends TestCase
{
    public function testBindSkipsMySql56AlgorithmItems(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build('CREATE TABLE t(id INT)')))->bind('ALTER TABLE t ALGORITHM = INPLACE, LOCK = SHARED, ENABLE KEYS');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame([TableCommand::EnableKeys], $statement->alterations);
        self::assertSame([TableAlgorithm::Inplace, IndexLock::Shared], [$statement->algorithm, $statement->lock]);
    }

    public function testBindReadsUpgradePartitioningOnMySql57(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t(id INT)')))->bind('ALTER TABLE t UPGRADE PARTITIONING');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame([TableCommand::UpgradePartitioning], $statement->alterations);
    }

    #[TestWith(['ALTER TABLE t DROP INDEX k', DropKey::class])]
    #[TestWith(['ALTER TABLE t DROP COLUMN k', DropColumn::class])]
    #[TestWith(['ALTER TABLE t RENAME COLUMN k TO j', RenameColumn::class])]
    #[TestWith(['ALTER TABLE t RENAME KEY k TO j', RenameIndex::class])]
    #[TestWith(['ALTER TABLE t RENAME TO j', RenameTable::class])]
    public function testAddressedSelectsTheAddressedObject(string $sql, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind($sql);
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame($class, $statement->alterations[0]::class);
    }

    #[TestWith(['alter table t add column n int', \SqlSemantics\Model\Definition\MySqlTable\Column\AddColumn::class, 'ALTER TABLE `t` ADD COLUMN `n` integer'])]
    #[TestWith(['alter table t add index jx (id)', \SqlSemantics\Model\Definition\MySqlTable\Key\AddIndex::class, 'ALTER TABLE `t` ADD INDEX `jx`(`id`)'])]
    #[TestWith(['alter table t add constraint u unique (id)', \SqlSemantics\Model\Definition\MySqlTable\Key\AddConstraint::class, 'ALTER TABLE `t` ADD CONSTRAINT `u` UNIQUE(`id`)'])]
    #[TestWith(['alter table t change k j bigint', \SqlSemantics\Model\Definition\MySqlTable\Column\ChangeColumn::class, 'ALTER TABLE `t` CHANGE COLUMN `k` `j` bigint'])]
    #[TestWith(['alter table t modify k bigint', \SqlSemantics\Model\Definition\MySqlTable\Column\ModifyColumn::class, 'ALTER TABLE `t` MODIFY COLUMN `k` bigint'])]
    #[TestWith(['alter table t alter column k set default 1', \SqlSemantics\Model\Definition\MySqlTable\Column\ColumnDefaultAssignment::class, 'ALTER TABLE `t` ALTER COLUMN `k` SET DEFAULT 1'])]
    #[TestWith(['alter table t alter index ix invisible', \SqlSemantics\Model\Definition\MySqlTable\Key\SetIndexVisibility::class, 'ALTER TABLE `t` ALTER INDEX `ix` INVISIBLE'])]
    #[TestWith(['alter table t alter check c not enforced', \SqlSemantics\Model\Definition\MySqlTable\Key\SetConstraintEnforcement::class, 'ALTER TABLE `t` ALTER CHECK `c` NOT ENFORCED'])]
    #[TestWith(['alter table t convert to character set utf8mb4', \SqlSemantics\Model\Definition\MySqlTable\Table\ConvertCharacterSet::class, 'ALTER TABLE `t` CONVERT TO CHARACTER SET `utf8mb4`'])]
    #[TestWith(['alter table t order by id', \SqlSemantics\Model\Definition\MySqlTable\Table\OrderRows::class, 'ALTER TABLE `t` ORDER BY `id`'])]
    #[TestWith(['alter table t disable keys', TableCommand::class, 'ALTER TABLE `t` DISABLE KEYS'])]
    #[TestWith(['alter table t force', TableCommand::class, 'ALTER TABLE `t` FORCE'])]
    #[TestWith(['alter table t engine = InnoDB', \SqlSemantics\Model\Definition\MySqlTable\Table\ChangeTableOptions::class, 'ALTER TABLE `t` ENGINE `InnoDB`'])]
    #[TestWith(['alter table t rename index ix to jx', RenameIndex::class, 'ALTER TABLE `t` RENAME INDEX `ix` TO `jx`'])]
    #[TestWith(['alter table t drop primary key', TableCommand::class, 'ALTER TABLE `t` DROP PRIMARY KEY'])]
    #[TestWith(['alter table t drop foreign key f', DropKey::class, 'ALTER TABLE `t` DROP FOREIGN KEY `f`'])]
    #[TestWith(['alter table t drop check c', DropKey::class, 'ALTER TABLE `t` DROP CHECK `c`'])]
    #[TestWith(['alter table t rename column k to j', RenameColumn::class, 'ALTER TABLE `t` RENAME COLUMN `k` TO `j`'])]
    public function testBindClassifiesEveryLowercaseItem(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, k INT, CONSTRAINT c CHECK (id > 0), INDEX ix (k))')))->bind($sql);
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame([$class, $expected], [$statement->alterations[0]::class, $statement->toString()]);
    }
}

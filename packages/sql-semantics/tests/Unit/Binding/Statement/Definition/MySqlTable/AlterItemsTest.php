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
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\MySqlTable\TableChanges;

#[CoversClass(TableChanges::class)]
#[Medium]
final class TableChangesTest extends TestCase
{
    public function testWriteWritesTableAlterations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t RENAME AS u, CONVERT TO CHARACTER SET DEFAULT COLLATE x, ORDER BY n DESC, PACK_KEYS = DEFAULT');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame(['RENAME TO `u`', 'CONVERT TO CHARACTER SET DEFAULT COLLATE `x`', 'ORDER BY `n` DESC', 'PACK_KEYS = DEFAULT'], array_map(static fn ($alteration): string => TableChanges::write($alteration)?->toString() ?? '', $statement->alterations));
    }

    #[TestWith(['ALTER TABLE t ADD PARTITION NO_WRITE_TO_BINLOG PARTITIONS 2', 'ADD PARTITION NO_WRITE_TO_BINLOG PARTITIONS 2'])]
    #[TestWith(['ALTER TABLE t CHECK PARTITION ALL QUICK', 'CHECK PARTITION ALL QUICK'])]
    #[TestWith(['ALTER TABLE t REPAIR PARTITION p EXTENDED', 'REPAIR PARTITION `p` EXTENDED'])]
    #[TestWith(['ALTER TABLE t COALESCE PARTITION 2', 'COALESCE PARTITION 2'])]
    #[TestWith(['ALTER TABLE t IMPORT PARTITION p TABLESPACE', 'IMPORT PARTITION `p` TABLESPACE'])]
    #[TestWith(['ALTER TABLE t SECONDARY_UNLOAD PARTITION (p)', 'SECONDARY_UNLOAD PARTITION(`p`)'])]
    #[TestWith(['ALTER TABLE t EXCHANGE PARTITION p WITH TABLE u', 'EXCHANGE PARTITION `p` WITH TABLE `u`'])]
    public function testPartitionsWritesStandaloneCommands(string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)')))->bind($sql);
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame($expected, TableChanges::partitions($statement->alterations[0])?->toString());
    }

    public function testHeadWritesTheBinlogPolicy(): void
    {
        self::assertSame('OPTIMIZE PARTITION', TableChanges::head('OPTIMIZE', BinlogPolicy::Write)->toString());
    }

    public function testSelectionWritesNames(): void
    {
        self::assertSame('`a`, `b`', TableChanges::selection(new NamedPartitions(['a', 'b']))->toString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Storage\StorageOptionRule;

#[CoversClass(StorageOptionRule::class)]
#[Medium]
final class StorageOptionRuleTest extends TestCase
{
    public function testOptionsFlattensTheListsWithAndWithoutCommas(): void
    {
        self::assertSame("CREATE LOGFILE GROUP g ADD UNDOFILE 'u' INITIAL_SIZE 1, UNDO_BUFFER_SIZE 2 REDO_BUFFER_SIZE 3, NODEGROUP 4 WAIT COMMENT 'c'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("create logfile group g add undofile 'u' initial_size 1, undo_buffer_size 2 redo_buffer_size 3, nodegroup 4 wait comment 'c'")->toString());
    }

    public function testOptionLowersEveryOptionOf80(): void
    {
        self::assertSame("CREATE TABLESPACE ts ADD DATAFILE 'f' AUTOEXTEND_SIZE 4M, EXTENT_SIZE 1 NODEGROUP 2 FILE_BLOCK_SIZE 3 ENCRYPTION 'N' ENGINE_ATTRIBUTE '{}' NO_WAIT", (new Semantics(Dialect::MySql))->analyze("create tablespace ts add datafile 'f' autoextend_size 4M, extent_size 1 nodegroup 2 file_block_size 3 encryption 'N' engine_attribute '{}' no_wait")->toString());
    }

    public function testValueRetainsTheEqualsSign(): void
    {
        self::assertSame("CREATE TABLESPACE ts ADD DATAFILE 'f' COMMENT = 'c'", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("create tablespace ts add datafile 'f' comment = 'c'")->toString());
    }

    public function testEngineRetainsStorage(): void
    {
        self::assertSame('DROP TABLESPACE ts STORAGE ENGINE = innodb', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('drop tablespace ts storage engine = innodb')->toString());
    }
    /**
     * @return list<array{string}>
     */
    public static function providerRefusedOptions(): array
    {
        return [
            ['ALTER UNDO TABLESPACE u SET ACTIVE ENGINE a ENGINE b'],
            ['ALTER TABLESPACE ts ENGINE a ENGINE b'],
            ["CREATE LOGFILE GROUP g ADD UNDOFILE 'f' COMMENT 'a' COMMENT 'b'"],
            ["CREATE UNDO TABLESPACE u ADD DATAFILE 'u.ibu' ENGINE a ENGINE b"],
            ["ALTER LOGFILE GROUP g ADD UNDOFILE 'f' ENGINE a ENGINE b"],
            ['DROP TABLESPACE ts ENGINE a ENGINE b'],
            ["CREATE TABLESPACE ts ADD DATAFILE 'f' NODEGROUP 1 NODEGROUP 2"],
            ['DROP LOGFILE GROUP g ENGINE a ENGINE b'],
            ['DROP UNDO TABLESPACE u ENGINE a ENGINE b'],
        ];
    }

    #[DataProvider('providerConflicts')]
    public function testOptionsRefusesARepeatedOptionWhileReading(string $sql): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze($sql);
            self::fail('An option is written twice.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem::class, $failure->problem);
        }
    }
    public function testOptionsRetainsTheUnrepresentableSizeBoundary(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze("ALTER TABLESPACE ts ADD DATAFILE 'f' INITIAL_SIZE 1X");
        self::assertSame("ALTER TABLESPACE ts ADD DATAFILE 'f' INITIAL_SIZE 1X", $statement->toString());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem::class, $statement->diagnostics()[0]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
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
        self::assertSame("CREATE LOGFILE GROUP g ADD UNDOFILE 'u' INITIAL_SIZE 1 UNDO_BUFFER_SIZE 2 REDO_BUFFER_SIZE 3 NODEGROUP 4 WAIT COMMENT 'c'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("create logfile group g add undofile 'u' initial_size 1, undo_buffer_size 2 redo_buffer_size 3, nodegroup 4 wait comment 'c'")->toString());
    }

    public function testOptionLowersEveryOptionOf80(): void
    {
        self::assertSame("CREATE TABLESPACE ts ADD DATAFILE 'f' AUTOEXTEND_SIZE `4M` EXTENT_SIZE 1 NODEGROUP 2 FILE_BLOCK_SIZE 3 ENCRYPTION 'N' ENGINE_ATTRIBUTE '{}' NO_WAIT", (new Semantics(Dialect::MySql))->analyze("create tablespace ts add datafile 'f' autoextend_size 4M, extent_size 1 nodegroup 2 file_block_size 3 encryption 'N' engine_attribute '{}' no_wait")->toString());
    }

    public function testValueSkipsTheEqualsSign(): void
    {
        self::assertSame("CREATE TABLESPACE ts ADD DATAFILE 'f' COMMENT 'c'", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("create tablespace ts add datafile 'f' comment = 'c'")->toString());
    }

    public function testEngineSkipsStorage(): void
    {
        self::assertSame('DROP TABLESPACE ts ENGINE innodb', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('drop tablespace ts storage engine = innodb')->toString());
    }
}

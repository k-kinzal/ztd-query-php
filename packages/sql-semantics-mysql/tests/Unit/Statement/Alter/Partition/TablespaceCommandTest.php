<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\TablespaceCommand;

#[CoversClass(TablespaceCommand::class)]
#[Medium]
final class TablespaceCommandTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t IMPORT TABLESPACE')->facts->diagnostics);
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t DISCARD PARTITION p0, p1 TABLESPACE', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t DISCARD PARTITION p0, p1 TABLESPACE')->toString());
    }

    public function testRenderWritesThePartitions(): void
    {
        self::assertSame('ALTER TABLE t IMPORT PARTITION ALL TABLESPACE', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t IMPORT PARTITION ALL TABLESPACE')->toString());
    }
}

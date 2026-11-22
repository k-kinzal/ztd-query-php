<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\TablespaceCommand;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TablespaceCommand::class)]
#[Medium]
final class TablespaceCommandTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        self::assertSame([], (new Inspection())->inspect('ALTER TABLE t IMPORT TABLESPACE')->facts->diagnostics);
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t DISCARD PARTITION p0, p1 TABLESPACE', (new Inspection())->inspect('ALTER TABLE t DISCARD PARTITION p0, p1 TABLESPACE')->toString());
    }

    public function testRenderWritesThePartitions(): void
    {
        self::assertSame('ALTER TABLE t IMPORT PARTITION ALL TABLESPACE', (new Inspection('mysql-5.7.44'))->inspect('ALTER TABLE t IMPORT PARTITION ALL TABLESPACE')->toString());
    }
}

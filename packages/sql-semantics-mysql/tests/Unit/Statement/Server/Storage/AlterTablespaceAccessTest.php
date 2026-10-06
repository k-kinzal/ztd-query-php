<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess;

#[CoversClass(AlterTablespaceAccess::class)]
#[Medium]
final class AlterTablespaceAccessTest extends TestCase
{
    public function testRenderWritesTheAccessMode(): void
    {
        self::assertSame('ALTER TABLESPACE ts READ_WRITE', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('alter tablespace ts read_write')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLESPACE ts READ_ONLY');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}

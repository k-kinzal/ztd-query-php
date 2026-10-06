<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceDatafile;

#[CoversClass(AlterTablespaceDatafile::class)]
#[Medium]
final class AlterTablespaceDatafileTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame("ALTER TABLESPACE ts DROP DATAFILE 'f' NO_WAIT", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("alter tablespace ts drop datafile 'f' no_wait")->toString());
    }

    public function testDeriveStatementReportsRejectedOptions(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("ALTER TABLESPACE ts ADD DATAFILE 'f' INITIAL_SIZE 1X");

        self::assertInstanceOf(StorageProblem::class, $operation->facts->diagnostics[0]);
    }
}

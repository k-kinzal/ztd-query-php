<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace;

#[CoversClass(CreateTablespace::class)]
#[Medium]
final class CreateTablespaceTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame("CREATE TABLESPACE ts ADD DATAFILE 'f' USE LOGFILE GROUP g", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("create tablespace ts add datafile 'f' use logfile group g")->toString());
    }

    public function testDeriveStatementReportsRejectedOptions(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE TABLESPACE ts ADD DATAFILE 'f' NODEGROUP 1 NODEGROUP 2");

        self::assertInstanceOf(StorageProblem::class, $operation->facts->diagnostics[0]);
    }
}

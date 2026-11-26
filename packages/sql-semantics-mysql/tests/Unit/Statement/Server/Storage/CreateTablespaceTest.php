<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateTablespace::class)]
#[Medium]
final class CreateTablespaceTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame("CREATE TABLESPACE ts ADD DATAFILE 'f' USE LOGFILE GROUP g", (new Inspection('mysql-5.7.44'))->diagnose("create tablespace ts add datafile 'f' use logfile group g")->toString());
    }

    public function testDeriveStatementReportsRejectedOptions(): void
    {
        $operation = (new Inspection())->diagnose("CREATE TABLESPACE ts ADD DATAFILE 'f' NODEGROUP 1 NODEGROUP 2");

        self::assertInstanceOf(StorageProblem::class, $operation->facts->diagnostics[0]);
    }
}

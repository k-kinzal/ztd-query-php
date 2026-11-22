<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespace;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AlterTablespace::class)]
#[Medium]
final class AlterTablespaceTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('ALTER TABLESPACE ts INITIAL_SIZE `1M`', (new Inspection())->diagnose('alter tablespace ts initial_size 1M')->toString());
    }

    public function testDeriveStatementReportsRejectedOptions(): void
    {
        $operation = (new Inspection())->diagnose('ALTER TABLESPACE ts ENGINE a ENGINE b');

        self::assertInstanceOf(StorageProblem::class, $operation->facts->diagnostics[0]);
    }
}

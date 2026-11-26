<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateLogfileGroup;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateLogfileGroup::class)]
#[Medium]
final class CreateLogfileGroupTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame("CREATE LOGFILE GROUP g ADD UNDOFILE 'f' INITIAL_SIZE 1", (new Inspection())->diagnose("create logfile group g add undofile 'f' initial_size 1")->toString());
    }

    public function testDeriveStatementReportsRejectedOptions(): void
    {
        $operation = (new Inspection())->diagnose("CREATE LOGFILE GROUP g ADD UNDOFILE 'f' COMMENT 'a' COMMENT 'b'");

        self::assertInstanceOf(StorageProblem::class, $operation->facts->diagnostics[0]);
    }
}

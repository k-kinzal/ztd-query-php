<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterLogfileGroup;

#[CoversClass(AlterLogfileGroup::class)]
#[Medium]
final class AlterLogfileGroupTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame("ALTER LOGFILE GROUP g ADD UNDOFILE 'f' ENGINE `ndb`", (new Semantics(Dialect::MySql))->analyze("alter logfile group g add undofile 'f' engine ndb")->toString());
    }

    public function testDeriveStatementReportsRejectedOptions(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("ALTER LOGFILE GROUP g ADD UNDOFILE 'f' ENGINE a ENGINE b");

        self::assertInstanceOf(StorageProblem::class, $operation->facts->diagnostics[0]);
    }
}

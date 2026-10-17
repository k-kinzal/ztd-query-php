<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateLogfileGroup;

#[CoversClass(CreateLogfileGroup::class)]
#[Medium]
final class CreateLogfileGroupTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame("CREATE LOGFILE GROUP g ADD UNDOFILE 'f' INITIAL_SIZE 1", (new Semantics(Dialect::MySql))->analyze("create logfile group g add undofile 'f' initial_size 1")->toString());
    }

    public function testDeriveStatementAcceptsAConsistentOptionList(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE LOGFILE GROUP g ADD UNDOFILE 'f' COMMENT 'a'");

        self::assertSame([], $operation->facts->diagnostics);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\RenameTablespace;

#[CoversClass(RenameTablespace::class)]
#[Medium]
final class RenameTablespaceTest extends TestCase
{
    public function testRenderWritesBothNames(): void
    {
        self::assertSame('ALTER TABLESPACE a RENAME TO `b c`', (new Semantics(Dialect::MySql))->analyze('alter tablespace a rename to `b c`')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('ALTER TABLESPACE a RENAME TO b');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\IndexVisibility;

#[CoversClass(IndexVisibility::class)]
#[Medium]
final class IndexVisibilityTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        self::assertSame([], $semantics->analyze('ALTER TABLE t ALTER INDEX i VISIBLE', [$table])->facts->diagnostics);
    }

    public function testRenderWritesTheVisibility(): void
    {
        self::assertSame('ALTER TABLE t ALTER INDEX i VISIBLE', (new Semantics(Dialect::MySql))->analyze('alter table t alter index i visible')->toString());
    }
}

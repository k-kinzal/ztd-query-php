<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\IndexVisibility;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(IndexVisibility::class)]
#[Medium]
final class IndexVisibilityTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        self::assertSame([], $semantics->inspect('ALTER TABLE t ALTER INDEX i VISIBLE', [$table])->facts->diagnostics);
    }

    public function testRenderWritesTheVisibility(): void
    {
        self::assertSame('ALTER TABLE t ALTER INDEX i VISIBLE', (new Inspection())->inspect('alter table t alter index i visible')->toString());
    }
}

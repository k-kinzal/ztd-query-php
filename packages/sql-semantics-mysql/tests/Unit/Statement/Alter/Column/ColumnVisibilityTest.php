<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnVisibility;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ColumnVisibility::class)]
#[Medium]
final class ColumnVisibilityTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->inspect('ALTER TABLE t ALTER a SET VISIBLE', [$table]);

        self::assertSame([], $alter->facts->diagnostics);
    }

    public function testRenderWritesTheVisibility(): void
    {
        self::assertSame('ALTER TABLE t ALTER COLUMN a SET VISIBLE', (new Inspection())->inspect('ALTER TABLE t ALTER a SET VISIBLE')->toString());
    }
}

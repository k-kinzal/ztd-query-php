<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\Reorder;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Reorder::class)]
#[Medium]
final class ReorderTest extends TestCase
{
    public function testDeriveCommandResolvesTheColumnsInTheChangedTable(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->diagnose('ALTER TABLE t ORDER BY a, x', [$table]);

        self::assertSame('Column x does not exist.', $alter->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheDirections(): void
    {
        self::assertSame('ALTER TABLE t ORDER BY a ASC, b DESC', (new Inspection('mysql-5.6.51'))->diagnose('ALTER TABLE t ORDER BY a ASC, b DESC')->toString());
    }
}

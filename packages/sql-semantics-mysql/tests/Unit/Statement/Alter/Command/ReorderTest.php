<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\Reorder;

#[CoversClass(Reorder::class)]
#[Medium]
final class ReorderTest extends TestCase
{
    public function testDeriveCommandResolvesTheColumnsInTheChangedTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t ORDER BY a, x', [$table]);

        self::assertSame('Column x does not exist.', $alter->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheDirections(): void
    {
        self::assertSame('ALTER TABLE t ORDER BY a ASC, b DESC', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t ORDER BY a ASC, b DESC')->toString());
    }
}

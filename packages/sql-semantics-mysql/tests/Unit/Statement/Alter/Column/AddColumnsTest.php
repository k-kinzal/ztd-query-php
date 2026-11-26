<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumns;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AddColumns::class)]
#[Medium]
final class AddColumnsTest extends TestCase
{
    public function testDeriveCommandDerivesEveryElement(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->diagnose('ALTER TABLE t ADD (c INT DEFAULT (x))', [$table]);

        self::assertSame('Column x does not exist.', $alter->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheElements(): void
    {
        self::assertSame('ALTER TABLE t ADD COLUMN (c INT, d INT)', (new Inspection('mysql-5.7.44'))->diagnose('ALTER TABLE t ADD COLUMN (c INT, d INT)')->toString());
    }
}

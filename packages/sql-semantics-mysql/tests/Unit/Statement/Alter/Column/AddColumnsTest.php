<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumns;

#[CoversClass(AddColumns::class)]
#[Medium]
final class AddColumnsTest extends TestCase
{
    public function testDeriveCommandDerivesEveryElement(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->inspect('ALTER TABLE t ADD (c INT DEFAULT (x))', [$table]);

        self::assertSame('Column x does not exist.', $alter->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheElements(): void
    {
        self::assertSame('ALTER TABLE t ADD COLUMN (c INT, d INT)', (new Semantics('mysql-5.7.44'))->inspect('ALTER TABLE t ADD COLUMN (c INT, d INT)')->toString());
    }
}

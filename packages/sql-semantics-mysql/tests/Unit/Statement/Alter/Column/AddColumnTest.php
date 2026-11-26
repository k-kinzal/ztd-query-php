<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AddColumn::class)]
#[Medium]
final class AddColumnTest extends TestCase
{
    public function testDeriveCommandDerivesTheDefinitionInTheChangedTable(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->inspect('ALTER TABLE t ADD c INT AS (a + d), ADD d INT', [$table]);

        self::assertSame([], $alter->facts->diagnostics);
    }

    public function testRenderWritesTheDefinitionAndThePosition(): void
    {
        self::assertSame('ALTER TABLE t ADD COLUMN c INT NOT NULL AFTER a', (new Inspection('mysql-5.6.51'))->inspect('ALTER TABLE t ADD c INT NOT NULL AFTER a')->toString());
    }
}

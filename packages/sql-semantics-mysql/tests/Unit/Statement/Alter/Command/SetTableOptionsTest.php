<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\SetTableOptions;

#[CoversClass(SetTableOptions::class)]
#[Medium]
final class SetTableOptionsTest extends TestCase
{
    public function testDeriveCommandDerivesTheOptions(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        self::assertSame([], $semantics->analyze('ALTER TABLE t ENGINE = InnoDB', [$table])->facts->diagnostics);
    }

    public function testRenderWritesTheRunsApart(): void
    {
        $alter = (new Semantics(Dialect::MySql))->analyze("ALTER TABLE t ENGINE = InnoDB COMMENT = 'x', AUTO_INCREMENT = 5");

        self::assertInstanceOf(AlterTable::class, $alter->statement);
        self::assertCount(2, $alter->statement->commands);
    }
}

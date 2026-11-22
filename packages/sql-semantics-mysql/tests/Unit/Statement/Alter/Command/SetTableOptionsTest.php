<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\SetTableOptions;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SetTableOptions::class)]
#[Medium]
final class SetTableOptionsTest extends TestCase
{
    public function testDeriveCommandDerivesTheOptions(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        self::assertSame([], $semantics->inspect('ALTER TABLE t ENGINE = InnoDB', [$table])->facts->diagnostics);
    }

    public function testRenderWritesTheRunsApart(): void
    {
        $alter = (new Inspection())->inspect("ALTER TABLE t ENGINE = InnoDB COMMENT = 'x', AUTO_INCREMENT = 5");

        self::assertInstanceOf(AlterTable::class, $alter->statement);
        self::assertCount(2, $alter->statement->commands);
    }
}

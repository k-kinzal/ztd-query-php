<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Rules\TableChange\ColumnNames;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(ColumnNames::class)]
#[Medium]
final class ColumnNamesTest extends TestCase
{
    public function testCheckReportsEveryAbsentName(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');

        self::assertSame(['Column x does not exist in the table.', 'Column y does not exist in the table.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->inspect('ALTER TABLE t PARTITION BY KEY (a, x, y)', [$table])->facts->diagnostics));
    }

    public function testPresentLeavesAnUndeclaredTableUndecided(): void
    {
        $semantics = new Semantics();

        self::assertSame([], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->inspect('ALTER TABLE t PARTITION BY KEY (x)')->facts->diagnostics));
    }

    public function testCountComparesWithoutRegardToCase(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');

        self::assertSame([], $semantics->inspect('ALTER TABLE t PARTITION BY KEY (A)', [$table])->facts->diagnostics);
    }

    public function testWriteWritesTheParenthesizedNames(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY KEY (`a b`, c)', (new Semantics())->inspect('ALTER TABLE t PARTITION BY KEY (`a b`, c)')->toString());
    }
}

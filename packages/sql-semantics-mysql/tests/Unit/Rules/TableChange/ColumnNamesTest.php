<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableChange\ColumnNames;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(ColumnNames::class)]
#[Medium]
final class ColumnNamesTest extends TestCase
{
    public function testCheckReportsEveryAbsentName(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertSame(['Column x does not exist in the table.', 'Column y does not exist in the table.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t PARTITION BY KEY (a, x, y)', [$table])->facts->diagnostics));
    }

    public function testPresentLeavesAnUndeclaredTableUndecided(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t PARTITION BY KEY (x)')->facts->diagnostics));
    }

    public function testCountComparesWithoutRegardToCase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertSame([], $semantics->analyze('ALTER TABLE t PARTITION BY KEY (A)', [$table])->facts->diagnostics);
    }

    public function testWriteWritesTheParenthesizedNames(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY KEY (`a b`, c)', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t PARTITION BY KEY (`a b`, c)')->toString());
    }
}

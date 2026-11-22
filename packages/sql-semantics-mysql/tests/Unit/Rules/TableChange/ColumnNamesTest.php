<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Rules\TableChange\ColumnNames;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ColumnNames::class)]
#[Medium]
final class ColumnNamesTest extends TestCase
{
    public function testCheckReportsEveryAbsentName(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT, b INT)');

        self::assertSame(['Column x does not exist in the table.', 'Column y does not exist in the table.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->diagnose('ALTER TABLE t PARTITION BY KEY (a, x, y)', [$table])->facts->diagnostics));
    }

    public function testPresentLeavesAnUndeclaredTableUndecided(): void
    {
        $semantics = new Inspection();

        self::assertSame([], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->diagnose('ALTER TABLE t PARTITION BY KEY (x)')->facts->diagnostics));
    }

    public function testCountComparesWithoutRegardToCase(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT, b INT)');

        self::assertSame([], $semantics->diagnose('ALTER TABLE t PARTITION BY KEY (A)', [$table])->facts->diagnostics);
    }

    public function testWriteWritesTheParenthesizedNames(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY KEY (`a b`, c)', (new Inspection())->diagnose('ALTER TABLE t PARTITION BY KEY (`a b`, c)')->toString());
    }
}

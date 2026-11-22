<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\MySql\Rules\TableChange\Renamings;
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Renamings::class)]
#[Medium]
final class RenamingsTest extends TestCase
{
    public function testDeriveResolvesANameAnEarlierRenameProduced(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT, b INT)');
        $rename = $semantics->diagnose('RENAME TABLE t TO u, u TO v', [$table]);

        self::assertInstanceOf(RenameTable::class, $rename->statement);
        self::assertEquals(new DeclaredTable($table->declarations()[0]), $rename->facts->relation($rename->statement->renamings[1])->table);
    }

    public function testVacatedMakesAMovedNameMissing(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT, b INT)');
        $rename = $semantics->diagnose('RENAME TABLE t TO u, t TO v', [$table]);

        self::assertInstanceOf(RenameTable::class, $rename->statement);
        self::assertInstanceOf(MissingTable::class, $rename->facts->relation($rename->statement->renamings[1])->table);
    }

    public function testExistsReportsANewNameThatIsTaken(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT, b INT)');

        self::assertSame(['Relation t does not exist.', 'Table y already exists.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->diagnose('RENAME TABLE t TO x, x TO y, t TO y', [$table])->facts->diagnostics));
    }
}

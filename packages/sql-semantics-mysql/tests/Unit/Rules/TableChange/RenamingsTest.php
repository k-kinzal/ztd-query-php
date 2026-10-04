<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableChange\Renamings;
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;

#[CoversClass(Renamings::class)]
#[Medium]
final class RenamingsTest extends TestCase
{
    public function testDeriveResolvesANameAnEarlierRenameProduced(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $rename = $semantics->analyze('RENAME TABLE t TO u, u TO v', [$table]);

        self::assertInstanceOf(RenameTable::class, $rename->statement);
        self::assertEquals(new DeclaredTable($table->declarations()[0]), $rename->facts->relation($rename->statement->renamings[1])->table);
    }

    public function testVacatedMakesAMovedNameMissing(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $rename = $semantics->analyze('RENAME TABLE t TO u, t TO v', [$table]);

        self::assertInstanceOf(RenameTable::class, $rename->statement);
        self::assertInstanceOf(MissingTable::class, $rename->facts->relation($rename->statement->renamings[1])->table);
    }

    public function testExistsReportsANewNameThatIsTaken(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertSame(['Relation t does not exist.', 'Table y already exists.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('RENAME TABLE t TO x, x TO y, t TO y', [$table])->facts->diagnostics));
    }
}

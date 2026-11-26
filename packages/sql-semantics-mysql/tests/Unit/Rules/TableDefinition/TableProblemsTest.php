<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableProblems;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\IncorrectColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NullablePrimaryKey;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TableProblems::class)]
#[Medium]
final class TableProblemsTest extends TestCase
{
    public function testReportReportsANullablePrimaryKeyFromMySql57(): void
    {
        $create = (new Inspection())->diagnose('CREATE TABLE t (a INT NULL, PRIMARY KEY (a))');
        $legacy = (new Inspection('5.6.51'))->diagnose('CREATE TABLE t (a INT NULL, PRIMARY KEY (a))');

        self::assertInstanceOf(NullablePrimaryKey::class, $create->facts->diagnostics[0]);
        self::assertSame([], $legacy->facts->diagnostics);
    }

    public function testDuplicatesReportsARepeatedColumn(): void
    {
        $create = (new Inspection())->diagnose('CREATE TABLE t (a INT, A INT)');

        self::assertInstanceOf(DuplicateColumn::class, $create->facts->diagnostics[0]);
    }

    public function testSelectedReportsAGeneratedColumnTheQueryFills(): void
    {
        $semantics = new Inspection();
        $filled = $semantics->diagnose('CREATE TABLE t (a INT, G INT AS (a)) SELECT 1 AS a, 2 AS g');
        $kept = $semantics->diagnose('CREATE TABLE t (g INT AS (1), a INT) SELECT 1 AS a, 3 AS b');

        self::assertSame(["The value specified for generated column 'g' in table 't' is not allowed."], array_map(static fn ($diagnostic): string => $diagnostic->message(), $filled->facts->diagnostics));
        self::assertSame([], $kept->facts->diagnostics);
    }

    public function testKeyColumnsReportsAMissingKeyColumn(): void
    {
        $create = (new Inspection())->diagnose('CREATE TABLE t (a INT, UNIQUE (b))');

        self::assertInstanceOf(UnknownKeyColumn::class, $create->facts->diagnostics[0]);
    }

    public function testNamesReportsAColumnNameThatIsNotValid(): void
    {
        $operation = (new Inspection())->diagnose("CREATE TABLE t AS SELECT 'a ', 1+1, 'b'", []);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(IncorrectColumnName::class, $operation->facts->diagnostics[0]);
        self::assertSame("Incorrect column name 'a '.", $operation->facts->diagnostics[0]->message());
    }
}

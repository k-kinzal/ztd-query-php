<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableProblems;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\IncorrectColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NullablePrimaryKey;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;

#[CoversClass(TableProblems::class)]
#[Medium]
final class TableProblemsTest extends TestCase
{
    public function testReportReportsANullablePrimaryKeyFromMySql57(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT NULL, PRIMARY KEY (a))');
        $legacy = (new Semantics(Dialect::MySql, '5.6.51'))->analyze('CREATE TABLE t (a INT NULL, PRIMARY KEY (a))');

        self::assertInstanceOf(NullablePrimaryKey::class, $create->facts->diagnostics[0]);
        self::assertSame([], $legacy->facts->diagnostics);
    }

    public function testDuplicatesReportsARepeatedColumn(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, A INT)');

        self::assertInstanceOf(DuplicateColumn::class, $create->facts->diagnostics[0]);
    }

    public function testSelectedReportsAGeneratedColumnTheQueryFills(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $filled = $semantics->analyze('CREATE TABLE t (a INT, G INT AS (a)) SELECT 1 AS a, 2 AS g');
        $kept = $semantics->analyze('CREATE TABLE t (g INT AS (1), a INT) SELECT 1 AS a, 3 AS b');

        self::assertSame(["The value specified for generated column 'g' in table 't' is not allowed."], array_map(static fn ($diagnostic): string => $diagnostic->message(), $filled->facts->diagnostics));
        self::assertSame([], $kept->facts->diagnostics);
    }

    public function testKeyColumnsReportsAMissingKeyColumn(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, UNIQUE (b))');

        self::assertInstanceOf(UnknownKeyColumn::class, $create->facts->diagnostics[0]);
    }

    public function testNamesReportsAColumnNameThatIsNotValid(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE TABLE t AS SELECT 'a ', 1+1, 'b'", []);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(IncorrectColumnName::class, $operation->facts->diagnostics[0]);
        self::assertSame("Incorrect column name 'a '.", $operation->facts->diagnostics[0]->message());
    }
}

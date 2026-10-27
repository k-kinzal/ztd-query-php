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
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TableProblems::class)]
#[Medium]
final class TableProblemsTest extends TestCase
{
    public function testCollationsRejectsNamesUnavailableInTheRelease(): void
    {
        $legacy = new Semantics(Dialect::MySql, '5.7.44');
        $column = $legacy->analyze('CREATE TABLE t(v VARCHAR(10) COLLATE utf8mb4_0900_ai_ci)');
        $table = $legacy->analyze('CREATE TABLE t(v VARCHAR(10)) COLLATE utf8mb4_0900_as_cs');
        $generated = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t(v VARCHAR(10) COLLATE missing_collation AS (\'x\'))');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation::class, $column->diagnostics()[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation::class, $table->diagnostics()[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation::class, $generated->diagnostics()[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $generated->warnings()[0]);
        self::assertTrue($generated->warnings()[0]->aborts);
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t(v VARCHAR(10)) COLLATE utf8mb4_0900_as_cs')->diagnostics());
    }

    public function testReportReportsANullablePrimaryKeyFromMySql57(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT NULL, PRIMARY KEY (a))');
        $legacy = (new Semantics(Dialect::MySql, '5.6.51'))->analyze('CREATE TABLE t (a INT NULL, PRIMARY KEY (a))');

        self::assertInstanceOf(NullablePrimaryKey::class, $create->diagnostics()[0]);
        self::assertSame([], $legacy->diagnostics());
    }

    public function testReportKeepsExplicitNullForInlinePrimaryKeys(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertInstanceOf(NullablePrimaryKey::class, $semantics->analyze('CREATE TABLE t(a INT NULL KEY)')->diagnostics()[0]);
        self::assertInstanceOf(NullablePrimaryKey::class, $semantics->analyze('CREATE TABLE t(a INT NULL NOT NULL PRIMARY KEY)')->diagnostics()[0]);
        self::assertSame([], $semantics->analyze('CREATE TABLE t(a INT NOT NULL PRIMARY KEY)')->diagnostics());
    }

    public function testDuplicatesReportsARepeatedColumn(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE t (a INT, A INT)');

        self::assertInstanceOf(DuplicateColumn::class, $create->diagnostics()[0]);
    }

    public function testSelectedReportsAGeneratedColumnTheQueryFills(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $filled = $semantics->analyze('CREATE TABLE t (a INT, G INT AS (a)) SELECT 1 AS a, 2 AS g');
        $kept = $semantics->analyze('CREATE TABLE t (g INT AS (1), a INT) SELECT 1 AS a, 3 AS b');

        self::assertSame(["The value specified for generated column 'g' in table 't' is not allowed."], array_map(static fn ($diagnostic): string => $diagnostic->message(), $filled->diagnostics()));
        self::assertSame([], $kept->diagnostics());
    }

    public function testKeyColumnsReportsAMissingKeyColumn(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE t (a INT, UNIQUE (b))');

        self::assertInstanceOf(UnknownKeyColumn::class, $create->diagnostics()[0]);
    }

    public function testNamesReportsAColumnNameThatIsNotValid(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE TABLE t AS SELECT 'a ', 1+1, 'b'", []);

        self::assertCount(1, $operation->diagnostics());
        self::assertInstanceOf(IncorrectColumnName::class, $operation->diagnostics()[0]);
        self::assertSame("Incorrect column name 'a '.", $operation->diagnostics()[0]->message());
    }

    public function testKeyColumnsReportsTheFirstColumnAnIndexNamesTwice(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertEquals([new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\DuplicateKeyColumn(new Name('A'))], $semantics->analyze('CREATE PROCEDURE p() CREATE TABLE t (a INT, KEY (a, A, zz))')->diagnostics());
        self::assertEquals([new UnknownKeyColumn(new Name('zz')), new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\DuplicateKeyColumn(new Name('a'))], $semantics->analyze('CREATE PROCEDURE p() CREATE TABLE t (a INT, KEY (zz, a, a))')->diagnostics());
    }
}

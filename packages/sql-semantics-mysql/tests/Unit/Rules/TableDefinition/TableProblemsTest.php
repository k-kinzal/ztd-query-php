<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableProblems;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\IncorrectColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\MultiplePrimaryKeys;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns;
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
        $legacy = (new Semantics(Dialect::MySql, '5.6.51'))->analyze('CREATE TABLE t (a INT NULL, PRIMARY KEY (a))');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $legacy);

        self::assertInstanceOf(NullablePrimaryKey::class, (new TableProblems())->problem($legacy->elements, null, \SqlSemantics\Contract\GrammarRelease::MySql5744));
        self::assertNull((new TableProblems())->problem($legacy->elements, null, \SqlSemantics\Contract\GrammarRelease::MySql5651));
        self::assertSame([], $legacy->diagnostics());
    }

    public function testReportKeepsExplicitNullForInlinePrimaryKeys(): void
    {
        $semantics = new Semantics(Dialect::MySql, '5.6.51');
        $inline = $semantics->analyze('CREATE TABLE t(a INT NULL KEY)');
        $explicit = $semantics->analyze('CREATE TABLE t(a INT NULL NOT NULL PRIMARY KEY)');
        $sound = $semantics->analyze('CREATE TABLE t(a INT NOT NULL PRIMARY KEY)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $inline);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $explicit);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $sound);

        self::assertInstanceOf(NullablePrimaryKey::class, (new TableProblems())->problem($inline->elements, null, \SqlSemantics\Contract\GrammarRelease::MySql847));
        self::assertInstanceOf(NullablePrimaryKey::class, (new TableProblems())->problem($explicit->elements, null, \SqlSemantics\Contract\GrammarRelease::MySql847));
        self::assertNull((new TableProblems())->problem($sound->elements, null, \SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testDuplicatesReportsAColumnTheQueryRepeats(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t AS SELECT 1 AS a, 2 AS A');

        self::assertInstanceOf(DuplicateColumn::class, $create->diagnostics()[0]);
    }

    /**
     * @param class-string<\SqlSemantics\Statement\Fact\Diagnostic>|null $modern
     * @param class-string<\SqlSemantics\Statement\Fact\Diagnostic>|null $legacy
     */
    #[DataProvider('providerWrittenProblems')]
    public function testProblemFollowsTheServerOrderOfEachRelease(string $sql, ?string $modern, ?string $legacy): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE t (' . $sql . ')');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $create);
        $body = $create->body;
        self::assertTrue($body instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable || $body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable);
        $elements = $body->elements;
        $modernProblem = (new TableProblems())->problem($elements, null, \SqlSemantics\Contract\GrammarRelease::MySql847);
        $legacyProblem = (new TableProblems())->problem($elements, null, \SqlSemantics\Contract\GrammarRelease::MySql5744);

        self::assertSame($modern, $modernProblem === null ? null : $modernProblem::class);
        self::assertSame($legacy, $legacyProblem === null ? null : $legacyProblem::class);
    }

    /**
     * @return iterable<string, array{string, class-string<\SqlSemantics\Statement\Fact\Diagnostic>|null, class-string<\SqlSemantics\Statement\Fact\Diagnostic>|null}>
     */
    public static function providerWrittenProblems(): iterable
    {
        yield 'duplicate column before a missing key column' => ['a INT, INDEX (b), c INT, C INT', DuplicateColumn::class, DuplicateColumn::class];
        yield 'two primary key attributes before a missing key column' => ['a INT PRIMARY KEY, b INT PRIMARY KEY, INDEX (z)', MultiplePrimaryKeys::class, MultiplePrimaryKeys::class];
        yield 'attribute and element: second key first on 8.0, its column first on 5.7' => ['a INT PRIMARY KEY, PRIMARY KEY (b)', MultiplePrimaryKeys::class, UnknownKeyColumn::class];
        yield 'first key element names a missing column' => ['a INT, PRIMARY KEY (b), PRIMARY KEY (a)', UnknownKeyColumn::class, UnknownKeyColumn::class];
        yield 'a key without columns: the key column on 8.0, the empty table on 5.7' => ['PRIMARY KEY (a)', UnknownKeyColumn::class, NoColumns::class];
        yield 'foreign key column' => ['a INT, FOREIGN KEY (z) REFERENCES p (a)', UnknownKeyColumn::class, UnknownKeyColumn::class];
        yield 'two key elements' => ['a INT, b INT, INDEX (A), PRIMARY KEY (a), PRIMARY KEY (b)', MultiplePrimaryKeys::class, MultiplePrimaryKeys::class];
        yield 'a sound definition' => ['a INT, b INT, PRIMARY KEY (a), INDEX (B)', null, null];
    }

    public function testProblemReportsAnEmptyDefinitionBeforeItsKeysFromMySqlNine(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE t (PRIMARY KEY (a))');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $create);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable::class, $create->body);

        self::assertInstanceOf(NoColumns::class, (new TableProblems())->problem($create->body->elements, null, \SqlSemantics\Contract\GrammarRelease::MySql910));
        self::assertInstanceOf(UnknownKeyColumn::class, (new TableProblems())->problem($create->body->elements, null, \SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testProblemLeavesTheColumnsOfAQueryToExecution(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (PRIMARY KEY (b)) AS SELECT 1 AS a');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $create);

        self::assertNull((new TableProblems())->problem($create->elements, $create->query, \SqlSemantics\Contract\GrammarRelease::MySql847));
        self::assertNull((new TableProblems())->problem([], $create->query, \SqlSemantics\Contract\GrammarRelease::MySql5744));
    }

    public function testWrittenComparesColumnNamesWithoutRegardToCase(): void
    {
        self::assertTrue((new TableProblems())->written(new Name('A'), [new Name('b'), new Name('a')]));
        self::assertFalse((new TableProblems())->written(new Name('c'), [new Name('b'), new Name('a')]));
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
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (UNIQUE (b)) AS SELECT 1 AS a');

        self::assertInstanceOf(UnknownKeyColumn::class, $create->diagnostics()[0]);
    }

    public function testNamesReportsAColumnNameThatIsNotValid(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE TABLE t AS SELECT 'a ', 1+1, 'b'", []);

        self::assertCount(1, $operation->diagnostics());
        self::assertInstanceOf(IncorrectColumnName::class, $operation->diagnostics()[0]);
        self::assertSame("Incorrect column name 'a '.", $operation->diagnostics()[0]->message());
    }

    public function testKeyColumnsReportsAColumnTheQueryLacksBeforeALaterPart(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertEquals([new UnknownKeyColumn(new Name('zz'))], $semantics->analyze('CREATE TABLE t (KEY (zz, a)) AS SELECT 1 AS a')->diagnostics());
    }
}

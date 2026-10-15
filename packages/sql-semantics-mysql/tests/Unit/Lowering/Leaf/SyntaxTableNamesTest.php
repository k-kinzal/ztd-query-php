<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxTableNames;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName;
use SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind;

#[CoversClass(SyntaxTableNames::class)]
#[Medium]
final class SyntaxTableNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ObjectNameKind}>
     */
    public static function providerRefusals(): iterable
    {
        yield 'table' => ['ALTER TABLE `` ADD a INT', ObjectNameKind::Table];
        yield 'database' => ['OPTIMIZE TABLE ``.t', ObjectNameKind::Database];
        yield 'handler read' => ['HANDLER `` READ FIRST', ObjectNameKind::Table];
        yield 'handler close' => ['HANDLER `` CLOSE', ObjectNameKind::Table];
        yield 'unexecuted branch' => ['CREATE PROCEDURE p() BEGIN IF 0 THEN ALTER TABLE `` ADD a INT; END IF; END', ObjectNameKind::Table];
    }

    #[DataProvider('providerRefusals')]
    public function testRejectedRetainsOnlyTheRoleAndCompletionBoundary(string $sql, ObjectNameKind $kind): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $failure = (new SyntaxWarnings())->rejected($semantics->parser()->parse($sql), $semantics->profile());

        self::assertInstanceOf(RejectedInput::class, $failure);
        self::assertEquals(new EmptyObjectName($kind), $failure->problem);
        self::assertGreaterThan(0, $failure->end);
        self::assertFalse($failure->aborts);
    }

    public function testRejectedPreservesEmptyAliasesAndFilters(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $statements = ['SELECT ``.a FROM t AS ``', 'SELECT ``.a FROM (SELECT 1 AS a) AS ``', 'DELETE `` FROM t AS ``', 'DELETE FROM `` USING t AS ``', 'WITH `` AS (SELECT 1 AS a) SELECT 1', 'HANDLER t OPEN AS ``', 'SHOW OPEN TABLES FROM ``'];
        $refusals = array_map(static fn (string $sql): ?RejectedInput => (new SyntaxWarnings())->rejected($semantics->parser()->parse($sql), $semantics->profile()), $statements);

        self::assertSame(array_fill(0, count($statements), null), $refusals);
    }

    public function testCompletedOnlyAbortsAtTheTableOperandInLegacyReleases(): void
    {
        $legacy = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $modern = new Semantics(Dialect::MySql);
        $sql = 'ALTER TABLE `` ADD';
        $oldParser = $legacy->parser();
        $newParser = $modern->parser();
        self::assertInstanceOf(\SqlParser\Parser\ReductionParser::class, $oldParser);
        self::assertInstanceOf(\SqlParser\Parser\ReductionParser::class, $newParser);

        self::assertCount(1, (new SyntaxWarnings())->completed($oldParser, $sql, strlen($sql), $legacy->profile()));
        self::assertSame([], (new SyntaxWarnings())->completed($newParser, $sql, strlen($sql), $modern->profile()));
    }

    public function testWithinPreservesACompletedProgramRefusalBeforeConstructingItsStatement(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $sql = 'CREATE PROCEDURE p() ALTER TABLE `` ADD a INT';
        $parser = $semantics->parser();
        self::assertInstanceOf(\SqlParser\Parser\ReductionParser::class, $parser);
        $notices = (new SyntaxWarnings())->completed($parser, $sql, strlen($sql), $semantics->profile());

        self::assertCount(1, $notices);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $notices[0][1]);
        self::assertEquals(new EmptyObjectName(ObjectNameKind::Table), $notices[0][1]->problem);
    }

    public function testRejectedPreventsPublishingAnInvalidStatement(): void
    {
        $this->expectException(RejectedInput::class);
        $this->expectExceptionMessage("Incorrect table name ''");

        (new Semantics(Dialect::MySql))->analyze('CREATE VIEW `` AS SELECT 1 AS a');
    }
    public function testCreatedReadsOnlyTheTargetBeforeAnInvalidDefinition(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $rule = new SyntaxTableNames();
        $name = $rule->created($semantics->parser()->parse('CREATE TABLE d.t(a INT DEFAULT (ABS()))'), $semantics->profile());

        self::assertSame('d', $name?->schema?->value);
        self::assertSame('t', $name->name->value);
        self::assertNull($rule->created($semantics->parser()->parse('SELECT ABS()'), $semantics->profile()));
        self::assertNull($rule->created($semantics->parser()->parse('CREATE PROCEDURE d.p() CREATE TABLE t(a INT DEFAULT (ABS()))'), $semantics->profile()));
    }

}

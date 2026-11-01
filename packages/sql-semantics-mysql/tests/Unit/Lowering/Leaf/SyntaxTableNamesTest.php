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
     * @return iterable<string, array{string, string, list<string>}> Independent write heads
     */
    public static function providerWriteHeads(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.4.7'] as $release) {
            yield $release . ' insert' => [$release, 'INSERT INTO t SELECT ABS()', ['t']];
            yield $release . ' replace' => [$release, 'REPLACE t SET a=ABS()', ['t']];
            yield $release . ' joined update' => [$release, 'UPDATE d.t JOIN u SET t.a=ABS()', ['t', 'u']];
            yield $release . ' derived source' => [$release, 'UPDATE d.t JOIN (SELECT 1 FROM u) c SET t.a=ABS()', ['t', 'u']];
            yield $release . ' stored instruction' => [$release, 'CREATE PROCEDURE p() UPDATE t SET a=ABS()', []];
        }
        yield 'common table binding' => ['8.4.7', 'WITH c AS(SELECT 1 FROM u) UPDATE d.t JOIN c SET t.a=ABS()', ['t', 'u']];
    }

    /**
     * @param list<string> $expected Independently resolved physical relation names
     */
    #[DataProvider('providerWriteHeads')]
    public function testWrittenRetainsOnlyNamesOwnedByTheOuterWrite(string $release, string $sql, array $expected): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $names = (new SyntaxTableNames())->written($semantics->parser()->parse($sql), $semantics->profile(), \SqlSemantics\Statement\Identifier\Comparison::Sensitive, strlen($sql));

        self::assertSame($expected, array_map(static fn (\SqlSemantics\Statement\Identifier\QualifiedName $name): string => $name->name->value, $names));
    }

    public function testContextualDistinguishesDmlFromDefinitionTargetsInsideStoredBodies(): void
    {
        $parser = new \SqlParser\MySql\MySqlParser('mysql-5.7.44');
        $input = new SyntaxTableNames();
        $sql = 'CREATE PROCEDURE p() BEGIN ALTER TABLE t ADD a INT; UPDATE u SET a=1; SELECT 1 FROM v; END';
        $nodes = $parser->parse($sql)->find('table_ident');
        $tokens = $parser->tokenize($sql);

        self::assertSame([false, true, true], array_map(static fn (\SqlParser\Parser\Node $node): bool => $input->contextual($node, $tokens), $nodes));
        self::assertFalse($input->contextual($parser->parse('SELECT 1'), $tokens));
    }

    public function testCompletedDefersLegacyDmlNamesUntilContextualization(): void
    {
        $parser = new \SqlParser\MySql\MySqlParser('mysql-5.7.44');
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5744);
        $sql = 'SELECT 1 FROM `` ,';

        self::assertSame([], (new SyntaxWarnings())->completed($parser, $sql, strlen($sql), $profile));
        self::assertEquals([new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure(new EmptyObjectName(ObjectNameKind::Table), true)], array_column((new SyntaxWarnings())->completed($parser, 'ALTER TABLE `` ADD', 100, $profile), 1));
    }

    public function testDependenciesRetainsValidatedNamesAndCompletionOffsetsWithoutSourceNodes(): void
    {
        $parser = new \SqlParser\MySql\MySqlParser('mysql-5.6.51');
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5651);
        $reader = new SyntaxTableNames();
        $sql = 'SELECT 1 FROM d.t, u,';
        $names = $reader->dependencies($parser, $sql, strlen($sql), $profile);

        self::assertSame([17, 20], array_column($names, 0));
        self::assertSame(['t', 'u'], array_map(static fn (array $item): string => $item[1]->name->value, $names));
        self::assertSame(['d', null], array_map(static fn (array $item): ?string => $item[1]->schema?->value, $names));
        self::assertSame([], $reader->dependencies($parser, $sql, 14, $profile));
        self::assertSame([], $reader->dependencies($parser, 'SELECT 1 FROM `` ,', 100, $profile));
    }

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

    public function testDeletedRetainsOnlyTheOuterCommandsTargetNames(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $rule = new SyntaxTableNames();
        $names = $rule->deleted($semantics->parser()->parse('DELETE a, d.b FROM (SELECT HIGH_PRIORITY 1) x'), $semantics->profile());

        self::assertSame(['a', 'b'], array_map(static fn (\SqlSemantics\Statement\Identifier\QualifiedName $name): string => $name->name->value, $names));
        self::assertSame([null, 'd'], array_map(static fn (\SqlSemantics\Statement\Identifier\QualifiedName $name): ?string => $name->schema?->value, $names));
        self::assertSame([], $rule->deleted($semantics->parser()->parse('CREATE PROCEDURE p() DELETE a FROM a'), $semantics->profile()));
    }

    public function testDeletedReadsLegacyTargetsBeforeAnInvalidQuery(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $names = (new SyntaxTableNames())->deleted($semantics->parser()->parse('DELETE a.*, d.b FROM (SELECT HIGH_PRIORITY 1) x'), $semantics->profile());

        self::assertSame(['a', 'b'], array_map(static fn (\SqlSemantics\Statement\Identifier\QualifiedName $name): string => $name->name->value, $names));
        self::assertSame([null, 'd'], array_map(static fn (\SqlSemantics\Statement\Identifier\QualifiedName $name): ?string => $name->schema?->value, $names));
    }

    public function testDeletedRequiresTheCompleteTargetListBeforeTheRefusal(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tree = $semantics->parser()->parse('DELETE a, d.b FROM (SELECT ABS()) AS x');
        $rule = new SyntaxTableNames();

        self::assertSame([], $rule->deleted($tree, $semantics->profile(), 11));
        self::assertCount(2, $rule->deleted($tree, $semantics->profile(), 13));
    }

}

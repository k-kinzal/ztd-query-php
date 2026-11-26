<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Resolution\CommonBinding;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\CommonTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Derivation::class)]
#[Medium]
final class DerivationTest extends TestCase
{
    public function testEnvironmentSeesNoRelationAndTheContext(): void
    {
        $context = (new Inspection())->context([]);

        $environment = (new Derivation($context))->environment();

        self::assertSame($context, $environment->context);
        self::assertSame([], $environment->relations);
        self::assertNull($environment->outer);
    }

    public function testStatementDerivesTheRootAndRecordsItsRows(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context([]));

        $derivation->statement($semantics->diagnose('SELECT 1 AS a')->statement);

        $output = $derivation->facts()->output;
        self::assertNotNull($output);
        self::assertSame('a', $output->fields()?->at(0)->name?->value);
    }

    public function testInspectedDiscardsDeclarationsAndRowsButKeepsFactsAndDiagnostics(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INTEGER)');
        $create = $table->statement;
        $select = $semantics->diagnose('SELECT b FROM t', [$table])->statement;
        $derivation = new Derivation($semantics->context([$table]));

        $derivation->inspected($create);
        $derivation->inspected($select);

        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(ResultColumn::class, $select->columns[0]);
        $facts = $derivation->facts();
        self::assertSame([], $facts->declarations);
        self::assertNull($facts->output);
        self::assertTrue($facts->covers($select));
        self::assertTrue($facts->covers($select->columns[0]->expression));
        self::assertCount(1, $facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $facts->diagnostics[0]);
    }

    public function testInspectedKeepsTheRowsRecordedBefore(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context([]));
        $derivation->statement($semantics->diagnose('SELECT 1 AS first')->statement);

        $derivation->inspected($semantics->diagnose('SELECT 2 AS second')->statement);

        self::assertSame('first', $derivation->facts()->output?->fields()?->at(0)->name?->value);
    }

    public function testScalarRecordsTheFactAndADiagnosticResolution(): void
    {
        $derivation = new Derivation((new Inspection())->context([]));
        $use = new ColumnUse(new Name('a'));

        $fact = $derivation->scalar($use, $derivation->environment());

        self::assertInstanceOf(MissingColumn::class, $fact->resolution);
        self::assertSame($fact, $derivation->facts()->scalar($use));
        self::assertSame([$fact->resolution], $derivation->facts()->diagnostics);
    }

    public function testRelationRecordsTheFactOfAnOccurrence(): void
    {
        $derivation = new Derivation((new Inspection())->context());
        $input = new TableInput(new QualifiedName(new Name('t')));

        $fact = $derivation->relation($input, $derivation->environment());

        self::assertInstanceOf(UndeclaredTable::class, $fact->table);
        self::assertSame($fact, $derivation->facts()->relation($input));
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testTargetRecordsATableUseThatIsNoRelationAndItsDiagnostic(): void
    {
        $derivation = new Derivation((new Inspection())->context([]));
        $node = new Star();
        $missing = new MissingTable(new QualifiedName(new Name('t')));

        $fact = $derivation->target($node, new RelationFact(new RowShape([]), $missing));

        self::assertSame($fact, $derivation->facts()->relation($node));
        self::assertTrue($derivation->facts()->covers($node));
        self::assertSame([$missing], $derivation->facts()->diagnostics);
    }

    public function testQueryRecordsTheOutputOfAQueryAtAPosition(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context([]));
        $query = $semantics->diagnose('SELECT 1, 2')->statement;

        self::assertInstanceOf(Select::class, $query);
        $fact = $derivation->query($query, $derivation->environment());

        self::assertCount(2, $fact->projection);
        self::assertSame($fact, $derivation->facts()->query($query));
        self::assertNull($derivation->facts()->output);
    }

    public function testTableResolvesTheNearestCommonTableBeforeTheContext(): void
    {
        $semantics = new Inspection();
        $declared = $semantics->diagnose('CREATE TABLE c (a INTEGER)');
        $derivation = new Derivation($semantics->context([$declared]));
        $definition = new Star();
        $environment = new Environment($derivation->context, null, [], [new CommonBinding(new Name('c'), $definition, new RowShape([]))]);

        $common = $derivation->table(new QualifiedName(new Name('c')), $environment);
        $qualified = $derivation->table(new QualifiedName(new Name('c'), new Name('main')), $environment);

        self::assertInstanceOf(CommonTable::class, $common);
        self::assertSame($definition, $common->definition);
        self::assertInstanceOf(DeclaredTable::class, $qualified);
        self::assertSame($declared->declarations()[0], $qualified->table);
    }

    public function testDeclareRecordsADeclarationTheStatementProvides(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INTEGER)')->declarations()[0];
        $derivation = new Derivation($semantics->context([]));

        $derivation->declare($table);

        self::assertSame([$table], $derivation->facts()->declarations);
    }

    public function testOutputRecordsTheRowsOfTheRootOnce(): void
    {
        $derivation = new Derivation((new Inspection())->context([]));
        $fact = new QueryFact([], Comparison::Sensitive);

        $derivation->output($fact);

        self::assertSame($fact, $derivation->facts()->output);
    }

    public function testOutputRefusesASecondRowSet(): void
    {
        $derivation = new Derivation((new Inspection())->context([]));
        $derivation->output(new QueryFact([], Comparison::Sensitive));

        $this->expectExceptionMessage('A statement has one output.');

        $derivation->output(new QueryFact([], Comparison::Sensitive));
    }

    public function testReportRecordsAProblemThatIsNoResolution(): void
    {
        $derivation = new Derivation((new Inspection())->context([]));
        $problem = new MissingColumn(new Name('a'));

        $derivation->report($problem);

        self::assertSame([$problem], $derivation->facts()->diagnostics);
    }

    public function testFactsFreezesEverythingRecorded(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context([]));
        $derivation->statement($semantics->diagnose('SELECT a FROM t WHERE a > 1')->statement);

        $facts = $derivation->facts();

        self::assertNotNull($facts->output);
        self::assertCount(3, $facts->diagnostics);
        self::assertSame([], $facts->declarations);
    }

    public function testWithinReadsANestedStatementUnderOtherSearchSettings(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE aux.t (a INTEGER)');
        $context = $semantics->context([$table]);
        $select = $semantics->diagnose('SELECT a FROM t', $context)->statement;
        self::assertInstanceOf(Select::class, $select);
        $nested = new AnalysisContext($context->profile, [new Name('aux')], $context->tables, true, Comparison::AsciiInsensitive, Comparison::AsciiInsensitive);
        $derivation = new Derivation($context);

        $derivation->within($nested, $select);

        self::assertInstanceOf(DeclaredTable::class, $derivation->facts()->relation($select->input() ?? $select)->table);
        self::assertSame($context, $derivation->context);
    }

    public function testWithinRefusesADeclarationTheEnclosingContextDoesNotHold(): void
    {
        $semantics = new Inspection();
        $context = $semantics->context([]);
        $foreign = new AnalysisContext($context->profile, $context->searchPath, $semantics->diagnose('CREATE TABLE t (a INTEGER)')->declarations());

        $this->expectExceptionMessage('A nested statement sees only the declarations of the enclosing context and of the statement itself.');

        (new Derivation($context))->within($foreign, $semantics->diagnose('SELECT 1')->statement);
    }

    public function testAdmitAcceptsTheDeclarationsTheStatementProvided(): void
    {
        $semantics = new Inspection();
        $context = $semantics->context([]);
        $derivation = new Derivation($context);
        $derivation->statement($semantics->diagnose('CREATE TABLE t (a INTEGER)')->statement);
        $own = new AnalysisContext($context->profile, $context->searchPath, $derivation->facts()->declarations, true, $context->relationNames, $context->columnNames);

        $derivation->admit($own);

        self::assertCount(1, $derivation->facts()->declarations);
    }

    public function testAdmitRefusesAContextThatDropsAnEnclosingDeclaration(): void
    {
        $semantics = new Inspection();
        $context = $semantics->context([$semantics->diagnose('CREATE TABLE t (a INTEGER)')]);
        $empty = new AnalysisContext($context->profile, $context->searchPath, [], true);

        $this->expectExceptionMessage('A nested statement sees every declaration of the enclosing context.');

        (new Derivation($context))->admit($empty);
    }

    public function testMemberKeepsDeclarationsButNotRows(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context([]));

        $derivation->member($semantics->diagnose('SELECT 1 AS a')->statement);
        $derivation->member($semantics->diagnose('CREATE TABLE t (a INTEGER)')->statement);

        self::assertNull($derivation->facts()->output);
        self::assertCount(1, $derivation->facts()->declarations);
    }

    public function testInspectedReadsTheStatementAtTheGivenEnvironment(): void
    {
        $semantics = new Inspection();
        $context = $semantics->context([]);
        $select = $semantics->diagnose('SELECT 1 AS a')->statement;
        $environment = new Environment($context);
        $derivation = new Derivation($context);

        $derivation->inspected($select, $environment);

        self::assertNull($derivation->facts()->output);
        self::assertTrue($derivation->facts()->covers($select));
        self::assertNotSame($environment, $derivation->environment());
    }
}

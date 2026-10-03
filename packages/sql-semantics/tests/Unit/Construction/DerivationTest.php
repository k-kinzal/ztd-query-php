<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Resolution\CommonBinding;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Shape\RowShape;

#[CoversClass(Derivation::class)]
#[Medium]
final class DerivationTest extends TestCase
{
    public function testEnvironmentSeesNoRelationAndTheContext(): void
    {
        $context = (new Semantics(Dialect::Sqlite))->context([]);

        $environment = (new Derivation($context))->environment();

        self::assertSame($context, $environment->context);
        self::assertSame([], $environment->relations);
        self::assertNull($environment->outer);
    }

    public function testStatementDerivesTheRootAndRecordsItsRows(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $derivation = new Derivation($semantics->context([]));

        $derivation->statement($semantics->analyze('SELECT 1 AS a')->statement);

        $output = $derivation->facts()->output;
        self::assertNotNull($output);
        self::assertSame('a', $output->fields()?->at(0)->name?->value);
    }

    public function testInspectedDiscardsDeclarationsAndRowsButKeepsFactsAndDiagnostics(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $create = $table->statement;
        $select = $semantics->analyze('SELECT b FROM t', [$table])->statement;
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
        $semantics = new Semantics(Dialect::Sqlite);
        $derivation = new Derivation($semantics->context([]));
        $derivation->statement($semantics->analyze('SELECT 1 AS first')->statement);

        $derivation->inspected($semantics->analyze('SELECT 2 AS second')->statement);

        self::assertSame('first', $derivation->facts()->output?->fields()?->at(0)->name?->value);
    }

    public function testScalarRecordsTheFactAndADiagnosticResolution(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));
        $use = new ColumnUse(new Name('a'));

        $fact = $derivation->scalar($use, $derivation->environment());

        self::assertInstanceOf(MissingColumn::class, $fact->resolution);
        self::assertSame($fact, $derivation->facts()->scalar($use));
        self::assertSame([$fact->resolution], $derivation->facts()->diagnostics);
    }

    public function testRelationRecordsTheFactOfAnOccurrence(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context());
        $input = new TableInput(new QualifiedName(new Name('t')));

        $fact = $derivation->relation($input, $derivation->environment());

        self::assertInstanceOf(UndeclaredTable::class, $fact->table);
        self::assertSame($fact, $derivation->facts()->relation($input));
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testTargetRecordsATableUseThatIsNoRelationAndItsDiagnostic(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));
        $node = new Star();
        $missing = new MissingTable(new QualifiedName(new Name('t')));

        $fact = $derivation->target($node, new RelationFact(new RowShape([]), $missing));

        self::assertSame($fact, $derivation->facts()->relation($node));
        self::assertTrue($derivation->facts()->covers($node));
        self::assertSame([$missing], $derivation->facts()->diagnostics);
    }

    public function testQueryRecordsTheOutputOfAQueryAtAPosition(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $derivation = new Derivation($semantics->context([]));
        $query = $semantics->analyze('SELECT 1, 2')->statement;

        self::assertInstanceOf(Select::class, $query);
        $fact = $derivation->query($query, $derivation->environment());

        self::assertCount(2, $fact->projection);
        self::assertSame($fact, $derivation->facts()->query($query));
        self::assertNull($derivation->facts()->output);
    }

    public function testTableResolvesTheNearestCommonTableBeforeTheContext(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $declared = $semantics->analyze('CREATE TABLE c (a INTEGER)');
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
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)')->declarations()[0];
        $derivation = new Derivation($semantics->context([]));

        $derivation->declare($table);

        self::assertSame([$table], $derivation->facts()->declarations);
    }

    public function testOutputRecordsTheRowsOfTheRootOnce(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));
        $fact = new QueryFact([], Comparison::Sensitive);

        $derivation->output($fact);

        self::assertSame($fact, $derivation->facts()->output);
    }

    public function testOutputRefusesASecondRowSet(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));
        $derivation->output(new QueryFact([], Comparison::Sensitive));

        $this->expectExceptionMessage('A statement has one output.');

        $derivation->output(new QueryFact([], Comparison::Sensitive));
    }

    public function testReportRecordsAProblemThatIsNoResolution(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));
        $problem = new MissingColumn(new Name('a'));

        $derivation->report($problem);

        self::assertSame([$problem], $derivation->facts()->diagnostics);
    }

    public function testFactsFreezesEverythingRecorded(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $derivation = new Derivation($semantics->context([]));
        $derivation->statement($semantics->analyze('SELECT a FROM t WHERE a > 1')->statement);

        $facts = $derivation->facts();

        self::assertNotNull($facts->output);
        self::assertCount(3, $facts->diagnostics);
        self::assertSame([], $facts->declarations);
    }
}

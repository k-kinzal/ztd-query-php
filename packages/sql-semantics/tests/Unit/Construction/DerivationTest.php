<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
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
use SqlSemantics\Statement\Fact\Warning;
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
    public function testDiagnosticsExposeReportedConditionsWithoutFreezingOperandFacts(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));
        $missing = new MissingColumn(new Name('first'));
        $derivation->report($missing);

        self::assertSame([$missing], $derivation->diagnostics());

        $use = new ColumnUse(new Name('second'));
        $fact = $derivation->scalar($use, $derivation->environment());

        self::assertSame([$missing, $fact->resolution], $derivation->diagnostics());
        self::assertSame($fact, $derivation->facts()->scalar($use));
    }

    public function testBindReferencePreservesOccurrenceIdentity(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));
        $name = new QualifiedName(new Name('t'));
        $input = new TableInput($name);
        $derivation->bindReference($name, $input);
        $derivation->bindReference($name, $input);

        self::assertSame($input, $derivation->facts()->referencedRelation($name));
        self::assertNull($derivation->facts()->referencedRelation(new QualifiedName(new Name('t'))));
    }

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

        $derivation->statement((new \SqlSemantics\Construction\Occurrences())->copy($semantics->analyze('SELECT 1 AS a')));

        $output = $derivation->facts()->output;
        self::assertNotNull($output);
        self::assertSame('a', $output->fields()?->at(0)->name?->value);
    }

    public function testInspectedDiscardsDeclarationsAndRowsButKeepsFactsAndDiagnostics(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $create = $table;
        $select = $semantics->analyze('SELECT b FROM t', [$table]);
        $derivation = new Derivation($semantics->context([$table]));

        $derivation->inspected($create = (new \SqlSemantics\Construction\Occurrences())->copy($create));
        $derivation->inspected($select = (new \SqlSemantics\Construction\Occurrences())->copy($select));

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
        $derivation->statement((new \SqlSemantics\Construction\Occurrences())->copy($semantics->analyze('SELECT 1 AS first')));

        $derivation->inspected((new \SqlSemantics\Construction\Occurrences())->copy($semantics->analyze('SELECT 2 AS second')));

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
        $node = new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new QualifiedName(new Name('t')));
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
        $query = $semantics->analyze('SELECT 1, 2');

        self::assertInstanceOf(Select::class, $query);
        $fact = $derivation->query($query = (new \SqlSemantics\Construction\Occurrences())->copy($query), $derivation->environment());

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

    public function testDeferredWithholdsTheProblemsOfADefinitionUntilItsFirstUse(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));
        $definition = new Star();
        $problem = new MissingColumn(new Name('a'));
        $environment = new Environment($derivation->context, null, [], [new CommonBinding(new Name('c'), $definition, new RowShape([]))]);

        $result = $derivation->deferred($definition, static function () use ($derivation, $problem): string {
            $derivation->report($problem);

            return 'derived';
        });
        $withheld = $derivation->facts()->diagnostics;
        $derivation->table(new QualifiedName(new Name('c')), $environment);
        $derivation->table(new QualifiedName(new Name('c')), $environment);

        self::assertSame('derived', $result);
        self::assertSame([], $withheld);
        self::assertSame([$problem], $derivation->facts()->diagnostics);
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

    public function testWarnRecordsAConditionThatIsNoProblem(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));
        $warning = new class () implements Warning {
            public function message(): string
            {
                return 'deprecated';
            }
        };

        $derivation->warn($warning);

        self::assertSame([$warning], $derivation->facts()->warnings);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testFactsFreezesEverythingRecorded(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $derivation = new Derivation($semantics->context([]));
        $derivation->statement((new \SqlSemantics\Construction\Occurrences())->copy($semantics->analyze('SELECT a FROM t WHERE a > 1')));

        $facts = $derivation->facts();

        self::assertNotNull($facts->output);
        self::assertCount(3, $facts->diagnostics);
        self::assertSame([], $facts->declarations);
    }

    public function testWithinReadsANestedStatementUnderOtherSearchSettings(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE aux.t (a INTEGER)');
        $context = $semantics->context([$table]);
        $select = $semantics->analyze('SELECT a FROM t', $context);
        self::assertInstanceOf(Select::class, $select);
        $nested = new AnalysisContext($context->profile, [new Name('aux')], $context->tables, true, Comparison::AsciiInsensitive, Comparison::AsciiInsensitive);
        $derivation = new Derivation($context);

        $derivation->within($nested, $select = (new \SqlSemantics\Construction\Occurrences())->copy($select));

        self::assertInstanceOf(DeclaredTable::class, $derivation->facts()->relation($select->input() ?? $select)->table);
        self::assertSame($context, $derivation->context);
    }

    public function testWithinRefusesADeclarationTheEnclosingContextDoesNotHold(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $context = $semantics->context([]);
        $foreign = new AnalysisContext($context->profile, $context->searchPath, $semantics->analyze('CREATE TABLE t (a INTEGER)')->declarations());

        $this->expectExceptionMessage('A nested statement sees only the declarations of the enclosing context and of the statement itself.');

        (new Derivation($context))->within($foreign, $semantics->analyze('SELECT 1'));
    }

    public function testMemberKeepsDeclarationsButNotRows(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $derivation = new Derivation($semantics->context([]));

        $derivation->member((new \SqlSemantics\Construction\Occurrences())->copy($semantics->analyze('SELECT 1 AS a')));
        $derivation->member((new \SqlSemantics\Construction\Occurrences())->copy($semantics->analyze('CREATE TABLE t (a INTEGER)')));

        self::assertNull($derivation->facts()->output);
        self::assertCount(1, $derivation->facts()->declarations);
    }

    public function testInspectedReadsTheStatementAtTheGivenEnvironment(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $context = $semantics->context([]);
        $select = $semantics->analyze('SELECT 1 AS a');
        $environment = new Environment($context);
        $derivation = new Derivation($context);

        $derivation->inspected($select = (new \SqlSemantics\Construction\Occurrences())->copy($select), $environment);

        self::assertNull($derivation->facts()->output);
        self::assertTrue($derivation->facts()->covers($select));
        self::assertNotSame($environment, $derivation->environment());
    }

    public function testProgramDerivesAStoredStatementWhileInProgramHolds(): void
    {
        $semantics = new Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $operation = $semantics->analyze('CREATE PROCEDURE p() SET @@version = 1');

        self::assertSame([], $operation->diagnostics());
    }

    public function testInProgramIsFalseOutsideAStoredProgram(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));

        self::assertFalse($derivation->inProgram());
    }

    public function testStoredInstructionNoticesKeepTheirOwnersWithoutSourceLocations(): void
    {
        $semantics = new Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.4.7');
        $sql = 'CREATE PROCEDURE p() BEGIN ALTER TABLE t CONVERT TO CHARSET utf8mb4 COLLATE latin1_bin; ALTER TABLE t CONVERT TO CHARSET missing; END';
        $parsed = $semantics->analyze($sql);
        $lowered = (new \SqlSemantics\Platform\MySql\Platform())->lower($semantics->parser()->parse($sql), $semantics->profile(), new \SqlSemantics\Lowering\Leaves());
        $operation = Semantics::fromStatement($semantics->context([]), $lowered[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Block::class, $operation->body);
        $instructions = $operation->body->statements;
        $warnings = $operation->warnings();

        self::assertNull($operation->source());
        self::assertCount(2, $warnings);
        self::assertSame($instructions, array_map($operation->statementInformation()->warningInstruction(...), $warnings));
        self::assertSame(array_map(static fn ($warning): string => $warning->message(), $parsed->warnings()), array_map(static fn ($warning): string => $warning->message(), $warnings));
    }

    public function testWritesRecordsTheColumnsTheRowsOfAQueryAreWrittenTo(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));
        $query = new Select([new Star()]);
        $derivation->writes($query, [], true);

        self::assertSame([[], true], $derivation->written($query));
    }

    public function testWrittenIsNullForAQueryWhoseRowsAreNotWritten(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));

        self::assertNull($derivation->written(new Select([new Star()])));
    }

    public function testReadingWarningsMergesSourceNoticesAtExplicitBoundaries(): void
    {
        $semantics = new Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.4.7');
        $origins = new \SqlSemantics\Construction\Origins();
        $input = (new \SqlSemantics\Platform\MySql\Platform())->lower($semantics->parser()->parse('SELECT !full, BINARY full'), $semantics->profile(), new \SqlSemantics\Lowering\Leaves(), $origins);
        $derivation = new Derivation($semantics->context([]), $origins->publish());
        $derivation->statement($input[0]);

        self::assertSame([\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BangNot->value, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::UnquotedFull->value, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::UnquotedFull->value, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BinaryOperator->value], array_map(static fn ($warning): string => $warning->message(), $derivation->facts()->warnings));
    }

    public function testOrderWarningsOrdersOnlyTheRequestedInputScope(): void
    {
        $node = new Select([new Star()]);
        $sources = new \SqlSemantics\Statement\Source\SourceMap([new \SqlSemantics\Statement\Source\Origin($node, 0, 10)]);
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]), $sources);
        $before = self::createStub(Warning::class);
        $after = self::createStub(Warning::class);
        $derivation->warn($after, $node);
        $derivation->warn($before, $node, false);
        $derivation->orderWarnings($node);

        self::assertSame([$before, $after], $derivation->facts()->warnings);
    }


    public function testStoredKeepsProgramMemberOwnershipAndRestoresTheOuterContext(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));
        $outer = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block();
        $inner = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block();
        $first = self::createStub(Warning::class);
        $second = self::createStub(Warning::class);
        $third = self::createStub(Warning::class);
        $derivation->stored($outer, static function () use ($derivation, $inner, $first, $second, $third): void {
            self::assertTrue($derivation->inProgram());
            $derivation->warn($first);
            $derivation->stored($inner, static function () use ($derivation, $second): void {
                $derivation->warn($second);
            });
            self::assertTrue($derivation->inProgram());
            $derivation->warn($third);
        });
        self::assertFalse($derivation->inProgram());
        self::assertSame($outer, $derivation->facts()->warningInstruction($first));
        self::assertSame($inner, $derivation->facts()->warningInstruction($second));
        self::assertSame($outer, $derivation->facts()->warningInstruction($third));
    }

    public function testStoredRestoresScopeWhenTheMemberCannotBeDerived(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));
        $member = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block();
        $warning = self::createStub(Warning::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('scope exit');
        try {
            $derivation->stored($member, static function (): void {
                throw new RuntimeException('scope exit');
            });
        } finally {
            self::assertFalse($derivation->inProgram());
            $derivation->warn($warning);
            self::assertNull($derivation->facts()->warningInstruction($warning));
        }
    }

    public function testDeferredCanKeepOnlyTheWarningsOfAnUnusedRole(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context([]));
        $kept = self::createStub(Warning::class);
        $omitted = self::createStub(Warning::class);
        $node = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1');
        $derivation->deferred($node, static function () use ($derivation, $kept, $omitted): void {
            $derivation->warn($kept);
            $derivation->warn($omitted);
        }, static fn (Warning $warning): bool => $warning === $kept);
        $derivation->warn($omitted);
        self::assertSame([$kept, $omitted], $derivation->facts()->warnings);
    }


    public function testStatementsRetainEachNestedResult(): void
    {
        $context = (new Semantics(Dialect::Sqlite))->context();
        $first = new Select([new ResultColumn(new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1'), new Name('a'))]);
        $second = new Select([new ResultColumn(new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('2'), new Name('b'))]);
        $derivation = new Derivation($context);
        $derivation->statement(new \SqlSemantics\Statement\Script([$first, $second]));
        $statements = $derivation->statements();
        self::assertCount(3, $statements);
        self::assertSame($first, $statements[0][0]);
        self::assertSame($context, $statements[0][1]);
        self::assertSame('a', $statements[0][2]->output?->fields()?->at(0)->name?->value);
        self::assertSame('b', $statements[1][2]->output?->fields()?->at(0)->name?->value);
    }

}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisContext as Context;
use SqlSemantics\AnalysisException;
use SqlSemantics\Analyzer;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\Sqlite\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\Sqlite\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\Sqlite\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\AnalysisException as SyntaxError;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\SemanticException;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\SemanticsIR;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Semantics::class)]
#[Medium]
final class SemanticsTest extends TestCase
{
    /**
     * @throws AnalysisException
     */
    public function testAnalyzeReturnsTheCompleteValueThroughTheCommonInterface(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE t (id INTEGER)']);
        $analyze = static fn (Analyzer $analyzer): SemanticsIR => $analyzer->analyze('SELECT id, t.id FROM t', $context);
        $query = $analyze($semantics);

        self::assertInstanceOf(Select::class, $query);
        self::assertNotNull($query->source);
        self::assertCount(1, $query->source->columns);
        self::assertEquals($query->columns[0]->expression, $query->columns[1]->expression);
    }

    public function testAnalyzePublishesACompleteProjection(): void
    {
        $query = (new Semantics())->analyze('SELECT 42 AS answer');

        self::assertSame('SELECT 42 AS "answer"', $query->toString());
    }

    public function testAnalyzeRejectsAnOperationOutsideTheCompleteDomain(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE records (a)']);
        $this->expectExceptionMessage('A checked trigger program currently requires VALUES insertions.');

        $semantics->analyze('CREATE TRIGGER t AFTER INSERT ON records BEGIN SELECT 1; END', $context);
    }

    public function testInspectLeavesMissingInputsInAnOpenContextAsFacts(): void
    {
        $query = (new Inspection())->inspect('SELECT id FROM t');

        $type = $query->field(0)->type;
        self::assertInstanceOf(Dependent::class, $type);
        self::assertSame('the declaration of relation t', $type->missing[0]->describe());
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testThrowsAnAnalysisExceptionForSqlOutsideTheGrammar(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics())->analyze('SELECT FROM WHERE');
    }

    public function testThrowsAnExceptionWithTheSemanticDiagnostics(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE t (id INTEGER)']);

        try {
            $semantics->analyze('SELECT missing FROM t', $context);
            self::fail('Expected a semantic error.');
        } catch (AnalysisException $error) {
            self::assertInstanceOf(SemanticException::class, $error);
            self::assertStringContainsString('missing', $error->getMessage());
            self::assertStringContainsString('missing', $error->diagnostics[0]->message());
        }
    }

    public function testRejectsAContextOfAnotherImplementation(): void
    {
        $this->expectExceptionMessage('The context must belong to the selected database.');

        (new Semantics())->analyze('SELECT 1', new class () implements Context {
        });
    }

    public function testAnalyzeAllThrowsWhenAnyStatementHasASemanticError(): void
    {
        $this->expectException(SemanticException::class);

        (new Semantics())->analyzeAll('SELECT 1; SELECT id FROM t', (new Semantics())->context([]));
    }

    public function testProfileIsFixedByTheDialectAndTheParameterStyle(): void
    {
        $semantics = new Semantics('sqlite-3.47.2', null, ParameterStyle::Named);

        self::assertSame(GrammarRelease::Sqlite3472, $semantics->profile()->grammar);
        self::assertSame(ParameterStyle::Named, $semantics->profile()->parameters);
        self::assertSame($semantics->profile(), $semantics->profile());
    }

    public function testContextIsOpenWithoutDeclarationsAndCompleteWithAList(): void
    {
        $semantics = new Semantics();
        $open = $semantics->context();
        $complete = $semantics->context([]);
        $partial = $semantics->context([], false);

        self::assertInstanceOf(AnalysisContext::class, $open);
        self::assertInstanceOf(AnalysisContext::class, $complete);
        self::assertInstanceOf(AnalysisContext::class, $partial);
        self::assertFalse($open->complete);
        self::assertTrue($complete->complete);
        self::assertFalse($partial->complete);
        self::assertSame([], $open->tables);
    }

    public function testContextCollectsTheDeclarationsOfOperationsAndTables(): void
    {
        $semantics = new Inspection();
        $operation = $semantics->inspect('CREATE TABLE t (a INTEGER)');
        $table = $semantics->inspect('CREATE TABLE u (b TEXT)')->declarations()[0];

        $context = $semantics->context([$operation, $table, $semantics->inspect('DELETE FROM t')]);

        self::assertSame([$operation->declarations()[0], $table], $context->tables);
    }

    public function testContextRefusesAnOperationOfAnotherProfile(): void
    {
        $native = new Inspection();
        $named = new Inspection(null, null, ParameterStyle::Named);
        $operation = $named->inspect('CREATE TABLE t (a INTEGER)');

        $this->expectExceptionMessage('A declaring operation must belong to the selected language profile.');

        $native->context([$operation]);
    }

    public function testInspectReturnsAnOperationBoundToTheGivenDeclarations(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER)');

        $declared = $semantics->inspect('SELECT a FROM t', [$table]);
        $prepared = $semantics->inspect('SELECT a FROM t', $semantics->context([$table]));
        $open = $semantics->inspect('SELECT a FROM t');

        self::assertInstanceOf(DeclaredTable::class, $declared->facts->relation($declared->singleNamedInput())->table);
        self::assertInstanceOf(DeclaredTable::class, $prepared->facts->relation($prepared->singleNamedInput())->table);
        self::assertInstanceOf(UndeclaredTable::class, $open->facts->relation($open->singleNamedInput())->table);
        self::assertSame('SELECT a FROM t', $declared->toString());
    }

    public function testInspectRendersFromTheStructureAndChecksTheTokens(): void
    {
        $semantics = new Inspection();

        $operation = $semantics->inspect("select   t.a as \"x\", 'it''s' from t as T /* note */ where a is not null");

        self::assertSame("SELECT t.a AS x, 'it''s' FROM t AS T WHERE a IS NOT NULL", $operation->toString());
        self::assertSame('x', $operation->field(0)->name?->value);
    }

    public function testInspectRefusesAContextOfAnotherProfile(): void
    {
        $semantics = new Inspection();
        $foreign = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472, new LexicalSettings(), ParameterStyle::Named), [new Name('main')]);

        $this->expectExceptionMessage('The context must match the selected language profile.');

        $semantics->inspect('SELECT 1', $foreign);
    }

    public function testAnalyzeRejectsSqlOutsideTheGrammar(): void
    {
        $this->expectException(SyntaxError::class);

        (new Semantics())->analyze('SELECT FROM WHERE');
    }

    public function testAnalyzeAllPublishesCompleteValuesAgainstTheSameContext(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE t (a INTEGER)']);

        $results = $semantics->analyzeAll('SELECT a FROM t; SELECT a AS value FROM t', $context);

        self::assertCount(2, $results);
        self::assertInstanceOf(Select::class, $results[0]);
        self::assertInstanceOf(Select::class, $results[1]);
        self::assertSame('a', $results[0]->columns[0]->name->value);
        self::assertSame('value', $results[1]->columns[0]->name->value);
        self::assertEquals($results[0]->source, $results[1]->source);
        self::assertSame([], $semantics->analyzeAll(''));
    }

    public function testSplitFindsTheStatementBoundariesAsTheDatabaseDoes(): void
    {
        $semantics = new Semantics();

        self::assertSame(['SELECT 1;', " SELECT ';'"], $semantics->split("SELECT 1; SELECT ';'"));
        self::assertSame(['SELECT 1;', ' SELECT 2; '], $semantics->split('SELECT 1; SELECT 2; '));
    }

    public function testSplitRejectsATailOutsideTheGrammar(): void
    {
        $this->expectException(SyntaxError::class);

        (new Semantics())->split('SELECT 1; SELECT');
    }

    public function testDiagnoseRetainsDiagnosticsForStatementValidation(): void
    {
        $operation = (new Inspection())->diagnose('SELECT * FROM t', []);

        self::assertSame('SELECT * FROM t', $operation->toString());
        self::assertNotEmpty($operation->facts->diagnostics);
    }
}

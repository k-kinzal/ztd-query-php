<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisContext as Context;
use SqlSemantics\AnalysisException;
use SqlSemantics\Analyzer;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\Sqlite\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\Sqlite\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\Sqlite\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\AnalysisException as SyntaxError;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\SemanticException;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Operation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Query;

#[CoversClass(Semantics::class)]
#[Medium]
final class SemanticsTest extends TestCase
{
    /**
     * @throws AnalysisException
     */
    public function testAnalyzeReturnsTheDatabaseOperationThroughTheCommonInterface(): void
    {
        $semantics = new Semantics();
        $table = $semantics->analyze('CREATE TABLE t (id INTEGER)');
        $context = $semantics->context([$table]);
        $analyze = static fn (Analyzer $analyzer): Query => $analyzer->analyze('select id from t', $context);
        $query = $analyze($semantics);

        self::assertInstanceOf(Operation::class, $query);
        self::assertSame('SELECT id FROM t', $query->toString());
        self::assertSame($table->declarations()[0]->columns[0], $query->field('id')->column());
        self::assertSame($context, $query->context);
    }

    public function testLeavesMissingInputsInAnOpenContextAsFacts(): void
    {
        $query = (new Semantics())->analyze('SELECT id FROM t');

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
        $table = $semantics->analyze('CREATE TABLE t (id INTEGER)');

        try {
            $semantics->analyze('SELECT missing FROM t', [$table]);
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

        (new Semantics())->analyzeAll('CREATE TABLE t (id INTEGER); SELECT id FROM t', []);
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

        self::assertFalse($semantics->context()->complete);
        self::assertTrue($semantics->context([])->complete);
        self::assertFalse($semantics->context([], false)->complete);
        self::assertSame([], $semantics->context()->tables);
    }

    public function testContextCollectsTheDeclarationsOfOperationsAndTables(): void
    {
        $semantics = new Semantics();
        $operation = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $table = $semantics->analyze('CREATE TABLE u (b TEXT)')->declarations()[0];

        $context = $semantics->context([$operation, $table, $semantics->analyze('DELETE FROM t')]);

        self::assertSame([$operation->declarations()[0], $table], $context->tables);
    }

    public function testContextRefusesAnOperationOfAnotherProfile(): void
    {
        $native = new Semantics();
        $named = new Semantics(null, null, ParameterStyle::Named);
        $operation = $named->analyze('CREATE TABLE t (a INTEGER)');

        $this->expectExceptionMessage('A declaring operation must belong to the selected language profile.');

        $native->context([$operation]);
    }

    public function testAnalyzeReturnsAnOperationBoundToTheGivenDeclarations(): void
    {
        $semantics = new Semantics();
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');

        $declared = $semantics->analyze('SELECT a FROM t', [$table]);
        $prepared = $semantics->analyze('SELECT a FROM t', $semantics->context([$table]));
        $open = $semantics->analyze('SELECT a FROM t');

        self::assertInstanceOf(DeclaredTable::class, $declared->facts->relation($declared->singleNamedInput())->table);
        self::assertInstanceOf(DeclaredTable::class, $prepared->facts->relation($prepared->singleNamedInput())->table);
        self::assertInstanceOf(UndeclaredTable::class, $open->facts->relation($open->singleNamedInput())->table);
        self::assertSame('SELECT a FROM t', $declared->toString());
    }

    public function testAnalyzeRendersFromTheStructureAndChecksTheTokens(): void
    {
        $semantics = new Semantics();

        $operation = $semantics->analyze("select   t.a as \"x\", 'it''s' from t as T /* note */ where a is not null");

        self::assertSame("SELECT t.a AS x, 'it''s' FROM t AS T WHERE a IS NOT NULL", $operation->toString());
        self::assertSame('x', $operation->field(0)->name?->value);
    }

    public function testAnalyzeRefusesAContextOfAnotherProfile(): void
    {
        $semantics = new Semantics();
        $foreign = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472, new LexicalSettings(), ParameterStyle::Named), [new Name('main')]);

        $this->expectExceptionMessage('The context must match the selected language profile.');

        $semantics->analyze('SELECT 1', $foreign);
    }

    public function testAnalyzeRejectsSqlOutsideTheGrammar(): void
    {
        $this->expectException(SyntaxError::class);

        (new Semantics())->analyze('SELECT FROM WHERE');
    }

    public function testAnalyzeAllDerivesEveryStatementAgainstTheSameContext(): void
    {
        $semantics = new Semantics();

        $operations = $semantics->analyzeAll('CREATE TABLE t (a INTEGER); SELECT a FROM t; DELETE FROM t');

        self::assertCount(3, $operations);
        self::assertContainsOnlyInstancesOf(Operation::class, $operations);
        self::assertSame(['CREATE TABLE t (a INTEGER)', 'SELECT a FROM t', 'DELETE FROM t'], array_map(static fn (Operation $operation): string => $operation->toString(), $operations));
        self::assertInstanceOf(UndeclaredTable::class, $operations[1]->facts->relation($operations[1]->singleNamedInput())->table);
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

    public function testInspectRetainsDiagnosticsForStatementValidation(): void
    {
        $operation = (new Semantics())->inspect('SELECT * FROM t', []);

        self::assertSame('SELECT * FROM t', $operation->toString());
        self::assertNotEmpty($operation->facts->diagnostics);
    }
}

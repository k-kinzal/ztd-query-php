<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisContext;
use SqlSemantics\AnalysisException;
use SqlSemantics\Analyzer;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Diagnostic\SemanticException;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\SemanticsIR;
use SqlSemantics\Testing\MySql\Inspection;

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
        $context = $semantics->context(['CREATE TABLE app.t (id INTEGER)']);
        $analyze = static fn (Analyzer $analyzer): SemanticsIR => $analyzer->analyze('SELECT id, t.id FROM app.t', $context);
        $query = $analyze($semantics);

        self::assertInstanceOf(Select::class, $query);
        self::assertInstanceOf(TableSource::class, $query->source);
        self::assertCount(1, $query->source->columns);
        self::assertEquals($query->columns[0]->expression, $query->columns[1]->expression);
    }

    public function testAnalyzePublishesACompleteProjection(): void
    {
        $ir = (new Semantics())->analyze('SELECT 42 AS answer');

        self::assertSame('SELECT 42 AS `answer`', $ir->toString());
    }

    public function testAnalyzeRejectsAnOperationOutsideTheCompleteDomain(): void
    {
        $this->expectExceptionMessage('Complete MySQL IR currently supports a single projection SELECT.');

        (new Semantics())->analyze('CREATE SCHEMA app');
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

        (new Semantics())->analyze('SELECT 1', new class () implements AnalysisContext {
        });
    }

    public function testAnalyzeAllThrowsWhenAnyStatementHasASemanticError(): void
    {
        $this->expectException(SemanticException::class);

        (new Semantics())->analyzeAll('SELECT 1; SELECT id FROM t', (new Semantics())->context([]));
    }

    public function testProfileIsFixedForAnAnalyzer(): void
    {
        $semantics = new Semantics();

        self::assertSame($semantics->profile(), $semantics->profile());
    }

    public function testContextPreparesDeclarationsAndChecksPreparedContexts(): void
    {
        $semantics = new Inspection();
        $declaration = $semantics->inspect('CREATE TABLE t (id INTEGER)');
        $context = $semantics->context([$declaration]);

        self::assertSame($declaration->declarations(), $context->tables);
        self::assertSame($context, $semantics->context($context));
    }

    public function testSplitFindsTheStatementBoundaries(): void
    {
        $semantics = new Semantics();

        self::assertSame(['SELECT 1;', ' SELECT 2;'], $semantics->split('SELECT 1; SELECT 2;'));
    }

    public function testDiagnoseRetainsDiagnosticsForStatementValidation(): void
    {
        $operation = (new Inspection())->diagnose('SELECT * FROM t', []);

        self::assertSame('SELECT * FROM t', $operation->toString());
        self::assertNotEmpty($operation->facts->diagnostics);
    }
}

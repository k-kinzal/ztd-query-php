<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisContext;
use SqlSemantics\AnalysisException;
use SqlSemantics\Analyzer;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\SemanticException;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Operation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\PostgreSql\Semantics;
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

    public function testKeepsWarningsOnTheQuery(): void
    {
        $query = (new Semantics())->analyze('CREATE OPERATOR === (rightarg = int4, function = int4eq, colour = red)');

        self::assertSame('operator attribute "colour" not recognized', $query->facts->diagnostics[0]->message());
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

        (new Semantics())->analyzeAll('CREATE TABLE t (id INTEGER); SELECT id FROM t', []);
    }

    public function testProfileIsFixedForAnAnalyzer(): void
    {
        $semantics = new Semantics();

        self::assertSame($semantics->profile(), $semantics->profile());
    }

    public function testContextPreparesDeclarationsAndChecksPreparedContexts(): void
    {
        $semantics = new Semantics();
        $declaration = $semantics->analyze('CREATE TABLE t (id INTEGER)');
        $context = $semantics->context([$declaration]);

        self::assertSame($declaration->declarations(), $context->tables);
        self::assertSame($context, $semantics->context($context));
    }

    public function testSplitFindsTheStatementBoundaries(): void
    {
        $semantics = new Semantics();

        self::assertSame(['SELECT 1;', ' SELECT 2;'], $semantics->split('SELECT 1; SELECT 2;'));
    }

    public function testInspectRetainsDiagnosticsForStatementValidation(): void
    {
        $operation = (new Semantics())->inspect('SELECT * FROM t', []);

        self::assertSame('SELECT * FROM t', $operation->toString());
        self::assertNotEmpty($operation->facts->diagnostics);
    }
}

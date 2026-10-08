<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\SourceException;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(Semantics::class)]
#[Medium]
final class SemanticsTest extends TestCase
{
    public function testProfileIsFixedByTheDialectAndTheParameterStyle(): void
    {
        $semantics = new Semantics(Dialect::Sqlite, 'sqlite-3.47.2', null, ParameterStyle::Named);

        self::assertSame(GrammarRelease::Sqlite3472, $semantics->profile()->grammar);
        self::assertSame(ParameterStyle::Named, $semantics->profile()->parameters);
        self::assertSame($semantics->profile(), $semantics->profile());
    }

    public function testParserAnswersOneParserWhoseTreesTheFacadeAnalyzes(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        $tree = $semantics->parser()->parse('select   2');

        self::assertSame($semantics->parser(), $semantics->parser());
        self::assertSame('SELECT 2', $semantics->analyze($tree)->toString());
    }

    public function testParserRefusesSqlOutsideTheGrammarOfTheProfile(): void
    {
        $this->expectException(SourceException::class);

        (new Semantics(Dialect::Sqlite))->parser()->parse('SELECT FROM WHERE');
    }

    public function testContextIsOpenWithoutDeclarationsAndCompleteWithAList(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertFalse($semantics->context()->complete);
        self::assertTrue($semantics->context([])->complete);
        self::assertFalse($semantics->context([], false)->complete);
        self::assertSame([], $semantics->context()->tables);
    }

    public function testContextCollectsTheDeclarationsOfOperationsAndTables(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $table = $semantics->analyze('CREATE TABLE u (b TEXT)')->declarations()[0];

        $context = $semantics->context([$operation, $table, $semantics->analyze('DELETE FROM t')]);

        self::assertSame([$operation->declarations()[0], $table], $context->tables);
    }

    public function testContextAcceptsAnOperationOfAnotherParameterStyle(): void
    {
        $native = new Semantics(Dialect::Sqlite);
        $named = new Semantics(Dialect::Sqlite, null, null, ParameterStyle::Named);
        $operation = $named->analyze('CREATE TABLE t (a INTEGER)');

        self::assertSame($operation->declarations(), $native->context([$operation])->tables);
    }

    public function testContextRefusesAnOperationOfAnotherRelease(): void
    {
        $operation = (new Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze('CREATE TABLE t (a INT)');

        $this->expectExceptionMessage('A declaring operation must belong to the grammar release of the selected language profile.');

        (new Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.4.7'))->context([$operation]);
    }

    public function testAnalyzeReturnsAnOperationBoundToTheGivenDeclarations(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');

        $declared = $semantics->analyze('SELECT a FROM t', [$table]);
        $prepared = $semantics->analyze('SELECT a FROM t', $semantics->context([$table]));
        $open = $semantics->analyze('SELECT a FROM t');
        $missing = $semantics->analyze('SELECT a FROM t', []);

        self::assertInstanceOf(DeclaredTable::class, $declared->facts->relation($declared->singleNamedInput())->table);
        self::assertInstanceOf(DeclaredTable::class, $prepared->facts->relation($prepared->singleNamedInput())->table);
        self::assertInstanceOf(UndeclaredTable::class, $open->facts->relation($open->singleNamedInput())->table);
        self::assertInstanceOf(MissingTable::class, $missing->facts->relation($missing->singleNamedInput())->table);
        self::assertSame('SELECT a FROM t', $declared->toString());
    }

    public function testAnalyzeRendersFromTheStructureAndChecksTheTokens(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        $operation = $semantics->analyze("select   t.a as \"x\", 'it''s' from t as T /* note */ where a is not null");

        self::assertSame("SELECT t.a AS x, 'it''s' FROM t AS T WHERE a IS NOT NULL", $operation->toString());
        self::assertSame('x', $operation->field(0)->name?->value);
    }

    public function testAnalyzeRefusesAContextOfAnotherProfile(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $foreign = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472, new LexicalSettings(), ParameterStyle::Named), [new Name('main')]);

        $this->expectExceptionMessage('The context must match the selected language profile.');

        $semantics->analyze('SELECT 1', $foreign);
    }

    public function testAnalyzeRejectsSqlOutsideTheGrammar(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::Sqlite))->analyze('SELECT FROM WHERE');
    }

    public function testAnalyzeAllDerivesEveryStatementAgainstTheSameContext(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        $operations = $semantics->analyzeAll('CREATE TABLE t (a INTEGER); SELECT a FROM t; DELETE FROM t', []);

        self::assertCount(3, $operations);
        self::assertContainsOnlyInstancesOf(Operation::class, $operations);
        self::assertSame(['CREATE TABLE t (a INTEGER)', 'SELECT a FROM t', 'DELETE FROM t'], array_map(static fn (Operation $operation): string => $operation->toString(), $operations));
        self::assertInstanceOf(MissingTable::class, $operations[1]->facts->relation($operations[1]->singleNamedInput())->table);
        self::assertSame([], $semantics->analyzeAll(''));
    }

    public function testSplitFindsTheStatementBoundariesAsTheDatabaseDoes(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertSame(['SELECT 1;', " SELECT ';'"], $semantics->split("SELECT 1; SELECT ';'"));
        self::assertSame(['SELECT 1;', ' SELECT 2; '], $semantics->split('SELECT 1; SELECT 2; '));
    }

    public function testSplitRejectsATailOutsideTheGrammar(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::Sqlite))->split('SELECT 1; SELECT');
    }
}

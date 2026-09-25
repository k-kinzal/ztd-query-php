<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\BoundStatement::class)]
#[UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[UsesClass(Binder::class)]
#[UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[UsesClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[UsesClass(\SqlSemantics\Binding\Scope::class)]
#[UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[UsesClass(Dialect::class)]
#[UsesClass(\SqlSemantics\Model\BoundQuery::class)]
#[UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[UsesClass(\SqlSemantics\Model\Expression::class)]
#[UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[UsesClass(\SqlSemantics\Model\Join::class)]
#[UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[UsesClass(\SqlSemantics\Model\Ordering::class)]
#[UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[UsesClass(\SqlSemantics\Model\TableUse::class)]
#[UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[UsesClass(\SqlSemantics\Schema::class)]
#[UsesClass(SchemaBuilder::class)]
#[UsesClass(\SqlSemantics\SemanticException::class)]
#[UsesClass(\SqlSemantics\Type\Nullability::class)]
#[UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
#[UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\IndexEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\IndexBinder::class)]
#[UsesClass(\SqlSemantics\Schema\FunctionSignature::class)]
#[UsesClass(\SqlSemantics\Schema\Functions\Builtins::class)]
#[UsesClass(\SqlSemantics\Schema\Functions\BuiltinResult::class)]
#[UsesClass(\SqlSemantics\Schema\Functions\SignatureInvariant::class)]
#[UsesClass(\SqlSemantics\Schema\IndexDefinition::class)]
#[UsesClass(\SqlSemantics\Schema\IndexElement::class)]
#[UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[UsesClass(\SqlSemantics\Serializer::class)]
#[UsesClass(\SqlSemantics\StatementFactory::class)]
#[UsesClass(\SqlSemantics\SimpleSerializer::class)]
#[UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[UsesClass(\SqlSemantics\Model\Transformation\Context::class)]
#[UsesClass(\SqlSemantics\Model\Statement\InsertStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\ConfigurationStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\TableStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\DeleteStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\MergeStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\ValuesStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\UpdateStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\CompoundStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\CreateIndexStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\CreateTableStatement::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Literal::class)]
#[UsesClass(\SqlSemantics\Model\Sql\ExpressionFactory::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Build::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Parts::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Atom::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Tree::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Source::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Format::class)]
final class BoundStatementTest extends TestCase
{
    public function testRetainsCommandSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('/* source */ BEGIN');
        self::assertSame('BEGIN', $statement->kind->value);
        self::assertSame('/* source */ BEGIN', $statement->source->toString());
        self::assertNotInstanceOf(\SqlSemantics\Model\ResultStatement::class, $statement);
    }
    public function testToStringUsesCompactLayout(): void
    {
        $sql = '/* retained */ SELECT   1 -- trailing';
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('SELECT 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($sql, $statement->source->toString());
    }


    public function testToStringKeepsDiagnosticsAndTheirSourceLocations(): void
    {
        $sql = '/* retained */ SELECT   missing -- trailing';
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql, strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('SELECT "missing" AS "missing"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($sql, $statement->source->toString());
        self::assertSame(['unknown-column'], array_column($statement->diagnostics, 'reason'));
        self::assertSame($statement->outputs[0]->expression->source, $statement->diagnostics[0]->source);
    }
    public function testReplaceExpressionReturnsReboundIndependentSnapshot(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $changed = $statement->replaceExpression($statement->outputs[0]->expression, \SqlSemantics\Model\Expression::binary('+', \SqlSemantics\Model\Expression::literal(2, Dialect::Sqlite), \SqlSemantics\Model\Expression::literal(3, Dialect::Sqlite)));
        self::assertSame('+', $changed->outputs[0]->expression->spelling());
        self::assertSame('1', $statement->outputs[0]->expression->spelling());
        self::assertEquals($changed->withVerbatimSource(), $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($changed)));
    }
    public function testReplaceExpressionValidatesAnUnresolvedStatement(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('SELECT missing FROM t', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $repaired = $statement->replaceExpression($statement->outputs[0]->expression, \SqlSemantics\Model\Expression::reference(['id'], Dialect::PostgreSql));
        self::assertSame([], $repaired->diagnostics);
        self::assertSame('id', $repaired->outputs[0]->expression->columnBinding()?->column->name);
        self::assertSame(['unknown-column'], array_column($statement->diagnostics, 'reason'));
        self::assertSame('SELECT "missing" AS "missing" FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $this->expectException(\SqlSemantics\SemanticException::class);
        $statement->replaceExpression($statement->outputs[0]->expression, \SqlSemantics\Model\Expression::reference(['still_missing'], Dialect::PostgreSql));
    }

    public function testWithContextPreservesItsStatementSnapshot(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $statement = (new Binder($schema))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $changed = $statement->withContext(new \SqlSemantics\Binding\Editing\StatementContext($schema));
        self::assertNotSame($statement, $changed);
        self::assertSame($statement->outputs, $changed->outputs);
        self::assertSame($statement->origin->source, $changed->origin->source);
    }
    public function testReplaceExpressionRevalidatesProjectionOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $boundQuery1 = $binder->bind('SELECT 2');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        $changed = $statement->replaceExpression($statement->outputs[0]->expression, $boundQuery1->outputs[0]->expression);
        self::assertSame('2', $changed->outputs[0]->expression->spelling());
        self::assertSame('1', $statement->outputs[0]->expression->spelling());
    }
    public function testReplaceExpressionRejectsForeignOwnership(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $boundQuery1 = $binder->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        $foreign = $boundQuery1->outputs[0]->expression;
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->replaceExpression($foreign, \SqlSemantics\Model\Expression::literal(2, Dialect::PostgreSql));
    }
    public function testReplaceExpressionPreservesPrecedenceAndDropsTrivia(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER,b INTEGER)'));
        $statement = $binder->bind('/* original */ SELECT a*2 FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $replacement = \SqlSemantics\Model\Expression::binary('+', \SqlSemantics\Model\Expression::reference(['b'], Dialect::PostgreSql), \SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql));
        $changed = $statement->replaceExpression($statement->outputs[0]->expression->inputs()[0], $replacement);
        self::assertSame('*', $changed->outputs[0]->expression->spelling());
        self::assertSame('+', $changed->outputs[0]->expression->inputs()[0]->spelling());
        self::assertSame('b', $changed->outputs[0]->expression->lineage()[0]->column->name);
        self::assertStringNotContainsString('original', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertStringContainsString('original', $statement->source->toString());
    }

    public function testReplaceExpressionRetainsCteAliasOwnership(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH q(n) AS (SELECT 1) SELECT n FROM q');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertNotNull($statement->ctes);
        $cte = $statement->ctes->definitions[0]->query;
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $cte);
        self::assertNull($cte->outputs[0]->name);
        self::assertSame(['n'], $statement->ctes->definitions[0]->columns);
        $changed = $cte->replaceExpression($cte->outputs[0]->expression, \SqlSemantics\Model\Expression::literal(2, Dialect::PostgreSql));
        self::assertSame('2', $changed->outputs[0]->expression->spelling());
        self::assertSame('1', $cte->outputs[0]->expression->spelling());
    }

    public function testReplaceExpressionChangesOneExpandedStarOutput(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER,n INTEGER)')))->bind('SELECT * FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $changed = $statement->replaceExpression($statement->outputs[0]->expression, \SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql));
        self::assertSame('1', $changed->outputs[0]->expression->spelling());
        self::assertSame('n', $changed->outputs[1]->expression->columnBinding()?->column->name);
        self::assertCount(2, $changed->outputs);
    }

    public function testReplaceExpressionKeepsOtherNestedStarOutputs(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER,n INTEGER)')))->bind('WITH q AS (SELECT * FROM t) SELECT id,n FROM q');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertNotNull($statement->ctes);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement->ctes->definitions[0]->query);
        $changed = $statement->replaceExpression($statement->ctes->definitions[0]->query->outputs[0]->expression, \SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql));
        self::assertNotNull($changed->ctes);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $changed->ctes->definitions[0]->query);
        self::assertCount(2, $changed->ctes->definitions[0]->query->outputs);
        self::assertCount(2, $changed->outputs);
        self::assertSame('n', $changed->ctes->definitions[0]->query->outputs[1]->expression->columnBinding()?->column->name);
        self::assertSame('1', $changed->ctes->definitions[0]->query->outputs[0]->expression->spelling());
    }

    public function testReplaceExpressionUsesConfigurationValueGrammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SET LOCAL work_mem='64MB'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $statement->settings[0]);
        $changed = $statement->replaceExpression($statement->settings[0]->values[0], \SqlSemantics\Model\Expression::literal('128MB', Dialect::PostgreSql));
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $changed->settings[0]);
        self::assertSame("'128MB'", $changed->settings[0]->values[0]->spelling());
        self::assertSame('local', $changed->settings[0]->scope->value);
    }
    public function testReplaceExpressionRejectsMixedDatabaseLanguages(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->replaceExpression($statement->outputs[0]->expression, \SqlSemantics\Model\Expression::literal(2, Dialect::MySql));
    }

    public function testCteAliasesKeepTheQueryProjectionSeparate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH q(new_name) AS (SELECT 1 AS old_name) SELECT new_name FROM q');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertNotNull($statement->ctes);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement->ctes->definitions[0]->query);
        self::assertSame('old_name', $statement->ctes->definitions[0]->query->outputs[0]->name);
        self::assertSame(['new_name'], $statement->ctes->definitions[0]->columns);
        self::assertSame('new_name', $statement->outputs[0]->name);
    }

    public function testWithDiagnosticsReplacesProvenanceWithoutChangingTheStructure(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t');
        $changed = $statement->withDiagnostics([new \SqlSemantics\Model\Diagnostic('custom', 'message', $statement->source)]);
        self::assertSame(['custom'], array_column($changed->diagnostics, 'reason'));
        self::assertSame($statement->scopeId, $changed->scopeId);
        self::assertSame($statement->source, $changed->source);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame([], $statement->diagnostics);
        self::assertSame([], $changed->withDiagnostics([])->diagnostics);
    }

    #[DataProvider('providerBoundText')]
    public function testToStringWritesBackTheBoundText(Dialect $dialect, string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t (a INT, b INT)')))->bind($sql);
        self::assertSame($sql, $statement->toString());
        self::assertTrue($statement->origin->verbatim);
    }

    /**
     * @return iterable<string, array{Dialect, string}>
     */
    public static function providerBoundText(): iterable
    {
        yield 'mysql comments and case' => [Dialect::MySql, "/* lead */ select   A , b\nFROM t -- tail\n"];
        yield 'mysql write' => [Dialect::MySql, 'insert  INTO t ( a )values(1) ;'];
        yield 'postgresql spacing' => [Dialect::PostgreSql, "  Select a+1  AS x from   t WHERE b>0\t"];
        yield 'postgresql definition' => [Dialect::PostgreSql, 'create TABLE  u( id int  PRIMARY key )'];
        yield 'sqlite command' => [Dialect::Sqlite, "-- lead\nbegin   IMMEDIATE ;"];
        yield 'sqlite update' => [Dialect::Sqlite, 'UPDATE t SET a = 2 /* why */ WHERE b = 1'];
    }

    public function testToStringWritesATransformedStatementWithTheSimpleSerializer(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT, b INT)')))->bind('select a  from t   -- all rows');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $changed = $statement->withWhere(\SqlSemantics\Model\Expression::reference(['b'], Dialect::MySql));
        self::assertFalse($changed->origin->verbatim);
        self::assertSame('SELECT `a` AS `a` FROM `t` WHERE `b`', $changed->toString());
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($changed), $changed->toString());
        self::assertSame('select a  from t   -- all rows', $statement->toString());
    }

    public function testToStringWritesAReplacedExpressionWithTheSimpleSerializer(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('select  1 ;');
        $changed = $statement->replaceExpression(\SqlSemantics\Model\Traversal\Expressions::all($statement)[0], \SqlSemantics\Model\Expression::literal(2, Dialect::Sqlite));
        self::assertSame('SELECT 2', $changed->toString());
        self::assertSame('select  1 ;', $statement->toString());
    }

    public function testToStringWritesAConstructedStatementWithTheSimpleSerializer(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $factory = new \SqlSemantics\StatementFactory($schema);
        $constructed = $factory->select([new \SqlSemantics\Model\OutputColumn(0, 'answer', \SqlSemantics\Model\Expression::literal(42, Dialect::PostgreSql))]);
        self::assertFalse($constructed->origin->verbatim);
        self::assertSame('SELECT 42 AS "answer"', $constructed->toString());
        $validated = $factory->create((new Binder($schema))->bind('select   42 as answer'));
        self::assertSame('SELECT 42 AS "answer"', $validated->toString());
    }

    public function testWithVerbatimSourceWritesTheSourceText(): void
    {
        $constructed = (new \SqlSemantics\StatementFactory((new SchemaBuilder(Dialect::Sqlite))->build()))->select([new \SqlSemantics\Model\OutputColumn(0, 'n', \SqlSemantics\Model\Expression::literal(7, Dialect::Sqlite))]);
        $verbatim = $constructed->withVerbatimSource();
        self::assertNotSame($constructed, $verbatim);
        self::assertTrue($verbatim->origin->verbatim);
        self::assertSame($constructed->source->toString(), $verbatim->toString());
        self::assertFalse($constructed->origin->verbatim);
    }

    public function testWithDiagnosticsKeepsTheBoundText(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('select  missing', strict: false);
        $changed = $statement->withDiagnostics([]);
        self::assertSame([], $changed->diagnostics);
        self::assertTrue($changed->origin->verbatim);
        self::assertSame('select  missing', $changed->toString());
    }

    public function testWithContextKeepsTheBoundText(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $statement = (new Binder($schema))->bind('Select 1  /* one */');
        $changed = $statement->withContext(new \SqlSemantics\Binding\Editing\StatementContext($schema));
        self::assertTrue($changed->origin->verbatim);
        self::assertSame('Select 1  /* one */', $changed->toString());
    }
}

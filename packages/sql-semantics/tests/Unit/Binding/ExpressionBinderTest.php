<?php

declare(strict_types=1);

namespace Tests\Unit\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Binding\TypeResolution::class)]
#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Model\Join::class)]
#[CoversClass(\SqlSemantics\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Model\BoundQuery::class)]
#[CoversClass(\SqlSemantics\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Schema::class)]
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\FunctionSignature::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\Builtins::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\BuiltinResult::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\SignatureInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexElement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Serializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\StatementFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SimpleSerializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\Context::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\InsertStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ConfigurationStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\TableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\DeleteStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\MergeStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ValuesStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\UpdateStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CompoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CreateIndexStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CreateTableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Literal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\ExpressionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Build::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Parts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Atom::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Source::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Format::class)]
final class ExpressionBinderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testOperationPreservesOperatorPrecedence(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build();
        $statement = (new Binder($schema))->bind('SELECT 1+2*3 AS result');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('+', $statement->outputs[0]->expression->spelling());
        self::assertSame('*', $statement->outputs[0]->expression->inputs()[1]->spelling());
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindsParenthesesAndUnaryOperators(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT -(score + 1) AS negative FROM users WHERE NOT (score > 0)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('-', $statement->outputs[0]->expression->spelling());
        self::assertSame('+', $statement->outputs[0]->expression->inputs()[0]->spelling());
        self::assertSame('NOT', $statement->where?->spelling());
    }

    public function testTokenBindsBitLiteral(): void
    {
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql));
        $literal = (new \SqlSemantics\Binding\ExpressionBinder())->token(new \SqlParser\Lexer\Token(0, 'BCONST', "B'101'", 0), $scope);
        self::assertSame('bit', $literal->type->name);
        self::assertSame("B'101'", $literal->spelling());
    }

    public function testQualifiedRejectsNonNameOperands(): void
    {
        $tree = (new \SqlParser\Sqlite\SqliteParser())->parse('SELECT a.id');
        $reader = new \SqlSemantics\Binding\ExpressionBinder();
        self::assertTrue($reader->qualified(\SqlSemantics\Ast\Tree::significant($tree->find('expr')[0])));
        self::assertFalse($reader->qualified([]));
        self::assertFalse($reader->qualified([new \SqlParser\Lexer\Token(1, 'ID', 'a', 0)]));
    }

    public function testCallRetainsNestedCoalesceInputs(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT COALESCE(parent_id, COALESCE(NULL, score)) FROM users');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame(Nullability::NotNull, $statement->outputs[0]->expression->nullability);
        self::assertSame(\SqlSemantics\Model\ExpressionKind::Coalesce, $statement->outputs[0]->expression->inputs()[1]->kind);
    }

    public function testTransparentUnwrapsASingleChildOrAParenthesizedChild(): void
    {
        $binder = new \SqlSemantics\Binding\ExpressionBinder();
        $inner = new \SqlParser\Parser\Node('a_expr', 0, [new \SqlParser\Lexer\Token(0, 'ICONST', '1', 1)]);
        self::assertSame($inner, $binder->transparent([$inner]));
        self::assertSame($inner, $binder->transparent([new \SqlParser\Lexer\Token(0, 'LP', '(', 0), $inner, new \SqlParser\Lexer\Token(0, 'RP', ')', 2)]));
        self::assertNull($binder->transparent([new \SqlParser\Lexer\Token(0, 'MINUS', '-', 0), $inner]));
        self::assertNull($binder->transparent([$inner, new \SqlParser\Lexer\Token(0, 'PLUS', '+', 1), $inner]));
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testOperationBindsMySqlSymbolicLogicalOperators(string $version): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build();
        $statement = (new Binder($schema))->bind('SELECT ! ?, 1 || 0 && 2');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('NOT', $statement->outputs[0]->expression->spelling());
        self::assertSame('OR', $statement->outputs[1]->expression->spelling());
        self::assertSame('AND', $statement->outputs[1]->expression->inputs()[1]->spelling());
        self::assertSame('integer', $statement->outputs[1]->expression->type->name);
        self::assertSame('SELECT (NOT ?), (1 OR (0 AND 2))', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['mysql-5.7.44', 'SELECT * LIMIT 1, ACCOUNT', 'SELECT * LIMIT `ACCOUNT` OFFSET 1'])]
    #[TestWith(['mysql-8.4.7', 'SELECT * LIMIT ACCOUNT', 'SELECT * LIMIT `ACCOUNT`'])]
    public function testNameReadsAKeywordIdentifierAsAName(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $query = $binder->bind($sql, strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame(\SqlSemantics\Model\ExpressionKind::UnresolvedColumn, $query->limit?->kind);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($query));
    }

    public function testIndirectionAppliesSubscriptsAndFieldsToAPositionalParameter(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT $1[1], $2.f, $3[1:2]');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ElementAccess::class, $query->outputs[0]->expression);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\Parameter::class, $query->outputs[0]->expression->base);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\FieldAccess::class, $query->outputs[1]->expression);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\SliceAccess::class, $query->outputs[2]->expression);
        self::assertSame('SELECT $1[1], ($2)."f", $3[1 : 2]', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testTokenTypesAPositionalParameterByItsDeclaredPosition(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('PREPARE p (integer, text) AS SELECT $2, $1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Prepared\PrepareQueryStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement->statement);
        self::assertSame('text', $statement->statement->outputs[0]->expression->type->name);
        self::assertSame('integer', $statement->statement->outputs[1]->expression->type->name);
    }

    public function testTokenResolvesSqliteKeywordTokensAsColumns(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER, indexed INTEGER, "left" INTEGER)'));
        $statement = $binder->bind('SELECT indexed, left FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame(\SqlSemantics\Model\ExpressionKind::Column, $statement->outputs[0]->expression->kind);
        self::assertSame(\SqlSemantics\Model\ExpressionKind::Column, $statement->outputs[1]->expression->kind);
        self::assertSame('SELECT "indexed" AS "indexed", "left" AS "left" FROM "main"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['rename'])]
    #[TestWith(['key'])]
    #[TestWith(['glob'])]
    #[TestWith(['row'])]
    #[TestWith(['replace'])]
    #[TestWith(['if'])]
    #[TestWith(['current'])]
    #[TestWith(['generated'])]
    public function testTokenResolvesSqliteFallbackKeywordsAsColumns(string $keyword): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $table = $binder->bind(sprintf('CREATE TABLE t (%s INT, CHECK (%s > 0))', $keyword, $keyword));
        self::assertSame(sprintf('CREATE TABLE "main"."t"("%s" "int", CHECK (("%s" > 0)))', $keyword, $keyword), (new \SqlSemantics\SimpleSerializer())->serialize($table));
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build(sprintf('CREATE TABLE t (%s INT)', $keyword))))->bind(sprintf('SELECT %s FROM t', $keyword));
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame(\SqlSemantics\Model\ExpressionKind::Column, $statement->outputs[0]->expression->kind);
        self::assertSame(sprintf('SELECT "%s" AS "%s" FROM "main"."t"', $keyword, $keyword), (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testTokenRejectsDefaultOutsideAWrite(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::DefaultContext->message());
        $binder->bind('SELECT DEFAULT');
    }

    public function testQualifiedAcceptsOnlyDottedNamePaths(): void
    {
        $reader = new \SqlSemantics\Binding\ExpressionBinder();
        $name = new \SqlParser\Parser\Node('nm', 0, [new \SqlParser\Lexer\Token(1, 'ID', 'a', 0)]);
        $dot = new \SqlParser\Lexer\Token(2, 'DOT', '.', 1);
        $plus = new \SqlParser\Lexer\Token(3, 'PLUS', '+', 1);
        $other = new \SqlParser\Parser\Node('expr', 0, [new \SqlParser\Lexer\Token(1, 'ID', 'a', 0)]);
        self::assertTrue($reader->qualified([$name, $dot, $name]));
        self::assertTrue($reader->qualified([$name, $dot, $name, $dot, $name]));
        self::assertFalse($reader->qualified([$name, $dot, $name, $dot]));
        self::assertFalse($reader->qualified([$name, $dot, $name, $dot, $name, $dot]));
        self::assertFalse($reader->qualified([$name, $plus, $name]));
        self::assertFalse($reader->qualified([$name, $dot, $name, $plus, $name]));
        self::assertFalse($reader->qualified([$other, $dot, $name]));
        self::assertFalse($reader->qualified([$name, $dot, $other]));
        self::assertFalse($reader->qualified([$name, $dot, $name, $dot, $other]));
    }

    public function testCallBindsAFunctionCall(): void
    {
        $tree = (new \SqlParser\Sqlite\SqliteParser())->parse('SELECT abs(1)');
        $node = $tree->find('expr')[0];
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::Sqlite));
        $call = (new \SqlSemantics\Binding\ExpressionBinder())->call($node, \SqlSemantics\Ast\Tree::significant($node), $scope);
        self::assertSame('ABS', $call->spelling());
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::Sqlite])]
    public function testOperationBindsPostfixNullTests(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind('SELECT 1 ISNULL, 1 NOTNULL');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('IS NULL', $statement->outputs[0]->expression->spelling());
        self::assertSame('IS NOT NULL', $statement->outputs[1]->expression->spelling());
        self::assertSame('SELECT (1 IS NULL), (1 IS NOT NULL)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith([Dialect::PostgreSql, 'SELECT ("id" IS NOT TRUE) FROM "public"."t"'])]
    #[TestWith([Dialect::MySql, 'SELECT (`id` IS NOT TRUE) FROM `t`'])]
    public function testOperationBindsTruthTests(Dialect $dialect, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id BOOLEAN)'));
        $statement = $binder->bind('SELECT id IS NOT TRUE FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('IS NOT TRUE', $statement->outputs[0]->expression->spelling());
        self::assertSame(\SqlSemantics\Model\ExpressionKind::Column, $statement->outputs[0]->expression->inputs()[0]->kind);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testOperationComparesARowSubqueryWithALowercaseOperator(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        self::assertSame('SELECT ((SELECT 1, 2) IS(1, 2))', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind('SELECT (SELECT 1, 2) is (1, 2)')));
    }

    #[TestWith([Dialect::PostgreSql, 'SELECT (SELECT 1, 2) = 1'])]
    #[TestWith([Dialect::Sqlite, 'SELECT (SELECT 1, 2) is (1, 2, 3)'])]
    public function testOperationRejectsComparedRowsOfDifferentWidths(Dialect $dialect, string $sql): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('Compared row operands must have equal widths.');
        $binder->bind($sql);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

#[CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
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
final class ExpressionRulesTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testOperatorNullTestsAreNeverNullable(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT parent_id IS NULL AS absent, parent_id IS NOT NULL AS present FROM users');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame(Nullability::NotNull, $statement->outputs[0]->expression->nullability);
        self::assertSame(Nullability::NotNull, $statement->outputs[1]->expression->nullability);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testCallNullIfCanIntroduceNullWithoutAnOuterJoin(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT NULLIF(score, 0) AS result FROM users');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame(Nullability::MaybeNull, $statement->outputs[0]->expression->nullability);
        self::assertSame([], $statement->outputs[0]->expression->nullExtendedBy);
    }

    public function testPredicateRejectsNonBooleanPostgresInputs(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('boolean type');
        (new Binder($schema))->bind('SELECT id FROM users WHERE score');
    }

    public function testCoerceRecordsPostgresCommonTypeConversions(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT COALESCE(id, 2147483648) FROM users');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $expression = $statement->outputs[0]->expression;
        self::assertSame(\SqlSemantics\Model\ExpressionKind::Cast, $expression->inputs()[0]->kind);
        self::assertSame('bigint', $expression->inputs()[0]->type->name);
        self::assertSame('integer', $expression->inputs()[0]->inputs()[0]->type->name);
        self::assertSame('id', $expression->lineage()[0]->column->name);
    }

    public function testArithmeticHandlesNegativePostgresIntegerBoundaries(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $statement = (new Binder($schema))->bind('SELECT -2147483648, -9223372036854775808');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('integer', $statement->outputs[0]->expression->type->name);
        self::assertSame('bigint', $statement->outputs[1]->expression->type->name);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testOperatorBindsSoundsLikeAsABooleanComparison(string $version): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (a TEXT, b TEXT NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a SOUNDS LIKE b FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $expression = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $expression);
        self::assertSame(\SqlSemantics\Model\Scalar\Operator\BinaryOperator::SoundsLike, $expression->operator);
        self::assertSame(Nullability::MaybeNull, $expression->nullability);
        self::assertSame('SELECT (`a` SOUNDS LIKE `b`) FROM `t`', $statement->toString());
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerOperatorAndCallAssignTypesAndNullFacts')]
    public function testOperatorAndCallAssignTypesAndNullFacts(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame($expected, $statement->outputs[0]->expression::class . ' ' . $statement->outputs[0]->expression->type->name . ' ' . $statement->outputs[0]->expression->nullability->name);
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerOperatorAndCallAssignTypesAndNullFacts(): iterable
    {
        return [
            'SELECT NULLIF(NULL, 1) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT NULLIF(NULL, 1) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\NullIf unknown AlwaysNull'],
            'SELECT NULLIF(i, n) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT NULLIF(i, n) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\NullIf integer MaybeNull'],
            'SELECT NULLIF(s, \'x\') FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT NULLIF(s, \'x\') FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\NullIf text MaybeNull'],
            'SELECT NULLIF(NULL, 1) FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT NULLIF(NULL, 1) FROM t', 'SqlSemantics\\Model\\Scalar\\Function\\FunctionCall unknown AlwaysNull'],
            'SELECT NULLIF(s, i) FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT NULLIF(s, i) FROM t', 'SqlSemantics\\Model\\Scalar\\Function\\FunctionCall text MaybeNull'],
            'SELECT NULLIF(NULL, s) FROM t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT NULLIF(NULL, s) FROM t', 'SqlSemantics\\Model\\Scalar\\Function\\FunctionCall unknown AlwaysNull'],
            'SELECT 1 MEMBER OF (\'[1]\') FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT 1 MEMBER OF (\'[1]\') FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\JsonMembership integer NotNull'],
            'SELECT i IN (1, 2) FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i IN (1, 2) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\InList integer NotNull'],
            'SELECT s LIKE \'a\' ESCAPE \'!\' FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT s LIKE \'a\' ESCAPE \'!\' FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\PatternMatch integer NotNull'],
            'SELECT s NOT LIKE \'a\' FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT s NOT LIKE \'a\' FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\PatternMatch integer NotNull'],
            'SELECT s RLIKE \'a\' FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT s RLIKE \'a\' FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\PatternMatch integer NotNull'],
            'SELECT s REGEXP \'a\' FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT s REGEXP \'a\' FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\PatternMatch integer NotNull'],
            'SELECT s GLOB \'a\' FROM t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT s GLOB \'a\' FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\PatternMatch integer NotNull'],
            'SELECT s LIKE \'a\' ESCAPE \'!\' FROM t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT s LIKE \'a\' ESCAPE \'!\' FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\PatternMatch integer NotNull'],
            'SELECT s NOT GLOB \'a\' FROM t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT s NOT GLOB \'a\' FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\PatternMatch integer NotNull'],
            'SELECT s MATCH \'a\' FROM t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT s MATCH \'a\' FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\PatternMatch integer NotNull'],
            'SELECT s SIMILAR TO \'a\' FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT s SIMILAR TO \'a\' FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\PatternMatch boolean NotNull'],
            'SELECT s NOT SIMILAR TO \'a\' ESCAPE \'!\' FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT s NOT SIMILAR TO \'a\' ESCAPE \'!\' FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\PatternMatch boolean NotNull'],
            'SELECT s ILIKE \'a\' FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT s ILIKE \'a\' FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\PatternMatch boolean NotNull'],
            'SELECT b AND b FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT b AND b FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression boolean NotNull'],
            'SELECT b AND (n > 1) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT b AND (n > 1) FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression boolean MaybeNull'],
            'SELECT i AND b FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i AND b FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression boolean NotNull'],
            'SELECT b OR i FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT b OR i FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression boolean NotNull'],
            'SELECT NOT i FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT NOT i FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\UnaryExpression boolean NotNull'],
            'SELECT i IS TRUE FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i IS TRUE FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\UnaryExpression boolean NotNull'],
            'SELECT b IS TRUE FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT b IS TRUE FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\UnaryExpression boolean NotNull'],
            'SELECT (i > 1) AND (i < 3) FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT (i > 1) AND (i < 3) FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression integer NotNull'],
            'SELECT (i > 1) AND (n < 3) FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT (i > 1) AND (n < 3) FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression integer MaybeNull'],
            'SELECT (i > 1) OR (i < 3) FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT (i > 1) OR (i < 3) FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression integer NotNull'],
            'SELECT i IS NULL FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i IS NULL FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\UnaryExpression integer NotNull'],
            'SELECT i ISNULL FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i ISNULL FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\UnaryExpression boolean NotNull'],
            'SELECT i NOTNULL FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i NOTNULL FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\UnaryExpression boolean NotNull'],
            'SELECT i = s FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i = s FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression boolean NotNull'],
            'SELECT i = 1 FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i = 1 FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression boolean NotNull'],
            'SELECT i IS DISTINCT FROM n FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i IS DISTINCT FROM n FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression boolean NotNull'],
            'SELECT i <=> n FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i <=> n FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression integer NotNull'],
            'SELECT i IS n FROM t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i IS n FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression integer NotNull'],
            'SELECT i IS NOT n FROM t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i IS NOT n FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression integer NotNull'],
            'SELECT s || s FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT s || s FROM t', 'SqlSemantics\Model\Scalar\Operator\BinaryExpression text Unknown'],
            'SELECT s || s FROM t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT s || s FROM t', 'SqlSemantics\Model\Scalar\Operator\BinaryExpression text Unknown'],
            'SELECT i + i FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i + i FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression bigint NotNull'],
            'SELECT i / i FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i / i FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression numeric NotNull'],
            'SELECT r + i FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT r + i FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression double precision MaybeNull'],
            'SELECT d + i FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT d + i FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression numeric MaybeNull'],
            'SELECT d * 2 FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT d * 2 FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression numeric MaybeNull'],
            'SELECT i DIV 2 FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i DIV 2 FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression bigint NotNull'],
            'SELECT i MOD 2 FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i MOD 2 FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression bigint NotNull'],
            'SELECT i % 2 FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i % 2 FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression bigint NotNull'],
            'SELECT i & 2 FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i & 2 FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression bigint NotNull'],
            'SELECT i << 2 FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i << 2 FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression bigint NotNull'],
            'SELECT i + i FROM t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i + i FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression dynamic NotNull'],
            'SELECT r * i FROM t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT r * i FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression dynamic MaybeNull'],
            'SELECT i + i FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i + i FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression integer NotNull'],
            'SELECT -2147483648 FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT -2147483648 FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\UnaryExpression integer NotNull'],
            'SELECT -2147483647 FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT -2147483647 FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\UnaryExpression integer NotNull'],
            'SELECT -9223372036854775808 FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT -9223372036854775808 FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\UnaryExpression bigint NotNull'],
            'SELECT +2147483648 FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT +2147483648 FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\UnaryExpression bigint NotNull'],
            'SELECT 0 - 2147483648 FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT 0 - 2147483648 FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression bigint NotNull'],
            'SELECT -(2147483648) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT -(2147483648) FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\UnaryExpression integer NotNull'],
            'SELECT -2_147_483_648 FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT -2_147_483_648 FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\UnaryExpression integer NotNull'],
            'SELECT -i FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT -i FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\UnaryExpression integer NotNull'],
            'SELECT i ^ 2 FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT i ^ 2 FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression integer NotNull'],
            'SELECT r * i FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT r * i FROM t', 'SqlSemantics\\Model\\Scalar\\Operator\\BinaryExpression real MaybeNull'],
            'SELECT GREATEST(i, n) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT GREATEST(i, n) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\Extremum integer NotNull'],
            'SELECT LEAST(i, 2) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT LEAST(i, 2) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\Extremum integer NotNull'],
            'SELECT COALESCE(i, 1.5) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT COALESCE(i, 1.5) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\Coalesce numeric NotNull'],
            'SELECT COALESCE(n, i) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT COALESCE(n, i) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\Coalesce integer NotNull'],
            'SELECT COALESCE(NULL, NULL) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT COALESCE(NULL, NULL) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\Coalesce text AlwaysNull'],
            'SELECT GREATEST(i, n) FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT NOT NULL, r REAL, d DECIMAL(5,2), b BOOLEAN NOT NULL)'], 'SELECT GREATEST(i, n) FROM t', 'SqlSemantics\\Model\\Scalar\\Function\\FunctionCall integer MaybeNull'],
        ];
    }

    #[TestWith(['SELECT b AND i FROM t', 'A PostgreSQL predicate must have boolean type.'])]
    #[TestWith(['SELECT b OR i FROM t', 'A PostgreSQL predicate must have boolean type.'])]
    #[TestWith(['SELECT NOT i FROM t', 'A PostgreSQL predicate must have boolean type.'])]
    #[TestWith(['SELECT i IS TRUE FROM t', 'A PostgreSQL predicate must have boolean type.'])]
    #[TestWith(['SELECT i = s FROM t', 'Cannot establish a common type for: integer, text'])]
    #[TestWith(['SELECT i < true FROM t', 'Cannot establish a common type for: integer, boolean'])]
    public function testOperatorRejectsIncompatiblePostgresOperands(string $sql, string $message): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (i INTEGER NOT NULL, s TEXT NOT NULL, b BOOLEAN NOT NULL)');
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage($message);
        (new Binder($schema))->bind($sql);
    }

    #[TestWith(['SELECT NULLIF(1, 2, 3)'])]
    #[TestWith(['SELECT NULLIF(1)'])]
    #[TestWith(['SELECT COALESCE()'])]
    public function testCallRejectsConditionalArity(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind($sql);
    }
}

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

#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
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
final class FromBinderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testJoinedBindsOnBeforeIntroducingThisJoinsNulls(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT b.score FROM users a LEFT JOIN users b ON a.id=b.id WHERE b.score > 0');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\Joining\OnJoin::class, $statement->from);
        self::assertSame(Nullability::NotNull, $statement->from->condition->inputs()[1]->nullability);
        self::assertNotNull($statement->where);
        self::assertSame(Nullability::MaybeNull, $statement->where->inputs()[0]->nullability);
        self::assertSame(['j0'], $statement->where->inputs()[0]->nullExtendedBy);
    }

    public function testJoinPropagatesNestedOuterJoinProvenance(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id, b.id, c.id FROM users a LEFT JOIN users b ON a.id=b.id RIGHT JOIN users c ON b.id=c.id');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\Joining\OnJoin::class, $statement->from);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\Joining\OnJoin::class, $statement->from->left);
        self::assertSame([$statement->from->id], $statement->outputs[0]->expression->nullExtendedBy);
        self::assertSame([$statement->from->left->id, $statement->from->id], $statement->outputs[1]->expression->nullExtendedBy);
        self::assertSame([], $statement->outputs[2]->expression->nullExtendedBy);
    }

    #[TestWith(['SELECT * FROM JSON_TABLE(1 = ALL (TABLE t), \'$\' COLUMNS(y FOR ORDINALITY)) AS n', 'SELECT `n`.`y` AS `y` FROM JSON_TABLE((1 = ALL (TABLE `t`)), \'$\' COLUMNS(`y` FOR ORDINALITY)) AS `n`'])]
    #[TestWith(['UPDATE JSON_TABLE(1 = ALL (TABLE t), \'$\' COLUMNS(y FOR ORDINALITY)) AS n SET n.y = DEFAULT', 'UPDATE JSON_TABLE((1 = ALL (TABLE `t`)), \'$\' COLUMNS(`y` FOR ORDINALITY)) AS `n` SET `n`.`y` = DEFAULT'])]
    public function testRelationKeepsATableFunctionWhoseOperandHasASubquery(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.3.0'))->build('CREATE TABLE t(x INT)'));
        self::assertSame($expected, $binder->bind($sql)->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith(['SELECT 1 FROM (SELECT 1) AS d', 'select_with_parens'])]
    #[TestWith(['SELECT 1 FROM generate_series((SELECT 1), 2) AS g', null])]
    public function testDerivedQueryIgnoresASubqueryInsideAFunctionOperand(string $sql, ?string $expected): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse($sql);
        $reference = \SqlSemantics\Ast\Tree::outer($tree, ['table_ref'])[0];
        self::assertSame($expected, \SqlSemantics\Binding\FromBinder::derivedQuery($reference)?->name);
    }

    public function testRelationFullJoinExtendsBothSides(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id, b.id FROM users a FULL JOIN users b ON a.id=b.id');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame(['j0'], $statement->outputs[0]->expression->nullExtendedBy);
        self::assertSame(['j0'], $statement->outputs[1]->expression->nullExtendedBy);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindCrossJoinHasNoMatchPredicate(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id FROM users a CROSS JOIN users b');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\Joining\CrossJoin::class, $statement->from);
        self::assertSame(\SqlSemantics\Model\JoinKind::Cross, $statement->from->kind);
        self::assertFalse(property_exists($statement->from, 'condition'));
    }

    public function testSqliteRespectsExplicitDatabaseNames(): void
    {
        $builder = new SchemaBuilder(Dialect::Sqlite);
        $schema = $builder->build('CREATE TABLE main.users (id INTEGER)');
        $statement = (new Binder($schema))->bind('SELECT u.id FROM main.users AS u');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('main', $statement->relations[0]->declaration->schema);
    }

    public function testTableAppliesAliasColumnLists(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER, n TEXT)');
        $query = (new Binder($schema))->bind('SELECT q.key, q.value FROM t AS q(key, value)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame(['key', 'value'], array_column($query->outputs, 'name'));
        self::assertSame('text', $query->outputs[1]->expression->type->name);
    }

    public function testKindRecognizesRightAndNatural(): void
    {
        $builder = new SchemaBuilder(Dialect::PostgreSql);
        $tables = new \SqlSemantics\Binding\TableResolver($builder->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)'), new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public');
        $reader = new \SqlSemantics\Binding\FromBinder($tables, new \SqlSemantics\Binding\IdentitySequence());
        $source = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('SELECT 1');
        self::assertSame(\SqlSemantics\Model\JoinKind::Right, $reader->kind('RIGHT OUTER JOIN', $source));
        self::assertSame(\SqlSemantics\Model\JoinKind::Inner, $reader->kind('NATURAL JOIN', $source));
    }
    public function testDerivedPreservesLateralCorrelation(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT q.v FROM t CROSS JOIN LATERAL (SELECT t.n + 1 AS v) q');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('n', $query->outputs[0]->expression->lineage()[1]->column->name);
    }

    public function testSqliteInputKeepsTheLeftSideOfADerivedJoin(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT a.id, q.n FROM t a JOIN (SELECT 1 AS n) q ON a.id=q.n');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertCount(2, $query->relations);
        self::assertSame(['id', 'n'], array_column($query->outputs, 'name'));
        self::assertInstanceOf(\SqlSemantics\Model\Relation\Joining\OnJoin::class, $query->from);
        self::assertSame('=', $query->from->condition->spelling());
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testDerivedKeepsItsInnerJoinInTheNestedScope(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT q.id FROM (SELECT a.id FROM t a JOIN t b ON a.id=b.id) q');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertCount(1, $query->relations);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DerivedRelation::class, $query->relations[0]);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query->relations[0]->query);
        self::assertCount(2, $query->relations[0]->query->relations);
        self::assertSame('id', $query->outputs[0]->name);
    }

    public function testTableResolvesCaseInsensitiveSqliteCtes(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('WITH Mixed(Value) AS (SELECT 1) SELECT value FROM mixed');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('Value', $query->outputs[0]->name);
        self::assertSame('integer', $query->outputs[0]->expression->type->name);
    }


    public function testSqliteInputBindsParenthesizedJoins(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE a (id INTEGER)', 'CREATE TABLE b (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT a.id, b.id FROM (a JOIN b ON a.id=b.id)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame(['a','b'], array_map(static fn ($relation): string => $relation->declaration->name, $query->relations));
        self::assertInstanceOf(\SqlSemantics\Model\Relation\Joining\OnJoin::class, $query->from);
        self::assertSame('=', $query->from->condition->spelling());
        self::assertSame(['id','id'], array_column($query->outputs, 'name'));
    }

    public function testExplicitBindsTableQueryAsAnOrderedProjection(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, name TEXT)');
        $query = (new Binder($schema))->bind('TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $query);
        self::assertSame(['id', 'name'], array_column($query->outputs, 'name'));
        self::assertSame($schema->tables[0], $query->relations[0]->declaration);
        self::assertSame('id', $query->outputs[0]->expression->columnBinding()?->column->name);
    }

    public function testDerivedBindsLegacySelectFactorInItsOwnScope(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT * FROM SELECT id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame(['id'], array_column($query->outputs, 'name'));
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DerivedRelation::class, $query->relations[0]);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query->relations[0]->query);
        self::assertSame('t', $query->relations[0]->query->relations[0]->declaration->name);
        self::assertNotSame($query->scopeId, $query->relations[0]->query->scopeId);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testGroupedKeepsParenthesizedJoinsAsRelations(string $version): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE a (id INTEGER)', 'CREATE TABLE b (n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT a.id, b.n FROM (a JOIN b ON a.id=b.n)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame(['id', 'n'], array_column($query->outputs, 'name'));
        self::assertSame(['a', 'b'], array_map(static fn ($relation): string => $relation->declaration->name, $query->relations));
        self::assertInstanceOf(\SqlSemantics\Model\Relation\Joining\OnJoin::class, $query->from);
        self::assertSame('=', $query->from->condition->spelling());
        self::assertInstanceOf(\SqlSemantics\Model\Relation\TableReference::class, $query->relations[0]);
    }

    public function testTableResolvesNumericCteNames(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH "123" AS (SELECT 1 AS id) SELECT id FROM "123"');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('123', $query->relations[0]->declaration->name);
        self::assertSame(['id'], array_column($query->outputs, 'name'));
        self::assertNotNull($query->ctes);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\CteReference::class, $query->relations[0]);
        self::assertSame($query->ctes->definitions[0]->query, $query->relations[0]->definition->query);
    }


    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testJoinedUsesStableIdentitiesForCommaAndCrossJoinInputs(string $version): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind('UPDATE LOW_PRIORITY IGNORE t a, t b, t c SET a.id=DEFAULT');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateJoinedStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\Joining\CrossJoin::class, $statement->from);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\Joining\CrossJoin::class, $statement->from->left);
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateJoinedStatement::class, $rebound);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\Joining\CrossJoin::class, $rebound->from);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\Joining\CrossJoin::class, $rebound->from->left);
        self::assertSame($statement->from->id, $rebound->from->id);
        self::assertSame($statement->from->left->id, $rebound->from->left->id);
        self::assertSame($statement->toString(), $rebound->toString());
    }

    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testDerivedAppliesTheMysqlColumnAliasList(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $query = $binder->bind('SELECT * FROM (SELECT 1 AS a, 2) AS d (b, c)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $relation = $query->relations[0];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DerivedRelation::class, $relation);
        self::assertSame(['b', 'c'], $relation->columnAliases);
        self::assertSame(['b', 'c'], array_map(static fn ($output): ?string => $output->name, $query->outputs));
        self::assertSame('SELECT `d`.`b` AS `b`, `d`.`c` AS `c` FROM(SELECT 1 AS `a`, 2) AS `d`(`b`, `c`)', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    #[TestWith([Dialect::MySql, 'SELECT * FROM (SELECT 1 AS a, 2) AS d (b)'])]
    #[TestWith([Dialect::MySql, 'SELECT * FROM (SELECT 1 AS a) AS d (b, c)'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT * FROM (SELECT 1 AS a) AS d (b, c)'])]
    public function testDerivedRejectsAColumnAliasListWiderThanTheQuery(Dialect $dialect, string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::DerivedColumnCount->message());
        (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
    }

    #[TestWith([Dialect::MySql, 'SELECT * FROM t, t'])]
    #[TestWith([Dialect::MySql, 'SELECT * FROM t a JOIN u a ON 1 = 1'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT * FROM t a, t a'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT * FROM t JOIN t USING (a)'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT * FROM t a, (SELECT 1) a'])]
    public function testUniqueRejectsARepeatedFromName(Dialect $dialect, string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::DuplicateRelation->message());
        (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind($sql);
    }

    #[TestWith([Dialect::MySql, 'SELECT 1 FROM t, t AS x WHERE EXISTS (SELECT 1 FROM t)'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT 1 FROM t, other.t'])]
    #[TestWith([Dialect::Sqlite, 'SELECT 1 FROM t, t'])]
    public function testUniqueAcceptsDistinctNames(Dialect $dialect, string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(a INT)')))->bind($sql, strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
    }
}

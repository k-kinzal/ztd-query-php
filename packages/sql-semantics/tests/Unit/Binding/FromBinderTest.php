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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\ExpressionInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\StatementInvariant::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\ValueList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\ClauseEditor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\SourceEdit::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\Context::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\TreeEdit::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CommandStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\InsertStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ConfigurationStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\TableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\DeleteStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\MergeStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\RelationQuery::class)]
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
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $statement->from);
        self::assertNotNull($statement->from->condition);
        self::assertSame(Nullability::NotNull, $statement->from->condition->operands[1]->nullability);
        self::assertNotNull($statement->where);
        self::assertSame(Nullability::MaybeNull, $statement->where->operands[0]->nullability);
        self::assertSame(['j0'], $statement->where->operands[0]->nullExtendedBy);
    }

    public function testJoinPropagatesNestedOuterJoinProvenance(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id, b.id, c.id FROM users a LEFT JOIN users b ON a.id=b.id RIGHT JOIN users c ON b.id=c.id');
        self::assertSame(['j0'], $statement->outputs[0]->expression->nullExtendedBy);
        self::assertSame(['j1', 'j0'], $statement->outputs[1]->expression->nullExtendedBy);
        self::assertSame([], $statement->outputs[2]->expression->nullExtendedBy);
    }

    public function testRelationFullJoinExtendsBothSides(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id, b.id FROM users a FULL JOIN users b ON a.id=b.id');
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
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $statement->from);
        self::assertSame(\SqlSemantics\Model\JoinKind::Cross, $statement->from->kind);
        self::assertNull($statement->from->condition);
    }

    public function testSqliteRespectsExplicitDatabaseNames(): void
    {
        $builder = new SchemaBuilder(Dialect::Sqlite);
        $schema = $builder->build('CREATE TABLE main.users (id INTEGER)');
        $statement = (new Binder($schema))->bind('SELECT u.id FROM main.users AS u');
        self::assertSame('main', $statement->relations[0]->declaration->schema);
    }

    public function testTableAppliesAliasColumnLists(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER, n TEXT)');
        $query = (new Binder($schema))->bind('SELECT q.key, q.value FROM t AS q(key, value)');
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
        self::assertSame('n', $query->outputs[0]->expression->lineage()[1]->column->name);
    }

    public function testSqliteInputKeepsTheLeftSideOfADerivedJoin(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT a.id, q.n FROM t a JOIN (SELECT 1 AS n) q ON a.id=q.n');
        self::assertCount(2, $query->relations);
        self::assertSame(['id', 'n'], array_column($query->outputs, 'name'));
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $query->from);
        self::assertSame('=', $query->from->condition?->symbol);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testDerivedKeepsItsInnerJoinInTheNestedScope(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT q.id FROM (SELECT a.id FROM t a JOIN t b ON a.id=b.id) q');
        self::assertCount(1, $query->relations);
        self::assertNotNull($query->relations[0]->query);
        self::assertCount(2, $query->relations[0]->query->relations);
        self::assertSame('id', $query->outputs[0]->name);
    }

    public function testTableResolvesCaseInsensitiveSqliteCtes(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('WITH Mixed(Value) AS (SELECT 1) SELECT value FROM mixed');
        self::assertSame('Value', $query->outputs[0]->name);
        self::assertSame('integer', $query->outputs[0]->expression->type->name);
    }


    public function testSqliteInputBindsParenthesizedJoins(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE a (id INTEGER)', 'CREATE TABLE b (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT a.id, b.id FROM (a JOIN b ON a.id=b.id)');
        self::assertSame(['a','b'], array_map(static fn ($relation): string => $relation->declaration->name, $query->relations));
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $query->from);
        self::assertSame('=', $query->from->condition?->symbol);
        self::assertSame(['id','id'], array_column($query->outputs, 'name'));
    }

    public function testExplicitBindsTableQueryAsAnOrderedProjection(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, name TEXT)');
        $query = (new Binder($schema))->bind('TABLE t');
        self::assertSame(['id', 'name'], array_column($query->outputs, 'name'));
        self::assertSame($schema->tables[0], $query->relations[0]->declaration);
        self::assertSame('id', $query->outputs[0]->expression->binding?->column->name);
    }

    public function testDerivedBindsLegacySelectFactorInItsOwnScope(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT * FROM SELECT id FROM t');
        self::assertSame(['id'], array_column($query->outputs, 'name'));
        self::assertNotNull($query->relations[0]->query);
        self::assertSame('t', $query->relations[0]->query->relations[0]->declaration->name);
        self::assertNotSame($query->scopeId, $query->relations[0]->query->scopeId);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testGroupedKeepsParenthesizedJoinsAsRelations(string $version): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE a (id INTEGER)', 'CREATE TABLE b (n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT a.id, b.n FROM (a JOIN b ON a.id=b.n)');
        self::assertSame(['id', 'n'], array_column($query->outputs, 'name'));
        self::assertSame(['a', 'b'], array_map(static fn ($relation): string => $relation->declaration->name, $query->relations));
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $query->from);
        self::assertSame('=', $query->from->condition?->symbol);
        self::assertNull($query->relations[0]->query);
    }

    public function testTableResolvesNumericCteNames(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH "123" AS (SELECT 1 AS id) SELECT id FROM "123"');
        self::assertSame('123', $query->relations[0]->declaration->name);
        self::assertSame(['id'], array_column($query->outputs, 'name'));
        self::assertSame($query->ctes[123], $query->relations[0]->query);
    }

}

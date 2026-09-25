<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Sql;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Serializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaBuilder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\StatementFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SimpleSerializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Binder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scope::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\FunctionSignature::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexElement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\BuiltinResult::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\SignatureInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\Builtins::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Expression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Literal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\ExpressionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Build::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Parts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Atom::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Source::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Format::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
final class BoundSelectTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite])]
    public function testWithOutputsRecomputesTypesAndNames(Dialect $dialect): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $changed = $statement->withOutputs([new OutputColumn(0, 'value', Expression::literal(3, $dialect))]);
        self::assertSame('value', $changed->outputs[0]->name);
        self::assertSame([], $changed->outputs[0]->expression->lineage());
        self::assertSame('id', $statement->outputs[0]->expression->columnBinding()?->column->name);
    }
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite])]
    public function testWithWhereCanAddAndRemoveAPredicate(Dialect $dialect): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $changed = $statement->withWhere(Expression::binary('>', Expression::reference(['id'], $dialect), Expression::literal(1, $dialect)));
        self::assertSame('>', $changed->where?->spelling());
        self::assertNull($statement->where);
        self::assertNull($changed->withWhere(null)->where);
    }
    public function testWithGroupByAndHavingBindAtTheirEvaluationStages(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $grouped = $statement->withGroupBy([Expression::reference(['id'], Dialect::PostgreSql)]);
        self::assertInstanceOf(Expression::class, $grouped->groupBy[0]);
        self::assertSame('id', $grouped->groupBy[0]->columnBinding()?->column->name);
        self::assertSame([], $grouped->withGroupBy([])->groupBy);
    }
    public function testWithHavingValidatesBooleanCondition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('TRUE', $statement->withHaving(Expression::literal(true, Dialect::PostgreSql))->having?->spelling());
        $this->expectException(\SqlSemantics\SemanticException::class);
        $statement->withHaving(Expression::literal(3, Dialect::PostgreSql));
    }

    public function testWithFromRebindsOutputNullabilityAfterAJoinChange(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL)'));
        $statement = $binder->bind('SELECT b.id FROM t a JOIN t b ON a.id=b.id');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $replacement = $binder->bind('SELECT b.id FROM t a LEFT JOIN t b ON a.id=b.id');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $replacement);
        $changed = $statement->withFrom($replacement->from);
        self::assertSame('maybe-null', $changed->outputs[0]->expression->nullability->value);
        self::assertSame('not-null', $statement->outputs[0]->expression->nullability->value);
        self::assertCount(1, $changed->outputs[0]->expression->nullExtendedBy);
    }

    public function testWithOutputsMaterializesExpandedStarsAsColumnReferences(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER,n INTEGER)')))->bind('SELECT * FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $changed = $statement->withOutputs([new OutputColumn(0, 'n', $statement->outputs[1]->expression)]);
        self::assertCount(1, $changed->outputs);
        self::assertSame('n', $changed->outputs[0]->expression->columnBinding()?->column->name);
        self::assertCount(2, $statement->outputs);
    }

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite])]
    public function testWithOutputsRequiresAProjectionOutsidePostgreSql(Dialect $dialect): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOutputs([]);
    }

    public function testWithOutputsPreservesAnEmptyPostgreSqlProjection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $changed = $statement->withOutputs([]);
        self::assertSame([], $changed->resultColumns());
        self::assertSame('SELECT FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertCount(1, $statement->outputs);
    }

    public function testWithOriginKeepsOperandsAndReplacesProvenance(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $origin = new \SqlSemantics\Model\Statement\Origin('other', $statement->source, Dialect::PostgreSql, [new \SqlSemantics\Model\Diagnostic('custom', 'message', $statement->source)], $statement->origin->context);
        $changed = $statement->withOrigin($origin);
        self::assertSame('other', $changed->scopeId);
        self::assertSame(['custom'], array_column($changed->diagnostics, 'reason'));
        self::assertSame($statement->outputs, $changed->outputs);
        self::assertSame($statement->from, $changed->from);
        self::assertSame('s0', $statement->scopeId);
        self::assertSame([], $statement->diagnostics);
    }

    public function testResultColumnsAreTheProjectionOutputs(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('SELECT id, n + 1 AS next FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame($statement->outputs, $statement->resultColumns());
        self::assertSame(['id', 'next'], array_column($statement->resultColumns(), 'name'));
    }

    public function testWithOrderByBindsSortKeysAndKeepsTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('SELECT id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $ordered = $statement->withOrderBy([new \SqlSemantics\Model\Ordering(Expression::reference(['n'], Dialect::PostgreSql), true, true)]);
        self::assertSame('SELECT "id" AS "id" FROM "public"."t" ORDER BY "n" DESC NULLS FIRST', (new \SqlSemantics\SimpleSerializer())->serialize($ordered));
        $key = $ordered->orderBy[0]->key;
        self::assertInstanceOf(Expression::class, $key);
        self::assertSame('n', $key->columnBinding()?->column->name);
        self::assertSame([], $statement->orderBy);
        self::assertSame('SELECT "id" AS "id" FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($ordered->withOrderBy([])));
    }

    public function testWithTiesIsOffByDefault(): void
    {
        $bound = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $bound);
        $select = new \SqlSemantics\Model\BoundSelect($bound->origin, null, $bound->outputs, null, $bound->quantifier, [], null, null);
        self::assertFalse($select->withTies);
        self::assertSame('SELECT 1', (new \SqlSemantics\SimpleSerializer())->serialize($select));
    }

    public function testFromMustUseTheStatementDialect(): void
    {
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('SELECT id FROM t WHERE id = 1');
        $postgres = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)')))->bind('SELECT id FROM t WHERE id = 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $mysql);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $postgres);
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\BoundSelect($mysql->origin, $postgres->from, $mysql->outputs, $mysql->where, $mysql->quantifier, [], null, null);
    }

    public function testOutputsMustUseTheStatementDialect(): void
    {
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        $postgres = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $mysql);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $postgres);
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\BoundSelect($mysql->origin, null, $postgres->outputs, null, $mysql->quantifier, [], null, null);
    }

    public function testWhereMustUseTheStatementDialect(): void
    {
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        $postgres = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 WHERE TRUE');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $mysql);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $postgres);
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\BoundSelect($mysql->origin, null, $mysql->outputs, $postgres->where, $mysql->quantifier, [], null, null);
    }

    public function testLocksAreRejectedInSqlite(): void
    {
        $sqlite = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        $postgres = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 FOR UPDATE');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $sqlite);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $postgres);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('SQLite queries do not have locking clauses.');
        new \SqlSemantics\Model\BoundSelect($sqlite->origin, null, $sqlite->outputs, null, $sqlite->quantifier, [], null, null, locks: $postgres->locks);
    }

    public function testLocksRejectAPostgreSqlStrengthInMySql(): void
    {
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        $postgres = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 FOR KEY SHARE');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $mysql);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $postgres);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('This lock strength is specific to PostgreSQL.');
        new \SqlSemantics\Model\BoundSelect($mysql->origin, null, $mysql->outputs, null, $mysql->quantifier, [], null, null, locks: $postgres->locks);
    }

    public function testLocksRejectANamedTargetOutsideTheInput(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)'));
        $locked = $binder->bind('SELECT id FROM t AS a FOR UPDATE OF a');
        $other = $binder->bind('SELECT id FROM t AS a');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $locked);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $other);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A named lock target must belong to this query input.');
        new \SqlSemantics\Model\BoundSelect($other->origin, $other->from, $other->outputs, null, $other->quantifier, [], null, null, locks: $locked->locks);
    }

    public function testLocksAcceptNamedTargetsOfTheInput(): void
    {
        $postgres = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)')))->bind('SELECT id FROM t AS a FOR NO KEY UPDATE OF a');
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('SELECT id FROM t AS a FOR UPDATE OF a');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $postgres);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $mysql);
        self::assertCount(1, $postgres->locks);
        self::assertSame('SELECT "id" AS "id" FROM "public"."t" AS "a" FOR NO KEY UPDATE OF "a"', (new \SqlSemantics\SimpleSerializer())->serialize($postgres));
        self::assertSame('SELECT `id` AS `id` FROM `t` AS `a` FOR UPDATE OF `a`', (new \SqlSemantics\SimpleSerializer())->serialize($mysql));
    }

    public function testWithWhereKeepsTheQueryBlockOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT)')))->bind('SELECT HIGH_PRIORITY SQL_SMALL_RESULT a FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $changed = $statement->withWhere(null)->withOrigin($statement->origin);
        self::assertSame([\SqlSemantics\Model\Query\Optimization\SelectOption::HighPriority, \SqlSemantics\Model\Query\Optimization\SelectOption::SmallResult], $changed->options);
        self::assertSame('SELECT HIGH_PRIORITY SQL_SMALL_RESULT `a` AS `a` FROM `t`', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testOptionsRequireMySql(): void
    {
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT HIGH_PRIORITY 1');
        $postgres = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $mysql);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $postgres);
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\BoundSelect($postgres->origin, null, $postgres->outputs, null, $postgres->quantifier, [], null, null, options: $mysql->options);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.3.0'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-9.0.1'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-9.1.0'])]
    public function testKeepsTheQualifyPredicateThroughStructuralSerialization(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind('SELECT a FROM t QUALIFY ROW_NUMBER() OVER (ORDER BY a) = 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(Expression::class, $statement->qualify);
        $written = (new \SqlSemantics\SimpleSerializer())->serialize($statement);
        self::assertSame('SELECT `a` AS `a` FROM `t` QUALIFY(row_number() OVER (ORDER BY `a` ASC) = 1)', $written);
        $rebound = $binder->bind($written);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $rebound);
        self::assertSame($written, (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

    public function testRejectsQualifyBeforeMySql83(): void
    {
        $modern = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t QUALIFY a > 1');
        $older = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.2.0'))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $modern);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $older);
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\BoundSelect($older->origin, $older->from, $older->outputs, null, $older->quantifier, [], null, null, qualify: $modern->qualify);
    }

    public function testWithGroupByAcceptsGroupingSetConstructs(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)')))->bind('SELECT a FROM t GROUP BY a');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(Expression::class, $statement->groupBy[0]);
        $changed = $statement->withGroupBy([new \SqlSemantics\Model\Query\Grouping\Rollup([$statement->groupBy[0]]), new \SqlSemantics\Model\Query\Grouping\EmptyGroupingSet()]);
        self::assertSame('SELECT "a" AS "a" FROM "public"."t" GROUP BY ROLLUP("a"), ()', $changed->toString());
        self::assertSame('SELECT "a" AS "a" FROM "public"."t" GROUP BY "a"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}

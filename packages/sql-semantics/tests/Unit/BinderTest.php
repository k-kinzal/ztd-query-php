<?php

declare(strict_types=1);

namespace Tests\Unit;

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

#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Binding\TypeResolution::class)]
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
final class BinderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindSelfJoinPreservesOccurrenceIdentityAndNullProvenance(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT child.id, parent.score AS parent_score, COALESCE(parent.score, 0) AS effective_score FROM users AS child LEFT JOIN users AS parent ON child.parent_id = parent.id');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('s0', $statement->scopeId);
        self::assertSame(['r0', 'r1'], array_column($statement->relations, 'id'));
        self::assertSame($statement->relations[0]->declaration, $statement->relations[1]->declaration);
        self::assertSame(['id', 'parent_score', 'effective_score'], array_column($statement->outputs, 'name'));
        self::assertSame(Nullability::NotNull, $statement->outputs[0]->expression->nullability);
        self::assertSame(Nullability::MaybeNull, $statement->outputs[1]->expression->nullability);
        self::assertSame(['j0'], $statement->outputs[1]->expression->nullExtendedBy);
        self::assertSame('integer', $statement->outputs[1]->expression->type->name);
        self::assertSame(Nullability::NotNull, $statement->outputs[2]->expression->nullability);
        self::assertSame([], $statement->outputs[2]->expression->nullExtendedBy);
        self::assertSame(['j0'], $statement->outputs[2]->expression->inputs()[0]->nullExtendedBy);
        self::assertSame('r1', $statement->outputs[2]->expression->lineage()[0]->relationId);
        self::assertSame(Nullability::NotNull, $statement->relations[1]->declaration->columns[2]->nullability);
    }

    public function testBindPreservesSourceTextAndExpressionNodeIdentity(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (score INTEGER)');
        $sql = '/* source */ SELECT score FROM users';
        $statement = (new Binder($schema))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame($sql, $statement->source->toString());
        self::assertSame($statement->source->find('columnref')[0], $statement->outputs[0]->expression->source);
        self::assertSame($schema->tables[0], $statement->relations[0]->declaration);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindResetsStatementIdentitiesWhenReusingTheBinder(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER)');
        $binder = new Binder($schema);
        $first = $binder->bind('SELECT a.id FROM users a LEFT JOIN users b ON a.id=b.id');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $first);
        $second = $binder->bind('SELECT id FROM users');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $second);
        self::assertSame(['r0', 'r1'], array_column($first->relations, 'id'));
        self::assertSame(['r0'], array_column($second->relations, 'id'));
        self::assertSame($schema->tables[0], $second->relations[0]->declaration);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindUsesTheSchemasDefaultNamespace(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect, 'app'))->build('CREATE TABLE users (id INTEGER)');
        $statement = (new Binder($schema))->bind('SELECT id FROM users');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('app', $statement->relations[0]->declaration->schema);
        self::assertSame($dialect, $statement->outputs[0]->expression->type->dialect);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindPropagatesSyntaxErrors(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build();
        $this->expectException(\SqlParser\Parser\SyntaxException::class);
        (new Binder($schema))->bind('SELECT FROM');
    }

    public function testBindRejectsMissingDeclarations(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $boundQuery1 = $binder->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        self::assertSame('1', $boundQuery1->outputs[0]->expression->spelling());
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Cannot resolve table');
        $binder->bind('SELECT id FROM missing');
    }
    #[DataProvider('providerStatements')]
    public function testVersionedLanguageRetainsEveryStatement(string $sql): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind($sql);
        self::assertSame($sql, $statement->source->toString());
        self::assertNotSame([], $statement->resultColumns());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'aggregate' => ['SELECT COUNT(*) FROM users'];
        yield 'grouping' => ['SELECT score FROM users GROUP BY score'];
        yield 'union' => ['SELECT id FROM users UNION SELECT id FROM users'];
        yield 'cte' => ['WITH u AS (SELECT id FROM users) SELECT id FROM u'];
        yield 'scalar subquery' => ['SELECT (SELECT id FROM users)'];
        yield 'using' => ['SELECT * FROM users a JOIN users b USING (id)'];
        yield 'natural' => ['SELECT * FROM users a NATURAL JOIN users b'];
        yield 'unknown function' => ['SELECT mystery(id) FROM users'];
        yield 'distinct on' => ['SELECT DISTINCT ON (id) id FROM users'];
        yield 'locking' => ['SELECT id FROM users FOR UPDATE'];

    }
    public function testBindAllKeepsStatementBoundaries(): void
    {
        $statements = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bindAll('SELECT 1; SELECT 2');
        self::assertCount(2, $statements);
        self::assertSame('1', $statements[0]->outputs[0]->expression->spelling());
        self::assertSame('2', $statements[1]->outputs[0]->expression->spelling());
    }

    #[DataProvider('providerReleases')]
    public function testBindUsesEveryDeclaredGrammarRelease(Dialect $dialect, string $version): void
    {
        $schema = (new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t (id INTEGER PRIMARY KEY, n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT n, count(*) AS total FROM t GROUP BY n HAVING count(*) > 0');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame($version, $schema->grammarVersion);
        self::assertSame(['n', 'total'], array_column($query->outputs, 'name'));
        self::assertCount(1, $query->groupBy);
        self::assertSame('>', $query->having?->spelling());
    }

    /**
     * @return iterable<string, array{Dialect, string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            yield $version => [Dialect::MySql, 'mysql-' . $version];
        }
        yield 'PostgreSQL 17.2' => [Dialect::PostgreSql, 'pg-17.2'];
        yield 'SQLite 3.47.2' => [Dialect::Sqlite, 'sqlite-3.47.2'];
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindFixtureRequirementsAcrossDialects(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE sales (id INTEGER PRIMARY KEY, amount NUMERIC(10,2), adjusted NUMERIC(10,2) GENERATED ALWAYS AS (amount + 1) STORED)');
        $query = (new Binder($schema))->bind('WITH totals AS (SELECT id, sum(adjusted) AS total FROM sales GROUP BY id HAVING count(*) > 0) SELECT id, total FROM totals WHERE total > 10 ORDER BY id');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\ComputedColumn::class, $schema->tables[0]->columns[2]->generation);
        self::assertSame(['id', 'total'], array_column($query->outputs, 'name'));
        self::assertSame('>', $query->where?->spelling());
        self::assertCount(1, $query->ctes->definitions[0]->query->groupBy);
        self::assertSame('adjusted', $query->outputs[1]->expression->inputs()[0]->lineage()[0]->column->name);
    }

    #[TestWith([Dialect::PostgreSql, 'join b using(id)'])]
    #[TestWith([Dialect::MySql, 'join b using(id)'])]
    #[TestWith([Dialect::Sqlite, 'join b using(id)'])]
    #[TestWith([Dialect::PostgreSql, 'natural join b'])]
    #[TestWith([Dialect::MySql, 'natural join b'])]
    #[TestWith([Dialect::Sqlite, 'natural join b'])]
    public function testBindRetainsLowercaseJoins(Dialect $dialect, string $join): void
    {
        $schema = (new SchemaBuilder($dialect))->build('create table a (id integer primary key, n integer)', 'create table b (id integer primary key, n integer)');
        $query = (new Binder($schema))->bind('select distinct a.id from a ' . $join);
        self::assertInstanceOf(\SqlSemantics\Model\Query\DistinctRows::class, $query->quantifier);
        self::assertSame('id', $query->outputs[0]->name);
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $query->from);
        self::assertNotSame([], $query->from->columns);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindRetainsLowercaseDialectOperations(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('create table a (id integer primary key, n integer)', 'create table b (id integer primary key, n integer)');
        $binder = new Binder($schema);
        $query = $binder->bind('select q.id from (select id from a) as q order by q.id desc limit 2 offset 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('q', $query->relations[0]->alias);
        self::assertSame('2', $query->limit?->spelling());
        self::assertSame('1', $query->offset?->spelling());
        self::assertTrue($query->orderBy[0]->descending);
        $mutation = $binder->bind('update a set n=2 where id=1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $mutation);
        self::assertSame('UPDATE', $mutation->kind->value);
        self::assertSame('=', $mutation->where?->spelling());
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $mutation->writes[0]);
        self::assertSame('2', $mutation->writes[0]->value->spelling());
        self::assertSame($mutation->affectedTables()[0], $mutation->target);

    }


    #[DataProvider('providerReleases')]
    public function testBindNestedQueriesAcrossReleases(Dialect $dialect, string $version): void
    {
        $schema = (new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(id INTEGER PRIMARY KEY, n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT a.id, b.n FROM t a LEFT JOIN (SELECT id, n FROM t WHERE n>0) b ON a.id=b.id ORDER BY a.id DESC LIMIT 3 OFFSET 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame(['id', 'n'], array_column($query->outputs, 'name'));
        self::assertSame(['a', 'b'], array_column($query->relations, 'alias'));
        self::assertSame('not-null', $query->outputs[0]->expression->nullability->value);
        self::assertSame('maybe-null', $query->outputs[1]->expression->nullability->value);
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $query->from);
        self::assertSame('left', $query->from->kind->value);
        self::assertSame('=', $query->from->condition?->spelling());
        self::assertSame('>', $query->relations[1]->query?->where?->spelling());
        self::assertCount(1, $query->orderBy);
        self::assertTrue($query->orderBy[0]->descending);
        self::assertSame('3', $query->limit?->spelling());
        self::assertSame('1', $query->offset?->spelling());
        self::assertCount(1, $query->outputs[1]->expression->nullExtendedBy);
        self::assertNull($query->where);
    }

    #[DataProvider('providerReleases')]
    public function testBindConditionalBranchesAcrossReleases(Dialect $dialect, string $version): void
    {
        $schema = (new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT CASE WHEN n<0 THEN n WHEN n=0 THEN 0 ELSE 1 END AS value FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('value', $query->outputs[0]->name);
        self::assertSame('case', $query->outputs[0]->expression->kind->value);
        self::assertSame('integer', $query->outputs[0]->expression->type->name);
        self::assertSame('maybe-null', $query->outputs[0]->expression->nullability->value);
        self::assertSame('n', $query->outputs[0]->expression->lineage()[0]->column->name);
    }

    #[DataProvider('providerReleases')]
    public function testBindMutationAssignmentsAcrossReleases(Dialect $dialect, string $version): void
    {
        $schema = (new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(id INTEGER PRIMARY KEY, n INTEGER, value TEXT)');
        $query = (new Binder($schema))->bind("UPDATE t SET n=n+1, value='updated' WHERE id=2");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $query);
        self::assertSame('UPDATE', $query->kind->value);
        self::assertSame(['n', 'value'], array_map(static fn ($write) => $write->destinations()[0]->column()->referenceParts()[0], $query->writes));
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $query->writes[0]);
        self::assertSame('+', $query->writes[0]->value->spelling());
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $query->writes[1]);
        self::assertSame("'updated'", $query->writes[1]->value->spelling());
        self::assertSame('=', $query->where?->spelling());
        self::assertSame('t', $query->affectedTables()[0]->declaration->name);
        self::assertSame([], $query->outputs);

        self::assertNull($query->limit);
    }

    #[DataProvider('providerReleases')]
    public function testBindCollectingDiagnosticsMatchesStrictBindingWithCompleteDefinitions(Dialect $dialect, string $version): void
    {
        $schema = (new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(id INTEGER NOT NULL)');
        $binder = new Binder($schema);
        $statement = $binder->bind('SELECT id + 1 AS next_id FROM t', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame([], $statement->diagnostics);
        self::assertEquals($binder->bind('SELECT id + 1 AS next_id FROM t'), $statement);
        self::assertSame('id', $statement->outputs[0]->expression->lineage()[0]->column->name);
    }

    public function testBindEmptyPostgreSqlProjectionAndOrdinaryDualTable(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE dual(id INTEGER)');
        $binder = new Binder($schema);
        self::assertSame([], $binder->bind('SELECT')->outputs);
        $boundQuery1 = $binder->bind('SELECT FROM dual');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        self::assertSame([], $boundQuery1->outputs);
        $boundQuery2 = $binder->bind('SELECT id FROM dual');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery2);
        self::assertSame($schema->tables[0], $boundQuery2->relations[0]->declaration);
    }



    public function testBindRetainsUnresolvedInputsAndEveryProjection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT t.id, 1 AS n, t.* FROM missing t WHERE t.id > 0', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame(['unknown-table', 'unknown-column', 'unknown-column'], array_column($statement->diagnostics, 'reason'));
        self::assertFalse($statement->relations[0]->declaration->resolved);
        self::assertSame([], $statement->relations[0]->declaration->columns);
        self::assertSame(['id', 'n', null], array_column($statement->outputs, 'name'));
        self::assertSame('unresolved-column', $statement->outputs[0]->expression->kind->value);
        self::assertSame(['t', 'id'], $statement->outputs[0]->expression->referenceParts());
        self::assertSame('unknown', $statement->outputs[0]->expression->type->name);
        self::assertSame('unknown', $statement->outputs[0]->expression->nullability->value);
        self::assertSame('integer', $statement->outputs[1]->expression->type->name);
        self::assertSame('wildcard', $statement->outputs[2]->expression->kind->value);
        self::assertSame(['t'], $statement->outputs[2]->expression->referenceParts());
        self::assertSame('>', $statement->where?->spelling());
    }

    public function testBindRetainsKnownBindingsAlongsideUnknownColumns(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT id, missing FROM t', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame(['unknown-column'], array_column($statement->diagnostics, 'reason'));
        self::assertSame($schema->tables[0]->columns[0]->name, $statement->outputs[0]->expression->columnBinding()?->column->name);
        self::assertSame('not-null', $statement->outputs[0]->expression->nullability->value);
        self::assertSame('missing', $statement->outputs[1]->name);
    }

    public function testBindRetainsAmbiguityInsteadOfSelectingAnArbitraryColumn(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('SELECT id FROM t a, t b', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame(['ambiguous-column'], array_column($statement->diagnostics, 'reason'));
        self::assertNull($statement->outputs[0]->expression->columnBinding());
        self::assertSame(['id'], $statement->outputs[0]->expression->referenceParts());
        self::assertCount(2, $statement->relations);
    }

    public function testBindRetainsOpenCteResultsAndMutationAssignments(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('WITH q AS (TABLE absent) SELECT * FROM q', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertFalse($query->relations[0]->declaration->resolved);
        self::assertSame('wildcard', $query->outputs[0]->expression->kind->value);
        self::assertSame('absent', $query->ctes->definitions[0]->query->relations[0]->declaration->name);
        $write = $binder->bind('UPDATE absent SET n=n+1 RETURNING n', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\UpdateStatement::class, $write);
        self::assertSame('UPDATE', $write->kind->value);
        self::assertSame(['n'], array_map(static fn ($write) => $write->destinations()[0]->column()->referenceParts()[0], $write->writes));
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $write->writes[0]);
        self::assertSame('+', $write->writes[0]->value->spelling());
        self::assertSame('unresolved-column', $write->outputs[0]->expression->kind->value);
    }

    #[DataProvider('providerInvalidSemantics')]
    public function testBindPreservesInvalidSemanticStructure(string $sql, string $reason): void
    {
        $result = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql, strict: false);
        self::assertContains($reason, array_column($result->diagnostics, 'reason'));
        self::assertSame($sql, $result->source->toString());
        self::assertNotSame([], $result->outputs);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerInvalidSemantics(): iterable
    {
        yield 'incompatible types' => ['SELECT COALESCE(1, TRUE)', 'incompatible-types'];
        yield 'non boolean predicate' => ['SELECT 1 WHERE 42', 'non-boolean-predicate'];
        yield 'ambiguous output' => ['SELECT 1 AS n, 2 AS n ORDER BY n', 'ambiguous-output'];
        yield 'star without relation' => ['SELECT *', 'unknown-relation'];
        yield 'duplicate relation' => ['SELECT 1 FROM t, t', 'duplicate-relation'];
    }
    public function testBindUnresolvedInputsDoNotAcquireInventedCommonTypes(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT COALESCE(missing, 1)', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('unknown', $query->outputs[0]->expression->type->name);
        self::assertSame('unresolved-column', $query->outputs[0]->expression->inputs()[0]->kind->value);
        self::assertSame('integer', $query->outputs[0]->expression->inputs()[1]->type->name);
    }


    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindAllKeepsDiagnosticsWithEachStatement(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER)'));
        $statements = $binder->bindAll('SELECT missing FROM t; SELECT id FROM t; SELECT other FROM t', strict: false);
        self::assertCount(3, $statements);
        self::assertInstanceOf(\SqlSemantics\Model\BoundQuery::class, $statements[0]);
        self::assertSame(['unknown-column'], array_column($statements[0]->diagnostics, 'reason'));
        self::assertSame([], $statements[1]->diagnostics);
        self::assertSame(['unknown-column'], array_column($statements[2]->diagnostics, 'reason'));
        self::assertSame(['missing'], $statements[0]->outputs[0]->expression->referenceParts());
        self::assertSame('id', $statements[1]->outputs[0]->expression->columnBinding()?->column->name);
        self::assertSame(['other'], $statements[2]->outputs[0]->expression->referenceParts());
        $boundQuery1 = $binder->bind('SELECT id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        self::assertSame([], $boundQuery1->diagnostics);
    }

    public function testBindAllRaisesSemanticErrorsByDefault(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('missing');
        $binder->bindAll('SELECT id FROM t; SELECT missing FROM t');
    }

    public function testBindCollectingDiagnosticsDoesNotChangeLaterStrictCalls(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT missing', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundQuery::class, $statement);
        self::assertSame(['unknown-column'], array_column($statement->diagnostics, 'reason'));
        $this->expectException(SemanticException::class);
        $binder->bind('SELECT missing');
    }

    public function testBindCollectingDiagnosticsStillRejectsInvalidSyntax(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(\SqlParser\Parser\SyntaxException::class);
        $binder->bind('SELECT FROM', strict: false);
    }

    public function testBindAllCollectingDiagnosticsStillRejectsInvalidSyntax(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(\SqlParser\Parser\SyntaxException::class);
        $binder->bindAll('SELECT 1; SELECT FROM', strict: false);
    }



    public function testBindDiagnosticSelectRetainsItsRelationalStages(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $sql = 'WITH q AS (SELECT id FROM t) SELECT DISTINCT q.id, missing FROM q WHERE q.id>0 GROUP BY q.id HAVING q.id>0 ORDER BY q.id DESC LIMIT 2 OFFSET 1';
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame(['unknown-column'], array_column($statement->diagnostics, 'reason'));
        self::assertInstanceOf(\SqlSemantics\Model\Query\DistinctRows::class, $statement->quantifier);
        self::assertSame($statement->ctes->definitions[0]->query, $statement->relations[0]->definition->query);
        self::assertSame($statement->relations[0], $statement->from);
        self::assertSame('>', $statement->where?->spelling());
        self::assertSame('id', $statement->groupBy[0]->columnBinding()?->column->name);
        self::assertSame('>', $statement->having?->spelling());
        self::assertTrue($statement->orderBy[0]->descending);
        self::assertSame('2', $statement->limit?->spelling());
        self::assertSame('1', $statement->offset?->spelling());
        self::assertSame($sql, $statement->source->toString());
    }

    public function testBindDiagnosticInsertRetainsStorageAndConflictEffects(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER PRIMARY KEY)'));
        $statement = $binder->bind('INSERT INTO t(id) VALUES(missing) ON CONFLICT(id) DO UPDATE SET id=EXCLUDED.id WHERE t.id>0 RETURNING id', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        self::assertContains('unknown-column', array_column($statement->diagnostics, 'reason'));
        self::assertSame($statement->affectedTables()[0], $statement->insertion?->target);
        self::assertSame('id', $statement->insertion->columns[0]->column()->columnBinding()?->column->name);
        self::assertSame(['missing'], $statement->rows[0][0]->referenceParts());
        self::assertSame('update', $statement->conflicts[0]->action->value);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\DoUpdate::class, $statement->conflicts[0]);
        self::assertSame('id', $statement->conflicts[0]->assignments[0]->destinations()[0]->column()->columnBinding()?->column->name);
        self::assertSame('>', $statement->conflicts[0]->where?->spelling());
        self::assertSame('id', $statement->outputs[0]->expression->columnBinding()?->column->name);
    }

    public function testBindKeepsNestedDiagnosticsAndCommandBoundaries(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('EXPLAIN UPDATE t SET id=missing WHERE id=1', strict: false);
        self::assertSame(['unknown-column'], array_column($statement->diagnostics, 'reason'));
        self::assertSame('UPDATE', $statement->statement->kind->value);
        self::assertSame('id', $statement->statement->writes[0]->destinations()[0]->column()->columnBinding()?->column->name);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $statement->statement->writes[0]);
        self::assertSame(['missing'], $statement->statement->writes[0]->value->referenceParts());
        self::assertSame('=', $statement->statement->where?->spelling());
    }
    #[DataProvider('providerReleases')]
    public function testBindDefinitionFactsAcrossEveryRelease(Dialect $dialect, string $version): void
    {
        $builder = new SchemaBuilder($dialect, grammarVersion: $version);
        $sql = 'CREATE TABLE t(id INTEGER, parent_id INTEGER, FOREIGN KEY (parent_id) REFERENCES p(id) ON DELETE CASCADE)';
        $schema = $builder->build($sql, 'CREATE INDEX ix ON t(parent_id,id)');
        self::assertSame('cascade', $schema->tables[0]->constraints[0]->onDelete->value);
        self::assertSame(['parent_id', 'id'], array_map(static fn ($key) => $key->column->columnBinding()->column->name, $schema->tables[0]->indexes[0]->elements));
        $binder = new Binder($schema);
        self::assertSame('cascade', $binder->bind($sql)->definition->table->constraints[0]->onDelete->value);
        self::assertSame('parent_id', $binder->bind('CREATE INDEX iy ON t(parent_id)')->index->definition->elements[0]->value()->columnBinding()?->column->name);
    }

    #[TestWith(['VALUES (1, 2), (3)'])]
    #[TestWith(['SELECT 1, 2 UNION SELECT 3'])]
    #[TestWith(['SELECT 1 ORDER BY 2'])]
    public function testBindDiagnosesShapesThatCannotFormAValidStatement(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql, strict: false);
    }

}

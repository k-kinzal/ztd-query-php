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
#[CoversClass(\SqlSemantics\Model\BoundSelect::class)]
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
final class BinderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindSelfJoinPreservesOccurrenceIdentityAndNullProvenance(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT child.id, parent.score AS parent_score, COALESCE(parent.score, 0) AS effective_score FROM users AS child LEFT JOIN users AS parent ON child.parent_id = parent.id');
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
        self::assertSame(['j0'], $statement->outputs[2]->expression->operands[0]->nullExtendedBy);
        self::assertSame('r1', $statement->outputs[2]->expression->lineage()[0]->relationId);
        self::assertSame(Nullability::NotNull, $statement->relations[1]->declaration->columns[2]->nullability);
    }

    public function testBindPreservesSourceTextAndExpressionNodeIdentity(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (score INTEGER)');
        $sql = '/* source */ SELECT score FROM users';
        $statement = (new Binder($schema))->bind($sql);
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
        $second = $binder->bind('SELECT id FROM users');
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
        self::assertSame('1', $binder->bind('SELECT 1')->outputs[0]->expression->symbol);
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
        self::assertTrue($statement->outputs !== [] || $statement->queries !== []);
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
        yield 'insert select' => ['INSERT INTO users (id) SELECT id FROM users'];
    }
    public function testBindAllKeepsStatementBoundaries(): void
    {
        $statements = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bindAll('SELECT 1; SELECT 2');
        self::assertCount(2, $statements);
        self::assertSame('1', $statements[0]->outputs[0]->expression->symbol);
        self::assertSame('2', $statements[1]->outputs[0]->expression->symbol);
    }

    #[DataProvider('providerReleases')]
    public function testBindUsesEveryDeclaredGrammarRelease(Dialect $dialect, string $version): void
    {
        $schema = (new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t (id INTEGER PRIMARY KEY, n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT n, count(*) AS total FROM t GROUP BY n HAVING count(*) > 0');
        self::assertSame($version, $schema->grammarVersion);
        self::assertSame(['n', 'total'], array_column($query->outputs, 'name'));
        self::assertCount(1, $query->groupBy);
        self::assertSame('>', $query->having?->symbol);
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
        self::assertNotNull($schema->tables[0]->columns[2]->generatedExpression);
        self::assertSame(['id', 'total'], array_column($query->outputs, 'name'));
        self::assertSame('>', $query->where?->symbol);
        self::assertCount(1, $query->ctes['totals']->groupBy);
        self::assertSame('adjusted', $query->outputs[1]->expression->operands[0]->lineage()[0]->column->name);
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
        self::assertTrue($query->distinct);
        self::assertSame('id', $query->outputs[0]->name);
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $query->from);
        self::assertNotNull($query->from->condition);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindRetainsLowercaseDialectOperations(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('create table a (id integer primary key, n integer)', 'create table b (id integer primary key, n integer)');
        $binder = new Binder($schema);
        $query = $binder->bind('select q.id from (select id from a) as q order by q.id desc limit 2 offset 1');
        self::assertSame('q', $query->relations[0]->alias);
        self::assertSame('2', $query->limit?->symbol);
        self::assertSame('1', $query->offset?->symbol);
        self::assertTrue($query->orderBy[0]->descending);
        $mutation = $binder->bind('update a set n=2 where id=1');
        self::assertSame('UPDATE', $mutation->kind);
        self::assertSame('=', $mutation->where?->symbol);
        self::assertSame('2', $mutation->assignments['n']->symbol);
        self::assertSame($mutation->targets[0], $mutation->from);
        self::assertFalse($mutation->distinct);
    }


    #[DataProvider('providerReleases')]
    public function testBindNestedQueriesAcrossReleases(Dialect $dialect, string $version): void
    {
        $schema = (new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(id INTEGER PRIMARY KEY, n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT a.id, b.n FROM t a LEFT JOIN (SELECT id, n FROM t WHERE n>0) b ON a.id=b.id ORDER BY a.id DESC LIMIT 3 OFFSET 1');
        self::assertSame(['id', 'n'], array_column($query->outputs, 'name'));
        self::assertSame(['a', 'b'], array_column($query->relations, 'alias'));
        self::assertSame('not-null', $query->outputs[0]->expression->nullability->value);
        self::assertSame('maybe-null', $query->outputs[1]->expression->nullability->value);
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $query->from);
        self::assertSame('left', $query->from->kind->value);
        self::assertSame('=', $query->from->condition?->symbol);
        self::assertSame('>', $query->relations[1]->query?->where?->symbol);
        self::assertCount(1, $query->orderBy);
        self::assertTrue($query->orderBy[0]->descending);
        self::assertSame('3', $query->limit?->symbol);
        self::assertSame('1', $query->offset?->symbol);
        self::assertCount(1, $query->outputs[1]->expression->nullExtendedBy);
        self::assertNull($query->where);
    }

    #[DataProvider('providerReleases')]
    public function testBindConditionalBranchesAcrossReleases(Dialect $dialect, string $version): void
    {
        $schema = (new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT CASE WHEN n<0 THEN n WHEN n=0 THEN 0 ELSE 1 END AS value FROM t');
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
        self::assertSame('UPDATE', $query->kind);
        self::assertSame(['n', 'value'], array_keys($query->assignments));
        self::assertSame('+', $query->assignments['n']->symbol);
        self::assertSame("'updated'", $query->assignments['value']->symbol);
        self::assertSame('=', $query->where?->symbol);
        self::assertSame('t', $query->targets[0]->declaration->name);
        self::assertSame([], $query->outputs);
        self::assertSame([], $query->rows);
        self::assertNull($query->limit);
    }

}

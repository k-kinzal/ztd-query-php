<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
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
#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[CoversClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[CoversClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[CoversClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[CoversClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
#[UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[CoversClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[CoversClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[UsesClass(Dialect::class)]
#[UsesClass(\SqlSemantics\Model\BoundQuery::class)]
#[UsesClass(\SqlSemantics\Model\BoundStatement::class)]
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
final class QueryBinderTest extends TestCase
{
    public function testCompoundPreservesGroupingAndOutputs(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER, n INTEGER)');
        $query = (new Binder($schema))->bind('WITH x(k, v) AS (SELECT id, n FROM t) SELECT k, sum(v) AS total FROM x GROUP BY k HAVING count(*) > 1 UNION ALL SELECT id, n FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CompoundStatement::class, $query);
        self::assertSame('UNION ALL', $query->setOperator->value);

        self::assertSame(['k', 'total'], array_column($query->outputs, 'name'));
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query->left);
        self::assertCount(1, $query->left->groupBy);
        self::assertSame('>', $query->left->having?->spelling());
        self::assertSame('bigint', $query->outputs[1]->expression->type->name);
    }

    public function testWithRetainsAnchorAndRecursiveDependencies(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH RECURSIVE nums(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM nums WHERE n < 5) SELECT n FROM nums');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('n', $query->outputs[0]->name);
        self::assertNotNull($query->ctes);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CompoundStatement::class, $query->ctes->definitions[0]->query);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query->ctes->definitions[0]->query->right);
        self::assertSame('<', $query->ctes->definitions[0]->query->right->where?->spelling());
    }
    public function testBindConstant(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('integer', $query->outputs[0]->expression->type->name);
    }

    public function testExpressionsRetainsHaving(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT count(*) HAVING count(*) > 0');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('>', $query->having?->spelling());
    }

    public function testRenameAppliesCteColumnLists(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH x(a,b) AS (SELECT 1,2) SELECT * FROM x');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame(['a', 'b'], array_column($query->outputs, 'name'));
    }

    public function testBranchesRetainsBagOperation(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 UNION ALL SELECT 2');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CompoundStatement::class, $query);
        self::assertSame('UNION ALL', $query->setOperator->value);
        self::assertSame(\SqlSemantics\Model\Query\SetOperator::UnionAll, $query->setOperator);

    }

    #[\PHPUnit\Framework\Attributes\TestWith(['select 1 union all select NULL', 'integer', 'maybe-null', false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['select 1 union select 2', 'integer', 'not-null', true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['select 1.5 intersect select 2', 'numeric', 'not-null', true])]
    public function testCompoundInfersAcrossAllBranches(string $sql, string $type, string $nullable, bool $distinct): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CompoundStatement::class, $query);
        self::assertSame($type, $query->outputs[0]->expression->type->name);
        self::assertSame($nullable, $query->outputs[0]->expression->nullability->value);
        self::assertSame($distinct, in_array($query->setOperator, [\SqlSemantics\Model\Query\SetOperator::Union, \SqlSemantics\Model\Query\SetOperator::Intersect, \SqlSemantics\Model\Query\SetOperator::Except], true));
        self::assertCount(2, $query->outputs[0]->expression->inputs());
    }

    public function testBindPreservesMultipleGroupingAndDistinctOnKeys(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('create table t (id integer, n integer)');
        $query = (new Binder($schema))->bind('select distinct on (id,n) id,n from t group by id,n having count(*)>0 order by id,n limit 4 offset 2');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Query\DistinctOn::class, $query->quantifier);
        self::assertCount(2, $query->groupBy);
        self::assertCount(2, $query->quantifier->keys);
        self::assertCount(2, $query->orderBy);
        self::assertSame('4', $query->limit?->spelling());
        self::assertSame('2', $query->offset?->spelling());
        self::assertFalse($query->withTies);
    }

    public function testBindRejectsNonBooleanWhere(): void
    {
        $this->expectException(\SqlSemantics\SemanticException::class);
        $this->expectExceptionMessage('boolean');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('select 1 where 2');
    }

    public function testCompoundRejectsDifferentWidths(): void
    {
        $this->expectException(\SqlSemantics\SemanticException::class);
        $this->expectExceptionMessage('same result width');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('select 1 union select 2,3');
    }


    public function testExpressionsBindsAllClauseValuesDirectly(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $tables = new \SqlSemantics\Binding\TableResolver($schema, new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public');
        $context = new \SqlSemantics\Binding\Query\QueryContext($tables);
        $binder = new \SqlSemantics\Binding\Query\QueryBinder($context);
        $parser = new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql);
        $tree = $parser->parse('SELECT 1 GROUP BY 1, 2');
        $body = \SqlSemantics\Binding\Query\QueryNodes::body($tree);
        $values = $binder->expressions($body, ['group_clause'], new \SqlSemantics\Binding\Scope($tables->identifiers));
        self::assertSame(['1', '2'], array_map(static fn ($value) => $value->spelling(), $values));
    }

    public function testWithExposesCtesToItsCaller(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $tables = new \SqlSemantics\Binding\TableResolver($schema, new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public');
        $context = new \SqlSemantics\Binding\Query\QueryContext($tables);
        $binder = new \SqlSemantics\Binding\Query\QueryBinder($context);
        $parser = new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql);
        $tree = $parser->parse('WITH a AS (SELECT 1 AS n), b AS (SELECT n FROM a) SELECT n FROM b');
        $visible = $binder->with($tree, null);
        self::assertSame(['a', 'b'], array_keys($visible->ctes));
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $visible->ctes['b']->query);
        self::assertSame('n', $visible->ctes['b']->query->outputs[0]->name);
    }

    public function testWithKeepsAliasesSeparateFromTheQuery(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH a(renamed) AS (SELECT 1 AS original LIMIT 1) SELECT renamed FROM a');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertNotNull($query->ctes);
        $definition = $query->ctes->definitions[0];
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $definition->query);
        self::assertNotNull($definition->query->limit);
        self::assertSame(['renamed'], $definition->columns);
        self::assertSame('original', $definition->query->outputs[0]->name);
        self::assertSame('1', $definition->query->limit->spelling());
        self::assertSame('renamed', $query->outputs[0]->name);
    }

    public function testBranchesAndCompoundAreComposable(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $tables = new \SqlSemantics\Binding\TableResolver($schema, new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public');
        $context = new \SqlSemantics\Binding\Query\QueryContext($tables);
        $binder = new \SqlSemantics\Binding\Query\QueryBinder($context);
        $parser = new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql);
        $tree = $parser->parse('SELECT 1 AS n UNION ALL SELECT 2');
        $body = \SqlSemantics\Binding\Query\QueryNodes::body($tree);
        self::assertCount(2, $binder->branches($body));
        $query = $binder->compound($tree, $body, $context, 'outer', 'UNION ALL', null);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CompoundStatement::class, $query);
        self::assertSame('outer', $query->scopeId);
        self::assertSame('n', $query->outputs[0]->name);
        self::assertSame($tree, $query->source);
    }

    public function testWithRetainsDataModifyingCteAndReturningAliases(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER, n INTEGER)');
        $query = (new Binder($schema))->bind('WITH moved(x) AS (DELETE FROM t WHERE n<0 RETURNING id) SELECT x FROM moved');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('SELECT', $query->kind->value);
        self::assertNotNull($query->ctes);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteTableStatement::class, $query->ctes->definitions[0]->query);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\CteReference::class, $query->relations[0]);
        self::assertSame('DELETE', $query->ctes->definitions[0]->query->kind->value);
        self::assertSame(['x'], array_column($query->outputs, 'name'));
        self::assertSame('t', $query->ctes->definitions[0]->query->affectedTables()[0]->declaration->name);
        self::assertSame('<', $query->ctes->definitions[0]->query->where?->spelling());
        self::assertSame($query->ctes->definitions[0]->query, $query->relations[0]->definition->query);
        self::assertSame('id', $query->outputs[0]->expression->inputs()[0]->columnBinding()?->column->name);
    }

    public function testRenameRetainsMutationEffectsInAliasedCte(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('WITH q(n) AS (INSERT INTO t(id) VALUES(1) RETURNING id) SELECT n FROM q');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertNotNull($statement->ctes);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement->ctes->definitions[0]->query);
        self::assertSame('id', $statement->ctes->definitions[0]->query->insertion->columns[0]->column()->columnBinding()?->column->name);
        self::assertSame(['n'], $statement->ctes->definitions[0]->columns);
        self::assertSame('id', $statement->ctes->definitions[0]->query->outputs[0]->name);
    }


    public function testWithRetainsNestedMergeEffectsAndOutputAliases(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE TABLE s(id INTEGER)');
        $statement = (new Binder($schema))->bind('WITH changed(result) AS (WITH input AS (SELECT id FROM s) MERGE INTO t USING input ON t.id=input.id WHEN MATCHED THEN DELETE RETURNING t.id) SELECT result FROM changed');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertNotNull($statement->ctes);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\MergeStatement::class, $statement->ctes->definitions[0]->query);
        self::assertNotNull($statement->ctes->definitions[0]->query->ctes);
        self::assertSame('MERGE', $statement->ctes->definitions[0]->query->kind->value);
        self::assertSame('delete', $statement->ctes->definitions[0]->query->merge->actions[0]->action->value);
        self::assertSame(['input'], array_column($statement->ctes->definitions[0]->query->ctes->definitions, 'name'));
        self::assertSame(['result'], $statement->ctes->definitions[0]->columns);
        self::assertSame(['id'], array_column($statement->ctes->definitions[0]->query->outputs, 'name'));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['TABLE t ORDER BY 1 FETCH FIRST 2 ROWS WITH TIES', 'TABLE "public"."t" ORDER BY 1 ASC FETCH FIRST 2 ROWS WITH TIES'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['(TABLE t ORDER BY 1 FETCH FIRST ROW WITH TIES)', 'TABLE "public"."t" ORDER BY 1 ASC FETCH FIRST 1 ROWS WITH TIES'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['VALUES (1) ORDER BY 1 FETCH FIRST ROW WITH TIES', 'VALUES (1) ORDER BY 1 ASC FETCH FIRST 1 ROWS WITH TIES'])]
    public function testBindRetainsWithTiesForTableAndValuesQueries(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)'));
        $query = $binder->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\BoundQuery::class, $query);
        self::assertTrue($query->withTies);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testLeadSkipsOpeningParenthesesOfAValuesBody(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $values = $binder->bind('( ( VALUES ROW (NULL) ) )');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\ValuesStatement::class, $values);
        self::assertSame('VALUES ROW(NULL)', (new \SqlSemantics\SimpleSerializer())->serialize($values));
        self::assertSame('VALUES', \SqlSemantics\Binding\Query\QueryBinder::lead((new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('((VALUES ROW(1)))')));
        self::assertSame('', \SqlSemantics\Binding\Query\QueryBinder::lead(new \SqlParser\Parser\Node('empty', 0, [])));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['SELECT 1 AS k UNION SELECT 2 AS j ORDER BY k', 'integer', 'not-null'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['SELECT 1 AS k UNION ALL SELECT NULL AS j ORDER BY k', 'integer', 'maybe-null'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['SELECT NULL AS k UNION ALL SELECT 1 AS j ORDER BY k', 'integer', 'maybe-null'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['SELECT 1 AS k UNION ALL SELECT 1.5 AS j ORDER BY k', 'numeric', 'not-null'])]
    public function testCompoundOrdersByTheCombinedLeftOutput(string $sql, string $type, string $nullability): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CompoundStatement::class, $query);
        $key = $query->orderBy[0]->key;
        self::assertInstanceOf(\SqlSemantics\Model\Query\Ordering\OutputAlias::class, $key);
        self::assertSame('k', $key->output->name);
        self::assertSame($type, $key->output->expression->type->name);
        self::assertSame($nullability, $key->output->expression->nullability->value);
        self::assertCount(2, $key->output->expression->inputs());
    }

    public function testCompoundAcceptsAnOperandOfUnknownWidth(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM nope UNION SELECT 1', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CompoundStatement::class, $query);
        self::assertSame('SELECT "nope".* FROM "public"."nope" UNION SELECT 1', (new \SqlSemantics\SimpleSerializer())->serialize($query));
    }

    public function testCompoundRejectsALockedPostgreSqlOperand(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('PostgreSQL does not allow FOR UPDATE');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 UNION SELECT 2 FOR UPDATE');
    }

    public function testCompoundAllowsALockedMySqlOperand(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('(SELECT id FROM t) UNION (SELECT n FROM t FOR UPDATE)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CompoundStatement::class, $query);
        self::assertSame('SELECT `id` AS `id` FROM `t` UNION (SELECT `n` AS `n` FROM `t` FOR UPDATE)', (new \SqlSemantics\SimpleSerializer())->serialize($query));
    }

    public function testCompoundKeepsTheSqliteTailOnTheCompound(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1 AS k UNION SELECT 2 ORDER BY k LIMIT 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CompoundStatement::class, $query);
        self::assertSame('SELECT 1 AS "k" UNION SELECT 2 ORDER BY "k" ASC LIMIT 1', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertCount(1, $query->orderBy);
        self::assertSame('1', $query->limit?->spelling());
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query->right);
        self::assertSame([], $query->right->orderBy);
        self::assertNull($query->right->limit);
        self::assertSame('SELECT 2', (new \SqlSemantics\SimpleSerializer())->serialize($query->right));
    }

    public function testWithRejectsADuplicateCteName(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('A WITH clause cannot define the same relation name twice.');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH a AS (SELECT 1), a AS (SELECT 2) SELECT 1');
    }

    public function testWithBindsEveryDataModifyingCte(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('WITH a AS (DELETE FROM t RETURNING id), b AS (DELETE FROM t RETURNING n) SELECT * FROM a, b');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertNotNull($query->ctes);
        self::assertSame(['a', 'b'], array_column($query->ctes->definitions, 'name'));
        self::assertSame(['id', 'n'], array_column($query->outputs, 'name'));
    }

    public function testBindReadsATableQueryOverACte(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('with c as (select 1 as x) table c');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\CteReference::class, $query->from);
        self::assertSame('WITH "c" AS (SELECT 1 AS "x") TABLE "c"', (new \SqlSemantics\SimpleSerializer())->serialize($query));
    }

    public function testBindReadsLowerCaseValuesWithTies(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('values (1) order by 1 fetch first row with ties');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\ValuesStatement::class, $query);
        self::assertTrue($query->withTies);
        self::assertSame('VALUES (1) ORDER BY 1 ASC FETCH FIRST 1 ROWS WITH TIES', (new \SqlSemantics\SimpleSerializer())->serialize($query));
    }

    public function testBindKeepsOptimizerHintsOnlyForMySql(): void
    {
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT /*+ MAX_EXECUTION_TIME(1000) */ 1');
        $postgres = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT /*+ MAX_EXECUTION_TIME(1000) */ 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $mysql);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $postgres);
        self::assertCount(1, $mysql->hints);
        self::assertSame([], $postgres->hints);
        self::assertSame('SELECT /*+ MAX_EXECUTION_TIME(1000) */ 1', (new \SqlSemantics\SimpleSerializer())->serialize($mysql));
    }


    public function testBindReadsWithTiesFromTheFetchKeywords(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a int)'));
        $ties = $binder->bind('SELECT a FROM t ORDER BY a FETCH FIRST 2 ROWS WITH TIES');
        self::assertSame('SELECT "a" AS "a" FROM "public"."t" ORDER BY "a" ASC FETCH FIRST 2 ROWS WITH TIES', (new \SqlSemantics\SimpleSerializer())->serialize($ties));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($ties), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($ties))));
    }
}

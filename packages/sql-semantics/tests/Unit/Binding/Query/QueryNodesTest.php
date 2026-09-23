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

#[CoversClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
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
#[CoversClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
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
final class QueryNodesTest extends TestCase
{
    public function testLocalDoesNotLeakNestedPagination(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT q.id FROM (SELECT id FROM t ORDER BY id DESC LIMIT 2) q');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertNull($query->limit);
        self::assertSame([], $query->orderBy);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DerivedRelation::class, $query->relations[0]);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query->relations[0]->query);
        self::assertSame('2', $query->relations[0]->query->limit?->spelling());
    }

    public function testBodyPreservesSetPrecedence(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SELECT 1 UNION SELECT 2 INTERSECT SELECT 3');
        $body = \SqlSemantics\Binding\Query\QueryNodes::body($tree);
        self::assertSame('UNION', \SqlSemantics\Binding\Query\QueryNodes::setOperator($body));
    }

    public function testSetOperatorIgnoresNestedOperators(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SELECT (SELECT 1 UNION SELECT 2)');
        self::assertNull(\SqlSemantics\Binding\Query\QueryNodes::setOperator(\SqlSemantics\Binding\Query\QueryNodes::body($tree)));
    }
    public function testLocalRetainsOwnedLockingOptions(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT id FROM t FOR UPDATE SKIP LOCKED');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockStrength::Update, $query->locks[0]->strength);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockWait::SkipLocked, $query->locks[0]->wait);
    }

    public function testIsBodyRecognizesLegacySelectFactors(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse('SELECT * FROM SELECT id FROM t');
        $factor = $tree->find('table_factor')[0];
        self::assertTrue(\SqlSemantics\Binding\Query\QueryNodes::isBody($factor));
        self::assertFalse(\SqlSemantics\Binding\Query\QueryNodes::isBody($tree->find('table_factor')[1]));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.7.44'])]
    public function testLegacyCompoundKeepsAllBranchesAndUnionQuantifiers(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $query = $binder->bind('SELECT 1 FROM DUAL WHERE 1 UNION ALL SELECT 2 UNION SELECT 3');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CompoundStatement::class, $query);
        self::assertSame('UNION', $query->setOperator->value);

        self::assertInstanceOf(\SqlSemantics\Model\Statement\CompoundStatement::class, $query->left);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query->left->left);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query->left->right);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query->right);
        self::assertSame('UNION ALL', $query->left->setOperator->value);
        self::assertSame('1', $query->left->left->outputs[0]->expression->spelling());
        self::assertSame('1', $query->left->left->where?->spelling());
        self::assertSame('2', $query->left->right->outputs[0]->expression->spelling());
        self::assertSame('3', $query->right->outputs[0]->expression->spelling());
    }

    public function testBodyRejectsInvalidLegacyUnionWidths(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE a (id INTEGER)');
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder($schema))->bind('SELECT * FROM (a UNION SELECT 1) q', strict: false);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.7.44'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7'])]
    public function testCompoundTailAppliesTrailingClausesToTheWholeUnion(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $query = $binder->bind('SELECT * FROM ((SELECT 1) UNION (SELECT 2 LIMIT 5) ORDER BY 1 LIMIT 1) d');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('SELECT `d`.`?column?` AS `?column?` FROM(SELECT 1 UNION (SELECT 2 LIMIT 5) ORDER BY 1 ASC LIMIT 1) AS `d`', $query->toString());
        self::assertSame('SELECT 1 UNION SELECT 2 ORDER BY 1 ASC LIMIT 2 OFFSET 1', $binder->bind('SELECT 1 UNION SELECT 2 ORDER BY 1 LIMIT 2 OFFSET 1')->toString());
    }

    public function testCompoundTailIsEmptyWhenTheBodyIsTheWholeSource(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SELECT 1 UNION SELECT 2');
        $body = \SqlSemantics\Binding\Query\QueryNodes::body($tree);
        self::assertSame([], \SqlSemantics\Binding\Query\QueryNodes::compoundTail($body, $body)->children);
        self::assertSame([], \SqlSemantics\Binding\Query\QueryNodes::compoundTail($tree, $body)->children);
    }

    public function testModifierScopeStopsAtTheParenthesizedOperand(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertSame('(SELECT 1 LIMIT 1) UNION SELECT 2 LIMIT 3', $binder->bind('(SELECT 1 LIMIT 1) UNION (SELECT 2) LIMIT 3')->toString());
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SELECT 1 LIMIT 1');
        $body = \SqlSemantics\Binding\Query\QueryNodes::body($tree);
        self::assertSame('select_no_parens', \SqlSemantics\Binding\Query\QueryNodes::modifierScope($tree, $body)->name);
        self::assertSame($body, \SqlSemantics\Binding\Query\QueryNodes::modifierScope($body, $body));
    }

    public function testContainsRecognizesTheNodeItselfAndItsDescendants(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SELECT 1');
        $body = \SqlSemantics\Binding\Query\QueryNodes::body($tree);
        self::assertTrue(\SqlSemantics\Binding\Query\QueryNodes::contains($tree, $body));
        self::assertTrue(\SqlSemantics\Binding\Query\QueryNodes::contains($body, $body));
        self::assertFalse(\SqlSemantics\Binding\Query\QueryNodes::contains($body, $tree));
    }

    public function testParentOfFindsTheProductionHoldingTheTarget(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SELECT 1');
        $body = \SqlSemantics\Binding\Query\QueryNodes::body($tree);
        $parent = \SqlSemantics\Binding\Query\QueryNodes::parentOf($tree, $body);
        self::assertNotNull($parent);
        self::assertContains($body, $parent->children);
        self::assertNull(\SqlSemantics\Binding\Query\QueryNodes::parentOf($body, $tree));
    }

    public function testDerivedCompoundMovesTrailingModifiersOutOfTheLastOperand(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse('SELECT * FROM (SELECT 1 UNION SELECT 2 ORDER BY 1 LIMIT 1) d');
        $union = $tree->find('select_derived_union')[0];
        $compound = \SqlSemantics\Binding\Query\QueryNodes::derivedCompound($union);
        self::assertSame('legacy_compound', $compound->name);
        $tail = \SqlSemantics\Ast\Tree::child($compound, ['legacy_compound_tail']);
        self::assertNotNull($tail);
        self::assertSame(['order_clause', 'limit_clause'], array_map(static fn (\SqlParser\Parser\Node $node): string => $node->name, array_values(array_filter($tail->children, static fn ($child): bool => $child instanceof \SqlParser\Parser\Node))));
        $plain = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse('SELECT * FROM (SELECT 1 UNION SELECT 2) d')->find('select_derived_union')[0];
        self::assertSame($plain, \SqlSemantics\Binding\Query\QueryNodes::derivedCompound($plain));
    }

    public function testTrailingModifiersAreReadAndRemovedFromLegacyOperands(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse('SELECT * FROM (SELECT 1 UNION SELECT 2 ORDER BY 1 LIMIT 1) d');
        $operands = $tree->find('query_specification');
        $last = $operands[count($operands) - 1];
        $modifiers = \SqlSemantics\Binding\Query\QueryNodes::trailingModifiers($last);
        self::assertSame(['order_clause', 'limit_clause'], array_map(static fn (\SqlParser\Parser\Node $node): string => $node->name, $modifiers));
        $stripped = \SqlSemantics\Binding\Query\QueryNodes::withoutTrailingModifiers($last);
        self::assertSame([], \SqlSemantics\Binding\Query\QueryNodes::trailingModifiers($stripped));
        self::assertSame('SELECT 2', trim($stripped->toString()));
    }

    public function testWithoutTrailingModifiersLeavesOperandsWithoutClausesIntact(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse('SELECT * FROM (SELECT 1 UNION SELECT 2) d');
        $operands = $tree->find('query_specification');
        $last = $operands[count($operands) - 1];
        self::assertSame($last->toString(), \SqlSemantics\Binding\Query\QueryNodes::withoutTrailingModifiers($last)->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['INSERT INTO t SELECT 1 UNION SELECT 2', 'insert_values'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['CREATE TABLE u AS SELECT 1 UNION SELECT 2', 'create3'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['CREATE VIEW v AS SELECT 1 UNION SELECT 2', 'view_select_aux'])]
    public function testLegacyContainerFindsTheProductionHoldingTheUnionTail(string $sql, string $container): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-5.6.51'))->parse($sql);
        self::assertSame($container, \SqlSemantics\Binding\Query\QueryNodes::legacyContainer($tree)?->name);
        self::assertNull(\SqlSemantics\Binding\Query\QueryNodes::legacyContainer((new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-5.6.51'))->parse('INSERT INTO t SELECT 1')));
    }

    public function testParenthesizedQueryUnwrapsLegacyDerivedTables(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t (a INT)'));
        self::assertSame('SELECT `d`.`?column?` AS `?column?` FROM(SELECT 1 LIMIT 1) AS `d`', $binder->bind('SELECT * FROM ((SELECT 1 LIMIT 1)) d')->toString());
        self::assertSame('SELECT `d`.`?column?` AS `?column?` FROM(SELECT 1 UNION SELECT 2) AS `d`', $binder->bind('SELECT * FROM ((SELECT 1) UNION (SELECT 2)) d')->toString());
        self::assertSame('SELECT `t`.`a` AS `a` FROM `t`', $binder->bind('SELECT * FROM ((t))')->toString());
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse('SELECT * FROM ((t))');
        self::assertNull(\SqlSemantics\Binding\Query\QueryNodes::parenthesizedQuery($tree->find('table_factor')[0]));
    }

    public function testClausesCollectsOnlyTheOuterQueryClauses(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SELECT (SELECT 1 LIMIT 1) FROM t WHERE 1 = 1 ORDER BY 1');
        $clauses = \SqlSemantics\Binding\Query\QueryNodes::clauses($tree);
        self::assertArrayHasKey('from_clause', $clauses);
        self::assertArrayHasKey('where_clause', $clauses);
        self::assertArrayHasKey('sort_clause', $clauses);
        self::assertArrayNotHasKey('limit_clause', $clauses);
    }

}

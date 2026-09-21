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
#[UsesClass(\SqlSemantics\Model\BoundSelect::class)]
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
#[UsesClass(\SqlSemantics\Model\Analysis::class)]
#[UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
final class QueryNodesTest extends TestCase
{
    public function testLocalDoesNotLeakNestedPagination(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT q.id FROM (SELECT id FROM t ORDER BY id DESC LIMIT 2) q');
        self::assertNull($query->limit);
        self::assertSame([], $query->orderBy);
        self::assertSame('2', $query->relations[0]->query?->limit?->symbol);
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
    public function testClausesRetainsLockingOptions(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT id FROM t FOR UPDATE SKIP LOCKED');
        self::assertArrayHasKey('for_locking_clause', $query->syntaxClauses);
        self::assertStringContainsString('SKIP LOCKED', \SqlSemantics\Ast\Tree::text($query->syntaxClauses['for_locking_clause'][0]));
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
        self::assertSame('UNION', $query->setOperator);
        self::assertCount(2, $query->branches);
        self::assertSame('UNION ALL', $query->branches[0]->setOperator);
        self::assertSame('1', $query->branches[0]->branches[0]->outputs[0]->expression->symbol);
        self::assertSame('1', $query->branches[0]->branches[0]->where?->symbol);
        self::assertSame('2', $query->branches[0]->branches[1]->outputs[0]->expression->symbol);
        self::assertSame('3', $query->branches[1]->outputs[0]->expression->symbol);
    }

    public function testBodyRetainsInvalidLegacyUnionInputsForDiagnostics(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE a (id INTEGER)');
        $analysis = (new Binder($schema))->analyze('SELECT * FROM (a UNION SELECT 1) q');
        self::assertContains('invalid-query-input', array_column($analysis->diagnostics, 'reason'));
        $compound = $analysis->statement->relations[0]->query;
        self::assertNotNull($compound);
        self::assertSame('UNION', $compound->setOperator);
        self::assertCount(2, $compound->branches);
        self::assertSame('a', $compound->branches[0]->relations[0]->declaration->name);
        self::assertSame([], $compound->branches[0]->outputs);
        self::assertSame('1', $compound->branches[1]->outputs[0]->expression->symbol);
    }

}

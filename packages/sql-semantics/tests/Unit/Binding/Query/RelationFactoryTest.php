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

#[CoversClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
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
#[UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[CoversClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
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
#[CoversClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[UsesClass(\SqlSemantics\Model\Analysis::class)]
#[UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
final class RelationFactoryTest extends TestCase
{
    public function testFunctionExposesTypedTableFunctionColumns(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT n FROM generate_series(1, 3) AS g(n)');
        self::assertSame('integer', $query->outputs[0]->expression->type->name);
        self::assertSame('GENERATE_SERIES', $query->relations[0]->query?->outputs[0]->expression->symbol);
    }

    public function testAliasHidesJoinInputs(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT q.a, q.b FROM (t a JOIN t b ON a.id = b.id) AS q(a,b)');
        self::assertSame(['a', 'b'], array_column($query->outputs, 'name'));
        self::assertSame('q', $query->relations[0]->alias);
        self::assertCount(2, $query->relations[0]->query->relations ?? []);
    }

    public function testAliasesPreservesQuotedColumnLabels(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT g."Value" FROM generate_series(1,2) AS g("Value")');
        self::assertSame('Value', $query->outputs[0]->name);
    }


    #[\PHPUnit\Framework\Attributes\TestWith(['json_each'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['json_tree'])]
    public function testFunctionBindsJsonTableColumnsAndArguments(string $name): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT * FROM ' . $name . "('[1,2]') AS items");
        self::assertSame(['key','value','type','atom','id','parent','fullkey','path'], array_column($query->outputs, 'name'));
        self::assertSame(['dynamic','dynamic','text','dynamic','integer','integer','text','text'], array_map(static fn ($output): string => $output->expression->type->name, $query->outputs));
        self::assertSame('items', $query->relations[0]->alias);
        self::assertNotNull($query->relations[0]->query);
        self::assertSame(strtoupper($name), $query->relations[0]->query->outputs[0]->expression->symbol);
        self::assertSame("'[1,2]'", $query->relations[0]->query->outputs[0]->expression->operands[0]->symbol);
    }

    public function testFunctionKeepsTheImplicitFunctionName(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM generate_series(1, 2)');
        self::assertSame(['generate_series'], array_column($query->outputs, 'name'));
        self::assertSame('integer', $query->outputs[0]->expression->type->name);
    }

    public function testRescopeAssignsAliasedJoinInputsToTheirInnerScope(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT q.a, q.b FROM (t a JOIN t b ON a.id=b.id) AS q(a,b)');
        $inner = $query->relations[0]->query;
        self::assertNotNull($inner);
        self::assertNotSame($query->scopeId, $inner->scopeId);
        self::assertSame([$inner->scopeId, $inner->scopeId], array_column($inner->relations, 'scopeId'));
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $inner->from);
        self::assertSame($inner->relations[0], $inner->from->left);
        self::assertSame($inner->relations[1], $inner->from->right);
        self::assertSame($inner->relations[0]->id, $inner->outputs[0]->expression->binding?->relationId);
        self::assertSame($inner->relations[1]->id, $inner->from->condition?->operands[1]->binding?->relationId);
    }

}

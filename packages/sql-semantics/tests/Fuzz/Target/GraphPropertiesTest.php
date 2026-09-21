<?php

declare(strict_types=1);

namespace Tests\Fuzz\Target;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

/**
 * Regression tests for the unrestricted semantic property harness.
 */
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Binder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scope::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Analysis::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Expression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaBuilder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Fuzz\Target\GraphProperties::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
final class GraphPropertiesTest extends TestCase
{
    /**
     * Checks that the property harness detects the specified invariant violation.
     */
    public function testStatementRejectsDroppedProjectionOrder(): void
    {
        $valid = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $broken = new \SqlSemantics\Model\BoundStatement('s0', null, [], [new \SqlSemantics\Model\OutputColumn(2, null, $valid->outputs[0]->expression)], null, false, [], null, null, $valid->source);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Output ordinals');
        (new \Fuzz\Target\GraphProperties(Dialect::PostgreSql))->statement($broken);
    }

    /**
     * Checks that the property harness detects the specified invariant violation.
     */
    public function testExpressionRejectsAResolvedColumnWithoutItsBinding(): void
    {
        $value = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->outputs[0]->expression;
        $broken = new \SqlSemantics\Model\Expression(\SqlSemantics\Model\ExpressionKind::Column, $value->type, $value->nullability, $value->source);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing its declaration');
        (new \Fuzz\Target\GraphProperties(Dialect::PostgreSql))->expression($broken);
    }

    /**
     * Checks that the property harness detects the specified invariant violation.
     */
    public function testExpressionRejectsFabricatedUnresolvedTypes(): void
    {
        $value = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->outputs[0]->expression;
        $broken = new \SqlSemantics\Model\Expression(\SqlSemantics\Model\ExpressionKind::UnresolvedColumn, $value->type, $value->nullability, $value->source, reference: ['missing']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fabricated type');
        (new \Fuzz\Target\GraphProperties(Dialect::PostgreSql))->expression($broken);
    }
    /**
     * Checks declarations as well as query expression types.
     */
    public function testDeclarationRejectsAColumnFromAnotherDialect(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INTEGER)')->tables[0];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('declaration has an invalid column type');
        (new \Fuzz\Target\GraphProperties(Dialect::PostgreSql))->declaration($table);
    }

}

<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Analysis;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\StatementList::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Binder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\BoundRelation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\NullFacts::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Scope::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\SelectBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\TableResolver::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\TypeResolution::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\Analysis::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\Expression::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Schema::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(SchemaBuilder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Type\Nullability::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
final class DiagnosticsTest extends TestCase
{
    public function testReportCollectsProblemsInEncounterOrder(): void
    {
        $diagnostics = new \SqlSemantics\Binding\Analysis\Diagnostics(true);
        $source = new \SqlParser\Parser\Node('expr', 0, []);
        $diagnostics->report('unknown-table', 'Missing table', $source);
        $diagnostics->report('unknown-column', 'Missing column', $source);
        self::assertTrue($diagnostics->collect);
        self::assertSame(['unknown-table', 'unknown-column'], array_column($diagnostics->items, 'reason'));
        self::assertSame('Missing column', $diagnostics->items[1]->message);
        self::assertSame($source, $diagnostics->items[1]->source);
    }

    public function testReportRaisesByDefault(): void
    {
        $diagnostics = new \SqlSemantics\Binding\Analysis\Diagnostics();
        self::assertFalse($diagnostics->collect);
        self::assertSame([], $diagnostics->items);
        $this->expectException(\SqlSemantics\SemanticException::class);
        $this->expectExceptionMessage('Missing column');
        $diagnostics->report('unknown-column', 'Missing column', new \SqlParser\Parser\Node('expr', 0, []));
    }
}

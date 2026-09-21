<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\ExpressionEdit::class)]
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
final class DiagnosticTest extends TestCase
{
    public function testRetainsReasonMessageAndOriginalSyntax(): void
    {
        $source = new \SqlParser\Parser\Node('columnref', 0, []);
        $diagnostic = new \SqlSemantics\Model\Diagnostic('unknown-column', 'Missing column', $source);
        self::assertSame('unknown-column', $diagnostic->reason);
        self::assertSame('Missing column', $diagnostic->message);
        self::assertSame($source, $diagnostic->source);
    }
}

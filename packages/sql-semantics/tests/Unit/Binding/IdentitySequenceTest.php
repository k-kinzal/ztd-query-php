<?php

declare(strict_types=1);

namespace Tests\Unit\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\SemanticException;

#[CoversClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[UsesClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[UsesClass(\SqlSemantics\Binder::class)]
#[UsesClass(\SqlSemantics\SchemaBuilder::class)]
#[UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[UsesClass(\SqlSemantics\Binding\Scope::class)]
#[UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[UsesClass(\SqlSemantics\Model\Expression::class)]
#[UsesClass(\SqlSemantics\Model\Join::class)]
#[UsesClass(\SqlSemantics\Model\Ordering::class)]
#[UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[UsesClass(\SqlSemantics\Model\TableUse::class)]
#[UsesClass(\SqlSemantics\Schema::class)]
#[UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[UsesClass(SemanticException::class)]
#[UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
#[UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
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
#[UsesClass(\SqlSemantics\Binding\Editing\ExpressionEdit::class)]
#[UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[UsesClass(\SqlSemantics\Model\Validation\ExpressionInvariant::class)]
#[UsesClass(\SqlSemantics\Model\Validation\StatementInvariant::class)]
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
final class IdentitySequenceTest extends TestCase
{
    public function testRelationAllocatesDeterministicSequence(): void
    {
        $ids = new \SqlSemantics\Binding\IdentitySequence();
        self::assertSame('r0', $ids->relation());
        self::assertSame('j0', $ids->join());
        self::assertSame('r1', $ids->relation());
        self::assertSame('j1', $ids->join());
    }

    public function testJoinDoesNotConsumeRelationOrdinals(): void
    {
        $ids = new \SqlSemantics\Binding\IdentitySequence();
        self::assertSame('j0', $ids->join());
        self::assertSame('j1', $ids->join());
        self::assertSame('r0', $ids->relation());
    }
    public function testScopeAllocatesDistinctScopes(): void
    {
        $ids = new \SqlSemantics\Binding\IdentitySequence();
        self::assertSame('s0', $ids->scope());
        self::assertSame('s1', $ids->scope());
    }

}

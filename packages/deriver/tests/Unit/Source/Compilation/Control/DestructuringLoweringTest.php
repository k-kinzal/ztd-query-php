<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Compilation\Control;

use Deriver\Result\Alternative;
use Deriver\Result\Exceptional;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use JsonException;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\Int_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Programs\DestructuringPrograms;
use Tests\Fake\SourceFixture;

#[CoversClass(DestructuringLowering::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\CatchTarget::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Allocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\NativeArguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\Handler::class)]
#[UsesClass(\Deriver\Evaluation\Control\IterationStep::class)]
#[UsesClass(\Deriver\Evaluation\Control\IteratorCursor::class)]
#[UsesClass(\Deriver\Evaluation\Control\LoopConvergence::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Components::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Key::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Table::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\Model\StateStorage::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Address::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Path::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Protocol::class)]
#[UsesClass(\Deriver\Evaluation\Offset\ProtocolAccess::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Reader::class)]
#[UsesClass(\Deriver\Evaluation\Offset\StringAccess::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Strings::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\CompoundAssignment::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\IntrinsicTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyReference::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\LiveArray::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\Builtin\FunctionModel::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\Builtin\ScalarFunctions::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanActions::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanCompiler::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanFootprints::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanValidation::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Result\Serialization\QueryEncoding::class)]
#[UsesClass(\Deriver\Result\Serialization\ValueGraph::class)]
#[UsesClass(\Deriver\Result\Statistics::class)]
#[UsesClass(\Deriver\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\LoopLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Increment::class)]
#[UsesClass(\Deriver\Value\NumericString::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class DestructuringLoweringTest extends TestCase
{
    /**
     * @throws JsonException If recorded engine observations cannot be decoded
     */
    #[DataProviderExternal(DestructuringPrograms::class, 'cases')]
    public function testAssignmentPreservesRecordedValuesReferenceEffectsAndExceptions(string $source, string $normalJson, string $exception, bool $diagnostic): void
    {
        $expected = json_decode($normalJson, true, 512, JSON_THROW_ON_ERROR);
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame($expected, array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes));
        self::assertSame($exception === '' ? [] : [$exception], array_map(static fn (Exceptional $outcome): int|float|string|bool|null => $outcome->exception->literal, $result->exceptionalOutcomes));
        self::assertSame($diagnostic, in_array('PHP_WARNING', array_column($result->frontiers, 'code'), true));
        self::assertSame([], array_diff(array_column($result->frontiers, 'code'), ['PHP_WARNING']));
    }

    #[DataProvider('providerReferencePatterns')]
    public function testReferencesFindsNestedReferenceLeavesWithoutTreatingEveryPatternAsShared(Expr $pattern, bool $expected): void
    {
        self::assertSame($expected, DestructuringLowering::references($pattern));
    }

    /**
     * @return iterable<string,array{Expr,bool}>
     */
    public static function providerReferencePatterns(): iterable
    {
        yield 'ordinary variable' => [new Expr\Variable('x'), false];
        yield 'ordinary list' => [new Expr\List_([new ArrayItem(new Expr\Variable('x'))]), false];
        yield 'list holes' => [new Expr\List_([null, new ArrayItem(new Expr\Variable('x'))]), false];
        yield 'reference list' => [new Expr\List_([new ArrayItem(new Expr\Variable('x'), byRef:true)]), true];
        yield 'nested reference' => [new Expr\List_([null, new ArrayItem(new Expr\Array_([new ArrayItem(new Expr\Variable('x'), byRef:true)]))]), true];
        yield 'nested value' => [new Expr\List_([new ArrayItem(new Expr\List_([new ArrayItem(new Expr\Variable('x'))]))]), false];
    }

    public function testAssignPreservesExplicitKeysAndMissingNumericPositions(): void
    {
        $lowering = SourceFixture::lowering();
        $pattern = new Expr\List_([null, new ArrayItem(new Expr\Variable('x')), new ArrayItem(new Expr\Variable('y'), new Int_(7))]);
        $result = (new DestructuringLowering($lowering))->assign($pattern, 'incoming');
        $instructions = $lowering->graph->instructions[0];
        self::assertSame('incoming', $result);
        self::assertSame(['constant', 'array-read', 'local', 'write', 'constant', 'array-read', 'local', 'write'], array_column($instructions, 'operation'));
        self::assertSame(1, $instructions[0]->constant?->literal);
        self::assertSame(7, $instructions[4]->constant?->literal);
        self::assertSame(['incoming', 'r0'], $instructions[1]->operands);
        self::assertSame(['incoming', 'r4'], $instructions[5]->operands);
        self::assertTrue($instructions[1]->attributes['destructure']);
        self::assertTrue($instructions[5]->attributes['destructure']);
    }

    #[DataProvider('providerSources')]
    public function testSourceDistinguishesWritableStorageAndTemporaryValues(Expr $expression, bool $diagnostic, string $operation): void
    {
        $lowering = SourceFixture::lowering();
        $result = (new DestructuringLowering($lowering))->source($expression, $diagnostic);
        $instructions = $lowering->graph->instructions[0];
        self::assertNotEmpty($instructions);
        $last = $instructions[array_key_last($instructions)];
        self::assertSame($result, $last->result);
        self::assertSame($operation, $last->operation);
        self::assertSame($operation === 'returned-address' ? ['temporary-reference' => true, 'temporary-warning' => $diagnostic] : [], $last->attributes);
    }

    /**
     * @return iterable<string,array{Expr,bool,string}>
     */
    public static function providerSources(): iterable
    {
        yield 'variable' => [new Expr\Variable('source'), true, 'local'];
        yield 'array element' => [new Expr\ArrayDimFetch(new Expr\Variable('source'), new Int_(0)), true, 'element-address'];
        yield 'property' => [new Expr\PropertyFetch(new Expr\Variable('source'), 'items'), true, 'field-address'];
        yield 'temporary array for foreach' => [new Expr\Array_([new ArrayItem(new Int_(1))]), false, 'returned-address'];
        yield 'ordinary function for assignment' => [new Expr\FuncCall(new \PhpParser\Node\Name('source')), true, 'returned-address'];
        yield 'ordinary function for foreach' => [new Expr\FuncCall(new \PhpParser\Node\Name('source')), false, 'returned-address'];
    }

    public function testAssignmentDelegatesOrdinaryVariableAssignments(): void
    {
        $lowering = SourceFixture::lowering();
        $result = (new DestructuringLowering($lowering))->assignment(new Expr\Assign(new Expr\Variable('x'), new Int_(7)));
        $instructions = $lowering->graph->instructions[0];
        self::assertSame(['constant', 'local', 'write', 'copy'], array_column($instructions, 'operation'));
        self::assertSame($result, $instructions[3]->result);
        self::assertSame(7, $instructions[0]->constant?->literal);
    }
}

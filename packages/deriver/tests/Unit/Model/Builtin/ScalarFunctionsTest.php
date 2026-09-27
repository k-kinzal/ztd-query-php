<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Builtin;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Model\Inputs;
use Deriver\Evaluation\Call\Model\NativeArguments;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\CollectionCalls;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Components;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Demand\Table;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Invocation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\CallableTransfer;
use Deriver\Evaluation\Transfer\IntrinsicTransfer;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Binding\ArgumentBindings;
use Deriver\Model\Binding\BoundArgument;
use Deriver\Model\Builtin\ArrayFunctions;
use Deriver\Model\Builtin\Formatting;
use Deriver\Model\Builtin\FunctionModel;
use Deriver\Model\Builtin\Library;
use Deriver\Model\Builtin\Replacement;
use Deriver\Model\Builtin\ScalarFunctions;
use Deriver\Model\Builtin\Sorting;
use Deriver\Model\Builtin\StringFunctions;
use Deriver\Model\Builtin\TypePredicates;
use Deriver\Model\CallDescription;
use Deriver\Model\Compilation\PlanActions;
use Deriver\Model\Compilation\PlanCompiler;
use Deriver\Model\Compilation\PlanFootprints;
use Deriver\Model\Compilation\PlanValidation;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Model\Signature\Signature;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\ResultRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Assessment;
use Deriver\Result\Derivation;
use Deriver\Result\DerivationResult;
use Deriver\Result\Serialization\JsonText;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Result\Serialization\ValueGraph;
use Deriver\Result\Statistics;
use Deriver\Result\StorageSnapshot;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\AggregateLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\EffectInspection;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Arithmetic;
use Deriver\Value\Arrays;
use Deriver\Value\Comparison;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScalarFunctions::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Inputs::class)]
#[UsesClass(NativeArguments::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(CollectionCalls::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(IntrinsicTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(ArgumentBindings::class)]
#[UsesClass(BoundArgument::class)]
#[UsesClass(ArrayFunctions::class)]
#[UsesClass(Formatting::class)]
#[UsesClass(FunctionModel::class)]
#[UsesClass(Library::class)]
#[UsesClass(Replacement::class)]
#[UsesClass(Sorting::class)]
#[UsesClass(StringFunctions::class)]
#[UsesClass(TypePredicates::class)]
#[UsesClass(CallDescription::class)]
#[UsesClass(PlanActions::class)]
#[UsesClass(PlanCompiler::class)]
#[UsesClass(PlanFootprints::class)]
#[UsesClass(PlanValidation::class)]
#[UsesClass(ModelDecision::class)]
#[UsesClass(ModelDescriptor::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(ProjectSnapshot::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(JsonText::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(AggregateLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ScalarFunctionsTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){return array_reduce([1,2,3],fn($a,$b)=>$a+$b,0);}');
        self::assertSame(6, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testClassNamePreservesRuntimeIdentityAndUnknownSubclasses(): void
    {
        $functions = new ScalarFunctions();
        self::assertSame('Box', $functions->className(new Term('object', 'a', attributes: ['class' => 'Box']))->native());
        self::assertSame('Closure', $functions->className(new Term('closure', 'b'))->native());
        self::assertSame('intrinsic', $functions->className(new Term('parameter', 'value', attributes: ['type' => 'Box']))->kind);
        self::assertSame('opaque', $functions->className(new Term('omitted'))->kind);
    }

    /**
     * @param string $name Registered intrinsic
     * @param list<Term> $arguments Bound arguments
     * @param mixed $expected Concrete result
     */
    #[DataProvider('providerIntrinsics')]
    public function testApplyRoutesRegisteredIntrinsicsWithTheirBoundArguments(string $name, array $arguments, mixed $expected): void
    {
        self::assertSame($expected, (new ScalarFunctions())->apply($name, $arguments)->native());
    }

    /**
     * @return iterable<string,array{string,list<Term>,mixed}>
     */
    public static function providerIntrinsics(): iterable
    {
        yield 'sort reindexes' => ['sort-values',[Term::fromNative(['b' => 3,'a' => 1])],[1,3]];
        yield 'replacement pair' => ['replace-pair',[Term::constant('a'),Term::constant('x'),Term::constant('banana')],['result' => 'bxnxnx','count' => 3]];
        yield 'formatting variadic' => ['sprintf',[Term::constant('%s:%d%%'),Term::fromNative(['id',7])],'id:7%'];
        yield 'runtime class' => ['get_class',[new Term('object', 'one', attributes:['class' => 'App\Box'])],'App\Box'];
        yield 'predicate' => ['is_int',[Term::constant(7)],true];
        yield 'default predicate operand' => ['is_null',[],true];
        yield 'count' => ['count',[Term::fromNative(['a' => 1,'b' => 2])],2];
        yield 'keys' => ['array_keys',[Term::fromNative(['a' => 1,7 => 2])],['a',7]];
        yield 'values' => ['array_values',[Term::fromNative(['a' => 1,7 => 2])],[1,2]];
        yield 'merge variadic' => ['array_merge',[Term::fromNative([[7 => 1,'x' => 2],[3,'x' => 4]])],[1,'x' => 4,3]];
        yield 'key existence' => ['array_key_exists',[Term::constant('x'),Term::fromNative(['x' => null])],true];
        yield 'membership' => ['in_array',[Term::constant(3),Term::fromNative([2,3]),Term::constant(true)],true];
        yield 'length' => ['strlen',[Term::constant('bytes')],5];
        yield 'lowercase' => ['strtolower',[Term::constant('AbC')],'abc'];
        yield 'uppercase' => ['strtoupper',[Term::constant('AbC')],'ABC'];
        yield 'trim' => ['trim',[Term::constant(' x '),Term::constant(' ')],'x'];
        yield 'substring' => ['substr',[Term::constant('abcde'),Term::constant(1),Term::constant(3)],'bcd'];
        yield 'implode' => ['implode',[Term::constant(':'),Term::fromNative(['a','b'])],'a:b'];
        yield 'join alias' => ['join',[Term::constant('/'),Term::fromNative(['a','b'])],'a/b'];
        yield 'explode' => ['explode',[Term::constant(':'),Term::constant('a:b:c')],['a','b','c']];
    }

    public function testApplyUnknownIntrinsicsRetainEveryInputDependency(): void
    {
        $arguments = [Term::constant('confidential', true),Term::parameter('unknown')];
        $result = (new ScalarFunctions())->apply('unregistered', $arguments);
        self::assertSame('opaque', $result->kind);
        self::assertSame('UNSUPPORTED_MODEL_CASE', $result->literal);
        self::assertSame($arguments, $result->operands);
        self::assertTrue($result->isSecret());
    }

    /**
     * @param Term $value Object identity
     * @param string $expected Runtime class spelling
     */
    #[DataProvider('providerKnownClasses')]
    public function testClassNamePreservesExactSpellingAndConfidentiality(Term $value, string $expected): void
    {
        $result = (new ScalarFunctions())->className($value);
        self::assertSame($expected, $result->native());
        self::assertTrue($result->isSecret());
    }

    /**
     * @return iterable<string,array{Term,string}>
     */
    public static function providerKnownClasses(): iterable
    {
        yield 'object' => [new Term('object', 'one', attributes:['class' => 'App\MiXeD'], secret:true),'App\MiXeD'];
        yield 'enum' => [new Term('enum', 'App\Mode::Ready', attributes:['class' => 'App\Mode'], secret:true),'App\Mode'];
        yield 'closure' => [new Term('closure', 'body', secret:true),'Closure'];
    }

    public function testClassNameRetainsUnknownRuntimeClassesAsDependentStringExpressions(): void
    {
        $input = new Term('parameter', 'object', attributes:['type' => 'Base'], secret:true);
        $result = (new ScalarFunctions())->className($input);
        self::assertSame('intrinsic', $result->kind);
        self::assertSame('get_class', $result->literal);
        self::assertSame([$input], $result->operands);
        self::assertSame('string', $result->attributes['type']);
        self::assertTrue($result->isSecret());
    }
}

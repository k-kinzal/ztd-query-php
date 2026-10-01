<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Builtin;

use Deriver\Model\Builtin\ScalarFunctions;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScalarFunctions::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\NativeArguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\CollectionCalls::class)]
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
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\IntrinsicTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\Builtin\ArrayFunctions::class)]
#[UsesClass(\Deriver\Model\Builtin\Formatting::class)]
#[UsesClass(\Deriver\Model\Builtin\FunctionModel::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\Builtin\Replacement::class)]
#[UsesClass(\Deriver\Model\Builtin\Sorting::class)]
#[UsesClass(\Deriver\Model\Builtin\StringFunctions::class)]
#[UsesClass(\Deriver\Model\Builtin\TypePredicates::class)]
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
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
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
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
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
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\FloatConversion::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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

<?php

declare(strict_types=1);

namespace Tests\Unit\Standard;

use Deriver\Standard\ScalarFunctions;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScalarFunctions::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\PlanActions::class)]
#[UsesClass(\Deriver\Internal\Model\PlanCompiler::class)]
#[UsesClass(\Deriver\Internal\Model\PlanFootprints::class)]
#[UsesClass(\Deriver\Internal\Model\PlanValidation::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Model\NativeArguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\CollectionCalls::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Cell::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Components::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\IntrinsicTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(\Deriver\Model\CallModel::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\ArrayFunctions::class)]
#[UsesClass(\Deriver\Standard\Formatting::class)]
#[UsesClass(\Deriver\Standard\FunctionModel::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(\Deriver\Standard\Replacement::class)]
#[UsesClass(ScalarFunctions::class)]
#[UsesClass(\Deriver\Standard\Sorting::class)]
#[UsesClass(\Deriver\Standard\StringFunctions::class)]
#[UsesClass(\Deriver\Standard\TypePredicates::class)]
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

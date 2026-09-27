<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Transfer;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\CatchTarget;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\Allocation;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\MethodInvocation;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Creation;
use Deriver\Evaluation\Call\Preparation\Methods;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Constant\ClassNames;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionChain;
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\Handler;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Offset\Address;
use Deriver\Evaluation\Offset\Path;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\ObjectAccess;
use Deriver\Evaluation\Transfer\PropertyAccessCheck;
use Deriver\Evaluation\Transfer\PropertyLookup;
use Deriver\Evaluation\Transfer\PropertyReference;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Evaluation\Transfer\PropertyTransfer;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Provider\DispatchDecision;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
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
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\Control\ConditionalLowering;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\Control\ExceptionLowering;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\ConstantSignatures;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\Declaration\Traits\LexicalConstants;
use Deriver\Source\Declaration\Traits\Members;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Arithmetic;
use Deriver\Value\Arrays;
use Deriver\Value\Identity;
use Deriver\Value\Increment;
use Deriver\Value\NumericString;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Transfer\PropertyReference
 */
#[CoversClass(PropertyReference::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(CatchTarget::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Allocation::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Creation::class)]
#[UsesClass(Methods::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ClassNames::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionChain::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(Handler::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Address::class)]
#[UsesClass(Path::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PropertyTransfer::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(DispatchDecision::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
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
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(ConditionalLowering::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LexicalConstants::class)]
#[UsesClass(Members::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Increment::class)]
#[UsesClass(NumericString::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class PropertyReferenceTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAliasRejectsReadonlySources(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{function __construct(public readonly int $x){}}function target(){$b=new B(1);try{$ref=&$b->x;}catch(Error $e){return $b->x;}return 999;}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(1, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testBindChecksPropertyAndSourceConstraintsBeforeRebinding(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{public int $x=1;}function target(){$b=new B;$x=[];try{$b->x=&$x;}catch(TypeError $e){return [$b->x,$x];}return 999;}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([1, []], $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testIncrementPreservesTheOldValueOnIntegerOverflow(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{public int $x=9223372036854775807;}function target(){$b=new B;try{$b->x++;}catch(TypeError $e){return $b->x;}return 999;}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(9223372036854775807, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testReadInitializesNullableStorageBeforeExposingItsReference(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{public ?int $x;}function target(){$b=new B;$r=&$b->x;return [$b->x,$r];}');
        self::assertSame([null,null], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertSame([], $result->frontiers);
    }
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testReadRejectsAnUninitializedNonnullableReference(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{public int $x;}function target(){$b=new B;try{$r=&$b->x;}catch(Error $e){return "uninitialized";}return 999;}');
        self::assertSame('uninitialized', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerUninitializedIncrement')]
    public function testIncrementRejectsUninitializedTypedPropertiesBeforeAnyWrite(string $type, int $delta, bool $post): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'a.php', 0, 1);
        $caller = new CallableGraph('target', [], [], $source);
        $state = new State();
        $address = new Location('object:one', ['x']);
        $before = new Term('uninitialized', attributes:['type' => $type]);
        $state->memory->write($address, $before);
        $slot = new PropertySlot(new Term('object', 'one', attributes:['class' => 'B']), 'x', 'B', new PropertyDeclaration('x', 'B', $type));
        $instruction = new Instruction('i', 'increment', $source, 'result', ['address'], attributes:['delta' => $delta,'post' => $post]);
        $paths = (new PropertyReference(new Machine($context)))->increment($caller, $instruction, $state, $slot, $address);
        self::assertCount(1, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertSame($before, $paths[0]->memory->read($address));
        self::assertSame([], $state->registers);
        self::assertSame([], $context->frontiers);
    }
    /**
     * @return iterable<string,array{string,int,bool}>
     */
    public static function providerUninitializedIncrement(): iterable
    {
        yield 'integer post increment' => ['int',1,true];
        yield 'integer pre increment' => ['int',1,false];
        yield 'nullable post decrement' => ['int|null',-1,true];
        yield 'nullable pre decrement' => ['int|null',-1,false];
        yield 'string post increment' => ['string',1,true];
        yield 'float pre decrement' => ['float',-1,false];
    }


    /**
     * @throws JsonException If independently observed fixture values cannot be decoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerPropertyAliases')]
    public function testBindPreservesRecordedReferenceAndPropertyMutationSemantics(string $source, string $expected): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expected, true, flags:JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @return iterable<string,array{string,string}>
     */
    public static function providerPropertyAliases(): iterable
    {
        foreach (\Tests\Fake\Programs\PropertyPrograms::cases() as $name => $case) {
            if (str_contains($name, 'reference') || str_contains($name, 'alias') || str_contains($name, 'increment')) {
                yield $name => $case;
            }
        }
        foreach (\Tests\Fake\Programs\PromotedReferencePrograms::cases() as $name => $case) {
            yield 'promotion: ' . $name => $case;
        }
    }

    public function testBindSeparatesSymbolicTypeFailuresBeforeMutatingEitherStorage(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'a.php', 0, 1);
        $caller = new CallableGraph('target', [], [], $source);
        $state = new State();
        $address = new Location('object:one', ['x']);
        $input = new Term('input', 'argument');
        $origin = $state->memory->allocate($input);
        $state->addresses['source'] = $origin;
        $state->memory->write($address, Term::constant(7));
        $slot = new PropertySlot(new Term('object', 'one', attributes:['class' => 'B']), 'x', 'B', new PropertyDeclaration('x', 'B', 'int'));
        $instruction = new Instruction('i', 'alias', $source, 'result', ['destination', 'source']);
        $paths = (new PropertyReference(new Machine($context)))->bind($caller, $instruction, $state, $slot, $address);
        self::assertCount(2, $paths);
        self::assertSame(['normal', 'throw'], array_map(static fn (State $path): string => $path->completion->kind, $paths));
        self::assertSame('type-refinement', $paths[0]->registers['result']->kind);
        self::assertSame('int', $paths[0]->registers['result']->attributes['type']);
        self::assertSame([$input], $paths[0]->registers['result']->operands);
        self::assertSame($paths[0]->registers['result'], $paths[0]->memory->read($origin));
        self::assertSame($paths[0]->registers['result'], $paths[0]->memory->read($address));
        self::assertSame('TypeError', $paths[1]->completion->value?->literal);
        self::assertSame(7, $paths[1]->memory->read($address)->literal);
        self::assertSame($input, $paths[1]->memory->read($origin));
        self::assertArrayNotHasKey('result', $paths[1]->registers);
        $paths[0]->memory->write($origin, Term::constant(12));
        self::assertSame(12, $paths[0]->memory->read($address)->literal);
        self::assertSame(7, $paths[1]->memory->read($address)->literal);
        self::assertSame([], $context->frontiers);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerReferenceCoercion')]
    public function testBindCoercesTheSharedSourceOnlyWhenEveryDeclarationAcceptsIt(bool $strict, ?string $sourceType, bool $success): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'a.php', 0, 1);
        $caller = new CallableGraph('target', [], [], $source, strict: $strict);
        $state = new State();
        $address = new Location('object:one', ['x']);
        $origin = new Location('object:two', ['y']);
        $state->addresses['source'] = $origin;
        $state->memory->write($origin, Term::constant('12'));
        $state->memory->propertyTypes['object:two'] = $sourceType === null ? [] : ['y' => $sourceType];
        $state->memory->write($address, Term::constant(7));
        $slot = new PropertySlot(new Term('object', 'one', attributes:['class' => 'B']), 'x', 'B', new PropertyDeclaration('x', 'B', 'int'));
        $instruction = new Instruction('i', 'alias', $source, 'result', ['destination', 'source']);
        $paths = (new PropertyReference(new Machine($context)))->bind($caller, $instruction, $state, $slot, $address);
        self::assertCount(1, $paths);
        self::assertSame($success ? 'normal' : 'throw', $paths[0]->completion->kind);
        self::assertSame($success ? null : 'TypeError', $paths[0]->completion->value?->literal);
        self::assertSame($success ? 12 : '12', $paths[0]->memory->read($origin)->literal);
        self::assertSame($success ? 12 : 7, $paths[0]->memory->read($address)->literal);
        self::assertSame($success ? 12 : null, ($paths[0]->registers['result'] ?? null)?->literal);
        $paths[0]->memory->write($origin, Term::constant(19));
        self::assertSame($success ? 19 : 7, $paths[0]->memory->read($address)->literal);
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return iterable<string,array{bool,?string,bool}>
     */
    public static function providerReferenceCoercion(): iterable
    {
        yield 'untyped source coerced' => [false, null, true];
        yield 'strict binding rejects numeric string' => [true, null, false];
        yield 'existing string declaration prevents integer coercion' => [false, 'string', false];
        yield 'existing union accepts the same integer' => [false, 'int|float', true];
    }

    public function testBindRejectsReadonlyPropertiesBeforeReadingTheSource(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'a.php', 0, 1);
        $caller = new CallableGraph('target', [], [], $source);
        $state = new State();
        $address = new Location('object:one', ['x']);
        $state->memory->write($address, Term::constant(7));
        $slot = new PropertySlot(new Term('object', 'one', attributes:['class' => 'B']), 'x', 'B', new PropertyDeclaration('x', 'B', 'int', readonly: true));
        $instruction = new Instruction('i', 'alias', $source, 'result', ['destination', 'unavailable']);
        $paths = (new PropertyReference(new Machine($context)))->bind($caller, $instruction, $state, $slot, $address);
        self::assertSame([$state], $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertSame(7, $paths[0]->memory->read($address)->literal);
        self::assertSame([], $paths[0]->registers);
    }

    public function testReadExposesTheCellOfAnUndeclaredDynamicProperty(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'a.php', 0, 1);
        $caller = new CallableGraph('target', [], [], $source);
        $state = new State();
        $address = new Location('object:one', ['x']);
        $state->addresses['property'] = $address;
        $state->memory->write($address, Term::constant(7));
        $slot = new PropertySlot(new Term('object', 'one', attributes:['class' => 'B']), 'x', 'B', null);
        $instruction = new Instruction('i', 'reference', $source, 'result', ['property']);
        $paths = (new PropertyReference(new Machine($context)))->read($caller, $instruction, $state, $slot, $address);
        self::assertSame([$state], $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('cell', $paths[0]->registers['result']->kind);
        self::assertIsString($paths[0]->registers['result']->literal);
        $paths[0]->memory->write(new Location($paths[0]->registers['result']->literal), Term::constant(12));
        self::assertSame(12, $paths[0]->memory->read($address)->literal);
        self::assertSame([], $context->frontiers);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerRejectedIncrement')]
    public function testIncrementLeavesStorageAndResultUntouchedWhenRejected(Term $before, bool $readonly, string $error): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'a.php', 0, 1);
        $caller = new CallableGraph('target', [], [], $source);
        $state = new State();
        $address = new Location('object:one', ['x']);
        $state->memory->write($address, $before);
        $slot = new PropertySlot(new Term('object', 'one', attributes:['class' => 'B']), 'x', 'B', new PropertyDeclaration('x', 'B', readonly: $readonly));
        $instruction = new Instruction('i', 'increment', $source, 'result', ['property'], attributes:['post' => true]);
        $paths = (new PropertyReference(new Machine($context)))->increment($caller, $instruction, $state, $slot, $address);
        self::assertSame([$state], $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame($error, $paths[0]->completion->value?->literal);
        self::assertSame($before, $paths[0]->memory->read($address));
        self::assertSame([], $paths[0]->registers);
    }

    /**
     * @return iterable<string,array{Term,bool,string}>
     */
    public static function providerRejectedIncrement(): iterable
    {
        yield 'readonly integer' => [Term::constant(1), true, 'Error'];
        yield 'array is not incrementable' => [Term::array([]), false, 'TypeError'];
        yield 'object is not incrementable' => [new Term('object', 'other', attributes:['class' => 'B']), false, 'TypeError'];
    }
}

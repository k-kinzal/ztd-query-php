<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Transfer;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\PropertyReference;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Memory\Location;
use Deriver\Reference\SourceRef;
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
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\CatchTarget::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Allocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Constant\ClassNames::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\Handler::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\Model\StateStorage::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Address::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Path::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
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
#[UsesClass(SourceRef::class)]
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
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ConditionalLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\LexicalConstants::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Members::class)]
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

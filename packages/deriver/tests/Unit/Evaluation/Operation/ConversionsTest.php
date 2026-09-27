<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operation;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\CatchTarget;
use Deriver\ControlFlow\ClassConstant;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\Allocation;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Constants;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Creation;
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
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Components;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Demand\Table;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\CallableTransfer;
use Deriver\Evaluation\Transfer\ConstantTransfer;
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
use Deriver\Result\Frontier;
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
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\Control\ExceptionLowering;
use Deriver\Source\Compilation\EffectInspection;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\ConstantSignatures;
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
use Deriver\Value\Identity;
use Deriver\Value\Increment;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Operation\Conversions
 */
#[CoversClass(Conversions::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(CatchTarget::class)]
#[UsesClass(ClassConstant::class)]
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
#[UsesClass(CallableCheck::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Constants::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Creation::class)]
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
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(Havoc::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(ConstantTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertyReference::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PropertyTransfer::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
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
#[UsesClass(Frontier::class)]
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
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
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
#[UsesClass(Identity::class)]
#[UsesClass(Increment::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ConversionsTest extends TestCase
{
    public function testApplyLeavesNonconversionInstructionsToOrdinaryTransfer(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $operation = new Conversions(new Machine($context));
        self::assertNull($operation->apply($body, new Instruction('x', 'constant', $body->source), new State()));
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testStringInvokesSourceEffectsOnce(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{public int $n=0; function __toString():string{$this->n++;return "ok";}} function target(){$b=new B; $s=(string)$b; return [$s,$b->n];}');
        self::assertSame(['ok', 1], $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testConcatKeepsLeftStringBeforeTheRightMethodMutatesItsReceiver(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{public int $n=0; function __toString():string{$this->n++;return (string)$this->n;}} function target(){$b=new B; return $b.$b;}');
        self::assertSame('12', $result->normalOutcomes[0]->values['return']->native());
    }
    public function testObjectDistinguishesProvenScalarTypesFromPossibleObjects(): void
    {
        $operation = new Conversions(new Machine(\Tests\Fake\SolverFixture::context()));
        self::assertFalse($operation->object(Term::parameter('x', 'int|string')));
        self::assertTrue($operation->object(Term::parameter('x', 'mixed')));
    }
    public function testBoundaryIncludesBothCompletionKindsAndEffects(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->memory->write($state->local('x'), Term::constant(1));
        $paths = (new Conversions(new Machine($context)))->boundary($body, new Instruction('x', 'cast', $body->source, 'result'), $state, 'object-cast');
        self::assertSame('opaque', $paths[0]->snapshot()['x']->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testReturnedEnforcesTheImplicitStringContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{function __toString(){return [];}} function target(){try{return (string)new B;}catch(TypeError $e){return "caught";}}');
        self::assertSame('caught', $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSourceStringConversions')]
    public function testStringPreservesOracleCheckedSourceEffectsAndCompletion(string $source, Term $expected): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected->native(), $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @return iterable<string,array{string,Term}>
     */
    public static function providerSourceStringConversions(): iterable
    {
        return array_intersect_key(\Tests\Fake\Programs\ConversionPrograms::cases(), array_fill_keys([
            'automatic Stringable interface','source string method effects','concatenation invokes both operands','source string method throws after writing','implicit string return declaration','strict implicit string return','invalid implicit string return','object without string method','closure string cast','enum string cast',
        ], true));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerScalarBounds')]
    public function testObjectRecognizesEveryScalarTypeAndConservativeObjectBound(Term $value, bool $expected): void
    {
        $operation = new Conversions(new Machine(\Tests\Fake\SolverFixture::context()));
        self::assertSame($expected, $operation->object($value));
    }

    /**
     * @return iterable<string,array{Term,bool}>
     */
    public static function providerScalarBounds(): iterable
    {
        yield 'constant' => [Term::constant(1),false];
        yield 'array' => [Term::array([]),false];
        yield 'uninitialized' => [new Term('uninitialized'),false];
        yield 'integer' => [Term::parameter('x', 'int'),false];
        yield 'float' => [Term::parameter('x', 'float'),false];
        yield 'string' => [Term::parameter('x', 'string'),false];
        yield 'boolean' => [Term::parameter('x', 'bool'),false];
        yield 'true' => [Term::parameter('x', 'true'),false];
        yield 'false' => [Term::parameter('x', 'false'),false];
        yield 'null' => [Term::parameter('x', 'null'),false];
        yield 'array bound' => [Term::parameter('x', 'array'),false];
        yield 'scalar union' => [Term::parameter('x', 'int|float|string|bool|true|false|null|array'),false];
        yield 'unknown' => [new Term('external', 'x'),true];
        yield 'mixed union' => [Term::parameter('x', 'string|Box'),true];
        yield 'mixed' => [Term::parameter('x'),true];
        yield 'object' => [new Term('object', 'one', attributes:['class' => 'Box']),true];
        yield 'closure' => [new Term('closure', 'closure:a.php:1'),true];
        yield 'enum' => [new Term('enum', 'E::A'),true];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerOrdinaryOperations')]
    public function testApplyDefersScalarAndStrictComparisonOperations(string $operation, string $name, Term $left, Term $right): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->registers = ['left' => $left,'right' => $right];
        $instruction = new Instruction('i', $operation, $body->source, 'result', ['left','right'], $name);
        $result = (new Conversions(new Machine($context)))->apply($body, $instruction, $state);
        self::assertNull($result);
        self::assertSame('normal', $state->completion->kind);
        self::assertArrayNotHasKey('result', $state->registers);
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return iterable<string,array{string,string,Term,Term}>
     */
    public static function providerOrdinaryOperations(): iterable
    {
        yield 'scalar int cast' => ['cast','int',Term::constant('12'),Term::constant(null)];
        yield 'object bool cast' => ['cast','bool',new Term('object', 'one'),Term::constant(null)];
        yield 'scalar equal' => ['binary','==',Term::constant(1),Term::constant(2)];
        yield 'strict identity' => ['binary','===',new Term('object', 'one'),new Term('object', 'two')];
        yield 'strict nonidentity' => ['binary','!==',new Term('object', 'one'),new Term('object', 'two')];
        yield 'arithmetic' => ['binary','+',Term::constant(1),Term::constant(2)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerOpaqueConversions')]
    public function testApplyKeepsUnsupportedObjectProtocolsExplicit(string $operation, string $name, Term $left, Term $right, string $frontier): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->registers = ['left' => $left,'right' => $right];
        $instruction = new Instruction('i', $operation, $body->source, 'result', ['left','right'], $name);
        $paths = (new Conversions(new Machine($context)))->apply($body, $instruction, $state);
        self::assertNotNull($paths);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('opaque', $paths[0]->value('result')->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        $frontiers = array_values($context->frontiers);
        self::assertSame('UNSUPPORTED_LANGUAGE_FEATURE', $frontiers[0]->code);
        self::assertSame($frontier, $frontiers[0]->operation);
        self::assertSame($body->source, $frontiers[0]->at);
    }

    /**
     * @return iterable<string,array{string,string,Term,Term,string}>
     */
    public static function providerOpaqueConversions(): iterable
    {
        $object = new Term('object', 'one', attributes:['class' => 'Box']);
        $scalar = Term::constant(1);
        yield 'object integer' => ['cast','int',$object,$scalar,'object-cast'];
        yield 'object float' => ['cast','float',$object,$scalar,'object-cast'];
        yield 'object array' => ['cast','array',$object,$scalar,'object-cast'];
        yield 'symbolic string' => ['cast','string',Term::parameter('x'),$scalar,'dynamic-string-conversion'];
        yield 'object equality' => ['binary','==',$object,$scalar,'object-comparison'];
        yield 'object inequality' => ['binary','!=',$scalar,$object,'object-comparison'];
        yield 'object less' => ['binary','<',$object,$scalar,'object-comparison'];
        yield 'object less equal' => ['binary','<=',$object,$scalar,'object-comparison'];
        yield 'object greater' => ['binary','>',$object,$scalar,'object-comparison'];
        yield 'object greater equal' => ['binary','>=',$object,$scalar,'object-comparison'];
        yield 'object ordering' => ['binary','<=>',$object,$scalar,'object-comparison'];
    }

    public function testStringRecordsArrayWarningsAndPreservesConfidentiality(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('i', 'cast', $body->source, 'result');
        $paths = (new Conversions(new Machine($context)))->string($body, $instruction, new State(), Term::array([Term::constant('private', true)]));
        self::assertCount(1, $paths);
        self::assertSame('Array', $paths[0]->value('result')->literal);
        self::assertTrue($paths[0]->value('result')->isSecret());
        $frontiers = array_values($context->frontiers);
        self::assertSame('PHP_WARNING', $frontiers[0]->code);
        self::assertSame('array-to-string', $frontiers[0]->operation);
    }

    public function testReturnedPreservesThrownCompletionsWithoutCheckingTheirReturnRegister(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->completion = new Completion('throw', new Term('throwable', 'RuntimeException'));
        $paths = (new Conversions(new Machine($context)))->returned([$state], new Instruction('i', 'cast', $body->source, 'result'), 'target');
        self::assertSame([$state], $paths);
        self::assertSame('RuntimeException', $paths[0]->completion->value?->literal);
        self::assertArrayNotHasKey('result', $paths[0]->registers);
    }

    public function testReturnedPartitionsAnUnknownReturnIntoValidStringsAndTypeErrors(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->registers['result'] = Term::parameter('result');
        $paths = (new Conversions(new Machine($context)))->returned([$state], new Instruction('i', 'cast', $body->source, 'result'), 'target');
        self::assertCount(2, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('TypeError', $paths[0]->completion->value?->literal);
        self::assertSame('normal', $paths[1]->completion->kind);
        self::assertSame('string', $paths[1]->value('result')->attributes['type']);
    }
}

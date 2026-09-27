<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Transfer;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\CatchTarget;
use Deriver\ControlFlow\ClassConstant;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\Program;
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
use Deriver\Evaluation\Call\MethodInvocation;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Creation;
use Deriver\Evaluation\Call\Preparation\Methods;
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
use Deriver\Evaluation\Transfer\PropertyMagic;
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
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Transfer\ConstantTransfer
 */
#[CoversClass(ConstantTransfer::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
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
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(Constants::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Creation::class)]
#[UsesClass(Methods::class)]
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
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertyMagic::class)]
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
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ConstantTransferTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyEvaluatesCapturedConstantExpressions(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php const BASE=20;const ANSWER=BASE*2+2;function target(){return ANSWER;}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(42, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testMemberChecksAccessBeforeReturningAnInitializer(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class Box{private const A=1;}function target(){try{return Box::A;}catch(Error $e){return 2;}}');
        self::assertSame(2, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testInitializerBuildsAnEnumFromItsBackingExpression(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php enum Flag:int{case A=1+2;}function target(){return Flag::A->value;}');
        self::assertSame(3, $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProviderExternal(\Tests\Fake\Programs\ClassNamePrograms::class, 'cases')]
    public function testMemberPreservesOracleCheckedClassNameAndConstantSemantics(string $source, string $expected): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->projectDiagnostics);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expected, true, flags:JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testMemberKeepsUnavailableDynamicClassLookupsExplicit(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$name="class";return Missing::{$name};}');
        self::assertSame('open', $result->assessment->closure);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->kind);
        self::assertContains('INCOMPLETE_SOURCE', array_column($result->frontiers, 'code'));
    }

    public function testFinishRetainsTheExactClassValueAndExistingState(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $state = new State();
        $source = new SourceRef('snapshot', 'class.php', 4, 18);
        $instruction = new Instruction('fetch', 'class-constant', $source, 'result');
        $value = Term::constant('Box', true);
        $paths = (new ConstantTransfer(new Machine($context)))->finish($instruction, $state, $value);
        self::assertSame([$state], $paths);
        self::assertSame($value, $paths[0]->registers['result']);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame([], $context->frontiers);
    }

    public function testFinishPropagatesClassResolutionErrorsWithoutWritingAValue(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $state = new State();
        $instruction = new Instruction('fetch', 'class-constant', new SourceRef('snapshot', 'class.php', 4, 18), 'result');
        $error = new Term('throwable', 'TypeError');
        $paths = (new ConstantTransfer(new Machine($context)))->finish($instruction, $state, $error);
        self::assertSame([$state], $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame($error, $paths[0]->completion->value);
        self::assertArrayNotHasKey('result', $paths[0]->registers);
    }

    public function testFinishRecordsTheUnresolvedClassDependencyAtItsSource(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('snapshot', 'class.php', 4, 18);
        $instruction = new Instruction('fetch', 'class-constant', $source, 'result');
        $dependency = Term::parameter('class');
        $value = Term::opaque('INCOMPLETE_SOURCE', dependencies: [$dependency]);
        $paths = (new ConstantTransfer(new Machine($context)))->finish($instruction, new State(), $value);
        self::assertSame('opaque', $paths[0]->registers['result']->kind);
        self::assertSame('INCOMPLETE_SOURCE', $paths[0]->registers['result']->literal);
        self::assertCount(1, $context->frontiers);
        $frontier = array_values($context->frontiers)[0];
        self::assertSame('INCOMPLETE_SOURCE', $frontier->code);
        self::assertSame('dynamic-class-constant', $frontier->operation);
        self::assertSame($source, $frontier->at);
        self::assertNotNull($frontier->residual);
        self::assertSame([$dependency], $frontier->residual->operands);
    }

    /**
     * @param list<string> $completions Expected path completions
     * @param list<string> $kinds Result or exception categories
     * @param list<int|float|string|bool|null> $literals Result or exception values
     */
    #[DataProvider('providerInitializerResults')]
    public function testInitializerChecksDeclaredTypesAndConstructsEnumIdentity(Term $value, ?ClassConstant $constant, array $completions, array $kinds, array $literals): void
    {
        $source = new SourceRef('snapshot', 'constants.php', 4, 20);
        $body = new CallableGraph('Box::VALUE:initializer', [], [new BasicBlock(0, [new Instruction('value', 'constant', $source, 'value', constant:$value)], new Terminator('return', 'value'))], $source);
        $program = self::createStub(Program::class);
        $program->method('constant')->willReturn($body);
        $configuration = new Configuration();
        $context = new Context($program, new ReturnQuery('target'), $configuration, new Registry($configuration));
        $state = new State();
        $marker = $state->memory->allocate(Term::constant('retained'));
        $instruction = new Instruction('fetch', 'class-constant', $source, 'result');
        $paths = (new ConstantTransfer(new Machine($context)))->initializer('Box::VALUE', $instruction, $state, $constant);
        self::assertSame($completions, array_map(static fn (State $path): string => $path->completion->kind, $paths));
        self::assertSame($kinds, array_map(static fn (State $path): string => ($path->completion->value ?? $path->value('result'))->kind, $paths));
        self::assertSame($literals, array_map(static fn (State $path) => ($path->completion->value ?? $path->value('result'))->literal, $paths));
        self::assertSame('retained', $paths[0]->memory->read($marker)->literal);
        self::assertNotSame($state, $paths[0]);
        self::assertNotSame($state->memory, $paths[0]->memory);
        self::assertArrayNotHasKey('result', $state->registers);
    }

    /**
     * @return iterable<string,array{Term,ClassConstant|null,list<string>,list<string>,list<int|float|string|bool|null>}>
     */
    public static function providerInitializerResults(): iterable
    {
        yield 'untyped global' => [Term::constant('value'),null,['normal'],['constant'],['value']];
        yield 'integer' => [Term::constant(7),new ClassConstant('Box', 'VALUE', type:'int'),['normal'],['constant'],[7]];
        yield 'strict scalar mismatch' => [Term::constant('7'),new ClassConstant('Box', 'VALUE', type:'int'),['throw'],['throwable'],['TypeError']];
        yield 'integer float widening' => [Term::constant(7),new ClassConstant('Box', 'VALUE', type:'float'),['normal'],['constant'],[7.0]];
        yield 'symbolic type split' => [Term::parameter('value'),new ClassConstant('Box', 'VALUE', type:'int'),['throw','normal'],['throwable','type-refinement'],['TypeError','int']];
        yield 'backed enum' => [Term::constant('token'),new ClassConstant('Mode', 'Ready', type:'string', enum:true),['normal'],['enum'],['Mode::Ready']];
        yield 'invalid backing type' => [Term::constant('token'),new ClassConstant('Mode', 'Ready', type:'int', enum:true),['throw'],['throwable'],['TypeError']];
    }

    public function testInitializerRetainsEnumCaseNameBackingValueAndDeclaringClass(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php enum Mode:string{case Ready="token";} function target(){}');
        $source = new SourceRef('snapshot', 'constants.php', 4, 20);
        $paths = (new ConstantTransfer(new Machine($context)))->initializer('Mode::Ready', new Instruction('fetch', 'class-constant', $source, 'result'), new State(), new ClassConstant('Mode', 'Ready', type:'string', enum:true));
        self::assertCount(1, $paths);
        $result = $paths[0]->registers['result'];
        self::assertSame('enum', $result->kind);
        self::assertSame('Mode::Ready', $result->literal);
        self::assertSame(['class' => 'Mode'], $result->attributes);
        self::assertSame('Ready', $result->operands['name']->literal);
        self::assertSame('token', $result->operands['value']->literal);
    }

    public function testInitializerPropagatesFailureBeforeEnforcingTheConstantType(): void
    {
        $source = new SourceRef('snapshot', 'constants.php', 4, 20);
        $error = new Term('throwable', 'RuntimeException');
        $body = new CallableGraph('Box::VALUE:initializer', [], [new BasicBlock(0, [new Instruction('error', 'constant', $source, 'error', constant:$error)], new Terminator('throw', 'error'))], $source);
        $program = self::createStub(Program::class);
        $program->method('constant')->willReturn($body);
        $configuration = new Configuration();
        $context = new Context($program, new ReturnQuery('target'), $configuration, new Registry($configuration));
        $paths = (new ConstantTransfer(new Machine($context)))->initializer('Box::VALUE', new Instruction('fetch', 'class-constant', $source, 'result'), new State(), new ClassConstant('Box', 'VALUE', type:'int'));
        self::assertCount(1, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame($error, $paths[0]->completion->value);
        self::assertArrayNotHasKey('result', $paths[0]->registers);
    }

    #[DataProvider('providerFallbackConstants')]
    public function testInitializerUsesCapturedAndBuiltinFallbacksWithoutExecutingMissingBodies(string $symbol, ?ClassConstant $constant, Term $expected): void
    {
        $program = self::createStub(Program::class);
        $program->method('classes')->willReturn(['box' => new ClassDeclaration('Box', constants:['VALUE' => Term::constant('captured')])]);
        $configuration = new Configuration();
        $context = new Context($program, new ReturnQuery('target'), $configuration, new Registry($configuration));
        $source = new SourceRef('snapshot', 'constants.php', 4, 20);
        $state = new State();
        $paths = (new ConstantTransfer(new Machine($context)))->initializer($symbol, new Instruction('fetch', 'constant-name', $source, 'result'), $state, $constant);
        self::assertSame([$state], $paths);
        self::assertSame($expected->kind, $state->registers['result']->kind);
        self::assertSame($expected->literal, $state->registers['result']->literal);
    }

    /**
     * @return iterable<string,array{string,ClassConstant|null,Term}>
     */
    public static function providerFallbackConstants(): iterable
    {
        yield 'integer width' => ['PHP_INT_SIZE',null,Term::constant(8)];
        yield 'class captured value' => ['Box::VALUE',new ClassConstant('Box', 'VALUE'),Term::constant('captured')];
        yield 'class missing value' => ['Box::Missing',new ClassConstant('Box', 'Missing'),Term::opaque('INCOMPLETE_SOURCE')];
    }

    /**
     * @param array<string,scalar|null> $attributes Class resolution syntax
     */
    #[DataProvider('providerDynamicMembers')]
    public function testMemberPreservesResolutionErrorsAndSecretClassNames(Term $class, Term $name, array $attributes, string $completion, string $kind, int|string $literal): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Box { public const VALUE=7; private const HIDDEN=9; } function target(){}');
        $source = new SourceRef('snapshot', 'constants.php', 4, 20);
        $state = new State();
        $state->registers['class'] = $class;
        $state->registers['name'] = $name;
        $instruction = new Instruction('fetch', 'class-constant', $source, 'result', ['class','name'], attributes:$attributes);
        $paths = (new ConstantTransfer(new Machine($context)))->member(new CallableGraph('target', [], [], $source), $instruction, $state);
        self::assertCount(1, $paths);
        self::assertSame($completion, $paths[0]->completion->kind);
        $value = $paths[0]->completion->value ?? $paths[0]->value('result');
        self::assertSame($kind, $value->kind);
        self::assertSame($literal, $value->literal);
    }

    /**
     * @return iterable<string,array{Term,Term,array<string,scalar|null>,string,string,int|string}>
     */
    public static function providerDynamicMembers(): iterable
    {
        yield 'ordinary' => [Term::constant('Box'),Term::constant('VALUE'),[],'normal','constant',7];
        yield 'runtime class name' => [Term::constant('bOx'),Term::constant('class'),['literal-class' => false,'class-name' => false],'normal','constant','Box'];
        yield 'literal class spelling' => [Term::constant('bOx'),Term::constant('class'),['literal-class' => true,'class-name' => true],'normal','constant','bOx'];
        yield 'missing literal class spelling' => [Term::constant('Missing'),Term::constant('class'),['class-name' => true],'normal','constant','Missing'];
        yield 'nonstring class' => [Term::constant(3),Term::constant('class'),['literal-class' => false,'class-name' => true],'throw','throwable','TypeError'];
        yield 'symbolic class' => [Term::parameter('class'),Term::constant('VALUE'),[],'normal','opaque','UNSUPPORTED_LANGUAGE_FEATURE'];
        yield 'symbolic name' => [Term::constant('Box'),Term::parameter('name'),[],'normal','opaque','UNSUPPORTED_LANGUAGE_FEATURE'];
        yield 'missing class' => [Term::constant('Missing'),Term::constant('VALUE'),[],'normal','opaque','INCOMPLETE_SOURCE'];
        yield 'missing member' => [Term::constant('Box'),Term::constant('MISSING'),[],'throw','throwable','Error'];
        yield 'inaccessible member' => [Term::constant('Box'),Term::constant('HIDDEN'),[],'throw','throwable','Error'];
    }

    public function testMemberPreservesConfidentialityOfTheClassNameAndMemberSelector(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Box {} function target(){}');
        $source = new SourceRef('snapshot', 'constants.php', 4, 20);
        $state = new State();
        $state->registers['class'] = Term::constant('Box');
        $state->registers['name'] = Term::constant('class', true);
        $paths = (new ConstantTransfer(new Machine($context)))->member(new CallableGraph('target', [], [], $source), new Instruction('fetch', 'class-constant', $source, 'result', ['class','name']), $state);
        self::assertTrue($paths[0]->registers['result']->isSecret());
        self::assertSame('Box', $paths[0]->registers['result']->literal);
    }
}

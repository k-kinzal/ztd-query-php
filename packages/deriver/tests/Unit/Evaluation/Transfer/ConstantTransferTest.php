<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Transfer;

use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\ClassConstant;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Program;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\ConstantTransfer;
use Deriver\Model\Registration\Registry;
use Deriver\Project\Configuration;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\SourceRef;
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
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\CatchTarget::class)]
#[UsesClass(ClassConstant::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
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
#[UsesClass(\Deriver\Evaluation\Call\Member\Constants::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Constant\ClassNames::class)]
#[UsesClass(Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\Handler::class)]
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
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\Model\StateStorage::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyMagic::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
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
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
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
#[UsesClass(\Deriver\Value\Operations::class)]
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

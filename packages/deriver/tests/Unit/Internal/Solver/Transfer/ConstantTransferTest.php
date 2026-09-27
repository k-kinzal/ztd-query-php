<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Transfer;

use Deriver\Api\Project\Configuration;
use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\IR\BasicBlock;
use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\ClassConstant;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\IR\Program;
use Deriver\Internal\IR\Terminator;
use Deriver\Internal\Model\Registry;
use Deriver\Internal\Solver\Context;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\State;
use Deriver\Internal\Solver\Transfer\ConstantTransfer;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Transfer\ConstantTransfer
 */
#[CoversClass(ConstantTransfer::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\LexicalConstants::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Members::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(ClassConstant::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Allocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Constants::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Constant\ClassNames::class)]
#[UsesClass(Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Handler::class)]
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
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\CallableTransfer::class)]
#[UsesClass(ConstantTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyMagic::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
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
        $body = new CallableIR('Box::VALUE:initializer', [], [new BasicBlock(0, [new Instruction('value', 'constant', $source, 'value', constant:$value)], new Terminator('return', 'value'))], $source);
        $program = self::createStub(Program::class);
        $program->method('constant')->willReturn($body);
        $configuration = new Configuration();
        $context = new Context($program, new \Deriver\Api\Query\ReturnQuery('target'), $configuration, new Registry($configuration));
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
        $body = new CallableIR('Box::VALUE:initializer', [], [new BasicBlock(0, [new Instruction('error', 'constant', $source, 'error', constant:$error)], new Terminator('throw', 'error'))], $source);
        $program = self::createStub(Program::class);
        $program->method('constant')->willReturn($body);
        $configuration = new Configuration();
        $context = new Context($program, new \Deriver\Api\Query\ReturnQuery('target'), $configuration, new Registry($configuration));
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
        $program->method('classes')->willReturn(['box' => new \Deriver\Internal\IR\ClassDeclaration('Box', constants:['VALUE' => Term::constant('captured')])]);
        $configuration = new Configuration();
        $context = new Context($program, new \Deriver\Api\Query\ReturnQuery('target'), $configuration, new Registry($configuration));
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
        $paths = (new ConstantTransfer(new Machine($context)))->member(new CallableIR('target', [], [], $source), $instruction, $state);
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
        $paths = (new ConstantTransfer(new Machine($context)))->member(new CallableIR('target', [], [], $source), new Instruction('fetch', 'class-constant', $source, 'result', ['class','name']), $state);
        self::assertTrue($paths[0]->registers['result']->isSecret());
        self::assertSame('Box', $paths[0]->registers['result']->literal);
    }
}

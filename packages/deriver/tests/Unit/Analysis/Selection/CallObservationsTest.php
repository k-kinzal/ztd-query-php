<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Selection;

use Deriver\Analysis\Selection\CallObservations;
use Deriver\Query\TupleQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CallObservations::class)]
#[UsesClass(\Deriver\Analysis\Execution\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\Selection\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\Execution\ResultAssessment::class)]
#[UsesClass(\Deriver\Session\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Builtins::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Query\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Location::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Materialization::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Memory::class)]
#[UsesClass(\Deriver\Evaluation\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Evaluation\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderExpansion::class)]
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
#[UsesClass(TupleQuery::class)]
#[UsesClass(\Deriver\Reference\ExpressionRef::class)]
#[UsesClass(\Deriver\Reference\Observation::class)]
#[UsesClass(\Deriver\Reference\PointRef::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Exceptional::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Result\Serialization\QueryEncoding::class)]
#[UsesClass(\Deriver\Result\Serialization\ValueGraph::class)]
#[UsesClass(\Deriver\Result\Statistics::class)]
#[UsesClass(\Deriver\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringCache::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweredCallable::class)]
#[UsesClass(\Deriver\Source\Lowering\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Parsing\ParseCache::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Source\Parsing\ParsedFile::class)]
#[UsesClass(\Deriver\Source\Lowering\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\CallLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\CallableLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Lowering\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\InstructionEmitter::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringScope::class)]
#[UsesClass(\Deriver\Source\Lowering\StatementLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\CallSiteIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\Lowering\LineMap::class)]
#[UsesClass(\Deriver\Source\Parsing\MagicContext::class)]
#[UsesClass(\Deriver\Source\Parsing\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Parsing\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Parsing\ClassScope::class)]
#[UsesClass(\Deriver\Source\Parsing\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Increment::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class CallObservationsTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testFindPreservesTheSemanticContract(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php $x=1; sink($x++, $x);');
        $observation = $session->callsTo('sink')[0];
        $result = $session->derive(new TupleQuery($observation->beforeInvocation(), ['before' => $observation->argument(0), 'after' => $observation->argument(1)]));
        self::assertSame(1, $result->normalOutcomes[0]->values['before']->native());
        self::assertSame(2, $result->normalOutcomes[0]->values['after']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testWithinFindsCallsAfterBothOperandOrders(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function f($x){return $x;} function target(){return f(1)+f(2);}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $calls = (new CallObservations($context->program))->within($body, 'f');
        self::assertCount(2, $calls);
    }

    public function testObservationRetainsTheObjectOperand(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new \Deriver\Reference\SourceRef('s', 'test.php', 0, 1);
        $instruction = new \Deriver\ControlFlow\Instruction('i', 'invoke-method', $source, 'returned', ['object','name']);
        $call = (new CallObservations($context->program))->observation('target', $instruction, 'query', []);
        self::assertSame('object', $call->receiver?->register);
    }

    public function testWithinSelectsCreationsByConstructorAndResolvesLexicalClasses(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class A {function __construct($x){}} class B extends A {function target($c){new self(1);new parent(2);new static(3);new A(4);new $c(5);}}');
        $body = $context->program->callable('B::target');
        self::assertNotNull($body);
        $observations = new CallObservations($context->program);
        self::assertSame(['B', 'A', 'static', 'A'], array_map(static fn (\Deriver\Reference\Observation $call): string => $call->target, $observations->within($body, '*')));
        self::assertSame(['A', 'A'], array_map(static fn (\Deriver\Reference\Observation $call): string => $call->target, $observations->within($body, 'a::__construct')));
        self::assertSame([], $observations->within($body, 'A'));
    }

    public function testNameAppliesTheRecordedFallbackOnlyWhenItIsDeclaredOrStandard(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function f(){}');
        $observations = new CallObservations($context->program);
        $source = new \Deriver\Reference\SourceRef('s', 'test.php', 0, 1);
        $call = static fn (string $fallback): \Deriver\ControlFlow\Instruction => new \Deriver\ControlFlow\Instruction('i', 'invoke', $source, 'r', ['name'], attributes: ['fallback' => $fallback]);
        self::assertSame('f', $observations->name('App\\f', $call('f')));
        self::assertSame('strlen', $observations->name('App\\strlen', $call('strlen')));
        self::assertSame('App\\g', $observations->name('App\\g', $call('g')));
        self::assertSame('App\\g', $observations->name('App\\g', $call('')));
    }

    public function testDeclaredRecognisesOnlyCapturedCallablesAndRegisteredModels(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function f(){}');
        self::assertTrue((new CallObservations($context->program))->declared('f'));
        self::assertFalse((new CallObservations($context->program))->declared('g'));
        self::assertFalse((new CallObservations($context->program, $context->models))->declared('strlen'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerCreatedClasses')]
    public function testClassNameResolvesOnlyLiteralSelfAndParent(string $name, bool $literal, string $expected): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class A {} class B extends A {} class C {}');
        $instruction = new \Deriver\ControlFlow\Instruction('i', 'new', new \Deriver\Reference\SourceRef('s', 'test.php', 0, 1), 'created', ['class'], attributes: ['scope' => 'B', 'literal-class' => $literal]);
        self::assertSame($expected, (new CallObservations($context->program))->className($name, $instruction));
    }

    /**
     * @return iterable<string,array{string,bool,string}>
     */
    public static function providerCreatedClasses(): iterable
    {
        yield 'self' => ['self', true, 'B'];
        yield 'parent' => ['PARENT', true, 'A'];
        yield 'static' => ['static', true, 'static'];
        yield 'qualified' => ['\\N\\C', true, 'N\\C'];
        yield 'dynamic string' => ['self', false, 'self'];
    }
}

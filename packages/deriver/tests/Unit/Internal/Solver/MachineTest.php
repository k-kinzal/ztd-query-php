<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Machine
 */
#[CoversClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\Exceptional::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Constraint\Constraints::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
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
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ResidualPaths::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Cell::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Components::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\Havoc::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class MachineTest extends TestCase
{
    public function testRunReturnsTheObservedValue(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $machine = new \Deriver\Internal\Solver\Machine($context);
        $body = $context->program->callable('target');
        self::assertNotNull($body);

        $states = $machine->run($body, new \Deriver\Internal\Solver\State());
        self::assertCount(1, $states);
        self::assertSame('return', $states[0]->completion->kind);
        self::assertSame(1, $states[0]->completion->value?->native());
    }
    public function testReturnedEnforcesTheDeclaredReturnType(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php declare(strict_types=1);function target():int{}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new \Deriver\Internal\Solver\State();
        $state->completion = new \Deriver\Internal\Solver\Completion('return', \Deriver\Value\Term::constant('invalid'));
        $paths = (new \Deriver\Internal\Solver\Machine($context))->returned($body, $state);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('TypeError', $paths[0]->completion->value?->literal);
    }
    public function testBlockTransfersOnlyDemandedDefinitions(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $machine = new \Deriver\Internal\Solver\Machine($context);
        $body = $context->program->callable('target');
        self::assertNotNull($body);

        $context->demands[$body] = (new \Deriver\Internal\Solver\Demand\Discovery($context))->instructions($body);
        $paths = $machine->block($body, new \Deriver\Internal\Solver\State());
        self::assertSame(1, $paths[0]->completion->value?->native());
    }
    public function testStepRecordsAConstantDefinition(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $machine = new \Deriver\Internal\Solver\Machine($context);
        $body = $context->program->callable('target');
        self::assertNotNull($body);

        $instruction = $body->blocks[0]->instructions[0];
        $paths = $machine->step($body, $instruction, new \Deriver\Internal\Solver\State());
        self::assertSame(1, $paths[0]->value($instruction->result)->native());
        self::assertArrayHasKey($instruction->id, $context->evidence);
    }
    public function testTerminateUsesTheAlreadyEvaluatedReturnRegister(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $machine = new \Deriver\Internal\Solver\Machine($context);
        $body = $context->program->callable('target');
        self::assertNotNull($body);

        $state = new \Deriver\Internal\Solver\State();
        $state->registers['result'] = \Deriver\Value\Term::constant(3);
        $paths = $machine->terminate($body, new \Deriver\Internal\IR\Terminator('return', 'result'), $state);
        self::assertSame(3, $paths[0]->completion->value?->native());
    }
    public function testBranchEliminatesAContradictedConstantPath(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $machine = new \Deriver\Internal\Solver\Machine($context);
        $body = $context->program->callable('target');
        self::assertNotNull($body);

        $state = new \Deriver\Internal\Solver\State();
        $state->registers['condition'] = \Deriver\Value\Term::constant(false);
        $paths = $machine->branch(new \Deriver\Internal\IR\Terminator('branch', 'condition', [1,2]), $state);
        self::assertCount(1, $paths);
        self::assertSame(2, $paths[0]->block);
    }
    public function testExecuteTransfersOnceWithoutPublishingSpeculativeObservations(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $context->demands[$body] = (new \Deriver\Internal\Solver\Demand\Discovery($context))->instructions($body);
        $paths = (new \Deriver\Internal\Solver\Machine($context))->execute($body, new \Deriver\Internal\Solver\State());
        self::assertSame(1, $paths[0]->completion->value?->literal);
        self::assertSame([], $context->normal);
    }
    public function testRunStopsLongDistinctCallChainsBeforeTheHostStackFails(): void
    {
        $source = '<?php function target(){return a();}function a(){return b();}function b(){return c();}function c(){return d();}function d(){return e();}function e(){return f();}function f(){return 1;}';
        $context = \Tests\Fake\SolverFixture::context($source, configuration:new \Deriver\Api\Project\Configuration(resources:new \Deriver\Api\Execution\ResourceLimits(stackFrames:64)));
        $body = \Tests\Fake\SummaryFixture::body($context);
        $paths = (new \Deriver\Internal\Solver\Machine($context))->run($body, new \Deriver\Internal\Solver\State());
        self::assertSame('STACK_LIMIT', $context->stopReason);
        self::assertNotEmpty($paths);
        self::assertContains('opaque', array_map(static fn (\Deriver\Internal\Solver\State $path): ?string => $path->completion->value?->kind, $paths));
        self::assertContains('throwable', array_map(static fn (\Deriver\Internal\Solver\State $path): ?string => $path->completion->value?->kind, $paths));
    }
}

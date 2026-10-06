<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Compilation\Control;

use Deriver\Source\Compilation\Control\GotoLowering;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SourceFixture;

#[CoversClass(GotoLowering::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
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
#[UsesClass(\Deriver\Evaluation\Transfer\CompoundAssignment::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
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
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\LoopLowering::class)]
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
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(\Deriver\Source\Compilation\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\LoopLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ConditionalLowering::class)]
#[Small]
final class GotoLoweringTest extends TestCase
{
    public function testResolveJumpsBackwardToALoopHeaderLabel(): void
    {
        $graph = SourceFixture::index('<?php function target(){$i=0;again:$i++;if($i<2)goto again;return $i;}')->callable('target');
        self::assertNotNull($graph);
        $jumps = array_values(array_filter($graph->blocks, static fn ($block) => $block->terminator->kind === 'complete-jump'));
        self::assertCount(1, $jumps);
        $label = $jumps[0]->terminator->targets[0];
        self::assertTrue($graph->blocks[$label]->loopHeader);
        self::assertSame(0, $jumps[0]->terminator->handlerDepth);
    }

    public function testResolveLeavesForwardLabelsOutOfLoopWidening(): void
    {
        $graph = SourceFixture::index('<?php function target(){goto done;$x=1;done:return 2;}')->callable('target');
        self::assertNotNull($graph);
        self::assertSame([], array_filter($graph->blocks, static fn ($block) => $block->loopHeader));
    }

    public function testResolveReleasesForeachIteratorsAfterNestedFinallyBlocks(): void
    {
        $graph = SourceFixture::index('<?php function target(){foreach([1] as $v){try{goto out;}finally{$v=2;}}out:return 1;}')->callable('target');
        self::assertNotNull($graph);
        $jumps = array_values(array_filter($graph->blocks, static fn ($block) => $block->terminator->kind === 'complete-jump'));
        self::assertCount(2, $jumps);
        self::assertSame(0, $jumps[0]->terminator->handlerDepth);
        $release = $graph->blocks[$jumps[0]->terminator->targets[0]];
        self::assertSame(['iterator-release'], array_column($release->instructions, 'operation'));
        self::assertSame('complete-jump', $release->terminator->kind);
    }

    public function testResolveSealsJumpsIntoLoopsAsExplicitBoundaries(): void
    {
        $graph = SourceFixture::index('<?php function target(){goto inside;while(true){inside:return 1;}}')->callable('target');
        self::assertNotNull($graph);
        $boundary = $graph->blocks[0];
        self::assertSame('residual', $boundary->terminator->kind);
        self::assertSame('unsupported', $boundary->instructions[count($boundary->instructions) - 1]->operation);
        self::assertSame('Stmt_Goto', $boundary->instructions[count($boundary->instructions) - 1]->name);
    }

    public function testJumpEndsTheBlockUntilResolution(): void
    {
        $lowering = SourceFixture::lowering();
        (new GotoLowering($lowering))->jump(new \PhpParser\Node\Stmt\Goto_('later'));
        self::assertSame('residual', $lowering->graph->terminators[0]->kind);
        self::assertSame(0, $lowering->graph->gotos[0]['block']);
        self::assertSame([], $lowering->graph->instructions[0]);
    }

    public function testUnwindSplitsTheJumpOnlyWhenAFinallyMustRunBeforeARelease(): void
    {
        $lowering = SourceFixture::lowering();
        $node = new \PhpParser\Node\Stmt\Goto_('out');
        $loop = ['key' => 'loop:1', 'kind' => 'loop', 'iterator' => 'r1', 'depth' => 0];
        $try = ['key' => 'region:0:try', 'kind' => 'try', 'iterator' => '', 'depth' => 0];
        (new GotoLowering($lowering))->unwind($node, [$loop]);
        self::assertSame(['iterator-release'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame([], $lowering->graph->terminators);
        $split = SourceFixture::lowering();
        (new GotoLowering($split))->unwind($node, [$try, $loop]);
        self::assertSame(['complete-jump', [1]], [$split->graph->terminators[0]->kind ?? '', $split->graph->terminators[0]->targets ?? []]);
        self::assertSame(['iterator-release'], array_column($split->graph->instructions[1], 'operation'));
    }

    public function testExitedListsInnermostScopesFirstAndRejectsUnmodeledJumps(): void
    {
        $goto = new GotoLowering(SourceFixture::lowering());
        $loop = ['key' => 'loop:1', 'kind' => 'loop', 'iterator' => 'r1', 'depth' => 0];
        $try = ['key' => 'region:0:try', 'kind' => 'try', 'iterator' => '', 'depth' => 0];
        $finally = ['key' => 'region:0:finally', 'kind' => 'finally', 'iterator' => '', 'depth' => 0];
        self::assertSame([$try, $loop], $goto->exited([$loop, $try], []));
        self::assertSame([], $goto->exited([$loop], [$loop]));
        self::assertNull($goto->exited([], [$loop]));
        self::assertNull($goto->exited([$finally], []));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testLabelAloneDoesNotDisturbState(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$x="a";here:return $x;}');
        self::assertSame(['a'], array_map(static fn ($outcome) => $outcome->values['return']->native(), $result->normalOutcomes));
        self::assertSame([], $result->frontiers);
    }
}

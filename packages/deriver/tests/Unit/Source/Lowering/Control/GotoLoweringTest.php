<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Lowering\Control;

use Deriver\Source\Lowering\Control\GotoLowering;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SourceFixture;

#[CoversClass(GotoLowering::class)]
#[UsesClass(\Deriver\Analysis\Execution\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\Selection\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\Execution\ResultAssessment::class)]
#[UsesClass(\Deriver\Session\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Evaluation\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Query\Resources::class)]
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
#[UsesClass(\Deriver\Evaluation\Memory\Location::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Materialization::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Memory::class)]
#[UsesClass(\Deriver\Evaluation\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Evaluation\Memory\StorageCapture::class)]
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
#[UsesClass(\Deriver\Project\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Execution\Alternative::class)]
#[UsesClass(\Deriver\Result\Execution\Assessment::class)]
#[UsesClass(\Deriver\Result\Execution\Derivation::class)]
#[UsesClass(\Deriver\Result\Execution\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Result\Serialization\QueryEncoding::class)]
#[UsesClass(\Deriver\Result\Serialization\ValueGraph::class)]
#[UsesClass(\Deriver\Result\Statistics::class)]
#[UsesClass(\Deriver\Result\Execution\StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringCache::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweredCallable::class)]
#[UsesClass(\Deriver\Source\Lowering\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Parsing\ParseCache::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Source\Parsing\ParsedFile::class)]
#[UsesClass(\Deriver\Source\Lowering\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\CallableLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\Control\LoopLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\InstructionEmitter::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringScope::class)]
#[UsesClass(\Deriver\Source\Lowering\StatementLowering::class)]
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
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[UsesClass(\Deriver\Source\Lowering\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(\Deriver\Source\Lowering\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Lowering\Control\LoopLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\Control\ConditionalLowering::class)]
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

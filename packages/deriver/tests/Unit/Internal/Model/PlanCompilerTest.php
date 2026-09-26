<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Model;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Model\PlanCompiler::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
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
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\ModelPrecedence::class)]
#[UsesClass(\Deriver\Internal\Model\PlanActions::class)]
#[UsesClass(\Deriver\Internal\Model\PlanFootprints::class)]
#[UsesClass(\Deriver\Internal\Model\PlanValidation::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\SignatureIdentity::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class PlanCompilerTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCompilePreservesTheSemanticContract(): void
    {
        $model = new \Tests\Fake\PlanModel(
            new \Deriver\Model\ModelDescriptor('example.key', '1', 'key', new \Deriver\Model\Signature\Signature([new \Deriver\Model\Signature\Parameter('id', 'int')])),
            new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::binary('.', \Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant('user:')), \Deriver\Model\Plan\Expression::parameter('id')))]),
        );
        $session = \Tests\Fake\Analysis::session('<?php function target(){return key(3);}', new \Deriver\Api\Project\Configuration(models: [$model]));
        $result = $session->derive(new \Deriver\Api\Query\ReturnQuery('target'));
        self::assertSame('user:3', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testExpressionLowersParameterReadsThroughStorage(): void
    {
        $compiler = new \Deriver\Internal\Model\PlanCompiler(new \Deriver\Api\Reference\SourceRef('s', 'model:example', 0, 1));
        $compiler->expression(\Deriver\Model\Plan\Expression::parameter('input'));
        self::assertSame(['local','read'], array_column($compiler->instructions[0], 'operation'));
        self::assertSame('input', $compiler->instructions[0][0]->name);
    }
    public function testActionsStopsAfterACompletion(): void
    {
        $compiler = new \Deriver\Internal\Model\PlanCompiler(new \Deriver\Api\Reference\SourceRef('s', 'model:example', 0, 1));
        $compiler->actions([\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant(1))),new \Deriver\Model\Plan\Action('unknown')]);
        self::assertSame('return', $compiler->terminators[0]->kind);
        self::assertCount(1, $compiler->instructions[0]);
    }
    public function testChoiceKeepsBothCorrelatedCompletions(): void
    {
        $compiler = new \Deriver\Internal\Model\PlanCompiler(new \Deriver\Api\Reference\SourceRef('s', 'model:example', 0, 1));
        $action = \Deriver\Model\Plan\Action::choice(\Deriver\Model\Plan\Expression::parameter('flag'), [\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant('yes')))], [\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant('no')))]);
        $compiler->choice($action, 'predicate');
        self::assertSame([1,2], $compiler->terminators[0]->targets);
        self::assertSame('yes', $compiler->instructions[1][0]->constant?->native());
        self::assertSame('no', $compiler->instructions[2][0]->constant?->native());
    }
    public function testEmitAllocatesUniqueRegistersWithModelProvenance(): void
    {
        $compiler = new \Deriver\Internal\Model\PlanCompiler(new \Deriver\Api\Reference\SourceRef('s', 'model:example', 0, 1));
        $a = $compiler->emit('constant', constant:\Deriver\Value\Term::constant(1));
        $b = $compiler->emit('copy', [$a]);
        self::assertNotSame($a, $b);
        self::assertSame('model:example', $compiler->instructions[0][0]->source->path);
        self::assertSame([$a], $compiler->instructions[0][1]->operands);
    }
    public function testCompilePreservesTheLexicalClassForRelativeReturnTypes(): void
    {
        $compiler = new \Deriver\Internal\Model\PlanCompiler(new \Deriver\Api\Reference\SourceRef('test', 'model:sample', 0, 1));
        $body = $compiler->compile(new \Deriver\Model\ModelDescriptor('sample', '1', 'Box::same', new \Deriver\Model\Signature\Signature(returnType: 'self')), new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::receiver())]));
        self::assertSame('Box', $body->className);
        self::assertSame('self', $body->returnType);
    }
}

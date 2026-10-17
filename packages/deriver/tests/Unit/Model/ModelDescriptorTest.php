<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Project\Configuration;
use Deriver\Project\Declaration\Signature;
use Deriver\Query\ReturnQuery;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModelDescriptor::class)]
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
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
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
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Location::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Materialization::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Memory::class)]
#[UsesClass(\Deriver\Evaluation\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanActions::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanCompiler::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanFootprints::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanValidation::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ModelPrecedence::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderExpansion::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\SignatureIdentity::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Declaration\Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Project\ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
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
#[UsesClass(\Deriver\Source\Lowering\CallLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\CallableLowering::class)]
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
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ModelDescriptorTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testPreservesItsSemanticContract(): void
    {
        $model = new \Tests\Fake\PlanModel(
            new ModelDescriptor('example.key', '1', 'key', new Signature([new \Deriver\Project\Declaration\Parameter('id', 'int')])),
            new SemanticPlan([Action::returns(Expression::binary('.', Expression::literal(Term::constant('user:')), Expression::parameter('id')))]),
        );
        $session = \Tests\Fake\Analysis::session('<?php function target(){return key(3);}', new Configuration(), contributions: new \Deriver\Model\Registration\Contributions(models: [$model]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame('user:3', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
}

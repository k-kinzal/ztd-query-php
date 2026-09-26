<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Model;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Model\ModelBoundary::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelPrecedence::class)]
#[UsesClass(\Deriver\Internal\Model\PlanActions::class)]
#[UsesClass(\Deriver\Internal\Model\PlanCompiler::class)]
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
#[UsesClass(\Deriver\Model\CallModel::class)]
#[UsesClass(\Deriver\Model\Contract\DomainLaws::class)]
#[UsesClass(\Deriver\Model\Domain\AbstractDomain::class)]
#[UsesClass(\Deriver\Model\Domain\DomainFact::class)]
#[UsesClass(\Deriver\Model\Intrinsic\IntrinsicDescriptor::class)]
#[UsesClass(\Deriver\Model\Intrinsic\PureIntrinsic::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Provider\DeclarationProvider::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchProvider::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchRequest::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchTarget::class)]
#[UsesClass(\Deriver\Model\Provider\DomainProvider::class)]
#[UsesClass(\Deriver\Model\Provider\EntryPointProvider::class)]
#[UsesClass(\Deriver\Model\Provider\EnvironmentProvider::class)]
#[UsesClass(\Deriver\Model\Provider\ObservationProvider::class)]
#[UsesClass(\Deriver\Model\Provider\Provider::class)]
#[UsesClass(\Deriver\Model\Provider\RefinementModel::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(\Deriver\Value\Projection::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ModelBoundaryTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDescribePreservesTheSemanticContract(): void
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
    public function testDescriptorCapturesTheDeclaredIdentity(): void
    {
        $model = new \Tests\Fake\PlanModel(new \Deriver\Model\ModelDescriptor('example', '1', 'f'), new \Deriver\Model\Plan\SemanticPlan([]));
        self::assertSame('example', (new \Deriver\Internal\Model\ModelBoundary())->descriptor($model)->id);
    }
    public function testIntrinsicDescriptorRetainsExplicitDependencyIndices(): void
    {
        $descriptor = (new \Deriver\Internal\Model\ModelBoundary())->intrinsicDescriptor(new \Tests\Fake\PolicyOperation('compose'));
        self::assertSame([0,1], $descriptor->dependencies);
        self::assertSame(2, $descriptor->arity);
    }
    public function testDomainRegistrationChecksTheLatticeBeforeAcceptingItsIdentity(): void
    {
        self::assertSame(['example.policy','1'], (new \Deriver\Internal\Model\ModelBoundary())->domainRegistration(new \Tests\Fake\PolicyDomain()));
    }
    public function testEvaluateRejectsAnArityMismatchAsAContractFailure(): void
    {
        $result = (new \Deriver\Internal\Model\ModelBoundary())->evaluate(new \Tests\Fake\PolicyOperation('compose'), [], new \Deriver\Api\Project\TargetProfile());
        self::assertSame('MODEL_CONTRACT_VIOLATION', $result->literal);
        self::assertSame('opaque', $result->kind);
    }
    public function testProviderRegistrationCapturesTheProviderVersion(): void
    {
        self::assertSame(['example.container','1'], (new \Deriver\Internal\Model\ModelBoundary())->providerRegistration(new \Tests\Fake\MiniContainer()));
    }
    public function testDeclarationsReturnsCapturedSourceWithoutExecutingIt(): void
    {
        $input = (new \Deriver\Internal\Model\ModelBoundary())->declarations(new \Tests\Fake\MiniContainer());
        self::assertSame('generated/service.php', $input->files[0]->path);
        self::assertStringContainsString('class GeneratedService', $input->files[0]->contents);
    }
    public function testEnvironmentRetainsExplicitCapturedValues(): void
    {
        $values = (new \Deriver\Internal\Model\ModelBoundary())->environment(new \Tests\Fake\MiniContainer());
        self::assertSame('captured', $values['env:APP_NAME']->native());
    }
    public function testEntriesPreservesTheProviderLifecycleRoots(): void
    {
        self::assertSame('entry', (new \Deriver\Internal\Model\ModelBoundary())->entries(new \Tests\Fake\MiniContainer())[0]->symbol);
    }
    public function testDomainsReturnsTheDeclaredLattice(): void
    {
        $domains = (new \Deriver\Internal\Model\ModelBoundary())->domains(new \Tests\Fake\PolicyProvider());
        self::assertCount(1, $domains);
        self::assertSame('example.policy', $domains[0]->id());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testQueriesUsesTheCapturedSessionEntries(): void
    {
        $provider = new \Tests\Fake\MiniContainer();
        $session = \Tests\Fake\Analysis::session('<?php function entry(){return 1;}', new \Deriver\Api\Project\Configuration(providers:[$provider]));
        $queries = (new \Deriver\Internal\Model\ModelBoundary())->queries($provider, $session);
        self::assertSame('entrypoint', $queries['entry-return']->scope()->mode);
        self::assertSame('entry', $queries['entry-return']->scope()->entries[0]->symbol);
    }
    public function testDispatchKeepsCompletenessAndTheBoundReceiver(): void
    {
        $request = new \Deriver\Model\Provider\DispatchRequest(\Deriver\Value\Term::parameter('service', 'Contract'), 'label', false, new \Deriver\Api\Reference\SourceRef('s', 'f.php', 0, 1), new \Deriver\Api\Project\TargetProfile());
        $decision = (new \Deriver\Internal\Model\ModelBoundary())->dispatch(new \Tests\Fake\MiniContainer(), $request);
        self::assertNotNull($decision);
        self::assertTrue($decision->exhaustive);
        self::assertSame('GeneratedService::label', $decision->targets[0]->symbol);
    }
    public function testRefinementPreservesTheSelectedBranchPolarity(): void
    {
        $predicate = \Deriver\Value\Term::parameter('flag', 'bool');
        $value = (new \Deriver\Internal\Model\ModelBoundary())->refinement(new \Tests\Fake\PolicyProvider(), $predicate, false);
        self::assertNotNull($value);
        self::assertSame('!', $value->literal);
        self::assertSame($predicate, $value->operands[0]);
    }
    public function testDomainJoinContainsBothInputFacts(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $value = (new \Deriver\Internal\Model\ModelBoundary())->domain($domain, 'join', $domain->bottom()->term(), $domain->top()->term());
        self::assertSame('top', $value->operands['representation']->kind);
    }
    public function testContainsRequiresTheDeclaredInclusionDirection(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $boundary = new \Deriver\Internal\Model\ModelBoundary();
        self::assertTrue($boundary->contains($domain, $domain->top()->term(), $domain->bottom()->term()));
        self::assertFalse($boundary->contains($domain, $domain->bottom()->term(), $domain->top()->term()));
    }
}

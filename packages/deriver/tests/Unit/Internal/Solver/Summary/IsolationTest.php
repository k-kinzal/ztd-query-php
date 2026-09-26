<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Summary;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Summary\Isolation
 */
#[CoversClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
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
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class IsolationTest extends TestCase
{
    public function testCallableChecksTheWholeMutuallyRecursiveComponent(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target(){return hop();}function hop(){return target();}');
        $proof = new \Deriver\Internal\Solver\Summary\Isolation($context);
        self::assertTrue($proof->callable(\Tests\Fake\SummaryFixture::body($context)));
    }
    public function testCallableRejectsTransitiveGlobalMutation(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target(){return hop();}function hop(){global $x;$x=2;return target();}');
        self::assertFalse((new \Deriver\Internal\Solver\Summary\Isolation($context))->callable(\Tests\Fake\SummaryFixture::body($context)));
    }
    public function testCallableRejectsReferencesAndUnknownCalls(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target(&$x){$x=2;}function unknown(){return missing();}');
        $proof = new \Deriver\Internal\Solver\Summary\Isolation($context);
        self::assertFalse($proof->callable(\Tests\Fake\SummaryFixture::body($context)));
        self::assertFalse($proof->callable(\Tests\Fake\SummaryFixture::body($context, 'unknown')));
    }
    public function testValueRejectsReferencesHiddenInsideArrayCopies(): void
    {
        $proof = new \Deriver\Internal\Solver\Summary\Isolation(\Tests\Fake\SolverFixture::context());
        self::assertFalse($proof->value(\Deriver\Value\Term::array([new \Deriver\Value\Term('cell', 'shared')])));
        self::assertFalse($proof->value(\Deriver\Value\Term::parameter('unknown')));
        self::assertTrue($proof->value(\Deriver\Value\Term::array([\Deriver\Value\Term::parameter('id', 'int')])));
    }
    public function testInstructionsRejectsExternalStateEvenIfNoReturnUsesIt(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target(){$unused=$_GET;return 1;}');
        self::assertFalse((new \Deriver\Internal\Solver\Summary\Isolation($context))->instructions(\Tests\Fake\SummaryFixture::body($context), []));
    }
    public function testValuePreservesSharedGraphComplexity(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(64, \Deriver\Value\Term::constant(1));
        self::assertTrue((new \Deriver\Internal\Solver\Summary\Isolation(\Tests\Fake\SolverFixture::context()))->value($value));
    }
    public function testCallableExcludesRuntimeErrorObjectAllocations(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target(){try{1/0;}catch(Error $e){return $e;}}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        self::assertFalse((new \Deriver\Internal\Solver\Summary\Isolation($context))->callable($body));
    }
    public function testLocalRejectsReferenceBearingFrames(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target(&$x){return $x;}');
        self::assertFalse((new \Deriver\Internal\Solver\Summary\Isolation($context))->local(\Tests\Fake\SummaryFixture::body($context)));
    }
    public function testDependenciesStopsAnOptionalProofAtItsNodeBudget(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target(){return 1+2;}', new \Deriver\Api\Query\Budget(nodes: 1));
        $work = 0;
        self::assertNull((new \Deriver\Internal\Solver\Summary\Isolation($context))->dependencies(\Tests\Fake\SummaryFixture::body($context), $work));
        self::assertFalse($context->sealed);
    }
    public function testCallableChecksAnImpureSiblingAfterTraversingACycle(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target(){a();global $x;$x=1;}function a(){target();}');
        self::assertFalse((new \Deriver\Internal\Solver\Summary\Isolation($context))->callable(\Tests\Fake\SummaryFixture::body($context)));
    }
    public function testValueChecksDeepSharedInputsIteratively(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(2000, \Deriver\Value\Term::constant(1));
        self::assertTrue((new \Deriver\Internal\Solver\Summary\Isolation(\Tests\Fake\SolverFixture::context()))->value($value));
    }
    public function testValueAbandonsAnOversizedOptionalProof(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(10, \Deriver\Value\Term::constant(1));
        $context = \Tests\Fake\SolverFixture::context(budget: new \Deriver\Api\Query\Budget(nodes: 2));
        self::assertFalse((new \Deriver\Internal\Solver\Summary\Isolation($context))->value($value));
        self::assertSame([], $context->frontiers);
    }
}

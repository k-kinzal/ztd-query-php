<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Summary;

use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\Solver\Summary\Isolation;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

/**
 * @covers \Deriver\Internal\Solver\Summary\Isolation
 */
#[CoversClass(Isolation::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(SourceRef::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\StaticLowering::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(Term::class)]
#[Small]
final class IsolationTest extends TestCase
{
    public function testCallableChecksTheWholeMutuallyRecursiveComponent(): void
    {
        $context = SolverFixture::context('<?php function target(){return hop();}function hop(){return target();}');
        $proof = new Isolation($context);
        self::assertTrue($proof->callable(\Tests\Fake\SummaryFixture::body($context)));
    }
    public function testCallableRejectsTransitiveGlobalMutation(): void
    {
        $context = SolverFixture::context('<?php function target(){return hop();}function hop(){global $x;$x=2;return target();}');
        self::assertFalse((new Isolation($context))->callable(\Tests\Fake\SummaryFixture::body($context)));
    }
    public function testCallableRejectsReferencesAndUnknownCalls(): void
    {
        $context = SolverFixture::context('<?php function target(&$x){$x=2;}function unknown(){return missing();}');
        $proof = new Isolation($context);
        self::assertFalse($proof->callable(\Tests\Fake\SummaryFixture::body($context)));
        self::assertFalse($proof->callable(\Tests\Fake\SummaryFixture::body($context, 'unknown')));
    }
    public function testValueRejectsReferencesHiddenInsideArrayCopies(): void
    {
        $proof = new Isolation(SolverFixture::context());
        self::assertFalse($proof->value(Term::array([new Term('cell', 'shared')])));
        self::assertFalse($proof->value(Term::parameter('unknown')));
        self::assertTrue($proof->value(Term::array([Term::parameter('id', 'int')])));
    }
    public function testInstructionsRejectsExternalStateEvenIfNoReturnUsesIt(): void
    {
        $context = SolverFixture::context('<?php function target(){$unused=$_GET;return 1;}');
        self::assertFalse((new Isolation($context))->instructions(\Tests\Fake\SummaryFixture::body($context), []));
    }
    public function testValuePreservesSharedGraphComplexity(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(64, Term::constant(1));
        self::assertTrue((new Isolation(SolverFixture::context()))->value($value));
    }
    public function testCallableExcludesRuntimeErrorObjectAllocations(): void
    {
        $context = SolverFixture::context('<?php function target(){try{1/0;}catch(Error $e){return $e;}}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        self::assertFalse((new Isolation($context))->callable($body));
    }
    public function testLocalRejectsReferenceBearingFrames(): void
    {
        $context = SolverFixture::context('<?php function target(&$x){return $x;}');
        self::assertFalse((new Isolation($context))->local(\Tests\Fake\SummaryFixture::body($context)));
    }
    public function testDependenciesStopsAnOptionalProofAtItsNodeBudget(): void
    {
        $context = SolverFixture::context('<?php function target(){return 1+2;}', new \Deriver\Api\Query\Budget(nodes: 1));
        $work = 0;
        self::assertNull((new Isolation($context))->dependencies(\Tests\Fake\SummaryFixture::body($context), $work));
        self::assertFalse($context->sealed);
    }
    public function testCallableChecksAnImpureSiblingAfterTraversingACycle(): void
    {
        $context = SolverFixture::context('<?php function target(){a();global $x;$x=1;}function a(){target();}');
        self::assertFalse((new Isolation($context))->callable(\Tests\Fake\SummaryFixture::body($context)));
    }
    public function testValueChecksDeepSharedInputsIteratively(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(2000, Term::constant(1));
        self::assertTrue((new Isolation(SolverFixture::context()))->value($value));
    }
    public function testValueAbandonsAnOversizedOptionalProof(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(10, Term::constant(1));
        $context = SolverFixture::context(budget: new \Deriver\Api\Query\Budget(nodes: 2));
        self::assertFalse((new Isolation($context))->value($value));
        self::assertSame([], $context->frontiers);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerFrameIsolation')]
    public function testLocalRequiresAnIndependentInvocationFrame(string $source, string $symbol, bool $expected): void
    {
        $context = SolverFixture::context($source);
        $body = $context->program->callable($symbol);
        self::assertNotNull($body);
        self::assertSame($expected, (new Isolation($context))->local($body));
    }

    /**
     * @return iterable<string,array{string,string,bool}>
     */
    public static function providerFrameIsolation(): iterable
    {
        yield 'ordinary function' => ['<?php function target($x){return $x;}', 'target', true];
        yield 'reference return' => ['<?php function &target(){static $x;return $x;}', 'target', false];
        yield 'reference parameter' => ['<?php function target($x,&$y){return $x;}', 'target', false];
        yield 'instance method' => ['<?php class Box{function target(){return 1;}}', 'Box::target', false];
        yield 'static method' => ['<?php class Box{static function target(){return 1;}}', 'Box::target', false];
        yield 'catch with variable' => ['<?php function target(){try{return 1;}catch(Error $e){return 2;}}', 'target', false];
        yield 'catch without variable' => ['<?php function target(){try{return 1;}catch(Error){return 2;}}', 'target', true];
    }

    public function testLocalRejectsCapturedVariablesScriptFramesAndModeledBodies(): void
    {
        $context = SolverFixture::context();
        $source = new SourceRef('test', 'a.php', 0, 1);
        $proof = new Isolation($context);
        self::assertFalse($proof->local(new CallableIR('closure:a', [], [], $source, captures:['value' => false])));
        self::assertFalse($proof->local(new CallableIR('script:a.php', [], [], $source)));
        $context->models->models['target'] = self::createStub(\Deriver\Model\CallModel::class);
        self::assertFalse($proof->local(new CallableIR('TARGET', [], [], $source)));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsolatedValues')]
    public function testValueRequiresEveryNestedOperandToBeIndependent(Term $value, bool $expected): void
    {
        $context = SolverFixture::context();
        $proof = new Isolation($context);
        self::assertSame($expected, $proof->value($value));
        self::assertSame($expected, $proof->value(Term::array([$value, $value])));
        self::assertSame([], $context->frontiers);
        self::assertFalse($context->sealed);
    }

    /**
     * @return iterable<string,array{Term,bool}>
     */
    public static function providerIsolatedValues(): iterable
    {
        yield 'null' => [Term::constant(null), true];
        yield 'uninitialized' => [new Term('uninitialized'), true];
        yield 'runtime error token' => [new Term('throwable', 'Error'), true];
        yield 'closed array' => [Term::fromNative(['x' => [1, 2]]), true];
        yield 'open array' => [Term::array([], true), false];
        yield 'cell' => [new Term('cell', 'shared'), false];
        yield 'object with scalar-looking bound' => [new Term('object', 'a', attributes:['type' => 'int']), false];
        yield 'closure with scalar-looking bound' => [new Term('closure', 'a', attributes:['type' => 'string']), false];
        yield 'domain with scalar-looking bound' => [new Term('domain', 'a', attributes:['type' => 'bool']), false];
        yield 'scalar union' => [new Term('input', 'a', attributes:['type' => 'int|float|string|bool|true|false|null']), true];
        yield 'object union' => [new Term('input', 'a', attributes:['type' => 'int|Box']), false];
        yield 'missing bound' => [new Term('input', 'a'), false];
        yield 'nonstring bound' => [new Term('input', 'a', attributes:['type' => 12]), false];
        yield 'unsafe operand under scalar expression' => [new Term('binary', '+', [new Term('cell', 'shared')], ['type' => 'int']), false];
        yield 'safe scalar expression' => [new Term('binary', '+', [Term::constant(1), Term::constant(2)], ['type' => 'int']), true];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerValueBudget')]
    public function testValueCountsUniqueNodesAtTheExactProofBudget(int $limit, bool $expected): void
    {
        $leaf = Term::constant(1);
        $value = Term::array([$leaf, $leaf]);
        $context = SolverFixture::context(budget: new \Deriver\Api\Query\Budget(nodes:$limit));
        self::assertSame($expected, (new Isolation($context))->value($value));
        self::assertFalse($context->sealed);
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return iterable<string,array{int,bool}>
     */
    public static function providerValueBudget(): iterable
    {
        yield 'one short' => [1, false];
        yield 'exactly two unique nodes' => [2, true];
        yield 'spare node' => [3, true];
    }

    public function testDependenciesIncludesDefaultInitializersAndEveryResolvedCallee(): void
    {
        $context = SolverFixture::context('<?php function first(){return 1;}function second(){return 2;}function target($a=3){$x=first();$y=second();return $x+$y;}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $work = 0;
        $dependencies = (new Isolation($context))->dependencies($body, $work);
        self::assertNotNull($dependencies);
        self::assertCount(3, $dependencies);
        self::assertSame($body->parameters[0]->default, $dependencies[0]);
        self::assertSame(['first', 'second'], array_map(static fn (CallableIR $callee): string => $callee->symbol, array_slice($dependencies, 1)));
        self::assertGreaterThan(0, $work);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInstructionBoundary')]
    public function testDependenciesHonorsTheRemainingInstructionBudget(int $alreadyUsed, bool $expected): void
    {
        $context = SolverFixture::context(budget:new \Deriver\Api\Query\Budget(nodes:2));
        $source = new SourceRef('test', 'a.php', 0, 1);
        $body = new CallableIR('target', [], [0 => new \Deriver\Internal\IR\BasicBlock(0, [new \Deriver\Internal\IR\Instruction('i', 'constant', $source, 'v', constant:Term::constant(1))], new \Deriver\Internal\IR\Terminator('return', 'v'))], $source);
        $work = $alreadyUsed;
        self::assertSame($expected ? [] : null, (new Isolation($context))->dependencies($body, $work));
        self::assertSame($alreadyUsed + 1, $work);
        self::assertFalse($context->sealed);
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return iterable<string,array{int,bool}>
     */
    public static function providerInstructionBoundary(): iterable
    {
        yield 'below limit' => [0, true];
        yield 'exact limit' => [1, true];
        yield 'over limit' => [2, false];
    }

    public function testInstructionsChecksTransitiveDependenciesAfterResolvingNames(): void
    {
        $context = SolverFixture::context('<?php function first(){return second();}function second(){global $x;return $x;}function target(){return first();}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        self::assertFalse((new Isolation($context))->instructions($body, []));
        self::assertFalse($context->sealed);
    }

    public function testCallableHonorsCancellationBeforeAdmittingAnEmptyBody(): void
    {
        $context = SolverFixture::context();
        $context->stopReason = 'CANCELLED';
        $body = new CallableIR('target', [], [], new SourceRef('test', 'a.php', 0, 1));
        self::assertFalse((new Isolation($context))->callable($body));
    }
}

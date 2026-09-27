<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Constraint;

use Deriver\Internal\Constraint\Constraints;
use Deriver\Internal\Solver\State;
use Deriver\Internal\Value\Identity;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Constraints::class)]
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
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Provider\Provider::class)]
#[UsesClass(\Deriver\Model\Provider\RefinementModel::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(Term::class)]
#[Small]
final class ConstraintsTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAssumePreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(int $x){if($x>0){if($x<=0)return "impossible";}return "ok";}');
        self::assertSame('ok', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame('ok', $result->normalOutcomes[1]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testComparisonNormalizesReversedIntegerBounds(): void
    {
        $constraints = new Constraints();
        $state = new State();
        $x = Term::parameter('x', 'int');
        self::assertTrue($constraints->comparison($state, new Term('binary', '<', [Term::constant(3),$x]), true));
        self::assertFalse($constraints->comparison($state, new Term('binary', '<=', [$x,Term::constant(3)]), true));
    }
    public function testRefineDoesNotAddFactsWithoutAProvider(): void
    {
        $state = new State();
        self::assertTrue((new Constraints())->refine($state, Term::parameter('flag', 'bool'), true));
        self::assertSame([], $state->constraints);
    }
    public function testBoundsIntersectsEqualitiesWithEarlierBounds(): void
    {
        $constraints = new Constraints();
        $state = new State();
        $x = Term::parameter('x', 'int');
        self::assertTrue($constraints->bounds($state, $x, Term::constant(0), '>'));
        self::assertFalse($constraints->bounds($state, $x, Term::constant(-1), '==='));
    }
    public function testBoundsPreservesExcludedConstants(): void
    {
        $constraints = new Constraints();
        $state = new State();
        $x = Term::parameter('x', 'int');
        self::assertTrue($constraints->bounds($state, $x, Term::constant(1), '!=='));
        self::assertFalse($constraints->bounds($state, $x, Term::constant(1), '==='));
    }
    public function testBoundsRejectsStrictFloatEqualityForAnInteger(): void
    {
        self::assertFalse((new Constraints())->bounds(new State(), Term::parameter('x', 'int'), Term::constant(1.0), '==='));
    }
    public function testOrderedRejectsBoundsOutsideTheIntegerDomain(): void
    {
        $bounds = ['min' => null,'max' => null,'equal' => null,'excluded' => []];
        $constraints = new Constraints();
        self::assertFalse($constraints->ordered($bounds, 9223372036854775807, '>'));
        self::assertFalse($constraints->ordered($bounds, -9223372036854775807 - 1, '<'));
        self::assertTrue($constraints->ordered($bounds, 9223372036854775807 - 1, '>'));
        self::assertSame(9223372036854775807, $bounds['min']);
    }

    #[DataProvider('providerConstantAssumptions')]
    public function testAssumeChecksConcreteTruthWithoutAddingGuards(Term $predicate, bool $truth, bool $expected): void
    {
        $state = new State();
        self::assertSame($expected, (new Constraints())->assume($state, $predicate, $truth));
        self::assertSame([], $state->guard);
    }

    /**
     * @return array<string,array{Term,bool,bool}>
     */
    public static function providerConstantAssumptions(): array
    {
        return ['true' => [Term::constant(true),true,true],'false requested' => [Term::constant(true),false,false],'false' => [Term::constant(false),false,true],'null' => [Term::constant(null),true,false],'empty array' => [Term::array([]),false,true]];
    }

    public function testAssumeSharesNegatedPredicateIdentityAndRejectsItsOpposite(): void
    {
        $state = new State();
        $predicate = Term::parameter('flag', 'bool');
        $constraints = new Constraints();
        $negated = $constraints->assume($state, new Term('unary', '!', [$predicate]), true);
        $repeated = $constraints->assume($state, $predicate, false);
        $opposite = $constraints->assume($state, $predicate, true);
        self::assertTrue($negated);
        self::assertTrue($repeated);
        self::assertFalse($opposite);
        self::assertSame([(new Identity())->key($predicate) => false], $state->guard);
    }

    #[DataProvider('providerOrderedBounds')]
    public function testOrderedIntersectsInclusiveIntegerAndFractionalEndpoints(int|float $number, string $operator, int|float|null $min, int|float|null $max): void
    {
        $bounds = ['min' => null,'max' => null,'equal' => null,'excluded' => []];
        self::assertTrue((new Constraints())->ordered($bounds, $number, $operator));
        self::assertSame($min, $bounds['min']);
        self::assertSame($max, $bounds['max']);
    }

    /**
     * @return array<string,array{int|float,string,int|float|null,int|float|null}>
     */
    public static function providerOrderedBounds(): array
    {
        return [
            'greater integer' => [3,'>',4,null], 'at least integer' => [3,'>=',3,null],
            'less integer' => [3,'<',null,2], 'at most integer' => [3,'<=',null,3],
            'greater integral float' => [3.0,'>',4.0,null], 'at least integral float' => [3.0,'>=',3.0,null],
            'less integral float' => [3.0,'<',null,2.0], 'at most integral float' => [3.0,'<=',null,3.0],
            'greater fraction' => [3.5,'>',4.0,null], 'at least fraction' => [3.5,'>=',4.0,null],
            'less fraction' => [3.5,'<',null,3.0], 'at most fraction' => [3.5,'<=',null,3.0],
            'negative fraction lower' => [-3.5,'>',-3.0,null], 'negative fraction upper' => [-3.5,'<',null,-4.0],
            'max inclusive' => [PHP_INT_MAX,'>=',PHP_INT_MAX,null], 'min inclusive' => [PHP_INT_MIN,'<=',null,PHP_INT_MIN],
            'unrecognized' => [3,'==',null,null],
        ];
    }

    public function testOrderedCannotWeakenExistingEndpoints(): void
    {
        $bounds = ['min' => 5,'max' => 8,'equal' => null,'excluded' => []];
        $constraints = new Constraints();
        self::assertTrue($constraints->ordered($bounds, 1, '>'));
        self::assertTrue($constraints->ordered($bounds, 20, '<'));
        self::assertSame(5, $bounds['min']);
        self::assertSame(8, $bounds['max']);
    }

    #[DataProvider('providerComparisonOrientation')]
    public function testComparisonNormalizesOrientationAndFalseBranches(string $operator, bool $truth, bool $reversed, int|null $min, int|null $max): void
    {
        $state = new State();
        $x = Term::parameter('x', 'int');
        $operands = array_reverse([$x,Term::constant(3)]);
        $arrangements = [[$x,Term::constant(3)],$operands];
        $operands = $arrangements[(int)$reversed];
        self::assertTrue((new Constraints())->comparison($state, new Term('binary', $operator, $operands), $truth));
        $bounds = $state->constraints[(new Identity())->key($x)];
        self::assertSame($min, $bounds['min']);
        self::assertSame($max, $bounds['max']);
    }

    /**
     * @return array<string,array{string,bool,bool,int|null,int|null}>
     */
    public static function providerComparisonOrientation(): array
    {
        return [
            'not less' => ['<',false,false,3,null], 'not at most' => ['<=',false,false,4,null],
            'not greater' => ['>',false,false,null,3], 'not at least' => ['>=',false,false,null,2],
            'reversed less' => ['<',true,true,4,null], 'reversed at most' => ['<=',true,true,3,null],
            'reversed greater' => ['>',true,true,null,2], 'reversed at least' => ['>=',true,true,null,3],
            'false inequality' => ['!==',false,false,3,3],
        ];
    }

    public function testBoundsPreservesAnEqualityAndRejectsItsExclusion(): void
    {
        $state = new State();
        $x = Term::parameter('x', 'int');
        $constraints = new Constraints();
        $number = Term::constant(3);
        self::assertTrue($constraints->bounds($state, $x, $number, '==='));
        self::assertSame($number, $state->constraints[(new Identity())->key($x)]['equal']);
        self::assertFalse($constraints->bounds($state, $x, $number, '!=='));
    }

    public function testBoundsKeepsEqualityInsideEarlierLimits(): void
    {
        $state = new State();
        $x = Term::parameter('x', 'int');
        $constraints = new Constraints();
        self::assertTrue($constraints->bounds($state, $x, Term::constant(1), '>='));
        self::assertTrue($constraints->bounds($state, $x, Term::constant(9), '<='));
        self::assertTrue($constraints->bounds($state, $x, Term::constant(4), '==='));
        self::assertSame(4, $state->constraints[(new Identity())->key($x)]['min']);
        self::assertSame(4, $state->constraints[(new Identity())->key($x)]['max']);
    }

    public function testBoundsIgnoresNonNumericConstantsAndStrictFloatExclusions(): void
    {
        $state = new State();
        $x = Term::parameter('x', 'int');
        $constraints = new Constraints();
        self::assertTrue($constraints->bounds($state, $x, Term::constant('x'), '==='));
        $beforeCount = count($state->constraints);
        self::assertTrue($constraints->bounds($state, $x, Term::constant(3.0), '!=='));
        self::assertSame([], $state->constraints[(new Identity())->key($x)]['excluded']);
        self::assertSame(0, $beforeCount);
    }

    public function testComparisonLeavesNonIntegerAndUnresolvedOperandsUnconstrained(): void
    {
        $state = new State();
        $constraints = new Constraints();
        self::assertTrue($constraints->comparison($state, Term::parameter('x'), true));
        self::assertTrue($constraints->comparison($state, new Term('binary', '<', [Term::parameter('x', 'float'),Term::constant(3)]), true));
        self::assertTrue($constraints->comparison($state, new Term('binary', '<', [Term::parameter('x', 'int'),Term::parameter('y', 'int')]), true));
        self::assertSame([], $state->constraints);
    }

    public function testRefineSkipsOtherProvidersAndAppliesEveryGuaranteedPredicate(): void
    {
        $other = self::createStub(\Deriver\Model\Provider\Provider::class);
        $other->method('id')->willReturn('other');
        $other->method('version')->willReturn('1');
        $x = Term::parameter('x', 'int');
        $lower = self::createStub(\Deriver\Model\Provider\RefinementModel::class);
        $lower->method('id')->willReturn('lower');
        $lower->method('version')->willReturn('1');
        $lower->method('refine')->willReturn(new Term('binary', '>=', [$x,Term::constant(3)]));
        $upper = self::createStub(\Deriver\Model\Provider\RefinementModel::class);
        $upper->method('id')->willReturn('upper');
        $upper->method('version')->willReturn('1');
        $upper->method('refine')->willReturn(new Term('binary', '<=', [$x,Term::constant(8)]));
        $context = \Tests\Fake\SolverFixture::context(configuration: new \Deriver\Api\Project\Configuration(providers:[$other,$lower,$upper]));
        $state = new State();
        self::assertTrue((new Constraints($context))->refine($state, Term::parameter('condition', 'bool'), true));
        $bounds = $state->constraints[(new Identity())->key($x)];
        self::assertSame(3, $bounds['min']);
        self::assertSame(8, $bounds['max']);
        self::assertCount(2, $state->guard);
        self::assertSame([], $context->frontiers);
    }

    #[DataProvider('providerRefinementResponses')]
    public function testRefinePreservesOptionalPredicatesAndImpossibleImplications(?Term $implied, bool $expected, int $guards): void
    {
        $provider = self::createStub(\Deriver\Model\Provider\RefinementModel::class);
        $provider->method('id')->willReturn('refinement');
        $provider->method('version')->willReturn('1');
        $provider->method('refine')->willReturn($implied);
        $context = \Tests\Fake\SolverFixture::context(configuration:new \Deriver\Api\Project\Configuration(providers:[$provider]));
        $state = new State();
        self::assertSame($expected, (new Constraints($context))->refine($state, Term::parameter('condition'), false));
        self::assertCount($guards, $state->guard);
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return iterable<string,array{Term|null,bool,int}>
     */
    public static function providerRefinementResponses(): iterable
    {
        yield 'not applicable' => [null,true,0];
        yield 'impossible' => [Term::constant(false),false,0];
        yield 'guaranteed' => [Term::constant(true),true,0];
        yield 'failure spelling in a literal' => [Term::constant('MODEL_CONTRACT_VIOLATION'),true,0];
        yield 'ordinary residual' => [Term::opaque('UNKNOWN_PREDICATE', 'bool'),true,1];
    }

    public function testRefineForwardsTheObservedPredicateAndPolarity(): void
    {
        $predicate = Term::parameter('condition', 'bool');
        $provider = self::createMock(\Deriver\Model\Provider\RefinementModel::class);
        $provider->method('id')->willReturn('refinement');
        $provider->method('version')->willReturn('1');
        $provider->expects(self::once())->method('refine')->with(self::identicalTo($predicate), false)->willReturn(null);
        $context = \Tests\Fake\SolverFixture::context(configuration:new \Deriver\Api\Project\Configuration(providers:[$provider]));
        self::assertTrue((new Constraints($context))->refine(new State(), $predicate, false));
    }

    public function testRefineRecordsProviderFailureWithoutClaimingAnImpossiblePath(): void
    {
        $provider = self::createStub(\Deriver\Model\Provider\RefinementModel::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $provider->method('refine')->willThrowException(new RuntimeException('provider failed'));
        $context = \Tests\Fake\SolverFixture::context(configuration:new \Deriver\Api\Project\Configuration(providers:[$provider]));
        $state = new State();
        self::assertTrue((new Constraints($context))->refine($state, Term::parameter('condition'), true));
        self::assertSame([], $state->guard);
        self::assertCount(1, $context->frontiers);
        $frontier = array_values($context->frontiers)[0];
        self::assertSame('MODEL_CONTRACT_VIOLATION', $frontier->code);
        self::assertSame('refinement-provider', $frontier->operation);
        self::assertSame('', $frontier->at->snapshotId);
        self::assertSame('model:refinement', $frontier->at->path);
        self::assertSame(0, $frontier->at->start);
        self::assertSame(0, $frontier->at->end);
    }

    #[DataProvider('providerNonComparisonTerms')]
    public function testComparisonLeavesIncompleteAndOtherOperationsUnconstrained(Term $predicate): void
    {
        $state = new State();
        self::assertTrue((new Constraints())->comparison($state, $predicate, true));
        self::assertSame([], $state->constraints);
    }

    /**
     * @return iterable<string,array{Term}>
     */
    public static function providerNonComparisonTerms(): iterable
    {
        $x = Term::parameter('x', 'int');
        yield 'missing right' => [new Term('binary', '<', [$x])];
        yield 'missing left' => [new Term('binary', '<', [1 => Term::constant(3)])];
        yield 'invalid operator' => [new Term('binary', null, [$x,Term::constant(3)])];
        yield 'other operation' => [new Term('intrinsic', '<', [$x,Term::constant(3)])];
        yield 'text endpoint' => [new Term('binary', '<', [$x,Term::constant('3')])];
        yield 'boolean endpoint' => [new Term('binary', '<', [$x,Term::constant(true)])];
        yield 'symbolic numeric payload' => [new Term('binary', '<', [$x,new Term('opaque', 3)])];
        yield 'two constants' => [new Term('binary', '<', [Term::constant(2),Term::constant(3)])];
    }

    public function testAssumeDoesNotTreatEveryUnaryOperationAsLogicalNegation(): void
    {
        $value = Term::parameter('x', 'int');
        $predicate = new Term('unary', '~', [$value]);
        $state = new State();
        self::assertTrue((new Constraints())->assume($state, $predicate, false));
        self::assertSame([(new Identity())->key($predicate) => false], $state->guard);
    }

    #[DataProvider('providerImpossibleIntegerBounds')]
    public function testBoundsRejectsStrictComparisonsPastIntegerExtrema(int $number, string $operator): void
    {
        $state = new State();
        self::assertFalse((new Constraints())->bounds($state, Term::parameter('x', 'int'), Term::constant($number), $operator));
        self::assertSame([], $state->constraints);
    }

    /**
     * @return iterable<string,array{int,string}>
     */
    public static function providerImpossibleIntegerBounds(): iterable
    {
        yield 'above maximum' => [PHP_INT_MAX,'>'];
        yield 'below minimum' => [PHP_INT_MIN,'<'];
    }

    public function testComparisonRoundsAFractionalLowerBoundAwayFromTheExcludedInteger(): void
    {
        $state = new State();
        $x = Term::parameter('x', 'int');
        self::assertTrue((new Constraints())->comparison($state, new Term('binary', '>=', [$x,Term::constant(3.2)]), true));
        self::assertSame(4.0, $state->constraints[(new Identity())->key($x)]['min']);
    }
}

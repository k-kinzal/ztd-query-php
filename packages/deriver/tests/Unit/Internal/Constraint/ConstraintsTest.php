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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
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
}

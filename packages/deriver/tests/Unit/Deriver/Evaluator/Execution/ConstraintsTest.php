<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution;

use Deriver\Deriver;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Extensions;
use Deriver\Deriver\Evaluator\Call\Provider\Provider;
use Deriver\Deriver\Evaluator\Call\Provider\RefinementEvaluator;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
use Deriver\Deriver\Evaluator\Execution\Call\ParameterBinding;
use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Call\TypeCheck;
use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Completion\Assessment;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Execution\Constraints;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Control\ObservationLimit;
use Deriver\Deriver\Evaluator\Execution\Control\StateJoin;
use Deriver\Deriver\Evaluator\Execution\Control\Unwinding;
use Deriver\Deriver\Evaluator\Execution\Demand\Cell;
use Deriver\Deriver\Evaluator\Execution\Demand\Components;
use Deriver\Deriver\Evaluator\Execution\Demand\Discovery;
use Deriver\Deriver\Evaluator\Execution\Demand\Key;
use Deriver\Deriver\Evaluator\Execution\Demand\Table;
use Deriver\Deriver\Evaluator\Execution\Dependencies;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Execution\InstructionTransfer;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Materialization;
use Deriver\Deriver\Evaluator\Execution\Memory\Memory;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\SlotReference;
use Deriver\Deriver\Evaluator\Execution\Memory\StorageCapture;
use Deriver\Deriver\Evaluator\Execution\ObservationCollector;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\CompletionRecord;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Invocation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Scalar\Arithmetic;
use Deriver\Deriver\Evaluator\Scalar\Comparison;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Execution\DerivationResult;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Execution\ResultAssessment;
use Deriver\Deriver\Identity\QueryEncoding;
use Deriver\Deriver\Identity\ValueGraph;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\EffectInspection;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Parameter;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;
use Deriver\Deriver\Lowerer\InstructionEmitter;
use Deriver\Deriver\Lowerer\Lowerer;
use Deriver\Deriver\Lowerer\LoweringScope;
use Deriver\Deriver\Lowerer\StatementLowering;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Resolver\AssignmentPatterns;
use Deriver\Deriver\Resolver\ClassScope;
use Deriver\Deriver\Resolver\ExpressionValidation;
use Deriver\Deriver\Resolver\MagicContext;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Selection\QueryValidation;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\LineMap;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(Constraints::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Evaluator::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(RefinementEvaluator::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderExpansion::class)]
#[UsesClass(Evaluators::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(DeriverConfig::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(Sources::class)]
#[UsesClass(Path::class)]
#[UsesClass(Snapshot::class)]
#[UsesClass(Source::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ConstraintsTest extends TestCase
{
    public function testAssumePreservesTheSemanticContract(): void
    {
        $source = '<?php function target(int $x){if($x>0){if($x<=0)return "impossible";}return "ok";}';
        $symbol = 'target';
        $input = new Sources([new Source('fixture.php', $source)]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        self::assertSame('ok', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame('ok', $result->normalOutcomes[1]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testComparisonNormalizesReversedIntegerBounds(): void
    {
        $constraints = new Constraints();
        $state = new State();
        $x = Term::parameter('x', 'int');
        self::assertTrue($constraints->comparison($state, new Term(TermKind::Binary, '<', [Term::constant(3), $x]), true));
        self::assertFalse($constraints->comparison($state, new Term(TermKind::Binary, '<=', [$x, Term::constant(3)]), true));
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
        $bounds = ['min' => null, 'max' => null, 'equal' => null, 'excluded' => []];
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
        return ['true' => [Term::constant(true), true, true], 'false requested' => [Term::constant(true), false, false], 'false' => [Term::constant(false), false, true], 'null' => [Term::constant(null), true, false], 'empty array' => [Term::array([]), false, true]];
    }
    public function testAssumeSharesNegatedPredicateIdentityAndRejectsItsOpposite(): void
    {
        $state = new State();
        $predicate = Term::parameter('flag', 'bool');
        $constraints = new Constraints();
        $negated = $constraints->assume($state, new Term(TermKind::Unary, '!', [$predicate]), true);
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
        $bounds = ['min' => null, 'max' => null, 'equal' => null, 'excluded' => []];
        self::assertTrue((new Constraints())->ordered($bounds, $number, $operator));
        self::assertSame($min, $bounds['min']);
        self::assertSame($max, $bounds['max']);
    }
    /**
     * @return array<string,array{int|float,string,int|float|null,int|float|null}>
     */
    public static function providerOrderedBounds(): array
    {
        return ['greater integer' => [3, '>', 4, null], 'at least integer' => [3, '>=', 3, null], 'less integer' => [3, '<', null, 2], 'at most integer' => [3, '<=', null, 3], 'greater integral float' => [3.0, '>', 4.0, null], 'at least integral float' => [3.0, '>=', 3.0, null], 'less integral float' => [3.0, '<', null, 2.0], 'at most integral float' => [3.0, '<=', null, 3.0], 'greater fraction' => [3.5, '>', 4.0, null], 'at least fraction' => [3.5, '>=', 4.0, null], 'less fraction' => [3.5, '<', null, 3.0], 'at most fraction' => [3.5, '<=', null, 3.0], 'negative fraction lower' => [-3.5, '>', -3.0, null], 'negative fraction upper' => [-3.5, '<', null, -4.0], 'max inclusive' => [PHP_INT_MAX, '>=', PHP_INT_MAX, null], 'min inclusive' => [PHP_INT_MIN, '<=', null, PHP_INT_MIN], 'unrecognized' => [3, '==', null, null]];
    }
    public function testOrderedCannotWeakenExistingEndpoints(): void
    {
        $bounds = ['min' => 5, 'max' => 8, 'equal' => null, 'excluded' => []];
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
        $operands = array_reverse([$x, Term::constant(3)]);
        $arrangements = [[$x, Term::constant(3)], $operands];
        $operands = $arrangements[(int) $reversed];
        self::assertTrue((new Constraints())->comparison($state, new Term(TermKind::Binary, $operator, $operands), $truth));
        $bounds = $state->constraints[(new Identity())->key($x)];
        self::assertSame($min, $bounds['min']);
        self::assertSame($max, $bounds['max']);
    }
    /**
     * @return array<string,array{string,bool,bool,int|null,int|null}>
     */
    public static function providerComparisonOrientation(): array
    {
        return ['not less' => ['<', false, false, 3, null], 'not at most' => ['<=', false, false, 4, null], 'not greater' => ['>', false, false, null, 3], 'not at least' => ['>=', false, false, null, 2], 'reversed less' => ['<', true, true, 4, null], 'reversed at most' => ['<=', true, true, 3, null], 'reversed greater' => ['>', true, true, null, 2], 'reversed at least' => ['>=', true, true, null, 3], 'false inequality' => ['!==', false, false, 3, 3]];
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
        self::assertTrue($constraints->comparison($state, new Term(TermKind::Binary, '<', [Term::parameter('x', 'float'), Term::constant(3)]), true));
        self::assertTrue($constraints->comparison($state, new Term(TermKind::Binary, '<', [Term::parameter('x', 'int'), Term::parameter('y', 'int')]), true));
        self::assertSame([], $state->constraints);
    }
    public function testRefineSkipsOtherProvidersAndAppliesEveryGuaranteedPredicate(): void
    {
        $other = self::createStub(Provider::class);
        $other->method('id')->willReturn('other');
        $other->method('version')->willReturn('1');
        $x = Term::parameter('x', 'int');
        $lower = self::createStub(RefinementEvaluator::class);
        $lower->method('id')->willReturn('lower');
        $lower->method('version')->willReturn('1');
        $lower->method('refine')->willReturn(new Term(TermKind::Binary, '>=', [$x, Term::constant(3)]));
        $upper = self::createStub(RefinementEvaluator::class);
        $upper->method('id')->willReturn('upper');
        $upper->method('version')->willReturn('1');
        $upper->method('refine')->willReturn(new Term(TermKind::Binary, '<=', [$x, Term::constant(8)]));
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions(providers: [$other, $lower, $upper]);
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
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
        $provider = self::createStub(RefinementEvaluator::class);
        $provider->method('id')->willReturn('refinement');
        $provider->method('version')->willReturn('1');
        $provider->method('refine')->willReturn($implied);
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions(providers: [$provider]);
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
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
        yield 'not applicable' => [null, true, 0];
        yield 'impossible' => [Term::constant(false), false, 0];
        yield 'guaranteed' => [Term::constant(true), true, 0];
        yield 'failure spelling in a literal' => [Term::constant('EVALUATOR_CONTRACT_VIOLATION'), true, 0];
        yield 'ordinary residual' => [Term::opaque('UNKNOWN_PREDICATE', 'bool'), true, 1];
    }
    public function testRefineForwardsTheObservedPredicateAndPolarity(): void
    {
        $predicate = Term::parameter('condition', 'bool');
        $provider = self::createMock(RefinementEvaluator::class);
        $provider->method('id')->willReturn('refinement');
        $provider->method('version')->willReturn('1');
        $provider->expects(self::once())->method('refine')->with(self::identicalTo($predicate), false)->willReturn(null);
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions(providers: [$provider]);
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        self::assertTrue((new Constraints($context))->refine(new State(), $predicate, false));
    }
    public function testRefinePropagatesProviderFailure(): void
    {
        $provider = self::createStub(RefinementEvaluator::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $provider->method('refine')->willThrowException(new RuntimeException('provider failed'));
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions(providers: [$provider]);
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $state = new State();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('provider failed');
        (new Constraints($context))->refine($state, Term::parameter('condition'), true);
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
        yield 'missing right' => [new Term(TermKind::Binary, '<', [$x])];
        yield 'missing left' => [new Term(TermKind::Binary, '<', [1 => Term::constant(3)])];
        yield 'invalid operator' => [new Term(TermKind::Binary, null, [$x, Term::constant(3)])];
        yield 'other operation' => [new Term(TermKind::Intrinsic, '<', [$x, Term::constant(3)])];
        yield 'text endpoint' => [new Term(TermKind::Binary, '<', [$x, Term::constant('3')])];
        yield 'boolean endpoint' => [new Term(TermKind::Binary, '<', [$x, Term::constant(true)])];
        yield 'symbolic numeric payload' => [new Term(TermKind::Binary, '<', [$x, new Term(TermKind::Opaque, 3)])];
        yield 'two constants' => [new Term(TermKind::Binary, '<', [Term::constant(2), Term::constant(3)])];
    }
    public function testAssumeDoesNotTreatEveryUnaryOperationAsLogicalNegation(): void
    {
        $value = Term::parameter('x', 'int');
        $predicate = new Term(TermKind::Unary, '~', [$value]);
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
        yield 'above maximum' => [PHP_INT_MAX, '>'];
        yield 'below minimum' => [PHP_INT_MIN, '<'];
    }
    public function testComparisonRoundsAFractionalLowerBoundAwayFromTheExcludedInteger(): void
    {
        $state = new State();
        $x = Term::parameter('x', 'int');
        self::assertTrue((new Constraints())->comparison($state, new Term(TermKind::Binary, '>=', [$x, Term::constant(3.2)]), true));
        self::assertSame(4.0, $state->constraints[(new Identity())->key($x)]['min']);
    }
}

<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Scalar;

use Deriver\Deriver;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Extensions;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
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
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Scalar\Arithmetic;
use Deriver\Deriver\Evaluator\Scalar\Comparison;
use Deriver\Deriver\Evaluator\Scalar\FloatConversion;
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
use Deriver\Deriver\Lowerer\Control\ConditionalLowering;
use Deriver\Deriver\Lowerer\Control\MatchLowering;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
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

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(Comparison::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(Dispatch::class)]
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
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(StorageCapture::class)]
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
#[UsesClass(ConditionalLowering::class)]
#[UsesClass(MatchLowering::class)]
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
#[UsesClass(FloatConversion::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ComparisonTest extends TestCase
{
    public function testApplyPreservesTheSemanticContract(): void
    {
        $source = '<?php function target(){return match(2){1=>"a",2=>"b",default=>"c"};}';
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
        self::assertSame('b', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testScalarPreservesPhpNumericStringAndStrictComparisons(): void
    {
        $comparison = new Comparison();
        self::assertSame(true, $comparison->scalar('==', 2, '2'));
        self::assertSame(false, $comparison->scalar('===', 2, '2'));
        self::assertSame(-1, $comparison->scalar('<=>', 2, 3));
    }
    public function testIdenticalComparesSharedArrayGraphsWithoutExpandingPaths(): void
    {
        $depth = 64;
        $leaf = Term::constant(1);
        $leaf = array_reduce(array_fill(0, $depth, null), static fn (Term $leaf): Term => Term::array([$leaf, $leaf]), $leaf);
        $a = $leaf;
        $depth1 = 64;
        $leaf1 = Term::constant(1);
        $leaf1 = array_reduce(array_fill(0, $depth1, null), static fn (Term $leaf1): Term => Term::array([$leaf1, $leaf1]), $leaf1);
        $b = $leaf1;
        $depth2 = 64;
        $leaf2 = Term::constant(2);
        $leaf2 = array_reduce(array_fill(0, $depth2, null), static fn (Term $leaf2): Term => Term::array([$leaf2, $leaf2]), $leaf2);
        $c = $leaf2;
        $comparison = new Comparison();
        self::assertTrue($comparison->identical($a, $b));
        self::assertFalse($comparison->identical($a, $c));
    }
    public function testIdenticalPreservesNaNAndSignedZeroSemantics(): void
    {
        $comparison = new Comparison();
        $nan = Term::array([Term::constant(NAN)]);
        self::assertFalse($comparison->identical($nan, $nan));
        self::assertTrue($comparison->identical(Term::fromNative([-0.0]), Term::fromNative([0.0])));
    }
    public function testIdenticalPreservesKeyOrderAndScalarTypes(): void
    {
        $comparison = new Comparison();
        self::assertFalse($comparison->identical(Term::fromNative(['a' => 1, 'b' => 2]), Term::fromNative(['b' => 2, 'a' => 1])));
        self::assertFalse($comparison->identical(Term::fromNative([1]), Term::fromNative([1.0])));
        self::assertFalse($comparison->identical(Term::parameter('x'), Term::parameter('x')));
    }
    public function testObjectDistinguishesClosureAllocationIdentities(): void
    {
        $a = new Term(TermKind::Closure, 'same-body', attributes: ['identity' => 'first']);
        $b = new Term(TermKind::Closure, 'same-body', attributes: ['identity' => 'second']);
        $comparison = new Comparison();
        self::assertTrue($comparison->object('===', $a, $a)?->native());
        self::assertFalse($comparison->object('===', $a, $b)?->native());
        self::assertTrue($comparison->object('!==', $a, $b)?->native());
        self::assertNull($comparison->object('==', $a, $b));
    }
    public function testObjectComparesTheSameIdentity(): void
    {
        $result = (new Comparison())->object('===', new Term(TermKind::Object, 'one'), new Term(TermKind::Object, 'one'));
        self::assertNotNull($result);
    }
    /**
     * @param string $operator
     * @param scalar|null $left
     * @param scalar|null $right
     * @param bool|int $expected
     */
    #[DataProvider('providerScalarComparisons')]
    public function testScalarKeepsEqualityOrderingAndNumericStringRules(string $operator, int|float|string|bool|null $left, int|float|string|bool|null $right, bool|int $expected): void
    {
        self::assertSame($expected, (new Comparison())->scalar($operator, $left, $right));
    }
    /**
     * @return list<array{string, scalar|null, scalar|null, bool|int}>
     */
    public static function providerScalarComparisons(): array
    {
        return [['===', 1, 1, true], ['===', 1, '1', false], ['!==', 1, '1', true], ['!==', 1, 1, false], ['==', 1, '1', true], ['==', 1, 'x', false], ['!=', 1, '1', false], ['!=', 1, 'x', true], ['<', 1, 2, true], ['<', 1, 1, false], ['<=', 1, 1, true], ['<=', 2, 1, false], ['>', 2, 1, true], ['>', 1, 1, false], ['>=', 1, 1, true], ['>=', 1, 2, false], ['<=>', 1, 2, -1], ['<=>', 2, 1, 1], ['<=>', 1, 1, 0], ['unknown', 1, 1, false], ['==', null, false, true], ['==', '10', '2', false], ['>', '10', '2', true]];
    }
    public function testApplyPreservesSymbolicOperandsAndResultCategories(): void
    {
        $comparison = new Comparison();
        $left = Term::parameter('left', 'int');
        $right = Term::constant(3);
        $ordered = $comparison->apply('<=>', $left, $right);
        self::assertSame(['binary', '<=>', [$left, $right], ['type' => 'int']], [$ordered->kind->value, $ordered->literal, $ordered->operands, $ordered->attributes]);
        self::assertSame('bool', $comparison->apply('===', $left, $right)->attributes['type']);
    }
    public function testIdenticalRequiresOrderedKeysAndRecursivelyIdenticalTypes(): void
    {
        $comparison = new Comparison();
        self::assertFalse($comparison->identical(Term::parameter('a'), Term::constant(1)));
        self::assertFalse($comparison->identical(Term::constant(1), Term::parameter('b')));
        self::assertFalse($comparison->identical(Term::array([]), Term::constant(null)));
        self::assertFalse($comparison->identical(Term::fromNative(['a' => 1, 'b' => 2]), Term::fromNative(['b' => 2, 'a' => 1])));
        self::assertFalse($comparison->identical(Term::fromNative([[1]]), Term::fromNative([['1']])));
        self::assertTrue($comparison->identical(Term::fromNative([[1]]), Term::fromNative([[1]])));
        self::assertTrue($comparison->apply('!==', Term::fromNative([1]), Term::fromNative([2]))->native());
        self::assertFalse($comparison->apply('!==', Term::fromNative([1]), Term::fromNative([1]))->native());
    }
    public function testObjectUsesAllocationIdentityForObjectsEnumsAndClosures(): void
    {
        $comparison = new Comparison();
        $object = new Term(TermKind::Object, 'same');
        self::assertTrue($comparison->object('===', $object, new Term(TermKind::Object, 'same'))?->native());
        self::assertFalse($comparison->object('!==', $object, new Term(TermKind::Object, 'same'))?->native());
        self::assertTrue($comparison->object('!==', $object, new Term(TermKind::Object, 'different'))?->native());
        self::assertTrue($comparison->object('===', new Term(TermKind::Enum, 'E::A'), new Term(TermKind::Enum, 'E::A'))?->native());
        self::assertFalse($comparison->object('===', new Term(TermKind::Closure, 'body', attributes: ['identity' => 'a']), new Term(TermKind::Closure, 'body', attributes: ['identity' => 'b']))?->native());
        self::assertNull($comparison->object('===', new Term(TermKind::Closure, 'body'), new Term(TermKind::Closure, 'body')));
        self::assertNull($comparison->object('===', $object, new Term(TermKind::Enum, 'same')));
        self::assertNull($comparison->object('==', $object, $object));
        self::assertNull($comparison->object('===', Term::constant(1), Term::constant(1)));
    }
    public function testScalarConvertsFloatsForNonNumericStringsUnderTheCapturedPrecision(): void
    {
        self::assertSame(-1, (new Comparison(14))->scalar('<=>', 0.1 + 0.2, '0.3 x'));
        self::assertSame(1, (new Comparison(-1))->scalar('<=>', 0.1 + 0.2, '0.3 x'));
    }
}

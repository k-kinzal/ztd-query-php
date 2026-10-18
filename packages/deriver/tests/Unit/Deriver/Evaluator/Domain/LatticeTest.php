<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Domain;

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
use Deriver\Deriver\Evaluator\Domain\AbstractDomain;
use Deriver\Deriver\Evaluator\Domain\DomainFact;
use Deriver\Deriver\Evaluator\Domain\DomainOperations;
use Deriver\Deriver\Evaluator\Domain\Lattice;
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
use Deriver\Deriver\Evaluator\Execution\Completion\Quality\DependencyClosure;
use Deriver\Deriver\Evaluator\Execution\Completion\Quality\Enumeration;
use Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision;
use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Execution\Constraints;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Control\LoopConvergence;
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
use Deriver\Deriver\Evaluator\Execution\Memory\ReferenceConstraint;
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
use Deriver\Deriver\Evaluator\Execution\Transfer\CompoundAssignment;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Projection;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Scalar\Arithmetic;
use Deriver\Deriver\Evaluator\Scalar\Comparison;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scalar\Increment;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Scalar\StringPrefix;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Execution\DerivationResult;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Execution\ResultAssessment;
use Deriver\Deriver\Identity\QueryEncoding;
use Deriver\Deriver\Identity\ValueGraph;
use Deriver\Deriver\Lowerer\AssignmentLowering;
use Deriver\Deriver\Lowerer\Control\DestructuringLowering;
use Deriver\Deriver\Lowerer\Control\LoopLowering;
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
use Override;
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
#[CoversClass(Lattice::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(Constraints::class)]
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
#[UsesClass(LoopConvergence::class)]
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
#[UsesClass(CompoundAssignment::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(DomainFact::class)]
#[UsesClass(DomainOperations::class)]
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
#[UsesClass(Frontier::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(LoopLowering::class)]
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
#[UsesClass(Increment::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Projection::class)]
#[UsesClass(StringPrefix::class)]
#[UsesClass(Term::class)]
#[Small]
final class LatticeTest extends TestCase
{
    public function testTypePreservesTheSemanticContract(): void
    {
        $source = '<?php function target(int $n){$s="";for($i=0;$i<$n;$i++){$s.="x";}return $s;}';
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
        self::assertSame(DependencyClosure::Closed, $result->assessment->closure);
        self::assertSame(ValuePrecision::Abstract, $result->assessment->precision);
        self::assertSame('WIDENED', $result->frontiers[0]->code);
        self::assertSame(Enumeration::NotEnumerated, $result->assessment->enumeration);
    }
    public function testContainsRespectsOpaqueTypeBounds(): void
    {
        $lattice = new Lattice();
        self::assertFalse($lattice->contains(Term::opaque('boundary', 'int'), Term::constant('a')));
        self::assertTrue($lattice->contains(Term::opaque('boundary', 'int'), Term::constant(1)));
    }
    public function testWidenContainsBothDifferentConstants(): void
    {
        $lattice = new Lattice();
        $joined = $lattice->widen(Term::constant(1), Term::constant(2));
        self::assertSame(TermKind::Abstract, $joined->kind);
        self::assertTrue($lattice->contains($joined, Term::constant(1)));
        self::assertTrue($lattice->contains($joined, Term::constant(2)));
    }
    public function testCompatibleRequiresKnownFieldsEvenWhenTheArrayHasAnUnknownRemainder(): void
    {
        $upper = Term::array(['required' => Term::constant(1)], true);
        $lattice = new Lattice();
        self::assertFalse($lattice->compatible($upper, Term::array([])));
        self::assertTrue($lattice->compatible($upper, Term::fromNative(['required' => 1, 'other' => 2])));
    }
    public function testContainsSharesRepeatedArraySubgraphObligations(): void
    {
        $depth = 30;
        $leaf = Term::constant(1);
        $leaf = array_reduce(array_fill(0, $depth, null), static fn (Term $leaf): Term => Term::array([$leaf, $leaf]), $leaf);
        $upper = $leaf;
        $depth1 = 30;
        $leaf1 = Term::constant(1);
        $leaf1 = array_reduce(array_fill(0, $depth1, null), static fn (Term $leaf1): Term => Term::array([$leaf1, $leaf1]), $leaf1);
        $lower = $leaf1;
        $lattice = new Lattice();
        self::assertTrue($lattice->contains($upper, $lower));
        self::assertFalse($lattice->contains($upper, Term::array([])));
    }
    public function testContainsPreservesKnownKeyOrderInsideAnOpenShape(): void
    {
        $upper = Term::array(['a' => Term::constant(1), 'b' => Term::constant(2)], true);
        self::assertFalse((new Lattice())->contains($upper, Term::fromNative(['b' => 2, 'a' => 1])));
    }
    public function testCompatibleDoesNotConfuseDomainIdentityWithAStringConstant(): void
    {
        $domain = new class () implements AbstractDomain {
            /**
             * @return string Domain identity
             */
            #[Override]
            public function id(): string
            {
                return 'example.policy';
            }
            /**
             * @return string Semantic version
             */
            #[Override]
            public function version(): string
            {
                return '1';
            }
            /**
             * @return DomainFact Empty set
             */
            #[Override]
            public function bottom(): DomainFact
            {
                return new DomainFact($this->id(), new Term(TermKind::Bottom));
            }
            /**
             * @return DomainFact All policy records
             */
            #[Override]
            public function top(): DomainFact
            {
                return new DomainFact($this->id(), new Term(TermKind::Top));
            }
            /**
             * @param DomainFact $a Contained fact
             * @param DomainFact $b Containing fact
             * @return bool Flat-lattice inclusion
             */
            #[Override]
            public function lessOrEqual(DomainFact $a, DomainFact $b): bool
            {
                return $a->representation->kind === TermKind::Bottom || $b->representation->kind === TermKind::Top || $a->representation->isConcrete() && $b->representation->isConcrete() && $a->representation->native() === $b->representation->native();
            }
            /**
             * @param DomainFact $a First possibility
             * @param DomainFact $b Second possibility
             * @return DomainFact Inclusive alternative
             */
            #[Override]
            public function join(DomainFact $a, DomainFact $b): DomainFact
            {
                if ($this->lessOrEqual($a, $b)) {
                    return $b;
                }
                return $this->lessOrEqual($b, $a) ? $a : $this->top();
            }
            /**
             * @param DomainFact $previous Previous approximation
             * @param DomainFact $next New approximation
             * @return DomainFact Finite widening
             */
            #[Override]
            public function widen(DomainFact $previous, DomainFact $next): DomainFact
            {
                return $this->join($previous, $next);
            }
            /**
             * @param DomainFact $value Policy record
             * @param Projection $projection Selected fields
             * @return DomainFact Projected fact
             */
            #[Override]
            public function project(DomainFact $value, Projection $projection): DomainFact
            {
                $term = $value->representation;
                foreach ($projection->path as $key) {
                    $term = $term->operands[$key] ?? new Term(TermKind::Top);
                }
                return new DomainFact($this->id(), $term);
            }
        };
        $top = $domain->top()->term();
        $lattice = new Lattice([$domain->id() => $domain]);
        self::assertFalse($lattice->contains($top, Term::constant($domain->id())));
        self::assertTrue($lattice->contains($lattice->widen($top, Term::constant($domain->id())), Term::constant($domain->id())));
    }
    #[DataProvider('providerTypes')]
    public function testTypeReturnsConservativeBoundsForEveryValueCategory(Term $value, string $expected): void
    {
        self::assertSame($expected, (new Lattice())->type($value));
    }
    /**
     * @return array<string,array{Term,string}>
     */
    public static function providerTypes(): array
    {
        return ['int' => [Term::constant(1), 'int'], 'float' => [Term::constant(1.5), 'float'], 'bool' => [Term::constant(false), 'bool'], 'string' => [Term::constant('a'), 'string'], 'null' => [Term::constant(null), 'null'], 'array' => [Term::array([]), 'array'], 'object' => [new Term(TermKind::Object, 'id'), 'object'], 'closure' => [new Term(TermKind::Closure, 'id'), 'object'], 'enum' => [new Term(TermKind::Enum, 'id'), 'object'], 'concat' => [new Term(TermKind::Concat), 'string'], 'arithmetic' => [new Term(TermKind::Binary, '+'), 'int|float'], 'array union' => [new Term(TermKind::Binary, '+', attributes: ['type' => 'array']), 'array'], 'comparison' => [new Term(TermKind::Binary, '===', attributes: ['type' => 'bool']), 'bool'], 'declared union' => [Term::parameter('x', 'int|string'), 'int|string'], 'unbounded' => [new Term(TermKind::External, 'x'), 'mixed'], 'malformed bound' => [new Term(TermKind::External, 'x', attributes: ['type' => 42]), 'mixed']];
    }
    #[DataProvider('providerInclusion')]
    public function testContainsPreservesDirectionAndCachesCompleteChildObligations(Term $upper, Term $lower, bool $expected): void
    {
        $lattice = new Lattice();
        $first = $lattice->contains($upper, $lower);
        $cached = $lattice->contains($upper, $lower);
        self::assertSame($expected, $first);
        self::assertSame($expected, $cached);
    }
    /**
     * @return array<string,array{Term,Term,bool}>
     */
    public static function providerInclusion(): array
    {
        return ['top' => [Term::opaque('unknown'), Term::fromNative(['x' => 1]), true], 'union accepts member' => [Term::opaque('unknown', 'int|string'), Term::constant(1), true], 'union rejects other member' => [Term::opaque('unknown', 'int|string'), Term::constant(null), false], 'narrow rejects union' => [Term::opaque('unknown', 'int'), Term::parameter('x', 'int|string'), false], 'abstract bound' => [new Term(TermKind::Abstract, attributes: ['type' => 'int']), Term::constant(1), true], 'constant equal' => [Term::constant(1), Term::constant(1), true], 'constant different' => [Term::constant(1), Term::constant(2), false], 'constant type distinct' => [Term::constant(1), Term::constant(1.0), false], 'exact input' => [Term::parameter('x', 'int'), Term::parameter('x', 'int'), true], 'independent inputs' => [Term::parameter('x', 'int'), Term::parameter('y', 'int'), false], 'closed keys cannot expand' => [Term::array([]), Term::fromNative([1]), false], 'closed cannot contain open' => [Term::array([]), Term::array([], true), false], 'open admits suffix' => [Term::array(['x' => Term::constant(1)], true), Term::fromNative(['x' => 1, 'y' => 2]), true], 'open admits unknown remainder' => [Term::array(['x' => Term::constant(1)], true), Term::array(['x' => Term::constant(1)], true), true], 'required key cannot disappear' => [Term::array(['x' => Term::constant(1)], true), Term::array([], true), false], 'nested member' => [Term::array(['x' => Term::opaque('bound', 'int')]), Term::fromNative(['x' => 4]), true], 'nested mismatch' => [Term::array(['x' => Term::opaque('bound', 'int')]), Term::fromNative(['x' => 'no']), false], 'known key order' => [Term::fromNative(['a' => 1, 'b' => 2]), Term::fromNative(['b' => 2, 'a' => 1]), false], 'object identity' => [new Term(TermKind::Object, 'a'), new Term(TermKind::Object, 'b'), false]];
    }
    #[DataProvider('providerWidening')]
    public function testWidenIncludesBothInputsWithStableUnionBounds(Term $a, Term $b, string $type): void
    {
        $lattice = new Lattice();
        $result = $lattice->widen($a, $b);
        self::assertSame(TermKind::Abstract, $result->kind);
        self::assertSame('WIDENED', $result->literal);
        self::assertSame($type, $result->attributes['type']);
        self::assertTrue($lattice->contains($result, $a));
        self::assertTrue($lattice->contains($result, $b));
    }
    /**
     * @return array<string,array{Term,Term,string}>
     */
    public static function providerWidening(): array
    {
        return ['integers' => [Term::constant(1), Term::constant(2), 'int|float'], 'floats' => [Term::constant(1.5), Term::constant(2.5), 'int|float'], 'mixed numeric' => [Term::constant(1), Term::constant(2.5), 'int|float'], 'strings' => [Term::constant('a'), Term::constant('b'), 'string'], 'sorted union' => [Term::constant('a'), Term::constant(false), 'bool|string'], 'unbounded' => [Term::constant(1), Term::parameter('x'), 'mixed']];
    }
    public function testWidenKeepsTheSharedPrefixOfStrings(): void
    {
        $lattice = new Lattice();
        $widened = $lattice->widen(Term::constant('SELECT * FROM t WHERE 1'), Term::constant('SELECT * FROM t WHERE 1 AND c = ?'));
        self::assertSame(TermKind::Concat, $widened->kind);
        self::assertSame('SELECT * FROM t WHERE 1', $widened->operands[0]->native());
        self::assertSame('string', $lattice->type($widened));
        self::assertTrue($lattice->contains($widened, Term::constant('SELECT * FROM t WHERE 1')));
        self::assertTrue($lattice->contains($widened, Term::constant('SELECT * FROM t WHERE 1 AND c = ? AND c = ?')));
        self::assertFalse($lattice->contains($widened, Term::constant('SELECT 1')));
        self::assertFalse($lattice->contains($widened, Term::parameter('x', 'string')));
        $next = new Term(TermKind::Concat, operands: [$widened, Term::constant(' AND d')], attributes: ['type' => 'string']);
        self::assertSame($widened, $lattice->widen($widened, $next));
        self::assertSame('WIDENED', $lattice->widen($widened, Term::constant('DELETE'))->literal);
    }
    public function testWidenPreservesAnExistingUpperBoundIdentity(): void
    {
        $upper = Term::opaque('known-bound', 'int|string');
        self::assertSame($upper, (new Lattice())->widen($upper, Term::constant('x')));
    }
    public function testWidenUsesRegisteredDomainLaws(): void
    {
        $domain = new class () implements AbstractDomain {
            /**
             * @return string Domain identity
             */
            #[Override]
            public function id(): string
            {
                return 'example.policy';
            }
            /**
             * @return string Semantic version
             */
            #[Override]
            public function version(): string
            {
                return '1';
            }
            /**
             * @return DomainFact Empty set
             */
            #[Override]
            public function bottom(): DomainFact
            {
                return new DomainFact($this->id(), new Term(TermKind::Bottom));
            }
            /**
             * @return DomainFact All policy records
             */
            #[Override]
            public function top(): DomainFact
            {
                return new DomainFact($this->id(), new Term(TermKind::Top));
            }
            /**
             * @param DomainFact $a Contained fact
             * @param DomainFact $b Containing fact
             * @return bool Flat-lattice inclusion
             */
            #[Override]
            public function lessOrEqual(DomainFact $a, DomainFact $b): bool
            {
                return $a->representation->kind === TermKind::Bottom || $b->representation->kind === TermKind::Top || $a->representation->isConcrete() && $b->representation->isConcrete() && $a->representation->native() === $b->representation->native();
            }
            /**
             * @param DomainFact $a First possibility
             * @param DomainFact $b Second possibility
             * @return DomainFact Inclusive alternative
             */
            #[Override]
            public function join(DomainFact $a, DomainFact $b): DomainFact
            {
                if ($this->lessOrEqual($a, $b)) {
                    return $b;
                }
                return $this->lessOrEqual($b, $a) ? $a : $this->top();
            }
            /**
             * @param DomainFact $previous Previous approximation
             * @param DomainFact $next New approximation
             * @return DomainFact Finite widening
             */
            #[Override]
            public function widen(DomainFact $previous, DomainFact $next): DomainFact
            {
                return $this->join($previous, $next);
            }
            /**
             * @param DomainFact $value Policy record
             * @param Projection $projection Selected fields
             * @return DomainFact Projected fact
             */
            #[Override]
            public function project(DomainFact $value, Projection $projection): DomainFact
            {
                $term = $value->representation;
                foreach ($projection->path as $key) {
                    $term = $term->operands[$key] ?? new Term(TermKind::Top);
                }
                return new DomainFact($this->id(), $term);
            }
        };
        $a = new DomainFact($domain->id(), Term::constant('a'));
        $b = new DomainFact($domain->id(), Term::constant('b'));
        $lattice = new Lattice([$domain->id() => $domain]);
        $joined = $lattice->widen($a->term(), $b->term());
        self::assertSame(TermKind::Domain, $joined->kind);
        self::assertSame($domain->id(), $joined->literal);
        self::assertTrue($lattice->contains($joined, $a->term()));
        self::assertTrue($lattice->contains($joined, $b->term()));
        self::assertFalse($lattice->contains($a->term(), $joined));
    }
    public function testWidenKeepsAnExistingContainingTypeBound(): void
    {
        $bound = new Term(TermKind::Abstract, 'WIDENED', attributes: ['type' => 'int|float']);
        $input = Term::constant(4);
        $lattice = new Lattice();
        self::assertTrue($lattice->contains($bound, $input));
        $joined = $lattice->widen($bound, $input);
        self::assertSame($bound, $joined);
        self::assertTrue($lattice->contains($joined, $bound));
    }
    public function testShapeChecksRequiredOrderAndUnknownRemainders(): void
    {
        $lattice = new Lattice();
        $closed = Term::fromNative(['a' => 1]);
        $open = Term::array(['a' => Term::constant(1)], true);
        self::assertFalse($lattice->shape($closed, $open));
        self::assertFalse($lattice->shape($open, Term::array([])));
        self::assertTrue($lattice->shape($open, Term::fromNative(['a' => 1, 'b' => 2])));
        self::assertFalse($lattice->shape(Term::fromNative(['a' => 1, 'b' => 2]), Term::fromNative(['b' => 2, 'a' => 1])));
    }
}

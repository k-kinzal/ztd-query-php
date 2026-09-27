<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\LoopConvergence;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Components;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Demand\Table;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Invocation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\CompoundAssignment;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Domain\DomainFact;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\ResultRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Assessment;
use Deriver\Result\Derivation;
use Deriver\Result\DerivationResult;
use Deriver\Result\Frontier;
use Deriver\Result\Serialization\JsonText;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Result\Serialization\ValueGraph;
use Deriver\Result\Statistics;
use Deriver\Result\StorageSnapshot;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\Control\LoopLowering;
use Deriver\Source\Compilation\EffectInspection;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Arithmetic;
use Deriver\Value\Comparison;
use Deriver\Value\Identity;
use Deriver\Value\Increment;
use Deriver\Value\Lattice;
use Deriver\Value\Operations;
use Deriver\Value\Projection;
use Deriver\Value\SecretFingerprint;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Lattice::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
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
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
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
#[UsesClass(\Deriver\Model\Domain\DomainOperations::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(ProjectSnapshot::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(JsonText::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(LoopLowering::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Increment::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Projection::class)]
#[UsesClass(SecretFingerprint::class)]
#[UsesClass(Term::class)]
#[Small]
final class LatticeTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testTypePreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(int $n){$s="";for($i=0;$i<$n;$i++){$s.="x";}return $s;}');
        self::assertSame('closed', $result->assessment->closure);
        self::assertSame('abstract', $result->assessment->precision);
        self::assertSame('WIDENED', $result->frontiers[0]->code);
        self::assertSame('not-enumerated', $result->assessment->enumeration);
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
        self::assertSame('abstract', $joined->kind);
        self::assertTrue($lattice->contains($joined, Term::constant(1)));
        self::assertTrue($lattice->contains($joined, Term::constant(2)));
    }

    public function testCompatibleRequiresKnownFieldsEvenWhenTheArrayHasAnUnknownRemainder(): void
    {
        $upper = Term::array(['required' => Term::constant(1)], true);
        $lattice = new Lattice();
        self::assertFalse($lattice->compatible($upper, Term::array([])));
        self::assertTrue($lattice->compatible($upper, Term::fromNative(['required' => 1,'other' => 2])));
    }
    public function testContainsSharesRepeatedArraySubgraphObligations(): void
    {
        $upper = \Tests\Fake\ValueDocument::shared(30, Term::constant(1));
        $lower = \Tests\Fake\ValueDocument::shared(30, Term::constant(1));
        $lattice = new Lattice();
        self::assertTrue($lattice->contains($upper, $lower));
        self::assertFalse($lattice->contains($upper, Term::array([])));
    }
    public function testContainsPreservesKnownKeyOrderInsideAnOpenShape(): void
    {
        $upper = Term::array(['a' => Term::constant(1),'b' => Term::constant(2)], true);
        self::assertFalse((new Lattice())->contains($upper, Term::fromNative(['b' => 2,'a' => 1])));
    }
    public function testCompatibleDoesNotConfuseDomainIdentityWithAStringConstant(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
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
        return [
            'int' => [Term::constant(1),'int'], 'float' => [Term::constant(1.5),'float'], 'bool' => [Term::constant(false),'bool'],
            'string' => [Term::constant('a'),'string'], 'null' => [Term::constant(null),'null'], 'array' => [Term::array([]),'array'],
            'object' => [new Term('object', 'id'),'object'], 'closure' => [new Term('closure', 'id'),'object'], 'enum' => [new Term('enum', 'id'),'object'],
            'concat' => [new Term('concat'),'string'], 'arithmetic' => [new Term('binary', '+'),'int|float'],
            'array union' => [new Term('binary', '+', attributes:['type' => 'array']),'array'],
            'comparison' => [new Term('binary', '===', attributes:['type' => 'bool']),'bool'],
            'declared union' => [Term::parameter('x', 'int|string'),'int|string'],
            'unbounded' => [new Term('external', 'x'),'mixed'], 'malformed bound' => [new Term('external', 'x', attributes:['type' => 42]),'mixed'],
        ];
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
        return [
            'top' => [Term::opaque('unknown'),Term::fromNative(['x' => 1]),true],
            'union accepts member' => [Term::opaque('unknown', 'int|string'),Term::constant(1),true],
            'union rejects other member' => [Term::opaque('unknown', 'int|string'),Term::constant(null),false],
            'narrow rejects union' => [Term::opaque('unknown', 'int'),Term::parameter('x', 'int|string'),false],
            'abstract bound' => [new Term('abstract', attributes:['type' => 'int']),Term::constant(1),true],
            'constant equal' => [Term::constant(1),Term::constant(1),true],
            'constant different' => [Term::constant(1),Term::constant(2),false],
            'constant type distinct' => [Term::constant(1),Term::constant(1.0),false],
            'exact input' => [Term::parameter('x', 'int'),Term::parameter('x', 'int'),true],
            'independent inputs' => [Term::parameter('x', 'int'),Term::parameter('y', 'int'),false],
            'closed keys cannot expand' => [Term::array([]),Term::fromNative([1]),false],
            'closed cannot contain open' => [Term::array([]),Term::array([], true),false],
            'open admits suffix' => [Term::array(['x' => Term::constant(1)], true),Term::fromNative(['x' => 1,'y' => 2]),true],
            'open admits unknown remainder' => [Term::array(['x' => Term::constant(1)], true),Term::array(['x' => Term::constant(1)], true),true],
            'required key cannot disappear' => [Term::array(['x' => Term::constant(1)], true),Term::array([], true),false],
            'nested member' => [Term::array(['x' => Term::opaque('bound', 'int')]),Term::fromNative(['x' => 4]),true],
            'nested mismatch' => [Term::array(['x' => Term::opaque('bound', 'int')]),Term::fromNative(['x' => 'no']),false],
            'known key order' => [Term::fromNative(['a' => 1,'b' => 2]),Term::fromNative(['b' => 2,'a' => 1]),false],
            'object identity' => [new Term('object', 'a'),new Term('object', 'b'),false],
        ];
    }

    #[DataProvider('providerWidening')]
    public function testWidenIncludesBothInputsWithStableUnionBounds(Term $a, Term $b, string $type, bool $secret): void
    {
        $lattice = new Lattice();
        $result = $lattice->widen($a, $b);
        self::assertSame('abstract', $result->kind);
        self::assertSame('WIDENED', $result->literal);
        self::assertSame($type, $result->attributes['type']);
        self::assertSame($secret, $result->isSecret());
        self::assertTrue($lattice->contains($result, $a));
        self::assertTrue($lattice->contains($result, $b));
    }

    /**
     * @return array<string,array{Term,Term,string,bool}>
     */
    public static function providerWidening(): array
    {
        return [
            'integers' => [Term::constant(1),Term::constant(2),'int|float',false],
            'floats' => [Term::constant(1.5),Term::constant(2.5),'int|float',false],
            'mixed numeric' => [Term::constant(1),Term::constant(2.5),'int|float',false],
            'strings' => [Term::constant('a'),Term::constant('b'),'string',false],
            'sorted union' => [Term::constant('a'),Term::constant(false),'bool|string',false],
            'unbounded' => [Term::constant(1),Term::parameter('x'),'mixed',false],
            'previous secret' => [Term::constant(1, true),Term::constant(2),'int|float',true],
            'next secret' => [Term::constant(1),Term::constant(2, true),'int|float',true],
        ];
    }

    public function testWidenPreservesAnExistingUpperBoundIdentity(): void
    {
        $upper = Term::opaque('known-bound', 'int|string');
        self::assertSame($upper, (new Lattice())->widen($upper, Term::constant('x')));
    }

    public function testWidenUsesRegisteredDomainLaws(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $a = new DomainFact($domain->id(), Term::constant('a'));
        $b = new DomainFact($domain->id(), Term::constant('b'));
        $lattice = new Lattice([$domain->id() => $domain]);
        $joined = $lattice->widen($a->term(), $b->term());
        self::assertSame('domain', $joined->kind);
        self::assertSame($domain->id(), $joined->literal);
        self::assertTrue($lattice->contains($joined, $a->term()));
        self::assertTrue($lattice->contains($joined, $b->term()));
        self::assertFalse($lattice->contains($a->term(), $joined));
    }

    public function testWidenRetainsASecretContainedByAnEarlierPublicTypeBound(): void
    {
        $public = new Term('abstract', 'WIDENED', attributes: ['type' => 'int|float']);
        $secret = Term::constant(4, true);
        $lattice = new Lattice();
        self::assertFalse($lattice->contains($public, $secret));
        $joined = $lattice->widen($public, $secret);
        self::assertTrue($joined->isSecret());
        self::assertTrue($lattice->contains($joined, $public));
        self::assertTrue($lattice->contains($joined, $secret));
    }

    public function testShapeChecksRequiredOrderAndUnknownRemainders(): void
    {
        $lattice = new Lattice();
        $closed = Term::fromNative(['a' => 1]);
        $open = Term::array(['a' => Term::constant(1)], true);
        self::assertFalse($lattice->shape($closed, $open));
        self::assertFalse($lattice->shape($open, Term::array([])));
        self::assertTrue($lattice->shape($open, Term::fromNative(['a' => 1,'b' => 2])));
        self::assertFalse($lattice->shape(Term::fromNative(['a' => 1,'b' => 2]), Term::fromNative(['b' => 2,'a' => 1])));
    }
}

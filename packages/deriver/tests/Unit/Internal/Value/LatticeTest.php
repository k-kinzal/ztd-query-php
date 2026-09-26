<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Value;

use Deriver\Internal\Value\Lattice;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Lattice::class)]
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
#[UsesClass(\Deriver\Internal\Constraint\Constraints::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\LoopLowering::class)]
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
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Control\LoopConvergence::class)]
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
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\CompoundAssignment::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\Increment::class)]
#[UsesClass(Lattice::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Internal\Value\SecretFingerprint::class)]
#[UsesClass(\Deriver\Model\Domain\AbstractDomain::class)]
#[UsesClass(\Deriver\Model\Domain\DomainFact::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Projection::class)]
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
        $a = new \Deriver\Model\Domain\DomainFact($domain->id(), Term::constant('a'));
        $b = new \Deriver\Model\Domain\DomainFact($domain->id(), Term::constant('b'));
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

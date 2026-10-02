<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Model\Domain\DomainFact;
use Deriver\Value\Lattice;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Lattice::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\LoopConvergence::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Components::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Key::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Table::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\CompoundAssignment::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(DomainFact::class)]
#[UsesClass(\Deriver\Model\Domain\DomainOperations::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Result\Serialization\QueryEncoding::class)]
#[UsesClass(\Deriver\Result\Serialization\ValueGraph::class)]
#[UsesClass(\Deriver\Result\Statistics::class)]
#[UsesClass(\Deriver\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\LoopLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Increment::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(\Deriver\Value\Projection::class)]
#[UsesClass(\Deriver\Value\SecretFingerprint::class)]
#[UsesClass(\Deriver\Value\StringPrefix::class)]
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

    public function testWidenKeepsTheSharedPrefixOfStrings(): void
    {
        $lattice = new Lattice();
        $widened = $lattice->widen(Term::constant('SELECT * FROM t WHERE 1'), Term::constant('SELECT * FROM t WHERE 1 AND c = ?'));
        self::assertSame('concat', $widened->kind);
        self::assertSame('SELECT * FROM t WHERE 1', $widened->operands[0]->native());
        self::assertSame('string', $lattice->type($widened));
        self::assertTrue($lattice->contains($widened, Term::constant('SELECT * FROM t WHERE 1')));
        self::assertTrue($lattice->contains($widened, Term::constant('SELECT * FROM t WHERE 1 AND c = ? AND c = ?')));
        self::assertFalse($lattice->contains($widened, Term::constant('SELECT 1')));
        self::assertFalse($lattice->contains($widened, Term::parameter('x', 'string')));
        $next = new Term('concat', operands: [$widened, Term::constant(' AND d')], attributes: ['type' => 'string']);
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

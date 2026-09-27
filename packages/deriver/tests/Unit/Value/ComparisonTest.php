<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Value\Comparison;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Comparison::class)]
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
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
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
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
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
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ConditionalLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\MatchLowering::class)]
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
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ComparisonTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){return match(2){1=>"a",2=>"b",default=>"c"};}');
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
        $a = \Tests\Fake\ValueDocument::shared(64, Term::constant(1));
        $b = \Tests\Fake\ValueDocument::shared(64, Term::constant(1));
        $c = \Tests\Fake\ValueDocument::shared(64, Term::constant(2));
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
        self::assertFalse($comparison->identical(Term::fromNative(['a' => 1,'b' => 2]), Term::fromNative(['b' => 2,'a' => 1])));
        self::assertFalse($comparison->identical(Term::fromNative([1]), Term::fromNative([1.0])));
        self::assertFalse($comparison->identical(Term::parameter('x'), Term::parameter('x')));
    }
    public function testObjectDistinguishesClosureAllocationIdentities(): void
    {
        $a = new Term('closure', 'same-body', attributes: ['identity' => 'first']);
        $b = new Term('closure', 'same-body', attributes: ['identity' => 'second']);
        $comparison = new Comparison();
        self::assertTrue($comparison->object('===', $a, $a)?->native());
        self::assertFalse($comparison->object('===', $a, $b)?->native());
        self::assertTrue($comparison->object('!==', $a, $b)?->native());
        self::assertNull($comparison->object('==', $a, $b));
    }
    public function testObjectRetainsTheConfidentialIdentityLabel(): void
    {
        $result = (new Comparison())->object('===', new Term('object', 'one', secret: true), new Term('object', 'one'));
        self::assertNotNull($result);
        self::assertTrue($result->isSecret());
    }

    /**
     * @param string $operator
     * @param scalar|null $left
     * @param scalar|null $right
     * @param bool|int $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerScalarComparisons')]
    public function testScalarKeepsEqualityOrderingAndNumericStringRules(string $operator, int|float|string|bool|null $left, int|float|string|bool|null $right, bool|int $expected): void
    {
        self::assertSame($expected, (new Comparison())->scalar($operator, $left, $right));
    }

    /**
     * @return list<array{string, scalar|null, scalar|null, bool|int}>
     */
    public static function providerScalarComparisons(): array
    {
        return [
            ['===', 1, 1, true], ['===', 1, '1', false], ['!==', 1, '1', true], ['!==', 1, 1, false],
            ['==', 1, '1', true], ['==', 1, 'x', false], ['!=', 1, '1', false], ['!=', 1, 'x', true],
            ['<', 1, 2, true], ['<', 1, 1, false], ['<=', 1, 1, true], ['<=', 2, 1, false],
            ['>', 2, 1, true], ['>', 1, 1, false], ['>=', 1, 1, true], ['>=', 1, 2, false],
            ['<=>', 1, 2, -1], ['<=>', 2, 1, 1], ['<=>', 1, 1, 0], ['unknown', 1, 1, false],
            ['==', null, false, true], ['==', '10', '2', false], ['>', '10', '2', true],
        ];
    }

    public function testApplyPreservesSymbolicOperandsAndResultCategories(): void
    {
        $comparison = new Comparison();
        $left = Term::parameter('left', 'int');
        $right = Term::constant(3);
        $ordered = $comparison->apply('<=>', $left, $right);
        self::assertSame(['binary', '<=>', [$left, $right], ['type' => 'int']], [$ordered->kind, $ordered->literal, $ordered->operands, $ordered->attributes]);
        self::assertSame('bool', $comparison->apply('===', $left, $right)->attributes['type']);
        self::assertTrue($comparison->apply('===', Term::constant(3, true), $right)->isSecret());
        self::assertTrue($comparison->apply('===', $right, Term::constant(3, true))->isSecret());
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
        $object = new Term('object', 'same', secret: true);
        self::assertTrue($comparison->object('===', $object, new Term('object', 'same'))?->native());
        self::assertFalse($comparison->object('!==', $object, new Term('object', 'same'))?->native());
        self::assertTrue($comparison->object('!==', $object, new Term('object', 'different'))?->native());
        self::assertTrue($comparison->object('===', $object, $object)?->isSecret());
        self::assertTrue($comparison->object('===', new Term('enum', 'E::A'), new Term('enum', 'E::A'))?->native());
        self::assertFalse($comparison->object('===', new Term('closure', 'body', attributes: ['identity' => 'a']), new Term('closure', 'body', attributes: ['identity' => 'b']))?->native());
        self::assertNull($comparison->object('===', new Term('closure', 'body'), new Term('closure', 'body')));
        self::assertNull($comparison->object('===', $object, new Term('enum', 'same')));
        self::assertNull($comparison->object('==', $object, $object));
        self::assertNull($comparison->object('===', Term::constant(1), Term::constant(1)));
    }
}

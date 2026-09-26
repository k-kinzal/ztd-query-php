<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Value;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Value\Comparison::class)]
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
#[UsesClass(\Deriver\Internal\Constraint\Constraints::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ConditionalLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\MatchLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
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
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
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
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
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
        $comparison = new \Deriver\Internal\Value\Comparison();
        self::assertSame(true, $comparison->scalar('==', 2, '2'));
        self::assertSame(false, $comparison->scalar('===', 2, '2'));
        self::assertSame(-1, $comparison->scalar('<=>', 2, 3));
    }
    public function testIdenticalComparesSharedArrayGraphsWithoutExpandingPaths(): void
    {
        $a = \Tests\Fake\ValueDocument::shared(64, \Deriver\Value\Term::constant(1));
        $b = \Tests\Fake\ValueDocument::shared(64, \Deriver\Value\Term::constant(1));
        $c = \Tests\Fake\ValueDocument::shared(64, \Deriver\Value\Term::constant(2));
        $comparison = new \Deriver\Internal\Value\Comparison();
        self::assertTrue($comparison->identical($a, $b));
        self::assertFalse($comparison->identical($a, $c));
    }
    public function testIdenticalPreservesNaNAndSignedZeroSemantics(): void
    {
        $comparison = new \Deriver\Internal\Value\Comparison();
        $nan = \Deriver\Value\Term::array([\Deriver\Value\Term::constant(NAN)]);
        self::assertFalse($comparison->identical($nan, $nan));
        self::assertTrue($comparison->identical(\Deriver\Value\Term::fromNative([-0.0]), \Deriver\Value\Term::fromNative([0.0])));
    }
    public function testIdenticalPreservesKeyOrderAndScalarTypes(): void
    {
        $comparison = new \Deriver\Internal\Value\Comparison();
        self::assertFalse($comparison->identical(\Deriver\Value\Term::fromNative(['a' => 1,'b' => 2]), \Deriver\Value\Term::fromNative(['b' => 2,'a' => 1])));
        self::assertFalse($comparison->identical(\Deriver\Value\Term::fromNative([1]), \Deriver\Value\Term::fromNative([1.0])));
        self::assertFalse($comparison->identical(\Deriver\Value\Term::parameter('x'), \Deriver\Value\Term::parameter('x')));
    }
    public function testObjectDistinguishesClosureAllocationIdentities(): void
    {
        $a = new \Deriver\Value\Term('closure', 'same-body', attributes: ['identity' => 'first']);
        $b = new \Deriver\Value\Term('closure', 'same-body', attributes: ['identity' => 'second']);
        $comparison = new \Deriver\Internal\Value\Comparison();
        self::assertTrue($comparison->object('===', $a, $a)?->native());
        self::assertFalse($comparison->object('===', $a, $b)?->native());
        self::assertTrue($comparison->object('!==', $a, $b)?->native());
        self::assertNull($comparison->object('==', $a, $b));
    }
    public function testObjectRetainsTheConfidentialIdentityLabel(): void
    {
        $result = (new \Deriver\Internal\Value\Comparison())->object('===', new \Deriver\Value\Term('object', 'one', secret: true), new \Deriver\Value\Term('object', 'one'));
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
        self::assertSame($expected, (new \Deriver\Internal\Value\Comparison())->scalar($operator, $left, $right));
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
        $comparison = new \Deriver\Internal\Value\Comparison();
        $left = \Deriver\Value\Term::parameter('left', 'int');
        $right = \Deriver\Value\Term::constant(3);
        $ordered = $comparison->apply('<=>', $left, $right);
        self::assertSame(['binary', '<=>', [$left, $right], ['type' => 'int']], [$ordered->kind, $ordered->literal, $ordered->operands, $ordered->attributes]);
        self::assertSame('bool', $comparison->apply('===', $left, $right)->attributes['type']);
        self::assertTrue($comparison->apply('===', \Deriver\Value\Term::constant(3, true), $right)->isSecret());
        self::assertTrue($comparison->apply('===', $right, \Deriver\Value\Term::constant(3, true))->isSecret());
    }

    public function testIdenticalRequiresOrderedKeysAndRecursivelyIdenticalTypes(): void
    {
        $comparison = new \Deriver\Internal\Value\Comparison();
        self::assertFalse($comparison->identical(\Deriver\Value\Term::parameter('a'), \Deriver\Value\Term::constant(1)));
        self::assertFalse($comparison->identical(\Deriver\Value\Term::constant(1), \Deriver\Value\Term::parameter('b')));
        self::assertFalse($comparison->identical(\Deriver\Value\Term::array([]), \Deriver\Value\Term::constant(null)));
        self::assertFalse($comparison->identical(\Deriver\Value\Term::fromNative(['a' => 1, 'b' => 2]), \Deriver\Value\Term::fromNative(['b' => 2, 'a' => 1])));
        self::assertFalse($comparison->identical(\Deriver\Value\Term::fromNative([[1]]), \Deriver\Value\Term::fromNative([['1']])));
        self::assertTrue($comparison->identical(\Deriver\Value\Term::fromNative([[1]]), \Deriver\Value\Term::fromNative([[1]])));
        self::assertTrue($comparison->apply('!==', \Deriver\Value\Term::fromNative([1]), \Deriver\Value\Term::fromNative([2]))->native());
        self::assertFalse($comparison->apply('!==', \Deriver\Value\Term::fromNative([1]), \Deriver\Value\Term::fromNative([1]))->native());
    }

    public function testObjectUsesAllocationIdentityForObjectsEnumsAndClosures(): void
    {
        $comparison = new \Deriver\Internal\Value\Comparison();
        $object = new \Deriver\Value\Term('object', 'same', secret: true);
        self::assertTrue($comparison->object('===', $object, new \Deriver\Value\Term('object', 'same'))?->native());
        self::assertFalse($comparison->object('!==', $object, new \Deriver\Value\Term('object', 'same'))?->native());
        self::assertTrue($comparison->object('!==', $object, new \Deriver\Value\Term('object', 'different'))?->native());
        self::assertTrue($comparison->object('===', $object, $object)?->isSecret());
        self::assertTrue($comparison->object('===', new \Deriver\Value\Term('enum', 'E::A'), new \Deriver\Value\Term('enum', 'E::A'))?->native());
        self::assertFalse($comparison->object('===', new \Deriver\Value\Term('closure', 'body', attributes: ['identity' => 'a']), new \Deriver\Value\Term('closure', 'body', attributes: ['identity' => 'b']))?->native());
        self::assertNull($comparison->object('===', new \Deriver\Value\Term('closure', 'body'), new \Deriver\Value\Term('closure', 'body')));
        self::assertNull($comparison->object('===', $object, new \Deriver\Value\Term('enum', 'same')));
        self::assertNull($comparison->object('==', $object, $object));
        self::assertNull($comparison->object('===', \Deriver\Value\Term::constant(1), \Deriver\Value\Term::constant(1)));
    }
}

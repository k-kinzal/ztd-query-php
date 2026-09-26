<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(\Deriver\Value\Term::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\InvalidInputException::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
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
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Path::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Reader::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[Small]
final class TermTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testConstantPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$x=1;$a=[&$x];$b=$a;$b[0]=2;return [$x,$a,$b];}');
        self::assertSame([2, [2], [2]], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testConstantPreservesConfidentiality(): void
    {
        $value = \Deriver\Value\Term::constant('token', true);
        self::assertTrue($value->isSecret());
        self::assertSame('token', $value->native());
    }

    public function testParameterKeepsItsDeclaredType(): void
    {
        $value = \Deriver\Value\Term::parameter('id', 'int');
        self::assertSame('parameter', $value->kind);
        self::assertSame('int', $value->attributes['type']);
        self::assertFalse($value->isConcrete());
    }

    public function testArrayPreservesKeyOrder(): void
    {
        $value = \Deriver\Value\Term::array(['name' => \Deriver\Value\Term::constant('a'), 4 => \Deriver\Value\Term::constant(2)]);
        self::assertSame(['name', 4], array_keys($value->operands));
        self::assertSame(['name' => 'a', 4 => 2], $value->native());
    }

    public function testOpaqueRetainsKnownDependencies(): void
    {
        $input = \Deriver\Value\Term::parameter('input');
        $value = \Deriver\Value\Term::opaque('MISSING_CALL_MODEL', 'string', [$input]);
        self::assertSame([$input], $value->operands);
        self::assertFalse($value->isConcrete());
    }

    public function testFromNativeRejectsApplicationObjects(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        \Deriver\Value\Term::fromNative(new stdClass());
    }

    public function testIsConcreteRejectsOpenArrayRemainders(): void
    {
        self::assertFalse(\Deriver\Value\Term::array(['id' => \Deriver\Value\Term::constant(1)], true)->isConcrete());
    }

    public function testNativeRejectsSymbolicInputs(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        \Deriver\Value\Term::parameter('id')->native();
    }

    public function testIsSecretPropagatesThroughExpressionOperands(): void
    {
        $value = new \Deriver\Value\Term('concat', operands: [\Deriver\Value\Term::constant('a'), \Deriver\Value\Term::constant('token', true)]);
        self::assertTrue($value->isSecret());
    }

    public function testFromNativeRejectsCyclicArrays(): void
    {
        $value = [];
        $value['cycle'] = &$value;
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        \Deriver\Value\Term::fromNative($value);
    }
    public function testIsConcreteTraversesSharedArraySubgraphsOnce(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(64, \Deriver\Value\Term::constant(1));
        self::assertTrue($value->isConcrete());
    }
    public function testIsSecretTraversesSharedPublicSubgraphsOnce(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(64, \Deriver\Value\Term::constant(1));
        self::assertFalse($value->isSecret());
    }
    public function testNativePreservesCopyOnWriteIsolationForSharedSubgraphs(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(8, \Deriver\Value\Term::constant(1));
        $native = $value->native();
        self::assertIsArray($native);
        self::assertIsArray($native[0]);
        $native[0][0] = 'changed';
        $again = $value->native();
        self::assertIsArray($again);
        self::assertIsArray($again[0]);
        self::assertIsArray($again[0][0]);
        self::assertIsArray($native[1]);
    }
    public function testNativeMaterializesADeepSharedArrayWithoutExpandingAllPaths(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(64, \Deriver\Value\Term::constant(1));
        self::assertTrue(is_array($value->native()));
    }
    public function testIsConcreteHandlesDeepSharedGraphsWithoutRecursion(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(2000, \Deriver\Value\Term::constant(1));
        self::assertTrue($value->isConcrete());
        self::assertFalse($value->isSecret());
    }
    public function testIsSecretHandlesDeepSharedGraphsWithoutRecursion(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(2000, \Deriver\Value\Term::constant('secret', true));
        self::assertTrue($value->isSecret());
    }
}

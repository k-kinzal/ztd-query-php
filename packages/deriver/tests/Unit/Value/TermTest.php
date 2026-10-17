<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Exception\InvalidInputException;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Term::class)]
#[UsesClass(\Deriver\Analysis\Execution\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\Selection\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\Execution\ResultAssessment::class)]
#[UsesClass(\Deriver\Session\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Query\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Address::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Path::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Reader::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Location::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Materialization::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Memory::class)]
#[UsesClass(\Deriver\Evaluation\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Evaluation\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderExpansion::class)]
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
#[UsesClass(\Deriver\Project\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Execution\Alternative::class)]
#[UsesClass(\Deriver\Result\Execution\Assessment::class)]
#[UsesClass(\Deriver\Result\Execution\Derivation::class)]
#[UsesClass(\Deriver\Result\Execution\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Result\Serialization\QueryEncoding::class)]
#[UsesClass(\Deriver\Result\Serialization\ValueGraph::class)]
#[UsesClass(\Deriver\Result\Statistics::class)]
#[UsesClass(\Deriver\Result\Execution\StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringCache::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweredCallable::class)]
#[UsesClass(\Deriver\Source\Lowering\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Parsing\ParseCache::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Source\Parsing\ParsedFile::class)]
#[UsesClass(\Deriver\Source\Lowering\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\CallableLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\InstructionEmitter::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringScope::class)]
#[UsesClass(\Deriver\Source\Lowering\StatementLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\Lowering\LineMap::class)]
#[UsesClass(\Deriver\Source\Parsing\MagicContext::class)]
#[UsesClass(\Deriver\Source\Parsing\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Parsing\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Parsing\ClassScope::class)]
#[UsesClass(\Deriver\Source\Parsing\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
        $value = Term::constant('token', true);
        self::assertTrue($value->isSecret());
        self::assertSame('token', $value->native());
    }

    public function testParameterKeepsItsDeclaredType(): void
    {
        $value = Term::parameter('id', 'int');
        self::assertSame('parameter', $value->kind);
        self::assertSame('int', $value->attributes['type']);
        self::assertFalse($value->isConcrete());
    }

    public function testArrayPreservesKeyOrder(): void
    {
        $value = Term::array(['name' => Term::constant('a'), 4 => Term::constant(2)]);
        self::assertSame(['name', 4], array_keys($value->operands));
        self::assertSame(['name' => 'a', 4 => 2], $value->native());
    }

    public function testOpaqueRetainsKnownDependencies(): void
    {
        $input = Term::parameter('input');
        $value = Term::opaque('MISSING_CALL_MODEL', 'string', [$input]);
        self::assertSame([$input], $value->operands);
        self::assertFalse($value->isConcrete());
    }

    public function testFromNativeRejectsApplicationObjects(): void
    {
        $this->expectException(InvalidInputException::class);
        Term::fromNative(new stdClass());
    }

    public function testIsConcreteRejectsOpenArrayRemainders(): void
    {
        self::assertFalse(Term::array(['id' => Term::constant(1)], true)->isConcrete());
    }

    public function testNativeRejectsSymbolicInputs(): void
    {
        $this->expectException(InvalidInputException::class);
        Term::parameter('id')->native();
    }

    public function testIsSecretPropagatesThroughExpressionOperands(): void
    {
        $value = new Term('concat', operands: [Term::constant('a'), Term::constant('token', true)]);
        self::assertTrue($value->isSecret());
    }

    public function testFromNativeRejectsCyclicArrays(): void
    {
        $value = [];
        $value['cycle'] = &$value;
        $this->expectException(InvalidInputException::class);
        Term::fromNative($value);
    }
    public function testIsConcreteTraversesSharedArraySubgraphsOnce(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(64, Term::constant(1));
        self::assertTrue($value->isConcrete());
    }
    public function testIsSecretTraversesSharedPublicSubgraphsOnce(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(64, Term::constant(1));
        self::assertFalse($value->isSecret());
    }
    public function testNativePreservesCopyOnWriteIsolationForSharedSubgraphs(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(8, Term::constant(1));
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
        $value = \Tests\Fake\ValueDocument::shared(64, Term::constant(1));
        self::assertTrue(is_array($value->native()));
    }
    public function testIsConcreteHandlesDeepSharedGraphsWithoutRecursion(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(2000, Term::constant(1));
        self::assertTrue($value->isConcrete());
        self::assertFalse($value->isSecret());
    }
    public function testIsSecretHandlesDeepSharedGraphsWithoutRecursion(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(2000, Term::constant('secret', true));
        self::assertTrue($value->isSecret());
    }
}

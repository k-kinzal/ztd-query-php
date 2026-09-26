<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php\Control;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Frontend\Php\Control\ConditionalLowering::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\AnalysisSession::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\Query::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
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
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Program::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ConditionalLoweringTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testLowerPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$x=0;false && ++$x;true || ++$x;return $x;}');
        self::assertSame(0, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testBinaryKeepsTheRightCallOffTheShortCircuitPath(): void
    {
        $l = \Tests\Fake\FrontendFixture::lowering();
        $node = new \PhpParser\Node\Expr\BinaryOp\BooleanAnd(new \PhpParser\Node\Expr\Variable('flag'), new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('effect')));
        (new \Deriver\Internal\Frontend\Php\Control\ConditionalLowering($l))->binary($node);
        self::assertSame(['local', 'read'], array_column($l->graph->instructions[0], 'operation'));
        self::assertSame(['constant', 'call-prepare', 'invoke', 'cast'], array_column($l->graph->instructions[1], 'operation'));
        self::assertSame(false, $l->graph->instructions[2][0]->constant?->native());
    }
    public function testSilentRecordsAnExistenceProbeWithoutAnOrdinaryRead(): void
    {
        $l = \Tests\Fake\FrontendFixture::lowering();
        (new \Deriver\Internal\Frontend\Php\Control\ConditionalLowering($l))->silent(new \PhpParser\Node\Expr\Variable('missing'), true);
        self::assertSame(['local', 'read-silent'], array_column($l->graph->instructions[0], 'operation'));
        self::assertTrue($l->graph->instructions[0][1]->attributes['existence']);
    }
    public function testCoalesceAssignEvaluatesTheAddressOnce(): void
    {
        $l = \Tests\Fake\FrontendFixture::lowering();
        $node = new \PhpParser\Node\Expr\AssignOp\Coalesce(new \PhpParser\Node\Expr\Variable('x'), new \PhpParser\Node\Scalar\Int_(3));
        (new \Deriver\Internal\Frontend\Php\Control\ConditionalLowering($l))->coalesceAssign($node);
        self::assertSame(['local', 'read-silent', 'not-null'], array_column($l->graph->instructions[0], 'operation'));
        self::assertSame([], $l->graph->instructions[1]);
        self::assertSame(['constant', 'write'], array_column($l->graph->instructions[2], 'operation'));
    }
    public function testChoiceLinksEachPhiOperandToItsBranch(): void
    {
        $l = \Tests\Fake\FrontendFixture::lowering();
        (new \Deriver\Internal\Frontend\Php\Control\ConditionalLowering($l))->choice(new \PhpParser\Node\Expr\Variable('flag'), 'condition', static fn (): string => 'true-value', static fn (): string => 'false-value');
        self::assertSame([1, 2], $l->graph->terminators[0]->targets);
        self::assertSame(['true-value', 'false-value', 'condition'], $l->graph->instructions[3][0]->operands);
        self::assertSame(['left' => 1, 'right' => 2], $l->graph->instructions[3][0]->attributes);
    }
    public function testIssetExpressionDelaysLaterOperandsUntilTheFirstIsPresent(): void
    {
        $l = \Tests\Fake\FrontendFixture::lowering();
        (new \Deriver\Internal\Frontend\Php\Control\ConditionalLowering($l))->issetExpression(new \PhpParser\Node\Expr\Isset_([new \PhpParser\Node\Expr\Variable('a'), new \PhpParser\Node\Expr\Variable('b')]), 0);
        self::assertSame('a', $l->graph->instructions[0][0]->name);
        self::assertSame('b', $l->graph->instructions[1][0]->name);
        self::assertSame(false, $l->graph->instructions[2][0]->constant?->native());
    }
    public function testMatchExpressionIncludesAnUnmatchedException(): void
    {
        $l = \Tests\Fake\FrontendFixture::lowering();
        (new \Deriver\Internal\Frontend\Php\Control\ConditionalLowering($l))->matchExpression(new \PhpParser\Node\Expr\Match_(new \PhpParser\Node\Scalar\Int_(1), []));
        self::assertSame('raise', $l->graph->instructions[0][1]->operation);
        self::assertSame('UnhandledMatchError', $l->graph->instructions[0][1]->name);
    }
}

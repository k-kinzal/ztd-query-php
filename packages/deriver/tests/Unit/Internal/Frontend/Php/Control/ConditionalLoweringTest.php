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
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\MatchLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Program::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\Allocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Handler::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Path::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Reader::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\Increment::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
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

    public function testLowerBuildsLazyFullTernaryBranches(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $node = new \PhpParser\Node\Expr\Ternary(new \PhpParser\Node\Scalar\Int_(1), new \PhpParser\Node\Scalar\Int_(2), new \PhpParser\Node\Scalar\Int_(3));
        $result = (new \Deriver\Internal\Frontend\Php\Control\ConditionalLowering($lowering))->lower($node);
        self::assertSame(1, $lowering->graph->instructions[0][0]->constant?->native());
        self::assertSame(2, $lowering->graph->instructions[1][0]->constant?->native());
        self::assertSame(3, $lowering->graph->instructions[2][0]->constant?->native());
        self::assertSame('branch', $lowering->graph->terminators[0]->kind);
        self::assertSame([1,2], $lowering->graph->terminators[0]->targets);
        self::assertSame($lowering->graph->instructions[3][0]->result, $result);
        self::assertSame([$lowering->graph->instructions[1][0]->result,$lowering->graph->instructions[2][0]->result,$lowering->graph->instructions[0][0]->result], $lowering->graph->instructions[3][0]->operands);
    }
    public function testLowerReusesTheEvaluatedConditionInAnAbbreviatedTernary(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $node = new \PhpParser\Node\Expr\Ternary(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('once')), null, new \PhpParser\Node\Scalar\Int_(3));
        (new \Deriver\Internal\Frontend\Php\Control\ConditionalLowering($lowering))->lower($node);
        self::assertSame(['constant','call-prepare','invoke'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame([], $lowering->graph->instructions[1]);
        self::assertSame(3, $lowering->graph->instructions[2][0]->constant?->native());
        self::assertSame($lowering->graph->instructions[0][2]->result, $lowering->graph->instructions[3][0]->operands[0]);
    }
    public function testLowerBuildsAnEmptyProbeFromASilentRead(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $node = new \PhpParser\Node\Expr\Empty_(new \PhpParser\Node\Expr\Variable('missing'));
        $result = (new \Deriver\Internal\Frontend\Php\Control\ConditionalLowering($lowering))->lower($node);
        self::assertSame(['local','read-silent','unary'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('Expr_BooleanNot', $lowering->graph->instructions[0][2]->name);
        self::assertSame([$lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame($lowering->graph->instructions[0][2]->result, $result);
    }
    public function testLowerRetainsAnExplicitBoundaryForAnUnexpectedExpression(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $result = (new \Deriver\Internal\Frontend\Php\Control\ConditionalLowering($lowering))->lower(new \PhpParser\Node\Expr\Variable('unexpected'));
        self::assertSame('unsupported', $lowering->graph->instructions[0][0]->operation);
        self::assertSame('Expr_Variable', $lowering->graph->instructions[0][0]->name);
        self::assertSame($lowering->graph->instructions[0][0]->result, $result);
    }
    public function testSilentMarksComputedOffsetExistenceWithoutAllocatingAnAddress(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $node = new \PhpParser\Node\Expr\ArrayDimFetch(new \PhpParser\Node\Expr\Array_([]), new \PhpParser\Node\Scalar\Int_(0));
        $result = (new \Deriver\Internal\Frontend\Php\Control\ConditionalLowering($lowering))->silent($node, true);
        self::assertSame(['constant','constant','array-read'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('array', $lowering->graph->instructions[0][0]->constant?->kind);
        self::assertSame(0, $lowering->graph->instructions[0][1]->constant?->native());
        self::assertSame(['silent' => true,'existence' => true], $lowering->graph->instructions[0][2]->attributes);
        self::assertSame([$lowering->graph->instructions[0][0]->result,$lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame($lowering->graph->instructions[0][2]->result, $result);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerConditionalEffects')]
    public function testLowerPreservesSelectedBranchEffects(string $source, mixed $expected): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @return iterable<string,array{string,mixed}>
     */
    public static function providerConditionalEffects(): iterable
    {
        yield 'ternary false' => ['<?php function target(){$a=0;$r=false?($a=1):($a=2);return [$r,$a];}',[2,2]];
        yield 'short ternary truthy' => ['<?php function target(){$a=0;$r=(++$a)?:++$a;return [$r,$a];}',[1,1]];
        yield 'short ternary falsey' => ['<?php function target(){$a=0;$r=$a?:++$a;return [$r,$a];}',[1,1]];
        yield 'coalesce present false' => ['<?php function target(){$a=0;$r=false??++$a;return [$r,$a];}',[false,0]];
        yield 'coalesce missing' => ['<?php function target(){$a=0;$r=$missing??++$a;return [$r,$a];}',[1,1]];
        yield 'boolean and evaluates' => ['<?php function target(){$a=0;$r=true&&++$a;return [$r,$a];}',[true,1]];
        yield 'boolean or evaluates' => ['<?php function target(){$a=0;$r=false||++$a;return [$r,$a];}',[true,1]];
        yield 'logical and short circuit' => ['<?php function target(){$a=0;$r=(false and ++$a);return [$r,$a];}',[false,0]];
        yield 'logical or short circuit' => ['<?php function target(){$a=0;$r=(true or ++$a);return [$r,$a];}',[true,0]];
        yield 'isset short circuit' => ['<?php function target(){$a=0;$r=isset($missing,$array[++$a]);return [$r,$a];}',[false,0]];
        yield 'isset all present' => ['<?php function target(){$a=0;$array=[1=>2];$r=isset($a,$array[++$a]);return [$r,$a];}',[true,1]];
        yield 'empty computed array' => ['<?php function target(){return empty([0][0]);}',true];
        yield 'isset computed absence' => ['<?php function target(){return isset([][0]);}',false];
        yield 'match selected effects' => ['<?php function target(){$a=0;$r=match(2){1=>++$a,2=>($a=4),default=>99};return [$r,$a];}',[4,4]];
        yield 'match default before arm' => ['<?php function target(){return match(2){default=>99,2=>4};}',4];
        yield 'match unmatched' => ['<?php function target(){try{return match(2){1=>3};}catch(UnhandledMatchError $e){return 4;}}',4];
        yield 'nullsafe skipped arguments' => ['<?php function target(){$x=null;$a=0;$r=$x?->method(++$a);return [$r,$a];}',[null,0]];
        yield 'nullsafe method' => ['<?php class B{function method($n){return $n+1;}}function target(){$a=0;$r=(new B)?->method(++$a);return [$r,$a];}',[2,1]];
        yield 'nullsafe property null' => ['<?php function target(){$x=null;return $x?->value;}',null];
        yield 'nullsafe property present' => ['<?php class B{public int $value=4;}function target(){return (new B)?->value;}',4];
    }

}

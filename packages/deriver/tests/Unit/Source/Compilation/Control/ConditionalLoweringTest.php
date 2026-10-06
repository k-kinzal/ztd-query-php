<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Compilation\Control;

use Deriver\Source\Compilation\Control\ConditionalLowering;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConditionalLowering::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\CatchTarget::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Allocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Constant\ClassNames::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\Handler::class)]
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
#[UsesClass(\Deriver\Evaluation\Model\StateStorage::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Address::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Path::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Reader::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
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
#[UsesClass(\Deriver\Source\Compilation\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\MatchLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
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
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Increment::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
        $l = \Tests\Fake\SourceFixture::lowering();
        $node = new \PhpParser\Node\Expr\BinaryOp\BooleanAnd(new \PhpParser\Node\Expr\Variable('flag'), new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('effect')));
        (new ConditionalLowering($l))->binary($node);
        self::assertSame(['local', 'read'], array_column($l->graph->instructions[0], 'operation'));
        self::assertSame(['constant', 'call-prepare', 'invoke', 'cast'], array_column($l->graph->instructions[1], 'operation'));
        self::assertSame(false, $l->graph->instructions[2][0]->constant?->native());
    }
    public function testSilentRecordsAnExistenceProbeWithoutAnOrdinaryRead(): void
    {
        $l = \Tests\Fake\SourceFixture::lowering();
        (new ConditionalLowering($l))->silent(new \PhpParser\Node\Expr\Variable('missing'), true);
        self::assertSame(['local', 'read-silent'], array_column($l->graph->instructions[0], 'operation'));
        self::assertTrue($l->graph->instructions[0][1]->attributes['existence']);
    }
    public function testCoalesceAssignEvaluatesTheAddressOnce(): void
    {
        $l = \Tests\Fake\SourceFixture::lowering();
        $node = new \PhpParser\Node\Expr\AssignOp\Coalesce(new \PhpParser\Node\Expr\Variable('x'), new \PhpParser\Node\Scalar\Int_(3));
        (new ConditionalLowering($l))->coalesceAssign($node);
        self::assertSame(['local', 'read-silent', 'not-null'], array_column($l->graph->instructions[0], 'operation'));
        self::assertSame([], $l->graph->instructions[1]);
        self::assertSame(['constant', 'write'], array_column($l->graph->instructions[2], 'operation'));
    }
    public function testChoiceLinksEachPhiOperandToItsBranch(): void
    {
        $l = \Tests\Fake\SourceFixture::lowering();
        (new ConditionalLowering($l))->choice(new \PhpParser\Node\Expr\Variable('flag'), 'condition', static fn (): string => 'true-value', static fn (): string => 'false-value');
        self::assertSame([1, 2], $l->graph->terminators[0]->targets);
        self::assertSame(['true-value', 'false-value', 'condition'], $l->graph->instructions[3][0]->operands);
        self::assertSame(['left' => 1, 'right' => 2], $l->graph->instructions[3][0]->attributes);
    }
    public function testIssetExpressionDelaysLaterOperandsUntilTheFirstIsPresent(): void
    {
        $l = \Tests\Fake\SourceFixture::lowering();
        (new ConditionalLowering($l))->issetExpression(new \PhpParser\Node\Expr\Isset_([new \PhpParser\Node\Expr\Variable('a'), new \PhpParser\Node\Expr\Variable('b')]), 0);
        self::assertSame('a', $l->graph->instructions[0][0]->name);
        self::assertSame('b', $l->graph->instructions[1][0]->name);
        self::assertSame(false, $l->graph->instructions[2][0]->constant?->native());
    }
    public function testMatchExpressionIncludesAnUnmatchedException(): void
    {
        $l = \Tests\Fake\SourceFixture::lowering();
        (new ConditionalLowering($l))->matchExpression(new \PhpParser\Node\Expr\Match_(new \PhpParser\Node\Scalar\Int_(1), []));
        self::assertSame('raise', $l->graph->instructions[0][1]->operation);
        self::assertSame('UnhandledMatchError', $l->graph->instructions[0][1]->name);
    }

    public function testLowerBuildsLazyFullTernaryBranches(): void
    {
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $node = new \PhpParser\Node\Expr\Ternary(new \PhpParser\Node\Scalar\Int_(1), new \PhpParser\Node\Scalar\Int_(2), new \PhpParser\Node\Scalar\Int_(3));
        $result = (new ConditionalLowering($lowering))->lower($node);
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
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $node = new \PhpParser\Node\Expr\Ternary(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('once')), null, new \PhpParser\Node\Scalar\Int_(3));
        (new ConditionalLowering($lowering))->lower($node);
        self::assertSame(['constant','call-prepare','invoke'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame([], $lowering->graph->instructions[1]);
        self::assertSame(3, $lowering->graph->instructions[2][0]->constant?->native());
        self::assertSame($lowering->graph->instructions[0][2]->result, $lowering->graph->instructions[3][0]->operands[0]);
    }
    public function testLowerBuildsAnEmptyProbeFromASilentRead(): void
    {
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $node = new \PhpParser\Node\Expr\Empty_(new \PhpParser\Node\Expr\Variable('missing'));
        $result = (new ConditionalLowering($lowering))->lower($node);
        self::assertSame(['local','read-silent','unary'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('Expr_BooleanNot', $lowering->graph->instructions[0][2]->name);
        self::assertSame([$lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame($lowering->graph->instructions[0][2]->result, $result);
    }
    public function testLowerRetainsAnExplicitBoundaryForAnUnexpectedExpression(): void
    {
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $result = (new ConditionalLowering($lowering))->lower(new \PhpParser\Node\Expr\Variable('unexpected'));
        self::assertSame('unsupported', $lowering->graph->instructions[0][0]->operation);
        self::assertSame('Expr_Variable', $lowering->graph->instructions[0][0]->name);
        self::assertSame($lowering->graph->instructions[0][0]->result, $result);
    }
    public function testSilentMarksComputedOffsetExistenceWithoutAllocatingAnAddress(): void
    {
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $node = new \PhpParser\Node\Expr\ArrayDimFetch(new \PhpParser\Node\Expr\Array_([]), new \PhpParser\Node\Scalar\Int_(0));
        $result = (new ConditionalLowering($lowering))->silent($node, true);
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

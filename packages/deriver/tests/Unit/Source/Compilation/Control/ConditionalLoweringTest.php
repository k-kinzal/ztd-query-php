<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Compilation\Control;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\CatchTarget;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\Allocation;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\MethodInvocation;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Creation;
use Deriver\Evaluation\Call\Preparation\Methods;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Constant\ClassNames;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionChain;
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\Handler;
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
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Offset\Address;
use Deriver\Evaluation\Offset\Path;
use Deriver\Evaluation\Offset\Reader;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\ConstantTransfer;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\ObjectAccess;
use Deriver\Evaluation\Transfer\PropertyAccessCheck;
use Deriver\Evaluation\Transfer\PropertyLookup;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Evaluation\Transfer\PropertyTransfer;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Provider\DispatchDecision;
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
use Deriver\Source\Compilation\AggregateLowering;
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\Control\ConditionalLowering;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\Control\ExceptionLowering;
use Deriver\Source\Compilation\Control\MatchLowering;
use Deriver\Source\Compilation\EffectInspection;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\ConstantSignatures;
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
use Deriver\Value\Arrays;
use Deriver\Value\Comparison;
use Deriver\Value\Identity;
use Deriver\Value\Increment;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConditionalLowering::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(CatchTarget::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Allocation::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Creation::class)]
#[UsesClass(Methods::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ClassNames::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionChain::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(Handler::class)]
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
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Address::class)]
#[UsesClass(Path::class)]
#[UsesClass(Reader::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(ConstantTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PropertyTransfer::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(DispatchDecision::class)]
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
#[UsesClass(AggregateLowering::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(MatchLowering::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
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
#[UsesClass(Arrays::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Increment::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
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

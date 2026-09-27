<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Compilation;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\CatchTarget;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\Allocation;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Creation;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
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
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
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
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\Control\ExceptionLowering;
use Deriver\Source\Compilation\Control\StaticLowering;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
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
use Deriver\Value\Arrays;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SourceFixture;

#[CoversClass(StatementLowering::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(CatchTarget::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Allocation::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Creation::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
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
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
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
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(StaticLowering::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
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
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class StatementLoweringTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testLowerPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$x="before";try{$x="after";throw new RuntimeException();$x="wrong";}catch(RuntimeException $e){return $x;}}');
        self::assertSame('after', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testConditionalKeepsReturnsSeparateFromTheCommonContinuation(): void
    {
        $l = SourceFixture::lowering();
        $node = new Stmt\If_(new Expr\Variable('flag'), ['stmts' => [new Stmt\Return_(new Int_(1))]]);
        (new StatementLowering($l))->conditional($node);
        self::assertSame('return', $l->graph->terminators[2]->kind);
        self::assertSame('jump', $l->graph->terminators[3]->kind);
        self::assertSame([1], $l->graph->terminators[3]->targets);
    }
    public function testOtherUnsetsTheStorageLocationWithoutReadingIt(): void
    {
        $l = SourceFixture::lowering();
        (new StatementLowering($l))->other(new Stmt\Unset_([new Expr\Variable('x')]));
        self::assertSame(['local', 'unset'], array_column($l->graph->instructions[0], 'operation'));
    }

    #[DataProvider('providerIgnoredDeclarations')]
    public function testOtherLeavesDeclarationsWithoutRuntimeEffectsUnemitted(Stmt $node): void
    {
        $lowering = SourceFixture::lowering();
        (new StatementLowering($lowering))->other($node);
        self::assertSame([0 => []], $lowering->graph->instructions);
        self::assertSame([], $lowering->graph->terminators);
        self::assertSame(0, $lowering->graph->current);
    }

    /**
     * @return iterable<string,array{Stmt}>
     */
    public static function providerIgnoredDeclarations(): iterable
    {
        yield 'constant' => [new Stmt\Const_([new \PhpParser\Node\Const_('VALUE', new Int_(1))])];
        yield 'empty' => [new Stmt\Nop()];
        yield 'function' => [new Stmt\Function_('declared')];
        yield 'class' => [new Stmt\Class_('Declared')];
        yield 'interface' => [new Stmt\Interface_('Declared')];
        yield 'trait' => [new Stmt\Trait_('Declared')];
        yield 'use' => [new Stmt\Use_([new \PhpParser\Node\UseItem(new Name('A'))])];
        yield 'group use' => [new Stmt\GroupUse(new Name('Prefix'), [new \PhpParser\Node\UseItem(new Name('A'))])];
        yield 'declaration without a body' => [new Stmt\Declare_([new \PhpParser\Node\DeclareItem('strict_types', new Int_(1))])];
    }

    #[DataProvider('providerNestedStatements')]
    public function testOtherExecutesNamespaceAndDeclareBodiesInSourceOrder(Stmt $node): void
    {
        $lowering = SourceFixture::lowering();
        (new StatementLowering($lowering))->other($node);
        self::assertSame(['constant','constant'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame(17, $lowering->graph->instructions[0][0]->constant?->literal);
        self::assertSame(29, $lowering->graph->instructions[0][1]->constant?->literal);
        self::assertSame([], $lowering->graph->terminators);
    }

    /**
     * @return iterable<string,array{Stmt}>
     */
    public static function providerNestedStatements(): iterable
    {
        $body = [new Stmt\Expression(new Int_(17)),new Stmt\Expression(new Int_(29))];
        yield 'namespace' => [new Stmt\Namespace_(new Name('Scope'), $body)];
        yield 'declare' => [new Stmt\Declare_([new \PhpParser\Node\DeclareItem('ticks', new Int_(1))], $body)];
    }

    /**
     * @param list<string> $operations Expected memory instructions
     */
    #[DataProvider('providerStorageDeclarations')]
    public function testOtherEvaluatesEachStorageAddressBeforeApplyingItsEffect(Stmt $node, array $operations): void
    {
        $lowering = SourceFixture::lowering();
        (new StatementLowering($lowering))->other($node);
        $instructions = $lowering->graph->instructions[0];
        self::assertSame($operations, array_column($instructions, 'operation'));
        self::assertSame('first', $instructions[0]->name);
        self::assertSame('second', $instructions[2]->name);
        self::assertSame(['r0'], $instructions[1]->operands);
        self::assertSame(['r2'], $instructions[3]->operands);
    }

    /**
     * @return iterable<string,array{Stmt,list<string>}>
     */
    public static function providerStorageDeclarations(): iterable
    {
        $variables = [new Expr\Variable('first'),new Expr\Variable('second')];
        yield 'unset' => [new Stmt\Unset_($variables),['local','unset','local','unset']];
        yield 'global' => [new Stmt\Global_($variables),['local','global','local','global']];
    }

    public function testOtherInitializesEveryFunctionStaticInItsOwnConditionalBlock(): void
    {
        $lowering = SourceFixture::lowering();
        $node = new Stmt\Static_([new \PhpParser\Node\StaticVar(new Expr\Variable('first'), new Int_(17)),new \PhpParser\Node\StaticVar(new Expr\Variable('second'), new Int_(29))]);
        (new StatementLowering($lowering))->other($node);
        self::assertSame(['local','static-initialized'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame(['local','static-initialized'], array_column($lowering->graph->instructions[2], 'operation'));
        self::assertSame('first', $lowering->graph->instructions[0][0]->name);
        self::assertSame('second', $lowering->graph->instructions[2][0]->name);
        self::assertSame(17, $lowering->graph->instructions[1][0]->constant?->literal);
        self::assertSame(29, $lowering->graph->instructions[3][0]->constant?->literal);
        self::assertSame('static-local', $lowering->graph->instructions[1][1]->operation);
        self::assertSame('static-local', $lowering->graph->instructions[3][1]->operation);
        self::assertSame(4, $lowering->graph->current);
    }

    public function testOtherMarksUnsupportedStatementsAtTheirOwnSourceRange(): void
    {
        $lowering = SourceFixture::lowering();
        $node = new Stmt\Echo_([new Int_(17)], ['startFilePos' => 6,'endFilePos' => 10]);
        (new StatementLowering($lowering))->other($node);
        self::assertCount(1, $lowering->graph->instructions[0]);
        $instruction = $lowering->graph->instructions[0][0];
        self::assertSame('unsupported', $instruction->operation);
        self::assertSame('Stmt_Echo', $instruction->name);
        self::assertSame(6, $instruction->source->start);
        self::assertSame(11, $instruction->source->end);
    }

    public function testLowerBareReturnsCompleteWithoutAnImplicitValueInstruction(): void
    {
        $lowering = SourceFixture::lowering();
        (new StatementLowering($lowering))->lower(new Stmt\Return_());
        self::assertSame([0 => []], $lowering->graph->instructions);
        self::assertSame('return', $lowering->graph->terminators[0]->kind);
        self::assertSame('', $lowering->graph->terminators[0]->operand);
    }

    public function testLowerReferenceReturnsExposeTheCellWithoutReadingIt(): void
    {
        $initial = SourceFixture::lowering();
        $lowering = new Lowering($initial->graph, $initial->index, 'target', returnsByReference:true);
        (new StatementLowering($lowering))->lower(new Stmt\Return_(new Expr\Variable('value')));
        self::assertSame(['local','reference'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('value', $lowering->graph->instructions[0][0]->name);
        self::assertSame(['r0'], $lowering->graph->instructions[0][1]->operands);
        self::assertSame('return', $lowering->graph->terminators[0]->kind);
        self::assertSame('r1', $lowering->graph->terminators[0]->operand);
    }

    public function testConditionalChecksElseifOnlyAfterAnEarlierFalseBranch(): void
    {
        $lowering = SourceFixture::lowering();
        $node = new Stmt\If_(new Expr\Variable('first'), ['stmts' => [new Stmt\Expression(new Int_(11))],'elseifs' => [new Stmt\ElseIf_(new Expr\Variable('second'), [new Stmt\Expression(new Int_(22))])],'else' => new Stmt\Else_([new Stmt\Expression(new Int_(33))])]);
        (new StatementLowering($lowering))->conditional($node);
        self::assertSame(1, $lowering->graph->current);
        self::assertSame('first', $lowering->graph->instructions[0][0]->name);
        self::assertSame('second', $lowering->graph->instructions[3][0]->name);
        self::assertSame('branch', $lowering->graph->terminators[0]->kind);
        self::assertSame([2,3], $lowering->graph->terminators[0]->targets);
        self::assertSame('branch', $lowering->graph->terminators[3]->kind);
        self::assertSame([4,5], $lowering->graph->terminators[3]->targets);
        self::assertSame(11, $lowering->graph->instructions[2][0]->constant?->literal);
        self::assertSame(22, $lowering->graph->instructions[4][0]->constant?->literal);
        self::assertSame(33, $lowering->graph->instructions[5][0]->constant?->literal);
        self::assertSame([1], $lowering->graph->terminators[2]->targets);
        self::assertSame([1], $lowering->graph->terminators[4]->targets);
        self::assertSame([1], $lowering->graph->terminators[5]->targets);
    }
}

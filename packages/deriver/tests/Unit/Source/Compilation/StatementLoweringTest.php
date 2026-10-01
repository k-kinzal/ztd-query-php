<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Compilation;

use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
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
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\CatchTarget::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Allocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
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
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
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
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\StaticLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(Lowering::class)]
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
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(\Deriver\Value\Term::class)]
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
        $node = new Stmt\Goto_(new \PhpParser\Node\Identifier('label'), ['startFilePos' => 6,'endFilePos' => 10]);
        (new StatementLowering($lowering))->other($node);
        self::assertCount(1, $lowering->graph->instructions[0]);
        $instruction = $lowering->graph->instructions[0][0];
        self::assertSame('unsupported', $instruction->operation);
        self::assertSame('Stmt_Goto', $instruction->name);
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

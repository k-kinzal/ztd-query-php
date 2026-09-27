<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php;

use Deriver\Internal\Frontend\Php\StatementLowering;
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
use Tests\Fake\FrontendFixture;

#[CoversClass(StatementLowering::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\StaticLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\Allocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
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
        $l = FrontendFixture::lowering();
        $node = new Stmt\If_(new Expr\Variable('flag'), ['stmts' => [new Stmt\Return_(new Int_(1))]]);
        (new StatementLowering($l))->conditional($node);
        self::assertSame('return', $l->graph->terminators[2]->kind);
        self::assertSame('jump', $l->graph->terminators[3]->kind);
        self::assertSame([1], $l->graph->terminators[3]->targets);
    }
    public function testOtherUnsetsTheStorageLocationWithoutReadingIt(): void
    {
        $l = FrontendFixture::lowering();
        (new StatementLowering($l))->other(new Stmt\Unset_([new Expr\Variable('x')]));
        self::assertSame(['local', 'unset'], array_column($l->graph->instructions[0], 'operation'));
    }

    #[DataProvider('providerIgnoredDeclarations')]
    public function testOtherLeavesDeclarationsWithoutRuntimeEffectsUnemitted(Stmt $node): void
    {
        $lowering = FrontendFixture::lowering();
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
        $lowering = FrontendFixture::lowering();
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
        $lowering = FrontendFixture::lowering();
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
        $lowering = FrontendFixture::lowering();
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
        $lowering = FrontendFixture::lowering();
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
        $lowering = FrontendFixture::lowering();
        (new StatementLowering($lowering))->lower(new Stmt\Return_());
        self::assertSame([0 => []], $lowering->graph->instructions);
        self::assertSame('return', $lowering->graph->terminators[0]->kind);
        self::assertSame('', $lowering->graph->terminators[0]->operand);
    }

    public function testLowerReferenceReturnsExposeTheCellWithoutReadingIt(): void
    {
        $initial = FrontendFixture::lowering();
        $lowering = new \Deriver\Internal\Frontend\Php\Lowering($initial->graph, $initial->index, 'target', returnsByReference:true);
        (new StatementLowering($lowering))->lower(new Stmt\Return_(new Expr\Variable('value')));
        self::assertSame(['local','reference'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('value', $lowering->graph->instructions[0][0]->name);
        self::assertSame(['r0'], $lowering->graph->instructions[0][1]->operands);
        self::assertSame('return', $lowering->graph->terminators[0]->kind);
        self::assertSame('r1', $lowering->graph->terminators[0]->operand);
    }

    public function testConditionalChecksElseifOnlyAfterAnEarlierFalseBranch(): void
    {
        $lowering = FrontendFixture::lowering();
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

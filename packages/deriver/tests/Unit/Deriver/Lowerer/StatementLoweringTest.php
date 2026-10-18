<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Lowerer;

use Deriver\Deriver;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Extensions;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Call\Allocation;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
use Deriver\Deriver\Evaluator\Execution\Call\CallExecutor;
use Deriver\Deriver\Evaluator\Execution\Call\Creation\Access;
use Deriver\Deriver\Evaluator\Execution\Call\Member\Invocation;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Invocation as NativeInvocation;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Properties;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Signatures;
use Deriver\Deriver\Evaluator\Execution\Call\ParameterBinding;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Creation;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Resolution;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Target;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Transfer;
use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Call\TypeCheck;
use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Completion\Assessment;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Control\ExceptionChain;
use Deriver\Deriver\Evaluator\Execution\Control\ExceptionMatch;
use Deriver\Deriver\Evaluator\Execution\Control\Handler;
use Deriver\Deriver\Evaluator\Execution\Control\ObservationLimit;
use Deriver\Deriver\Evaluator\Execution\Control\StateJoin;
use Deriver\Deriver\Evaluator\Execution\Control\Unwinding;
use Deriver\Deriver\Evaluator\Execution\Demand\Cell;
use Deriver\Deriver\Evaluator\Execution\Demand\Components;
use Deriver\Deriver\Evaluator\Execution\Demand\Discovery;
use Deriver\Deriver\Evaluator\Execution\Demand\Key;
use Deriver\Deriver\Evaluator\Execution\Demand\Table;
use Deriver\Deriver\Evaluator\Execution\Dependencies;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Execution\InstructionTransfer;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Materialization;
use Deriver\Deriver\Evaluator\Execution\Memory\Memory;
use Deriver\Deriver\Evaluator\Execution\Memory\ReferenceConstraint;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\SlotReference;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\StateStorage;
use Deriver\Deriver\Evaluator\Execution\Memory\StorageCapture;
use Deriver\Deriver\Evaluator\Execution\ObservationCollector;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\CompletionRecord;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Invocation as SummaryInvocation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Scalar\Arrays;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Execution\DerivationResult;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Execution\ResultAssessment;
use Deriver\Deriver\Identity\QueryEncoding;
use Deriver\Deriver\Identity\ValueGraph;
use Deriver\Deriver\Lowerer\AssignmentLowering;
use Deriver\Deriver\Lowerer\CallLowering;
use Deriver\Deriver\Lowerer\Control\DestructuringLowering;
use Deriver\Deriver\Lowerer\Control\ExceptionLowering;
use Deriver\Deriver\Lowerer\Control\GotoLowering;
use Deriver\Deriver\Lowerer\Control\StaticLowering;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Metadata\PropertyDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\CatchTarget;
use Deriver\Deriver\Lowerer\Graph\ExceptionRegion;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Literal;
use Deriver\Deriver\Lowerer\Graph\Parameter;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\Graph\TerminatorKind;
use Deriver\Deriver\Lowerer\Hierarchy\Builtins;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;
use Deriver\Deriver\Lowerer\InstructionEmitter;
use Deriver\Deriver\Lowerer\Lowerer;
use Deriver\Deriver\Lowerer\LoweringScope;
use Deriver\Deriver\Lowerer\StatementLowering;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Resolver\AssignmentPatterns;
use Deriver\Deriver\Resolver\ClassScope;
use Deriver\Deriver\Resolver\ExpressionValidation;
use Deriver\Deriver\Resolver\MagicContext;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Selection\QueryValidation;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\LineMap;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use PhpParser\Node\Const_ as NodeConst_;
use PhpParser\Node\DeclareItem;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\StaticVar;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Global_;
use PhpParser\Node\Stmt\Goto_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\InlineHTML;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Label;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Static_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(StatementLowering::class)]
#[UsesClass(GotoLowering::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
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
#[UsesClass(NativeInvocation::class)]
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
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Evaluator::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(SummaryInvocation::class)]
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
#[UsesClass(ProviderExpansion::class)]
#[UsesClass(Evaluators::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(DeriverConfig::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(Sources::class)]
#[UsesClass(Path::class)]
#[UsesClass(Snapshot::class)]
#[UsesClass(Source::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(StaticLowering::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Literal::class)]
#[Small]
final class StatementLoweringTest extends TestCase
{
    public function testLowerPreservesTheSemanticContract(): void
    {
        $source = '<?php function target(){$x="before";try{$x="after";throw new RuntimeException();$x="wrong";}catch(RuntimeException $e){return $x;}}';
        $symbol = 'target';
        $input = new Sources([new Source('fixture.php', $source)]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        self::assertSame('after', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testConditionalKeepsReturnsSeparateFromTheCommonContinuation(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $l = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $node = new If_(new Variable('flag'), ['stmts' => [new Return_(new Int_(1))]]);
        (new StatementLowering($l))->conditional($node);
        self::assertSame(TerminatorKind::Return, $l->graph->terminators[2]->kind);
        self::assertSame(TerminatorKind::Jump, $l->graph->terminators[3]->kind);
        self::assertSame([1], $l->graph->terminators[3]->targets);
    }
    public function testOtherUnsetsTheStorageLocationWithoutReadingIt(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $l = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        (new StatementLowering($l))->other(new Unset_([new Variable('x')]));
        self::assertSame(['local', 'unset'], array_column($l->graph->instructions[0], 'operation'));
    }
    #[DataProvider('providerIgnoredDeclarations')]
    public function testOtherLeavesDeclarationsWithoutRuntimeEffectsUnemitted(Stmt $node): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
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
        yield 'constant' => [new Const_([new NodeConst_('VALUE', new Int_(1))])];
        yield 'empty' => [new Nop()];
        yield 'function' => [new Function_('declared')];
        yield 'class' => [new Class_('Declared')];
        yield 'interface' => [new Interface_('Declared')];
        yield 'trait' => [new Trait_('Declared')];
        yield 'use' => [new Use_([new UseItem(new Name('A'))])];
        yield 'group use' => [new GroupUse(new Name('Prefix'), [new UseItem(new Name('A'))])];
        yield 'declaration without a body' => [new Declare_([new DeclareItem('strict_types', new Int_(1))])];
    }
    #[DataProvider('providerNestedStatements')]
    public function testOtherExecutesNamespaceAndDeclareBodiesInSourceOrder(Stmt $node): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        (new StatementLowering($lowering))->other($node);
        self::assertSame(['constant', 'constant'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame(17, $lowering->graph->instructions[0][0]->constant?->literal);
        self::assertSame(29, $lowering->graph->instructions[0][1]->constant?->literal);
        self::assertSame([], $lowering->graph->terminators);
    }
    /**
     * @return iterable<string,array{Stmt}>
     */
    public static function providerNestedStatements(): iterable
    {
        $body = [new Expression(new Int_(17)), new Expression(new Int_(29))];
        yield 'namespace' => [new Namespace_(new Name('Scope'), $body)];
        yield 'declare' => [new Declare_([new DeclareItem('ticks', new Int_(1))], $body)];
    }
    /**
     * @param list<string> $operations Expected memory instructions
     */
    #[DataProvider('providerStorageDeclarations')]
    public function testOtherEvaluatesEachStorageAddressBeforeApplyingItsEffect(Stmt $node, array $operations): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
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
        $variables = [new Variable('first'), new Variable('second')];
        yield 'unset' => [new Unset_($variables), ['local', 'unset', 'local', 'unset']];
        yield 'global' => [new Global_($variables), ['local', 'global', 'local', 'global']];
    }
    public function testOtherInitializesEveryFunctionStaticInItsOwnConditionalBlock(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $node = new Static_([new StaticVar(new Variable('first'), new Int_(17)), new StaticVar(new Variable('second'), new Int_(29))]);
        (new StatementLowering($lowering))->other($node);
        self::assertSame(['local', 'static-initialized'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame(['local', 'static-initialized'], array_column($lowering->graph->instructions[2], 'operation'));
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
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $node = new InlineHTML('html', ['startFilePos' => 6, 'endFilePos' => 10]);
        (new StatementLowering($lowering))->other($node);
        self::assertCount(1, $lowering->graph->instructions[0]);
        $instruction = $lowering->graph->instructions[0][0];
        self::assertSame('unsupported', $instruction->operation);
        self::assertSame('Stmt_InlineHTML', $instruction->name);
        self::assertSame(6, $instruction->source->start);
        self::assertSame(11, $instruction->source->end);
    }
    public function testLowerStartsALabelBlockAndDefersTheGotoTarget(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        (new StatementLowering($lowering))->lower(new Label('again'));
        (new StatementLowering($lowering))->lower(new Goto_('again'));
        self::assertSame([], $lowering->graph->instructions[1]);
        self::assertSame([0 => TerminatorKind::Jump, 1 => TerminatorKind::Residual], array_map(static fn ($terminator) => $terminator->kind, $lowering->graph->terminators));
        self::assertSame(1, $lowering->graph->labels['again']['block'] ?? null);
        self::assertSame(1, $lowering->graph->gotos[0]['block']);
    }
    public function testLowerBareReturnsCompleteWithoutAnImplicitValueInstruction(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        (new StatementLowering($lowering))->lower(new Return_());
        self::assertSame([0 => []], $lowering->graph->instructions);
        self::assertSame(TerminatorKind::Return, $lowering->graph->terminators[0]->kind);
        self::assertSame('', $lowering->graph->terminators[0]->operand);
    }
    public function testLowerReferenceReturnsExposeTheCellWithoutReadingIt(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $initial = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $lowering = new LoweringScope($initial->graph, $initial->index, 'target', returnsByReference: true);
        (new StatementLowering($lowering))->lower(new Return_(new Variable('value')));
        self::assertSame(['local', 'reference'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('value', $lowering->graph->instructions[0][0]->name);
        self::assertSame(['r0'], $lowering->graph->instructions[0][1]->operands);
        self::assertSame(TerminatorKind::Return, $lowering->graph->terminators[0]->kind);
        self::assertSame('r1', $lowering->graph->terminators[0]->operand);
    }
    public function testConditionalChecksElseifOnlyAfterAnEarlierFalseBranch(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $node = new If_(new Variable('first'), ['stmts' => [new Expression(new Int_(11))], 'elseifs' => [new ElseIf_(new Variable('second'), [new Expression(new Int_(22))])], 'else' => new Else_([new Expression(new Int_(33))])]);
        (new StatementLowering($lowering))->conditional($node);
        self::assertSame(1, $lowering->graph->current);
        self::assertSame('first', $lowering->graph->instructions[0][0]->name);
        self::assertSame('second', $lowering->graph->instructions[3][0]->name);
        self::assertSame(TerminatorKind::Branch, $lowering->graph->terminators[0]->kind);
        self::assertSame([2, 3], $lowering->graph->terminators[0]->targets);
        self::assertSame(TerminatorKind::Branch, $lowering->graph->terminators[3]->kind);
        self::assertSame([4, 5], $lowering->graph->terminators[3]->targets);
        self::assertSame(11, $lowering->graph->instructions[2][0]->constant?->literal);
        self::assertSame(22, $lowering->graph->instructions[4][0]->constant?->literal);
        self::assertSame(33, $lowering->graph->instructions[5][0]->constant?->literal);
        self::assertSame([1], $lowering->graph->terminators[2]->targets);
        self::assertSame([1], $lowering->graph->terminators[4]->targets);
        self::assertSame([1], $lowering->graph->terminators[5]->targets);
    }
}

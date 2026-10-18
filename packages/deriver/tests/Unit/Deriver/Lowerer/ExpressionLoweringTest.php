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
use Deriver\Deriver\Evaluator\Call\Provider\DispatchDecision;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
use Deriver\Deriver\Evaluator\Execution\Call\CallExecutor;
use Deriver\Deriver\Evaluator\Execution\Call\CallResolution;
use Deriver\Deriver\Evaluator\Execution\Call\Member\Invocation;
use Deriver\Deriver\Evaluator\Execution\Call\MethodInvocation;
use Deriver\Deriver\Evaluator\Execution\Call\ParameterBinding;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Methods;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Resolution;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Target;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Transfer;
use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Call\TypeCheck;
use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Completion\Assessment;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Execution\Constant\ClassNames;
use Deriver\Deriver\Evaluator\Execution\Context;
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
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\SlotReference;
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
use Deriver\Deriver\Evaluator\Scalar\Arithmetic;
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
use Deriver\Deriver\Lowerer\AggregateLowering;
use Deriver\Deriver\Lowerer\AssignmentLowering;
use Deriver\Deriver\Lowerer\CallLowering;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\ConstantSignatures;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\EffectInspection;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Literal;
use Deriver\Deriver\Lowerer\Graph\Parameter;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\Graph\TerminatorKind;
use Deriver\Deriver\Lowerer\Hierarchy\Access;
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
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_ as ExprArray_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\BitwiseNot;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Cast\Array_;
use PhpParser\Node\Expr\Cast\Bool_;
use PhpParser\Node\Expr\Cast\Double;
use PhpParser\Node\Expr\Cast\Int_ as CastInt_;
use PhpParser\Node\Expr\Cast\Object_;
use PhpParser\Node\Expr\Cast\String_ as CastString_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Clone_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\Print_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Scalar\MagicConst\Function_;
use PhpParser\Node\Scalar\MagicConst\Line;
use PhpParser\Node\Scalar\MagicConst\Method;
use PhpParser\Node\Scalar\MagicConst\Namespace_;
use PhpParser\Node\Scalar\String_;
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
#[CoversClass(ExpressionLowering::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Access::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(MethodInvocation::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Methods::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ClassNames::class)]
#[UsesClass(Context::class)]
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
#[UsesClass(StorageCapture::class)]
#[UsesClass(DispatchDecision::class)]
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
#[UsesClass(AggregateLowering::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Literal::class)]
#[Small]
final class ExpressionLoweringTest extends TestCase
{
    public function testLowerPreservesTheSemanticContract(): void
    {
        $source = '<?php function target(){return [2=>"left","x"=>1]+[2=>"right",3=>"new"];}';
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
        self::assertSame([2 => 'left', 'x' => 1, 3 => 'new'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testOperationReadsAnAddressableVariable(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $l = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        (new ExpressionLowering($l))->operation(new Variable('x'));
        self::assertSame(['local', 'read'], array_column($l->graph->instructions[0], 'operation'));
    }
    public function testBinaryMarksInteractingEffectsWithUnspecifiedOrder(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $l = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $node = new Plus(new PostInc(new Variable('x')), new Variable('x'));
        (new ExpressionLowering($l))->binary($node);
        self::assertSame(['evaluation-order'], array_column($l->graph->instructions[0], 'operation'));
        self::assertCount(4, $l->graph->instructions);
        self::assertSame(TerminatorKind::Branch, $l->graph->terminators[0]->kind);
    }
    public function testOtherMakesEvalASymbolTableBoundary(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $l = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        (new ExpressionLowering($l))->other(new Eval_(new String_('return 1;')));
        self::assertSame('symbol-table-boundary', $l->graph->instructions[0][1]->operation);
    }
    public function testComputedRegistersAClosureWithoutLoweringItsBody(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $l = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        (new ExpressionLowering($l))->computed(new ArrowFunction(['expr' => new FuncCall(new Name('sideEffect'))]));
        self::assertSame(['closure'], array_column($l->graph->instructions[0], 'operation'));
        self::assertSame(0, $l->index->graphCount());
    }
    public function testMagicUsesLexicalNamesForSeparateDefaultGraphs(): void
    {
        $source = '<?php class Box{static function value($name=__METHOD__){return $name;}}function target(){return Box::value();}';
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
        self::assertSame('Box::value', $result->normalOutcomes[0]->values['return']->native());
    }
    #[DataProvider('providerLiteralNodes')]
    public function testLowerPreservesLiteralTypesAndBytes(Expr $node, mixed $expected): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $register = (new ExpressionLowering($lowering))->lower($node);
        self::assertCount(1, $lowering->graph->instructions[0]);
        self::assertSame('constant', $lowering->graph->instructions[0][0]->operation);
        self::assertSame($expected, $lowering->graph->instructions[0][0]->constant?->native());
        self::assertSame($register, $lowering->graph->instructions[0][0]->result);
    }
    /**
     * @return iterable<string,array{Expr,mixed}>
     */
    public static function providerLiteralNodes(): iterable
    {
        yield 'integer' => [new Int_(42), 42];
        yield 'float' => [new Float_(2.5), 2.5];
        yield 'string' => [new String_("\x00\xff"), "\x00\xff"];
    }
    public function testLowerRetainsUnresolvedConstantNames(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $register = (new ExpressionLowering($lowering))->lower(new ConstFetch(new Name('EXAMPLE')));
        self::assertSame('constant-fetch', $lowering->graph->instructions[0][0]->operation);
        self::assertSame('EXAMPLE', $lowering->graph->instructions[0][0]->name);
        self::assertSame($register, $lowering->graph->instructions[0][0]->result);
    }
    #[DataProvider('providerLexicalMagic')]
    public function testMagicSelectsOnlyTheMatchingCapturedLexicalName(MagicConst $node, string $key, string $expected): void
    {
        $node->setAttribute('deriver-lexical', [$key => $expected, 'unrelated' => 'wrong']);
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $register = (new ExpressionLowering($lowering))->magic($node);
        self::assertSame('constant', $lowering->graph->instructions[0][0]->operation);
        self::assertSame($expected, $lowering->graph->instructions[0][0]->constant?->native());
        self::assertSame($register, $lowering->graph->instructions[0][0]->result);
    }
    /**
     * @return iterable<string,array{MagicConst,string,string}>
     */
    public static function providerLexicalMagic(): iterable
    {
        yield 'function' => [new Function_(), 'function', 'Example\run'];
        yield 'method' => [new Method(), 'method', 'Example\Box::run'];
        yield 'namespace' => [new Namespace_(), 'namespace', 'Example'];
    }
    public function testLowerResolvesSourceLineWithoutLexicalMetadata(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $node = new Line(['startLine' => 7]);
        $node->setAttribute('deriver-lexical', ['function' => 'irrelevant']);
        $register = (new ExpressionLowering($lowering))->lower($node);
        self::assertSame('constant', $lowering->graph->instructions[0][0]->operation);
        self::assertSame(7, $lowering->graph->instructions[0][0]->constant?->native());
        self::assertSame($register, $lowering->graph->instructions[0][0]->result);
    }
    public function testMagicRejectsNonStringLexicalMetadata(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $node = new Function_();
        $node->setAttribute('deriver-lexical', ['function' => 42]);
        (new ExpressionLowering($lowering))->magic($node);
        self::assertSame('magic-constant', $lowering->graph->instructions[0][0]->operation);
        self::assertSame('__FUNCTION__', $lowering->graph->instructions[0][0]->name);
    }
    #[DataProvider('providerUnaryAndCasts')]
    public function testOtherPreservesUnaryAndCastOperations(Expr $node, string $operation, string $name): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $register = (new ExpressionLowering($lowering))->other($node);
        self::assertSame(['constant', $operation], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame($name, $lowering->graph->instructions[0][1]->name);
        self::assertSame([$lowering->graph->instructions[0][0]->result], $lowering->graph->instructions[0][1]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][1]->result);
    }
    /**
     * @return iterable<string,array{Expr,string,string}>
     */
    public static function providerUnaryAndCasts(): iterable
    {
        $value = new Int_(5);
        yield 'boolean not' => [new BooleanNot($value), 'unary', 'Expr_BooleanNot'];
        yield 'unary minus' => [new UnaryMinus($value), 'unary', 'Expr_UnaryMinus'];
        yield 'unary plus' => [new UnaryPlus($value), 'unary', 'Expr_UnaryPlus'];
        yield 'bitwise not' => [new BitwiseNot($value), 'unary', 'Expr_BitwiseNot'];
        yield 'integer cast' => [new CastInt_($value), 'cast', 'Int'];
        yield 'string cast' => [new CastString_($value), 'cast', 'String'];
        yield 'boolean cast' => [new Bool_($value), 'cast', 'Bool'];
        yield 'float cast' => [new Double($value), 'cast', 'Double'];
        yield 'array cast' => [new Array_($value), 'cast', 'Array'];
        yield 'object cast' => [new Object_($value), 'cast', 'Object'];
    }
    public function testOtherPreservesCloneIdentityAsAnExplicitOperation(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $register = (new ExpressionLowering($lowering))->other(new Clone_(new Variable('object')));
        self::assertSame(['local', 'read', 'clone'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame([$lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][2]->result);
    }
    public function testOtherKeepsTheThrowOperandAndItsResultRegister(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $register = (new ExpressionLowering($lowering))->other(new Throw_(new Variable('error')));
        self::assertSame(['local', 'read', 'throw'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame([$lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][2]->result);
    }
    public function testOtherKeepsBothClassConstantNames(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $register = (new ExpressionLowering($lowering))->other(new ClassConstFetch(new Name('Box'), 'VALUE'));
        self::assertSame(['constant', 'constant', 'class-constant'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('Box', $lowering->graph->instructions[0][0]->constant?->native());
        self::assertSame('VALUE', $lowering->graph->instructions[0][1]->constant?->native());
        self::assertSame([$lowering->graph->instructions[0][0]->result, $lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][2]->result);
    }
    public function testOtherKeepsTheInstanceofReceiverAndClass(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $register = (new ExpressionLowering($lowering))->other(new Instanceof_(new Variable('object'), new Name('Box')));
        self::assertSame(['local', 'read', 'constant', 'instanceof'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('Box', $lowering->graph->instructions[0][2]->constant?->native());
        self::assertSame([$lowering->graph->instructions[0][1]->result, $lowering->graph->instructions[0][2]->result], $lowering->graph->instructions[0][3]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][3]->result);
    }
    public function testOtherCapturesIncludeBoundariesWithoutReadingTheFile(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $register = (new ExpressionLowering($lowering))->other(new Include_(new String_('unread.php'), Include_::TYPE_REQUIRE_ONCE));
        self::assertSame(['constant', 'symbol-table-boundary'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('unread.php', $lowering->graph->instructions[0][0]->constant?->native());
        self::assertSame('INCLUDE_SEMANTICS_UNSUPPORTED', $lowering->graph->instructions[0][1]->name);
        self::assertSame([$lowering->graph->instructions[0][0]->result], $lowering->graph->instructions[0][1]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][1]->result);
    }
    public function testComputedPreservesPrintOperandConversion(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $register = (new ExpressionLowering($lowering))->computed(new Print_(new String_('not emitted')));
        self::assertSame(['constant', 'cast', 'print'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('string', $lowering->graph->instructions[0][1]->name);
        self::assertSame($register, $lowering->graph->instructions[0][2]->result);
    }
    public function testComputedReadsOffsetsWithoutCreatingAnAddress(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $register = (new ExpressionLowering($lowering))->computed(new ArrayDimFetch(new ExprArray_([]), new Int_(2)));
        self::assertSame(['constant', 'constant', 'array-read'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame([$lowering->graph->instructions[0][0]->result, $lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame(2, $lowering->graph->instructions[0][1]->constant?->native());
        self::assertSame($register, $lowering->graph->instructions[0][2]->result);
    }
    #[DataProvider('providerClassFetchSyntax')]
    public function testLowerKeepsLiteralClassSyntaxDistinctFromEvaluatedClassValues(string $expression, bool $literal, bool $className): void
    {
        $source = '<?php function target($x,$name){return ' . $expression . ';}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $body = $index->callable('target');
        self::assertNotNull($body);
        $instructions = array_values(array_filter($body->blocks[0]->instructions, static fn (Instruction $instruction): bool => $instruction->operation === 'class-constant'));
        self::assertCount(1, $instructions);
        self::assertSame($literal, $instructions[0]->attributes['literal-class']);
        self::assertSame($className, $instructions[0]->attributes['class-name']);
    }
    /**
     * @return iterable<string,array{string,bool,bool}>
     */
    public static function providerClassFetchSyntax(): iterable
    {
        yield 'literal class name' => ['Missing::class', true, true];
        yield 'dynamic class name' => ['$x::class', false, true];
        yield 'literal class dynamic constant' => ['Box::{$name}', true, false];
        yield 'dynamic class dynamic constant' => ['$x::{$name}', false, false];
        yield 'class keyword case' => ['Box::ClAsS', true, true];
        yield 'ordinary constant' => ['Box::VALUE', true, false];
    }
    #[DataProvider('providerClassOperandSyntax')]
    public function testOtherPreservesWhetherAClassOperandIsLiteral(string $expression, bool $literal): void
    {
        $source = '<?php function target($class,$value){return ' . $expression . ';}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $body = $index->callable('target');
        self::assertNotNull($body);
        $instructions = array_values(array_filter($body->blocks[0]->instructions, static fn (Instruction $instruction): bool => $instruction->operation === 'instanceof'));
        self::assertCount(1, $instructions);
        self::assertSame($literal, $instructions[0]->attributes['literal-class']);
    }
    /**
     * @return iterable<string,array{string,bool}>
     */
    public static function providerClassOperandSyntax(): iterable
    {
        yield '$value instanceof Box' => ['$value instanceof Box', true];
        yield '$value instanceof $class' => ['$value instanceof $class', false];
    }
}

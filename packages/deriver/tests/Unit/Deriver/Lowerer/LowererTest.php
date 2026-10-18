<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Lowerer;

use Deriver\Deriver;
use Deriver\Deriver\Candidate;
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
use Deriver\Deriver\Evaluator\Execution\Call\Allocation;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
use Deriver\Deriver\Evaluator\Execution\Call\CallableCheck;
use Deriver\Deriver\Evaluator\Execution\Call\CallExecutor;
use Deriver\Deriver\Evaluator\Execution\Call\CallResolution;
use Deriver\Deriver\Evaluator\Execution\Call\Creation\Access;
use Deriver\Deriver\Evaluator\Execution\Call\Member\Invocation;
use Deriver\Deriver\Evaluator\Execution\Call\MethodInvocation;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Invocation as NativeInvocation;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Properties;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Signatures;
use Deriver\Deriver\Evaluator\Execution\Call\ParameterBinding;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Arguments;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Creation;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Methods;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Modes;
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
use Deriver\Deriver\Evaluator\Execution\Control\ExceptionChain;
use Deriver\Deriver\Evaluator\Execution\Control\ExceptionMatch;
use Deriver\Deriver\Evaluator\Execution\Control\Handler;
use Deriver\Deriver\Evaluator\Execution\Control\ObservationLimit;
use Deriver\Deriver\Evaluator\Execution\Control\StateJoin;
use Deriver\Deriver\Evaluator\Execution\Control\Unwinding;
use Deriver\Deriver\Evaluator\Execution\Demand\Discovery;
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
use Deriver\Deriver\Evaluator\Execution\Offset\Address;
use Deriver\Deriver\Evaluator\Execution\Offset\Path;
use Deriver\Deriver\Evaluator\Execution\Offset\Reader;
use Deriver\Deriver\Evaluator\Execution\Offset\Transfer as OffsetTransfer;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\CallableTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ObjectAccess;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyAccessCheck;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyLookup;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyReference;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertySlot;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Projection;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\Observation\Observation;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
use Deriver\Deriver\Evaluator\Scalar\Arithmetic;
use Deriver\Deriver\Evaluator\Scalar\Arrays;
use Deriver\Deriver\Evaluator\Scalar\Comparison;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scalar\NumericString;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Execution\DerivationResult;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Execution\ResultAssessment;
use Deriver\Deriver\Identity\QueryEncoding;
use Deriver\Deriver\Identity\ValueGraph;
use Deriver\Deriver\Lowerer\AggregateLowering;
use Deriver\Deriver\Lowerer\AssignmentLowering;
use Deriver\Deriver\Lowerer\CallLowering;
use Deriver\Deriver\Lowerer\Control\DestructuringLowering;
use Deriver\Deriver\Lowerer\Control\ExceptionLowering;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\CallSiteIndex;
use Deriver\Deriver\Lowerer\Declaration\ConstantSignatures;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Metadata\PropertyDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\Declaration\Traits\LexicalConstants;
use Deriver\Deriver\Lowerer\Declaration\Traits\Members;
use Deriver\Deriver\Lowerer\EffectInspection;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\Argument;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\CatchTarget;
use Deriver\Deriver\Lowerer\Graph\ExceptionRegion;
use Deriver\Deriver\Lowerer\Graph\ExpressionRef;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Parameter;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\Hierarchy\Access as HierarchyAccess;
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
use Deriver\Deriver\Publication\CandidateQuery;
use Deriver\Deriver\Resolver\AssignmentPatterns;
use Deriver\Deriver\Resolver\ClassScope;
use Deriver\Deriver\Resolver\ExpressionValidation;
use Deriver\Deriver\Resolver\MagicContext;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Selection\CallObservations;
use Deriver\Deriver\Selection\QueryValidation;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\LineMap;
use Deriver\Deriver\Sources\Path as SourcesPath;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use JsonException;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Function_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use WeakMap;

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(Lowerer::class)]
#[UsesClass(CallObservations::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
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
#[UsesClass(CallableCheck::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(HierarchyAccess::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(MethodInvocation::class)]
#[UsesClass(NativeInvocation::class)]
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
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Evaluator::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Address::class)]
#[UsesClass(Path::class)]
#[UsesClass(Reader::class)]
#[UsesClass(OffsetTransfer::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertyReference::class)]
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
#[UsesClass(ProviderExpansion::class)]
#[UsesClass(Evaluators::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(DeriverConfig::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(Sources::class)]
#[UsesClass(SourcesPath::class)]
#[UsesClass(Snapshot::class)]
#[UsesClass(Source::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ValueQuery::class)]
#[UsesClass(ExpressionRef::class)]
#[UsesClass(Observation::class)]
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
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallSiteIndex::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LexicalConstants::class)]
#[UsesClass(Members::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(NumericString::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Projection::class)]
#[UsesClass(Term::class)]
#[Small]
final class LowererTest extends TestCase
{
    public function testCompilePreservesTheSemanticContract(): void
    {
        $source = '<?php function target() {$x="first";$a=fn()=>$x;$b=function()use(&$x){return $x;};$x="second";return [$a(),$b()];}';
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
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        self::assertSame(['first', 'second'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testParametersCapturesReferenceAndDefaultExpressionSeparately(): void
    {
        $source1 = '<?php function target(int &$n = 2, ...$rest) {}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source1)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $source = $index->declarations['target'];
        self::assertInstanceOf(Function_::class, $source->node);
        $parameters = (new Lowerer($index))->parameters($source->node, $source);
        self::assertTrue($parameters[0]->byReference);
        self::assertNotNull($parameters[0]->default);
        self::assertSame(2, $parameters[0]->default->blocks[0]->instructions[0]->constant?->native());
        self::assertTrue($parameters[1]->variadic);
    }
    public function testExpressionProducesAnIndependentReturnGraph(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $compiler = new Lowerer(new Index($snapshotId, $trees));
        $body = $compiler->expression(new Int_(7), 'fixture.php', 'default:test');
        self::assertSame('default:test', $body->symbol);
        self::assertSame(7, $body->blocks[0]->instructions[0]->constant?->native());
        self::assertSame($body->blocks[0]->instructions[0]->result, $body->blocks[0]->terminator->operand);
    }
    public function testTypePreservesNullableUnionsAndIntersections(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $compiler = new Lowerer(new Index($snapshotId, $trees));
        self::assertSame('int|null', $compiler->type(new NullableType(new Identifier('int'))));
        self::assertSame('A&B', $compiler->type(new IntersectionType([new Name('A'), new Name('B')])));
        self::assertSame('mixed', $compiler->type(null));
    }
    public function testCapturesExcludesArrowParameters(): void
    {
        $arrow = new ArrowFunction(['params' => [new Param(new Variable('x'))], 'expr' => new Plus(new Variable('x'), new Variable('outside'))]);
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        self::assertSame(['outside' => false], (new Lowerer(new Index($snapshotId, $trees)))->captures($arrow));
    }
    public function testFreeVariablesDeduplicatesLexicalReads(): void
    {
        $node = new Plus(new Variable('x'), new Variable('x'));
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        self::assertSame(['x' => false], (new Lowerer(new Index($snapshotId, $trees)))->freeVariables($node));
    }
    public function testCompileOmitsAnExternalImplementationButRetainsItsSignature(): void
    {
        $snapshotId = 'test';
        $input = new Sources([new Source('stub.php', '<?php function sample(int &$value): int {return 99;}', true)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $body = (new Lowerer($index))->compile($index->declarations['sample']);
        self::assertTrue($body->external);
        self::assertTrue($body->parameters[0]->byReference);
        self::assertSame(['external-body'], array_column($body->blocks[0]->instructions, 'operation'));
    }
    /**
     * @param string $source Trusted default initializer fixture
     * @param string $expectedJson Independently observed PHP 8.3 result
     * @throws JsonException If expected fixture data cannot be decoded
     */
    #[DataProvider('providerInitializers')]
    public function testParametersPreservesTheDeclaringScalarModeForNewDefaults(string $source, string $expectedJson): void
    {
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
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @return array<string,array{string,string}>
     */
    public static function providerInitializers(): array
    {
        return ['strict function default' => ['<?php declare(strict_types=1);class Box{function __construct(public int $value){}}function helper($box=new Box("7")){return $box->value;}function target(){try{return helper();}catch(TypeError $e){return "error";}}', '"error"'], 'weak function default' => ['<?php class Box{function __construct(public int $value){}}function helper($box=new Box("7")){return $box->value;}function target(){return helper();}', '7'], 'strict method default' => ['<?php declare(strict_types=1);class Box{function __construct(public int $value){}}class Factory{function make($box=new Box("7")){return $box->value;}}function target(){try{$f=new Factory;return $f->make();}catch(TypeError $e){return "error";}}', '"error"'], 'strict static method default' => ['<?php declare(strict_types=1);class Box{function __construct(public int $value){}}class Factory{static function make($box=new Box("7")){return $box->value;}}function target(){try{return Factory::make();}catch(TypeError $e){return "error";}}', '"error"'], 'strict closure default' => ['<?php declare(strict_types=1);class Box{function __construct(public int $value){}}function target(){$f=function($box=new Box("7")){return $box->value;};try{return $f();}catch(TypeError $e){return "error";}}', '"error"'], 'strict arrow default' => ['<?php declare(strict_types=1);class Box{function __construct(public int $value){}}function target(){$f=fn($box=new Box("7"))=>$box->value;try{return $f();}catch(TypeError $e){return "error";}}', '"error"'], 'strict promoted default' => ['<?php declare(strict_types=1);class Box{function __construct(public int $value){}}class Holder{function __construct(public Box $box=new Box("7")){}}function target(){try{$h=new Holder;return $h->box->value;}catch(TypeError $e){return "error";}}', '"error"'], 'supplied argument skips invalid default' => ['<?php declare(strict_types=1);class Box{function __construct(public int $value){}}function helper($box=new Box("7")){return $box->value;}function target(){return helper(new Box(9));}', '9'], 'fresh default each invocation' => ['<?php class Box{}function helper($box=new Box){return $box;}function target(){$a=helper();$b=helper();return $a!==$b;}', 'true'], 'default fails before constructor body' => ['<?php declare(strict_types=1);class Box{function __construct(int $value){$GLOBALS["calls"]++;}}function helper($box=new Box("7")){return 9;}function target(){$GLOBALS["calls"]=0;try{helper();}catch(TypeError $e){return $GLOBALS["calls"];}return 99;}', '0']];
    }
    /**
     * @param string $library Captured declaration file
     * @param string $application Captured caller file
     * @param string $expectedJson PHP 8.3 observation
     * @throws JsonException If captured fixture data cannot be encoded
     */
    #[DataProvider('providerInitializerFiles')]
    public function testParametersUsesTheDeclarationFileModeAcrossCallerFiles(string $library, string $application, string $expectedJson): void
    {
        $prepared1Input = new Sources([new Source('library.php', $library), new Source('app.php', $application)]);
        $prepared1Configuration = new DeriverConfig();
        $prepared1Contributions = new Contributions();
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        $session = new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot);
        $result = $session->derive(new ReturnQuery('target'));
        $frontiers = array_reduce(iterator_to_array($result, false), static function (array $frontiers, Candidate $candidate1): array {
            if ($candidate1->result instanceof Term) {
                $graph = $candidate1->result;
                $snapshot = $candidate1->evidence[0]->snapshot;
                $pending = [$graph];
                $seen = new WeakMap();
                $frontiers1 = [];
                while ($pending !== []) {
                    $node = array_pop($pending);
                    if (isset($seen[$node])) {
                        continue;
                    }
                    $seen[$node] = true;
                    array_push($pending, ...array_values($node->operands));
                    $reason = $node->attributes['reason'] ?? null;
                    if (!is_string($reason)) {
                        continue;
                    }
                    $source1 = new SourceRef($snapshot->id, (string) ($node->attributes['source'] ?? ''), (int) ($node->attributes['start'] ?? 0), (int) ($node->attributes['end'] ?? $node->attributes['start'] ?? 0));
                    $identity = (string) ($node->attributes['identity'] ?? (new Identity())->key($node));
                    $frontiers1[$identity] = new Frontier($reason, $source1, (string) $node->literal, ['value'], [$identity], $node, $reason);
                }
                array_push($frontiers, ...array_values($frontiers1));
            }
            return $frontiers;
        }, []);
        self::assertSame([], $frontiers);
        self::assertSame([], array_filter(iterator_to_array($result, false), static fn ($candidate): bool => $candidate->type_name === 'never'));
        self::assertCount(1, $result);
        self::assertSame(json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR), $result[0]->result);
    }
    /**
     * @return array<string,array{string,string,string}>
     */
    public static function providerInitializerFiles(): array
    {
        $result = [];
        foreach ([0, 1] as $declaration) {
            foreach ([0, 1] as $caller) {
                $library = '<?php declare(strict_types=' . $declaration . ');class Box{function __construct(public int $value){}}function helper($box=new Box("7")){return $box->value;}';
                $application = '<?php declare(strict_types=' . $caller . ');function target(){try{return helper();}catch(TypeError $e){return "error";}}';
                $result['declaration ' . $declaration . ' caller ' . $caller] = [$library, $application, $declaration === 1 ? '"error"' : '7'];
            }
        }
        return $result;
    }
    /**
     * @param string $source Trusted promoted-property fixture
     * @param string $expectedJson PHP 8.3 observation
     * @throws JsonException If fixture data cannot be decoded
     */
    #[DataProvider('providerPromotedReferences')]
    public function testPromotionsPreserveAliasesAndReadonlyErrors(string $source, string $expectedJson): void
    {
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
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @return array<string,array{string,string}>
     */
    public static function providerPromotedReferences(): array
    {
        return ['caller write reaches property' => ['<?php class Box{function __construct(public int &$value){}}function target(){$x=1;$b=new Box($x);$x=2;return $b->value;}', '2'], 'property write reaches caller' => ['<?php class Box{function __construct(public int &$value){}}function target(){$x=1;$b=new Box($x);$b->value=2;return $x;}', '2'], 'constructor write reaches property' => ['<?php class Box{function __construct(public int &$value){$value=3;}}function target(){$x=1;$b=new Box($x);return [$x,$b->value];}', '[3,3]'], 'private promotion keeps alias' => ['<?php class Box{function __construct(private int &$value){}function read(){return $this->value;}}function target(){$x=1;$b=new Box($x);$x=4;return $b->read();}', '4'], 'protected promotion keeps alias' => ['<?php class Box{function __construct(protected int &$value){}function read(){return $this->value;}}function target(){$x=1;$b=new Box($x);$x=4;return $b->read();}', '4'], 'clone keeps shared reference' => ['<?php class Box{function __construct(public int &$value){}}function target(){$x=1;$a=new Box($x);$b=clone $a;$x=5;return [$a->value,$b->value];}', '[5,5]'], 'reference enforces live property type' => ['<?php class Box{function __construct(public int &$value){}}function target(){$x=1;$b=new Box($x);try{$x=[];}catch(TypeError $e){return [$x,$b->value];}return 99;}', '[1,1]'], 'unset removes property constraint' => ['<?php class Box{function __construct(public int &$value){}}function target(){$x=1;$b=new Box($x);unset($b->value);$x=[];return $x;}', '[]'], 'by value promotion stays independent' => ['<?php class Box{function __construct(public int $value){}}function target(){$x=1;$b=new Box($x);$x=2;return $b->value;}', '1'], 'reference readonly promotion rejected' => ['<?php class Box{function __construct(public readonly int &$value){}}function target(){$x=1;try{$b=new Box($x);return 99;}catch(Error $e){return $x;}}', '1'], 'reference readonly class rejected' => ['<?php readonly class Box{function __construct(public int &$value){}}function target(){$x=1;try{$b=new Box($x);return 99;}catch(Error $e){return $x;}}', '1'], 'array promotion keeps alias' => ['<?php class Box{function __construct(public array &$items){}}function target(){$x=[1];$b=new Box($x);$b->items[]=2;return $x;}', '[1,2]'], 'reference default aliases parameter and property' => ['<?php class Box{function __construct(public int &$value=1){$value=2;}}function target(){$b=new Box;return $b->value;}', '2'], 'reference named argument' => ['<?php class Box{function __construct(public int &$value){}}function target(){$x=1;$b=new Box(value:$x);$x=6;return $b->value;}', '6'], 'weak coercion precedes readonly failure' => ['<?php class Box{function __construct(public readonly int &$value){}}function target(){$x="1";try{$b=new Box($x);return 99;}catch(Error $e){return $x;}}', '1'], 'readonly promotion cannot reinitialize' => ['<?php class Box{function __construct(public readonly int $x){}}function target(){$b=new Box(1);try{$b->__construct(2);}catch(Error $e){return $b->x;}return 99;}', '1'], 'all parameter checks precede promotion' => ['<?php class Box{function __construct(public int $x,string $y){}}function target(){$b=new Box(1,"a");try{$b->__construct(2,[]);}catch(TypeError $e){return $b->x;}return 99;}', '1'], 'all coercions precede readonly failure' => ['<?php class Box{function __construct(public readonly int &$x,int &$y){}}function target(){$x=1;$y="2";try{new Box($x,$y);}catch(Error $e){return $y;}return 99;}', '2'], 'repeated promotion rebinds reference' => ['<?php class Box{function __construct(public int &$x){}}function target(){$x=1;$y=2;$b=new Box($x);$b->__construct($y);$x=3;$y=4;return [$x,$b->x];}', '[3,4]'], 'promotion reads latest coerced shared argument' => ['<?php class Box{function __construct(public &$x,float &$y){}}function target(){$v=1;$b=new Box($v,$v);return [$b->x,$v];}', '[1.0,1.0]'], 'parent constructor private promotion' => ['<?php class A{function __construct(private int &$x){}function read(){return $this->x;}}class B extends A{}function target(){$x=1;$b=new B($x);$x=9;return $b->read();}', '9'], 'trait constructor reference promotion' => ['<?php trait T{function __construct(private int &$x){}function read(){return $this->x;}}class B{use T;}function target(){$x=1;$b=new B($x);$x=9;return $b->read();}', '9']];
    }
    /**
     * @param string $reference \Deriver\Deriver\Lowerer\Graph\Parameter passing syntax
     * @param TermKind $expectedKind Expected selected value category
     * @param int|string $expectedLiteral Preserved symbolic identity or updated value
     */
    #[DataProvider('providerSymbolicPromotions')]
    public function testPromotionsInitializeSymbolicConstructorObservations(string $reference, TermKind $expectedKind, int|string $expectedLiteral): void
    {
        $input = new Sources([new Source('fixture.php', '<?php function sink($v){}class Box{function __construct(public int ' . $reference . '$value){$value=2;sink($this->value);}}')]);
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
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $result = $session->derive(new ValueQuery((new CallObservations($session->program, $session->evaluators))->find('sink')[0]->argument(0)));
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        $values = array_column(array_column($result->normalOutcomes, 'values'), 'value');
        self::assertNotEmpty($values);
        self::assertSame(array_fill(0, count($values), $expectedKind), array_column($values, 'kind'));
        self::assertSame([$expectedLiteral], array_values(array_unique(array_column($values, 'literal'))));
    }
    /**
     * @return array<string,array{string,TermKind,int|string}>
     */
    public static function providerSymbolicPromotions(): array
    {
        return ['by value' => ['', TermKind::Parameter, 'value'], 'by reference' => ['&', TermKind::Constant, 2]];
    }
}

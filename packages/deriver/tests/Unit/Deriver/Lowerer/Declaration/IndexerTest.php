<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Lowerer\Declaration;

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
use Deriver\Deriver\Evaluator\Execution\Call\CallResolution;
use Deriver\Deriver\Evaluator\Execution\Call\Creation\Access;
use Deriver\Deriver\Evaluator\Execution\Call\Member\Invocation;
use Deriver\Deriver\Evaluator\Execution\Call\ParameterBinding;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Arguments;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Creation;
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
use Deriver\Deriver\Evaluator\Execution\Context;
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
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ObjectAccess;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyAccessCheck;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyLookup;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertySlot;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyTransfer;
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
use Deriver\Deriver\Evaluator\Term as EvaluatorTerm;
use Deriver\Deriver\Execution\DerivationResult;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Execution\ResultAssessment;
use Deriver\Deriver\Identity\QueryEncoding;
use Deriver\Deriver\Identity\ValueGraph;
use Deriver\Deriver\Lowerer\CallLowering;
use Deriver\Deriver\Lowerer\Control\DestructuringLowering;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\ConstantSignatures;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassConstant;
use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Metadata\PropertyDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\Declaration\Traits\Members;
use Deriver\Deriver\Lowerer\EffectInspection;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\Argument;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Literal as Term;
use Deriver\Deriver\Lowerer\Graph\LiteralKind;
use Deriver\Deriver\Lowerer\Graph\Parameter;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\Hierarchy\Access as HierarchyAccess;
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
use PhpParser\Node\DeclareItem;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\ParserFactory;
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
#[CoversClass(Indexer::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassConstant::class)]
#[UsesClass(ClassDeclaration::class)]
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
#[UsesClass(Dispatch::class)]
#[UsesClass(HierarchyAccess::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Creation::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
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
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Isolation::class)]
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
#[UsesClass(Frontier::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(Members::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class IndexerTest extends TestCase
{
    public function testStrictPreservesTheSemanticContract(): void
    {
        $source = '<?php class Box{function __construct(public string $value){}}function target(){return (new Box("yes"))->value;}';
        $symbol = 'target';
        $input = new Sources([new Source('fixture.php', $source)]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(EvaluatorTerm::fromNative(...), $configuration->environment), $contributions);
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
        self::assertSame('yes', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testScanIndexesConditionalDeclarationsAsCandidateOrigins(): void
    {
        $source = '<?php if ($flag) { function conditional(){} } function always(){}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertSame(['always', 'conditional', 'script:fixture.php'], $index->symbols());
    }
    public function testClassDeclarationRetainsInheritanceAndInterfaces(): void
    {
        $source = '<?php final class Child extends ParentClass implements Service {use Shared; public function run(){}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $class = $index->classes()['child'];
        self::assertSame('ParentClass', $class->parent);
        self::assertSame(['Service'], $class->interfaces);
        self::assertSame(['Shared'], $class->traits);
        self::assertTrue($class->final);
        self::assertSame('Child::run', $class->methods['run']);
    }
    public function testPropertiesRetainsVisibilityReadonlyAndUnevaluatedDefaults(): void
    {
        $source = '<?php class Record {protected static int $count = 1+2; public readonly string $id;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $properties = $index->classes()['record']->properties;
        self::assertSame(Visibility::Protected, $properties['count']->visibility);
        self::assertTrue($properties['count']->static);
        self::assertNotNull($properties['count']->default);
        self::assertTrue($properties['id']->readonly);
    }
    public function testConstantsKeepsEnumCaseIdentityAndBackingValues(): void
    {
        $source = '<?php enum Status: string {case Ready = "ready";}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $ready = $index->classes()['status']->constants['Ready'];
        self::assertSame(LiteralKind::Enum, $ready->kind);
        self::assertSame('ready', $ready->operands['value']->native());
    }
    public function testPromotionsDoesNotUseTheParameterDefaultAsAPropertyDefault(): void
    {
        $source = '<?php class Record {public function __construct(public readonly int $id = 3){}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $property = $index->classes()['record']->properties['id'];
        self::assertTrue($property->readonly);
        self::assertSame('int', $property->type);
        self::assertNull($property->default);
    }
    /**
     * @param list<Stmt> $nodes File declarations
     * @param bool $expected Strict scalar coercion mode
     */
    #[DataProvider('providerStrictDeclarations')]
    public function testStrictReadsOnlyTheIntegerStrictTypesDirective(array $nodes, bool $expected): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        self::assertSame($expected, (new Indexer(new Index($snapshotId, $trees)))->strict($nodes));
    }
    /**
     * @return iterable<string,array{list<Stmt>,bool}>
     */
    public static function providerStrictDeclarations(): iterable
    {
        yield 'absent' => [[], false];
        yield 'unrelated statement' => [[new Nop()], false];
        yield 'enabled' => [[new Declare_([new DeclareItem('strict_types', new Int_(1))])], true];
        yield 'disabled' => [[new Declare_([new DeclareItem('strict_types', new Int_(0))])], false];
        yield 'other integer' => [[new Declare_([new DeclareItem('strict_types', new Int_(2))])], false];
        yield 'string directive' => [[new Declare_([new DeclareItem('strict_types', new String_('1'))])], false];
        yield 'unrelated directive' => [[new Declare_([new DeclareItem('ticks', new Int_(1))])], false];
        yield 'multiple declarations' => [[new Declare_([new DeclareItem('ticks', new Int_(1))]), new Declare_([new DeclareItem('strict_types', new Int_(1))])], true];
        yield 'multiple items' => [[new Declare_([new DeclareItem('ticks', new Int_(1)), new DeclareItem('strict_types', new Int_(1))])], true];
    }
    /**
     * @param string $declaration Declaration syntax
     * @param array{bool,bool,bool,bool,bool} $flags Final, abstract, interface, readonly, enum
     * @param string $parent Declared parent
     * @param list<string> $interfaces Declared interfaces
     */
    #[DataProvider('providerClassKinds')]
    public function testClassDeclarationPreservesEachDeclarationKind(string $declaration, array $flags, string $parent, array $interfaces): void
    {
        $source = '<?php namespace App; ' . $declaration;
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $class = $index->classIndex['app\subject'];
        self::assertSame('App\Subject', $class->name);
        self::assertSame($flags, [$class->final, $class->abstract, $class->interface, $class->readonly, $class->enum]);
        self::assertSame($parent, $class->parent);
        self::assertSame($interfaces, $class->interfaces);
        self::assertSame('App\Subject', $index->classSources['app\subject']->className);
        self::assertSame('fixture.php', $index->classSources['app\subject']->path);
    }
    /**
     * @return iterable<string,array{string,array{bool,bool,bool,bool,bool},string,list<string>}>
     */
    public static function providerClassKinds(): iterable
    {
        yield 'ordinary' => ['class Subject{}', [false, false, false, false, false], '', []];
        yield 'final' => ['final class Subject{}', [true, false, false, false, false], '', []];
        yield 'abstract' => ['abstract class Subject{}', [false, true, false, false, false], '', []];
        yield 'readonly' => ['readonly class Subject{}', [false, false, false, true, false], '', []];
        yield 'final readonly' => ['final readonly class Subject{}', [true, false, false, true, false], '', []];
        yield 'inheritance' => ['class Subject extends ParentType implements One,Two{}', [false, false, false, false, false], 'App\ParentType', ['App\One', 'App\Two']];
        yield 'trait' => ['trait Subject{}', [false, true, false, false, false], '', []];
        yield 'interface' => ['interface Subject extends One,Two{}', [false, false, true, false, false], '', ['App\One', 'App\Two']];
        yield 'unit enum' => ['enum Subject{case Ready;}', [true, false, false, false, true], '', []];
        yield 'backed enum' => ['enum Subject:string implements One{case Ready="ready";}', [true, false, false, false, true], '', ['App\One']];
    }
    public function testScanRetainsNamespaceCaseAndStrictModeWithoutLoweringBodies(): void
    {
        $source = '<?php declare(strict_types=1);namespace App{function MixedCase(){return nested();}const Key=4,Other=5;class Subject{function Run(){return 7;}}}namespace Other{function Call(){return 8;}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertSame('App\MixedCase', $index->declarations['app\mixedcase']->symbol);
        self::assertTrue($index->declarations['app\mixedcase']->strict);
        self::assertSame('fixture.php', $index->declarations['app\mixedcase']->path);
        self::assertSame('App\Subject', $index->declarations['app\subject::run']->className);
        self::assertTrue($index->declarations['app\subject::run']->strict);
        self::assertSame(['run' => 'App\Subject::Run'], $index->classIndex['app\subject']->methods);
        self::assertSame(['App\Key', 'App\Other'], array_keys($index->constantSources));
        self::assertTrue($index->constantSources['App\Other']->strict);
        self::assertArrayHasKey('other\call', $index->declarations);
        self::assertSame([], $index->graphs);
    }
    public function testScanUsesUnqualifiedNamesWhenNoResolverAnnotationsExist(): void
    {
        $source = '<?php';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $nodes = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php function Plain(){} const Key=7; class Subject{function Run(){}}');
        self::assertNotNull($nodes);
        self::assertInstanceOf(Function_::class, $nodes[0]);
        self::assertInstanceOf(Const_::class, $nodes[1]);
        self::assertInstanceOf(Class_::class, $nodes[2]);
        $nodes[0]->namespacedName = null;
        $nodes[1]->consts[0]->namespacedName = null;
        $nodes[2]->namespacedName = null;
        (new Indexer($index))->scan($nodes, 'fixture.php', true);
        self::assertSame('Plain', $index->declarations['plain']->symbol);
        self::assertSame('Key', $index->constantSources['Key']->symbol);
        self::assertSame('Subject', $index->classSources['subject']->symbol);
        self::assertSame(['run' => 'Subject::Run'], $index->classIndex['subject']->methods);
        self::assertTrue($index->classSources['subject']->strict);
    }
    public function testScanLeavesNestedAndAnonymousDeclarationsToRuntimeBoundaries(): void
    {
        $source = '<?php function Outer(){function Nested(){} class Local{}} if($flag){class Conditional{}} $a=new class{function Run(){}};';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertSame(['Outer', 'script:fixture.php'], $index->symbols());
        self::assertSame(['conditional'], array_keys($index->classIndex));
        self::assertSame([], $index->constantSources);
    }
    public function testClassDeclarationPreservesTraitUseOrderAndMethodNames(): void
    {
        $source = '<?php namespace App;trait One{}trait Two{}trait Three{}class Subject{use One,Two;use Three;function MixedCase(){}function next(){}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $class = $index->classIndex['app\subject'];
        self::assertSame(['App\One', 'App\Two', 'App\Three'], $class->traits);
        self::assertSame(['mixedcase' => 'App\Subject::MixedCase', 'next' => 'App\Subject::next'], $class->methods);
        self::assertSame('App\Subject', $index->declarations['app\subject::mixedcase']->className);
    }
    /**
     * @param string $declaration Source property declaration
     * @param bool $readonlyClass Enclosing class modifier
     * @param string $visibility Visibility
     * @param bool $static Whether storage belongs to the class
     * @param bool $readonly Whether reassignment is forbidden
     * @param string $type Declared type
     */
    #[DataProvider('providerPropertyDeclarations')]
    public function testPropertiesPreservesTypesStorageAndVisibility(string $declaration, bool $readonlyClass, string $visibility, bool $static, bool $readonly, string $type): void
    {
        $source = '<?php ' . ($readonlyClass ? 'readonly ' : '') . 'class Subject{' . $declaration . '}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $property = $index->classIndex['subject']->properties['value'];
        self::assertSame('value', $property->name);
        self::assertSame('Subject', $property->className);
        self::assertSame(Visibility::from($visibility), $property->visibility);
        self::assertSame($static, $property->static);
        self::assertSame($readonly, $property->readonly);
        self::assertSame($type, $property->type);
        self::assertNull($property->default);
    }
    /**
     * @return iterable<string,array{string,bool,string,bool,bool,string}>
     */
    public static function providerPropertyDeclarations(): iterable
    {
        yield 'untyped' => ['public $value;', false, 'public', false, false, 'mixed'];
        yield 'private typed' => ['private int $value;', false, 'private', false, false, 'int'];
        yield 'protected nullable' => ['protected ?string $value;', false, 'protected', false, false, 'string|null'];
        yield 'public union' => ['public int|string $value;', false, 'public', false, false, 'int|string'];
        yield 'private static' => ['private static int $value;', false, 'private', true, false, 'int'];
        yield 'protected static' => ['protected static array $value;', false, 'protected', true, false, 'array'];
        yield 'explicit readonly' => ['public readonly int $value;', false, 'public', false, true, 'int'];
        yield 'class readonly' => ['protected int $value;', true, 'protected', false, true, 'int'];
        yield 'both readonly' => ['public readonly int $value;', true, 'public', false, true, 'int'];
    }
    public function testPropertiesKeepsEachDefaultInItsOwnLexicalGraph(): void
    {
        $source = '<?php namespace App;class Subject{public int $first=3,$second=4;public $absent;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $properties = $index->classIndex['app\subject']->properties;
        self::assertSame(['first', 'second', 'absent'], array_keys($properties));
        self::assertNotNull($properties['first']->default);
        self::assertNotNull($properties['second']->default);
        self::assertSame('App\Subject::$first', $properties['first']->default->symbol);
        self::assertSame('App\Subject::$second', $properties['second']->default->symbol);
        self::assertSame('App\Subject', $properties['first']->default->className);
        self::assertSame(3, $properties['first']->default->blocks[0]->instructions[0]->constant?->native());
        self::assertSame(4, $properties['second']->default->blocks[0]->instructions[0]->constant?->native());
        self::assertNull($properties['absent']->default);
    }
    public function testConstantsDistinguishesScalarLiteralsFromUnevaluatedInitializers(): void
    {
        $source = '<?php class Subject{const TEXT="bytes",NUMBER=3,FRACTION=1.5,EXPR=1+2,FLAG=true;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $constants = $index->classIndex['subject']->constants;
        self::assertSame(['TEXT', 'NUMBER', 'FRACTION', 'EXPR', 'FLAG'], array_keys($constants));
        self::assertSame('bytes', $constants['TEXT']->native());
        self::assertSame(3, $constants['NUMBER']->native());
        self::assertSame(1.5, $constants['FRACTION']->native());
        self::assertSame(LiteralKind::Unresolved, $constants['EXPR']->kind);
        self::assertSame('UNSUPPORTED_LANGUAGE_FEATURE', $constants['EXPR']->literal);
        self::assertSame(LiteralKind::Unresolved, $constants['FLAG']->kind);
        self::assertSame([], $constants['TEXT']->operands);
    }
    /**
     * @param string $source Enum declaration
     * @param array<string,int|string> $fields Expected enum fields
     */
    #[DataProvider('providerEnumCases')]
    public function testConstantsKeepsNamespacedEnumIdentityAndOptionalBackingValues(string $source, array $fields): void
    {
        $source1 = '<?php namespace App;' . $source;
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source1)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $case = $index->classIndex['app\subject']->constants['Ready'];
        self::assertSame(LiteralKind::Enum, $case->kind);
        self::assertSame('App\Subject::Ready', $case->literal);
        self::assertSame('App\Subject', $case->attributes['class']);
        self::assertSame($fields, array_map(static fn (Term $value): int|float|string|bool|null => $value->literal, $case->operands));
    }
    /**
     * @return iterable<string,array{string,array<string,int|string>}>
     */
    public static function providerEnumCases(): iterable
    {
        yield 'unit' => ['enum Subject{case Ready;}', ['name' => 'Ready']];
        yield 'integer backed' => ['enum Subject:int{case Ready=4;}', ['name' => 'Ready', 'value' => 4]];
        yield 'string backed' => ['enum Subject:string{case Ready="ready";}', ['name' => 'Ready', 'value' => 'ready']];
    }
    /**
     * @param string $parameter Promoted parameter declaration
     * @param bool $readonlyClass Class modifier
     * @param string $visibility Expected visibility
     * @param bool $readonly Expected readonly contract
     */
    #[DataProvider('providerPromotedProperties')]
    public function testPromotionsCapturesPropertyModifiersWithoutCopyingParameterDefaults(string $parameter, bool $readonlyClass, string $visibility, bool $readonly): void
    {
        $source = '<?php ' . ($readonlyClass ? 'readonly ' : '') . 'class Subject{function __construct(' . $parameter . ',int $ordinary=9){}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $properties = $index->classIndex['subject']->properties;
        self::assertSame(['value'], array_keys($properties));
        self::assertSame('value', $properties['value']->name);
        self::assertSame('Subject', $properties['value']->className);
        self::assertSame('int', $properties['value']->type);
        self::assertSame(Visibility::from($visibility), $properties['value']->visibility);
        self::assertSame($readonly, $properties['value']->readonly);
        self::assertFalse($properties['value']->static);
        self::assertNull($properties['value']->default);
    }
    /**
     * @return iterable<string,array{string,bool,string,bool}>
     */
    public static function providerPromotedProperties(): iterable
    {
        yield 'public' => ['public int $value=3', false, 'public', false];
        yield 'protected' => ['protected int $value=3', false, 'protected', false];
        yield 'private' => ['private int $value=3', false, 'private', false];
        yield 'readonly' => ['public readonly int $value=3', false, 'public', true];
        yield 'readonly class' => ['private int $value=3', true, 'private', true];
        yield 'both readonly' => ['protected readonly int $value=3', true, 'protected', true];
    }
    public function testExistingClassRejectsDuplicateSourceDeclarations(): void
    {
        $source = '<?php class C{}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $node = new Class_('C');
        self::assertTrue((new Indexer($index))->existingClass($node, 'fixture.php', 'C'));
        self::assertSame('INVALID_PROGRAM', $index->diagnostics()[0]->code);
    }
    public function testConditionalMethodsRetainsBothImplementations(): void
    {
        $source = '<?php if($x){class A{function f(){return 1;}}}else{class A{function f(){return 2;}}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertCount(1, $index->variants('A::f'));
        self::assertSame([], $index->diagnostics());
    }
}

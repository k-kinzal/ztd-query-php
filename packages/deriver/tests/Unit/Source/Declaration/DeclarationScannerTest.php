<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Declaration;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\ClassConstant;
use Deriver\ControlFlow\ClassDeclaration;
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
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Creation;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
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
use Deriver\Result\Frontier;
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
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\Control\DestructuringLowering;
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
use Deriver\Source\Declaration\Traits\Members;
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
use PhpParser\Node\DeclareItem;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SourceFixture;

#[CoversClass(DeclarationScanner::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
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
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
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
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
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
#[UsesClass(Frontier::class)]
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
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(Members::class)]
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
final class DeclarationScannerTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testStrictPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class Box{function __construct(public string $value){}}function target(){return (new Box("yes"))->value;}');
        self::assertSame('yes', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testScanDoesNotIndexConditionallyDeclaredFunctionsAsUnconditional(): void
    {
        $index = SourceFixture::index('<?php if ($flag) { function conditional(){} } function always(){}');
        self::assertSame(['always', 'script:fixture.php'], $index->symbols());
    }
    public function testClassDeclarationRetainsInheritanceAndInterfaces(): void
    {
        $index = SourceFixture::index('<?php final class Child extends ParentClass implements Service {use Shared; public function run(){}}');
        $class = $index->classes()['child'];
        self::assertSame('ParentClass', $class->parent);
        self::assertSame(['Service'], $class->interfaces);
        self::assertSame(['Shared'], $class->traits);
        self::assertTrue($class->final);
        self::assertSame('Child::run', $class->methods['run']);
    }
    public function testPropertiesRetainsVisibilityReadonlyAndUnevaluatedDefaults(): void
    {
        $index = SourceFixture::index('<?php class Record {protected static int $count = 1+2; public readonly string $id;}');
        $properties = $index->classes()['record']->properties;
        self::assertSame('protected', $properties['count']->visibility);
        self::assertTrue($properties['count']->static);
        self::assertNotNull($properties['count']->default);
        self::assertTrue($properties['id']->readonly);
    }
    public function testConstantsKeepsEnumCaseIdentityAndBackingValues(): void
    {
        $index = SourceFixture::index('<?php enum Status: string {case Ready = "ready";}');
        $ready = $index->classes()['status']->constants['Ready'];
        self::assertSame('enum', $ready->kind);
        self::assertSame('ready', $ready->operands['value']->native());
    }
    public function testPromotionsDoesNotUseTheParameterDefaultAsAPropertyDefault(): void
    {
        $index = SourceFixture::index('<?php class Record {public function __construct(public readonly int $id = 3){}}');
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
        self::assertSame($expected, (new DeclarationScanner(SourceFixture::index()))->strict($nodes));
    }

    /**
     * @return iterable<string,array{list<Stmt>,bool}>
     */
    public static function providerStrictDeclarations(): iterable
    {
        yield 'absent' => [[],false];
        yield 'unrelated statement' => [[new Stmt\Nop()],false];
        yield 'enabled' => [[new Stmt\Declare_([new DeclareItem('strict_types', new Scalar\Int_(1))])],true];
        yield 'disabled' => [[new Stmt\Declare_([new DeclareItem('strict_types', new Scalar\Int_(0))])],false];
        yield 'other integer' => [[new Stmt\Declare_([new DeclareItem('strict_types', new Scalar\Int_(2))])],false];
        yield 'string directive' => [[new Stmt\Declare_([new DeclareItem('strict_types', new Scalar\String_('1'))])],false];
        yield 'unrelated directive' => [[new Stmt\Declare_([new DeclareItem('ticks', new Scalar\Int_(1))])],false];
        yield 'multiple declarations' => [[new Stmt\Declare_([new DeclareItem('ticks', new Scalar\Int_(1))]),new Stmt\Declare_([new DeclareItem('strict_types', new Scalar\Int_(1))])],true];
        yield 'multiple items' => [[new Stmt\Declare_([new DeclareItem('ticks', new Scalar\Int_(1)),new DeclareItem('strict_types', new Scalar\Int_(1))])],true];
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
        $index = SourceFixture::index('<?php namespace App; '.$declaration);
        $class = $index->classIndex['app\subject'];
        self::assertSame('App\Subject', $class->name);
        self::assertSame($flags, [$class->final,$class->abstract,$class->interface,$class->readonly,$class->enum]);
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
        yield 'ordinary' => ['class Subject{}',[false,false,false,false,false],'',[]];
        yield 'final' => ['final class Subject{}',[true,false,false,false,false],'',[]];
        yield 'abstract' => ['abstract class Subject{}',[false,true,false,false,false],'',[]];
        yield 'readonly' => ['readonly class Subject{}',[false,false,false,true,false],'',[]];
        yield 'final readonly' => ['final readonly class Subject{}',[true,false,false,true,false],'',[]];
        yield 'inheritance' => ['class Subject extends ParentType implements One,Two{}',[false,false,false,false,false],'App\ParentType',['App\One','App\Two']];
        yield 'trait' => ['trait Subject{}',[false,true,false,false,false],'',[]];
        yield 'interface' => ['interface Subject extends One,Two{}',[false,false,true,false,false],'',['App\One','App\Two']];
        yield 'unit enum' => ['enum Subject{case Ready;}',[true,false,false,false,true],'',[]];
        yield 'backed enum' => ['enum Subject:string implements One{case Ready="ready";}',[true,false,false,false,true],'',['App\One']];
    }

    public function testScanRetainsNamespaceCaseAndStrictModeWithoutLoweringBodies(): void
    {
        $index = SourceFixture::index('<?php declare(strict_types=1);namespace App{function MixedCase(){return nested();}const Key=4,Other=5;class Subject{function Run(){return 7;}}}namespace Other{function Call(){return 8;}}');
        self::assertSame('App\MixedCase', $index->declarations['app\mixedcase']->symbol);
        self::assertTrue($index->declarations['app\mixedcase']->strict);
        self::assertSame('fixture.php', $index->declarations['app\mixedcase']->path);
        self::assertSame('App\Subject', $index->declarations['app\subject::run']->className);
        self::assertTrue($index->declarations['app\subject::run']->strict);
        self::assertSame(['run' => 'App\Subject::Run'], $index->classIndex['app\subject']->methods);
        self::assertSame(['App\Key','App\Other'], array_keys($index->constantSources));
        self::assertTrue($index->constantSources['App\Other']->strict);
        self::assertArrayHasKey('other\call', $index->declarations);
        self::assertSame([], $index->graphs);
    }

    public function testScanUsesUnqualifiedNamesWhenNoResolverAnnotationsExist(): void
    {
        $index = SourceFixture::index('<?php');
        $nodes = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse('<?php function Plain(){} const Key=7; class Subject{function Run(){}}');
        self::assertNotNull($nodes);
        self::assertInstanceOf(Stmt\Function_::class, $nodes[0]);
        self::assertInstanceOf(Stmt\Const_::class, $nodes[1]);
        self::assertInstanceOf(Stmt\Class_::class, $nodes[2]);
        $nodes[0]->namespacedName = null;
        $nodes[1]->consts[0]->namespacedName = null;
        $nodes[2]->namespacedName = null;
        (new DeclarationScanner($index))->scan($nodes, 'fixture.php', true);
        self::assertSame('Plain', $index->declarations['plain']->symbol);
        self::assertSame('Key', $index->constantSources['Key']->symbol);
        self::assertSame('Subject', $index->classSources['subject']->symbol);
        self::assertSame(['run' => 'Subject::Run'], $index->classIndex['subject']->methods);
        self::assertTrue($index->classSources['subject']->strict);
    }

    public function testScanLeavesNestedAndAnonymousDeclarationsToRuntimeBoundaries(): void
    {
        $index = SourceFixture::index('<?php function Outer(){function Nested(){} class Local{}} if($flag){class Conditional{}} $a=new class{function Run(){}};');
        self::assertSame(['Outer','script:fixture.php'], $index->symbols());
        self::assertSame([], $index->classIndex);
        self::assertSame([], $index->constantSources);
    }

    public function testClassDeclarationPreservesTraitUseOrderAndMethodNames(): void
    {
        $index = SourceFixture::index('<?php namespace App;trait One{}trait Two{}trait Three{}class Subject{use One,Two;use Three;function MixedCase(){}function next(){}}');
        $class = $index->classIndex['app\subject'];
        self::assertSame(['App\One','App\Two','App\Three'], $class->traits);
        self::assertSame(['mixedcase' => 'App\Subject::MixedCase','next' => 'App\Subject::next'], $class->methods);
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
        $index = SourceFixture::index('<?php '.($readonlyClass ? 'readonly ' : '').'class Subject{'.$declaration.'}');
        $property = $index->classIndex['subject']->properties['value'];
        self::assertSame('value', $property->name);
        self::assertSame('Subject', $property->className);
        self::assertSame($visibility, $property->visibility);
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
        yield 'untyped' => ['public $value;',false,'public',false,false,'mixed'];
        yield 'private typed' => ['private int $value;',false,'private',false,false,'int'];
        yield 'protected nullable' => ['protected ?string $value;',false,'protected',false,false,'string|null'];
        yield 'public union' => ['public int|string $value;',false,'public',false,false,'int|string'];
        yield 'private static' => ['private static int $value;',false,'private',true,false,'int'];
        yield 'protected static' => ['protected static array $value;',false,'protected',true,false,'array'];
        yield 'explicit readonly' => ['public readonly int $value;',false,'public',false,true,'int'];
        yield 'class readonly' => ['protected int $value;',true,'protected',false,true,'int'];
        yield 'both readonly' => ['public readonly int $value;',true,'public',false,true,'int'];
    }

    public function testPropertiesKeepsEachDefaultInItsOwnLexicalGraph(): void
    {
        $index = SourceFixture::index('<?php namespace App;class Subject{public int $first=3,$second=4;public $absent;}');
        $properties = $index->classIndex['app\subject']->properties;
        self::assertSame(['first','second','absent'], array_keys($properties));
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
        $index = SourceFixture::index('<?php class Subject{const TEXT="bytes",NUMBER=3,FRACTION=1.5,EXPR=1+2,FLAG=true;}');
        $constants = $index->classIndex['subject']->constants;
        self::assertSame(['TEXT','NUMBER','FRACTION','EXPR','FLAG'], array_keys($constants));
        self::assertSame('bytes', $constants['TEXT']->native());
        self::assertSame(3, $constants['NUMBER']->native());
        self::assertSame(1.5, $constants['FRACTION']->native());
        self::assertSame('opaque', $constants['EXPR']->kind);
        self::assertSame('UNSUPPORTED_LANGUAGE_FEATURE', $constants['EXPR']->literal);
        self::assertSame('opaque', $constants['FLAG']->kind);
        self::assertSame([], $constants['TEXT']->operands);
    }

    /**
     * @param string $source Enum declaration
     * @param array<string,int|string> $fields Expected enum fields
     */
    #[DataProvider('providerEnumCases')]
    public function testConstantsKeepsNamespacedEnumIdentityAndOptionalBackingValues(string $source, array $fields): void
    {
        $index = SourceFixture::index('<?php namespace App;'.$source);
        $case = $index->classIndex['app\subject']->constants['Ready'];
        self::assertSame('enum', $case->kind);
        self::assertSame('App\Subject::Ready', $case->literal);
        self::assertSame('App\Subject', $case->attributes['class']);
        self::assertSame($fields, array_map(static fn (Term $value): int|float|string|bool|null => $value->literal, $case->operands));
    }

    /**
     * @return iterable<string,array{string,array<string,int|string>}>
     */
    public static function providerEnumCases(): iterable
    {
        yield 'unit' => ['enum Subject{case Ready;}',['name' => 'Ready']];
        yield 'integer backed' => ['enum Subject:int{case Ready=4;}',['name' => 'Ready','value' => 4]];
        yield 'string backed' => ['enum Subject:string{case Ready="ready";}',['name' => 'Ready','value' => 'ready']];
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
        $index = SourceFixture::index('<?php '.($readonlyClass ? 'readonly ' : '').'class Subject{function __construct('.$parameter.',int $ordinary=9){}}');
        $properties = $index->classIndex['subject']->properties;
        self::assertSame(['value'], array_keys($properties));
        self::assertSame('value', $properties['value']->name);
        self::assertSame('Subject', $properties['value']->className);
        self::assertSame('int', $properties['value']->type);
        self::assertSame($visibility, $properties['value']->visibility);
        self::assertSame($readonly, $properties['value']->readonly);
        self::assertFalse($properties['value']->static);
        self::assertNull($properties['value']->default);
    }

    /**
     * @return iterable<string,array{string,bool,string,bool}>
     */
    public static function providerPromotedProperties(): iterable
    {
        yield 'public' => ['public int $value=3',false,'public',false];
        yield 'protected' => ['protected int $value=3',false,'protected',false];
        yield 'private' => ['private int $value=3',false,'private',false];
        yield 'readonly' => ['public readonly int $value=3',false,'public',true];
        yield 'readonly class' => ['private int $value=3',true,'private',true];
        yield 'both readonly' => ['protected readonly int $value=3',true,'protected',true];
    }
}

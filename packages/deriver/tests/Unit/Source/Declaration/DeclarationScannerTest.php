<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Declaration;

use Deriver\Source\Declaration\DeclarationScanner;
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
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassConstant::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
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
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\Model\StateStorage::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
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
#[UsesClass(\Deriver\Result\Frontier::class)]
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
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Members::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
    public function testScanIndexesConditionalDeclarationsAsCandidateOrigins(): void
    {
        $index = SourceFixture::index('<?php if ($flag) { function conditional(){} } function always(){}');
        self::assertSame(['always', 'conditional', 'script:fixture.php'], $index->symbols());
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
        self::assertSame(['conditional'], array_keys($index->classIndex));
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
    public function testExistingClassRejectsDuplicateSourceDeclarations(): void
    {
        $index = SourceFixture::index('<?php class C{}');
        $node = new Stmt\Class_('C');
        self::assertTrue((new DeclarationScanner($index))->existingClass($node, 'fixture.php', 'C'));
        self::assertSame('INVALID_PROGRAM', $index->diagnostics()[0]->code);
    }

    public function testConditionalMethodsRetainsBothImplementations(): void
    {
        $index = SourceFixture::index('<?php if($x){class A{function f(){return 1;}}}else{class A{function f(){return 2;}}}');
        self::assertCount(1, $index->variants('A::f'));
        self::assertSame([], $index->diagnostics());
    }

}

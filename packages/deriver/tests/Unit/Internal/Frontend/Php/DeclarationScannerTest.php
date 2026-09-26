<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php;

use Deriver\Internal\Frontend\Php\DeclarationScanner;
use JsonException;
use PhpParser\Node\DeclareItem;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FrontendFixture;

#[CoversClass(DeclarationScanner::class)]
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
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Members::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\ClassConstant::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Allocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyTransfer::class)]
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
        $index = FrontendFixture::index('<?php if ($flag) { function conditional(){} } function always(){}');
        self::assertSame(['always', 'script:fixture.php'], $index->symbols());
    }
    public function testClassDeclarationRetainsInheritanceAndInterfaces(): void
    {
        $index = FrontendFixture::index('<?php final class Child extends ParentClass implements Service {use Shared; public function run(){}}');
        $class = $index->classes()['child'];
        self::assertSame('ParentClass', $class->parent);
        self::assertSame(['Service'], $class->interfaces);
        self::assertSame(['Shared'], $class->traits);
        self::assertTrue($class->final);
        self::assertSame('Child::run', $class->methods['run']);
    }
    public function testPropertiesRetainsVisibilityReadonlyAndUnevaluatedDefaults(): void
    {
        $index = FrontendFixture::index('<?php class Record {protected static int $count = 1+2; public readonly string $id;}');
        $properties = $index->classes()['record']->properties;
        self::assertSame('protected', $properties['count']->visibility);
        self::assertTrue($properties['count']->static);
        self::assertNotNull($properties['count']->default);
        self::assertTrue($properties['id']->readonly);
    }
    public function testConstantsKeepsEnumCaseIdentityAndBackingValues(): void
    {
        $index = FrontendFixture::index('<?php enum Status: string {case Ready = "ready";}');
        $ready = $index->classes()['status']->constants['Ready'];
        self::assertSame('enum', $ready->kind);
        self::assertSame('ready', $ready->operands['value']->native());
    }
    public function testPromotionsDoesNotUseTheParameterDefaultAsAPropertyDefault(): void
    {
        $index = FrontendFixture::index('<?php class Record {public function __construct(public readonly int $id = 3){}}');
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
        self::assertSame($expected, (new DeclarationScanner(FrontendFixture::index()))->strict($nodes));
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
        $index = FrontendFixture::index('<?php namespace App; '.$declaration);
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
        $index = FrontendFixture::index('<?php declare(strict_types=1);namespace App{function MixedCase(){return nested();}const Key=4,Other=5;class Subject{function Run(){return 7;}}}namespace Other{function Call(){return 8;}}');
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
        $index = FrontendFixture::index('<?php');
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
        $index = FrontendFixture::index('<?php function Outer(){function Nested(){} class Local{}} if($flag){class Conditional{}} $a=new class{function Run(){}};');
        self::assertSame(['Outer','script:fixture.php'], $index->symbols());
        self::assertSame([], $index->classIndex);
        self::assertSame([], $index->constantSources);
    }

    public function testClassDeclarationPreservesTraitUseOrderAndMethodNames(): void
    {
        $index = FrontendFixture::index('<?php namespace App;trait One{}trait Two{}trait Three{}class Subject{use One,Two;use Three;function MixedCase(){}function next(){}}');
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
        $index = FrontendFixture::index('<?php '.($readonlyClass ? 'readonly ' : '').'class Subject{'.$declaration.'}');
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
        $index = FrontendFixture::index('<?php namespace App;class Subject{public int $first=3,$second=4;public $absent;}');
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
        $index = FrontendFixture::index('<?php class Subject{const TEXT="bytes",NUMBER=3,FRACTION=1.5,EXPR=1+2,FLAG=true;}');
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
        $index = FrontendFixture::index('<?php namespace App;'.$source);
        $case = $index->classIndex['app\subject']->constants['Ready'];
        self::assertSame('enum', $case->kind);
        self::assertSame('App\Subject::Ready', $case->literal);
        self::assertSame('App\Subject', $case->attributes['class']);
        self::assertSame($fields, array_map(static fn (\Deriver\Value\Term $value): int|float|string|bool|null => $value->literal, $case->operands));
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
        $index = FrontendFixture::index('<?php '.($readonlyClass ? 'readonly ' : '').'class Subject{function __construct('.$parameter.',int $ordinary=9){}}');
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

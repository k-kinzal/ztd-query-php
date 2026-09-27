<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

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
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Constants;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Constant\ClassNames;
use Deriver\Evaluation\Context;
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
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Invocation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\ConstantTransfer;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
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
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Arithmetic;
use Deriver\Value\Comparison;
use Deriver\Value\Identity;
use Deriver\Value\IntegerConversion;
use Deriver\Value\NumericString;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(TypeBinding::class)]
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
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Constants::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ClassNames::class)]
#[UsesClass(Context::class)]
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
#[UsesClass(Havoc::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(ConstantTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
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
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
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
#[UsesClass(Arithmetic::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(IntegerConversion::class)]
#[UsesClass(NumericString::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class TypeBindingTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCheckPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function f(int $x){return $x;}function target(){return f("12");}');
        self::assertSame(12, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAcceptsRecognizesCapturedCallableStringsAndEnumClasses(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php enum E{case A;}function f(E $x){return $x===E::A;}function target(){return f(E::A);}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(true, $result->normalOutcomes[0]->values['return']->native());
    }
    public function testCoercibleRejectsNonNumericStringsAndUnboundedFloats(): void
    {
        $binding = new TypeBinding(SolverFixture::context());
        self::assertFalse($binding->coercible(Term::constant('x'), 'int'));
        self::assertFalse($binding->coercible(Term::constant(INF), 'int'));
        self::assertTrue($binding->coercible(Term::constant('42'), 'int'));
    }
    public function testDeclaredResolvesSelfAndLateStaticTypes(): void
    {
        $context = SolverFixture::context('<?php class A{function f(){}}class B extends A{}');
        $body = $context->program->callable('A::f');
        self::assertNotNull($body);
        $state = new State();
        $state->lateStaticClass = 'B';
        $binding = new TypeBinding($context);
        self::assertSame('A', $binding->declared('self', $body, $state));
        self::assertSame('B', $binding->declared('static', $body, $state));
    }
    public function testScalarAcceptsIntegerWideningButDistinguishesLiteralBooleans(): void
    {
        $binding = new TypeBinding(SolverFixture::context());
        self::assertTrue($binding->scalar(Term::constant(1), 'float'));
        self::assertFalse($binding->scalar(Term::constant(1), 'true'));
        self::assertTrue($binding->scalar(Term::constant(true), 'true'));
    }
    public function testScopeResolvesNullableSelfAndParentWithinTheDeclarationOwner(): void
    {
        $context = SolverFixture::context('<?php class A{}class B extends A{}class C extends B{}');
        $types = new TypeBinding($context);
        self::assertSame('B|null', $types->scope('self|null', 'B', 'C'));
        self::assertSame('A&C', $types->scope('parent&static', 'B', 'C'));
    }
    public function testPreferenceUsesNumericStringCategoryForScalarUnions(): void
    {
        $types = new TypeBinding(SolverFixture::context());
        self::assertSame(['float','int','string','bool'], $types->preference(Term::constant('1.0'), ['int','float']));
        self::assertSame(['int','float','string','bool'], $types->preference(Term::constant('9223372036854775807'), ['int','float']));
    }
    public function testCoerceRecordsPrecisionLossOnlyForImplicitIntegerConversion(): void
    {
        $types = new TypeBinding(SolverFixture::context());
        $loss = $types->coerce(Term::constant('1.5'), 'int');
        self::assertSame(1, $loss->value->native());
        self::assertTrue($loss->diagnostic);
        self::assertFalse($types->coerce(Term::constant('1.0'), 'int')->diagnostic);
    }
    public function testReportRecordsAnAppliedTargetDiagnosticAtItsSource(): void
    {
        $context = SolverFixture::context();
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        (new TypeBinding($context))->report(new TypeCheck(Term::constant(1), diagnostic: true), $source);
        self::assertSame(['PHP_WARNING'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testMatchingPreservesAnExactIntegerRegardlessOfUnionOrder(): void
    {
        $types = new TypeBinding(SolverFixture::context());
        self::assertSame(1, $types->matching(Term::constant(1), ['float','int'])?->value->native());
        self::assertSame(1.0, $types->matching(Term::constant(1), ['float'])?->value->native());
        self::assertNull($types->matching(Term::constant('1'), ['int']));
    }

    /**
     * @param scalar|null $expected Accepted scalar value
     */
    #[DataProvider('providerAcceptedScalars')]
    public function testCheckPreservesTargetScalarAcceptance(Term $input, string $type, bool $strict, int|float|string|bool|null $expected, bool $warning): void
    {
        $check = (new TypeBinding(SolverFixture::context()))->check($input, $type, $strict);
        self::assertSame($expected, $check->value->native());
        self::assertSame($input->isSecret(), $check->value->isSecret());
        self::assertFalse($check->mayFail);
        self::assertFalse($check->mustFail);
        self::assertSame($warning, $check->diagnostic);
        self::assertNull($check->coercion);
    }

    /**
     * @return array<string,array{Term,string,bool,scalar|null,bool}>
     */
    public static function providerAcceptedScalars(): array
    {
        return [
            'mixed' => [Term::constant(null),'mixed',true,null,false], 'untyped' => [Term::constant('x'),'',true,'x',false], 'void' => [Term::constant(null),'void',true,null,false],
            'exact int' => [Term::constant(4, true),'int',true,4,false], 'float promotion' => [Term::constant(4, true),'float',true,4.0,false],
            'exact nullable' => [Term::constant(null),'int|null',true,null,false], 'literal true' => [Term::constant(true),'true',true,true,false], 'literal false' => [Term::constant(false),'false',true,false,false],
            'numeric string' => [Term::constant('42'),'int',false,42,false], 'fraction truncation' => [Term::constant(3.5),'int',false,3,true],
            'numeric fraction truncation' => [Term::constant('3.5'),'int',false,3,true], 'integral float' => [Term::constant(3.0),'int',false,3,false],
            'numeric string to float' => [Term::constant('3.5'),'float',false,3.5,false], 'int to string' => [Term::constant(42),'string',false,'42',false],
            'bool to int' => [Term::constant(true),'int',false,1,false], 'bool to float' => [Term::constant(false),'float',false,0.0,false],
            'string to bool' => [Term::constant('0'),'bool',false,false,false], 'empty to bool' => [Term::constant(''),'bool',false,false,false],
            'exact string union' => [Term::constant('42'),'int|string',false,'42',false], 'exact int union' => [Term::constant(4),'float|int',false,4,false],
            'numeric fractional union' => [Term::constant('3.0'),'int|float',false,3.0,false], 'numeric integer union' => [Term::constant('3'),'int|float',false,3,false],
        ];
    }

    #[DataProvider('providerRejectedValues')]
    public function testCheckRejectsKnownIncompatibleValuesWithoutChangingThem(Term $input, string $type, bool $strict): void
    {
        $check = (new TypeBinding(SolverFixture::context()))->check($input, $type, $strict);
        self::assertTrue($check->mayFail);
        self::assertTrue($check->mustFail);
        self::assertSame($input, $check->value);
        self::assertSame('TypeError', $check->exception()->literal);
    }

    /**
     * @return array<string,array{Term,string,bool}>
     */
    public static function providerRejectedValues(): array
    {
        return [
            'strict string to int' => [Term::constant('3'),'int',true], 'strict float to int' => [Term::constant(3.0),'int',true],
            'null to int' => [Term::constant(null),'int',false], 'null to string' => [Term::constant(null),'string',false], 'invalid number' => [Term::constant('hello'),'float',false],
            'infinite to int' => [Term::constant(INF),'int',false], 'nan to int' => [Term::constant(NAN),'int',false], 'positive overflow' => [Term::constant(9223372036854775808.0),'int',false],
            'array to string' => [Term::array([]),'string',false], 'object to int' => [new Term('object', 'box', attributes:['class' => 'Box']),'int',false],
            'closure to array' => [new Term('closure', 'function'),'array',true], 'enum to string' => [new Term('enum', 'case', attributes:['class' => 'Choice']),'string',false],
            'wrong literal bool' => [Term::constant(false),'true',false], 'nontrue bool' => [Term::constant(1),'true',false],
        ];
    }

    #[DataProvider('providerAcceptedTypes')]
    public function testAcceptsChecksClassHierarchyIntersectionAndValueKinds(Term $value, string $type, bool $expected): void
    {
        $context = SolverFixture::context('<?php interface I{}interface J{}class B implements I{}class C extends B implements J{}enum E{case A;}function known(){}function target(){}');
        self::assertSame($expected, (new TypeBinding($context))->accepts($value, $type));
    }

    /**
     * @return array<string,array{Term,string,bool}>
     */
    public static function providerAcceptedTypes(): array
    {
        return [
            'known callable' => [Term::constant('known'),'callable',true], 'int not callable' => [Term::constant(1),'callable',false],
            'array iterable' => [Term::array([]),'iterable',true], 'array' => [Term::array([]),'array',true], 'array not object' => [Term::array([]),'object',false],
            'closure object' => [new Term('closure', 'id'),'object',true], 'closure class' => [new Term('closure', 'id'),'Closure',true], 'closure not int' => [new Term('closure', 'id'),'int',false],
            'enum object' => [new Term('enum', 'case', attributes:['class' => 'E']),'object',true], 'enum class' => [new Term('enum', 'case', attributes:['class' => 'E']),'E',true],
            'subclass' => [new Term('object', 'id', attributes:['class' => 'C']),'B',true], 'interface' => [new Term('object', 'id', attributes:['class' => 'C']),'I',true],
            'intersection' => [new Term('object', 'id', attributes:['class' => 'C']),'(I&J)',true], 'failed intersection' => [new Term('object', 'id', attributes:['class' => 'B']),'(I&J)',false],
            'unrelated class' => [new Term('object', 'id', attributes:['class' => 'B']),'J',false], 'declared symbolic' => [Term::parameter('x', 'string'),'string',true],
            'unconstrained symbolic' => [Term::parameter('x'),'string',false], 'nonobject class attribute' => [new Term('external', 'x', attributes:['class' => 'C']),'B',false],
            'int scalar' => [Term::constant(3),'int',true], 'float scalar' => [Term::constant(3.0),'float',true], 'bool scalar' => [Term::constant(false),'bool',true],
        ];
    }

    public function testCheckRetainsSymbolicTypeFailuresWithoutErasingItsOperand(): void
    {
        $value = Term::parameter('x');
        $check = (new TypeBinding(SolverFixture::context()))->check($value, 'int', true);
        self::assertSame('type-refinement', $check->value->kind);
        self::assertSame('int', $check->value->literal);
        self::assertSame([$value], $check->value->operands);
        self::assertSame('int', $check->value->attributes['type']);
        self::assertTrue($check->mayFail);
        self::assertFalse($check->mustFail);
    }

    public function testCheckKeepsImplicitUserConversionsAndCallableValidationEffectful(): void
    {
        $context = SolverFixture::context('<?php class B{function __toString():string{return "value";}}function target(){}');
        $object = new Term('object', 'id', attributes:['class' => 'B']);
        $check = (new TypeBinding($context))->check($object, 'string', false);
        self::assertTrue($check->mayFail);
        self::assertFalse($check->mustFail);
        self::assertSame($object, $check->coercion);
        self::assertSame('opaque', $check->value->kind);
        self::assertSame('Throwable', $check->exception()->literal);
        $input = Term::parameter('callable', 'string');
        $callable = (new TypeBinding($context))->check($input, 'callable', true);
        self::assertSame($input, $callable->coercion);
        self::assertSame('callable-validation', $callable->operation);
        self::assertTrue($callable->mayFail);
        self::assertFalse($callable->mustFail);
    }

    public function testReportInvalidatesReachableEffectsButPreservesPrivateLocals(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $private = $state->local('private');
        $state->memory->write($private, Term::constant(7));
        $state->memory->cells['global:value'] = Term::constant(1);
        $coercion = Term::parameter('input');
        $check = new TypeCheck(Term::opaque('UNSUPPORTED_LANGUAGE_FEATURE'), true, diagnostic:true, coercion:$coercion, operation:'coercion-test');
        (new TypeBinding($context))->report($check, new SourceRef('test', 'fixture.php', 0, 1), $state);
        self::assertSame('opaque', $state->memory->cells['global:value']->kind);
        self::assertSame(7, $state->memory->read($private)->native());
        self::assertSame(['UNSUPPORTED_LANGUAGE_FEATURE','PHP_WARNING'], array_column(array_values($context->frontiers), 'code'));
        self::assertSame(['coercion-test','implicit-integer-precision-loss'], array_column(array_values($context->frontiers), 'operation'));
    }

    public function testMatchingKeepsPromotedFloatSecrecyAndReturnsNullForNoMatch(): void
    {
        $binding = new TypeBinding(SolverFixture::context());
        $check = $binding->matching(Term::constant(3, true), ['float']);
        self::assertNotNull($check);
        self::assertSame(3.0, $check->value->native());
        self::assertTrue($check->value->isSecret());
        self::assertNull($binding->matching(Term::constant('bad'), ['int']));
    }
}

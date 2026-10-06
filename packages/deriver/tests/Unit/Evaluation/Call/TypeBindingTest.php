<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\State;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(TypeBinding::class)]
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
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Constants::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Constant\ClassNames::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
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
#[UsesClass(\Deriver\Evaluation\Havoc::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
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
#[UsesClass(SourceRef::class)]
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
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
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
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Value\NumericString::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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

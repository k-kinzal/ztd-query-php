<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\AnalysisSession::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\Query::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\ClassConstant::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Program::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Constants::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
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
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\NumericString::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
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
        $binding = new \Deriver\Internal\Solver\Call\TypeBinding(\Tests\Fake\SolverFixture::context());
        self::assertFalse($binding->coercible(\Deriver\Value\Term::constant('x'), 'int'));
        self::assertFalse($binding->coercible(\Deriver\Value\Term::constant(INF), 'int'));
        self::assertTrue($binding->coercible(\Deriver\Value\Term::constant('42'), 'int'));
    }
    public function testDeclaredResolvesSelfAndLateStaticTypes(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class A{function f(){}}class B extends A{}');
        $body = $context->program->callable('A::f');
        self::assertNotNull($body);
        $state = new \Deriver\Internal\Solver\State();
        $state->lateStaticClass = 'B';
        $binding = new \Deriver\Internal\Solver\Call\TypeBinding($context);
        self::assertSame('A', $binding->declared('self', $body, $state));
        self::assertSame('B', $binding->declared('static', $body, $state));
    }
    public function testScalarAcceptsIntegerWideningButDistinguishesLiteralBooleans(): void
    {
        $binding = new \Deriver\Internal\Solver\Call\TypeBinding(\Tests\Fake\SolverFixture::context());
        self::assertTrue($binding->scalar(\Deriver\Value\Term::constant(1), 'float'));
        self::assertFalse($binding->scalar(\Deriver\Value\Term::constant(1), 'true'));
        self::assertTrue($binding->scalar(\Deriver\Value\Term::constant(true), 'true'));
    }
    public function testScopeResolvesNullableSelfAndParentWithinTheDeclarationOwner(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class A{}class B extends A{}class C extends B{}');
        $types = new \Deriver\Internal\Solver\Call\TypeBinding($context);
        self::assertSame('B|null', $types->scope('self|null', 'B', 'C'));
        self::assertSame('A&C', $types->scope('parent&static', 'B', 'C'));
    }
    public function testPreferenceUsesNumericStringCategoryForScalarUnions(): void
    {
        $types = new \Deriver\Internal\Solver\Call\TypeBinding(\Tests\Fake\SolverFixture::context());
        self::assertSame(['float','int','string','bool'], $types->preference(\Deriver\Value\Term::constant('1.0'), ['int','float']));
        self::assertSame(['int','float','string','bool'], $types->preference(\Deriver\Value\Term::constant('9223372036854775807'), ['int','float']));
    }
    public function testCoerceRecordsPrecisionLossOnlyForImplicitIntegerConversion(): void
    {
        $types = new \Deriver\Internal\Solver\Call\TypeBinding(\Tests\Fake\SolverFixture::context());
        $loss = $types->coerce(\Deriver\Value\Term::constant('1.5'), 'int');
        self::assertSame(1, $loss->value->native());
        self::assertTrue($loss->diagnostic);
        self::assertFalse($types->coerce(\Deriver\Value\Term::constant('1.0'), 'int')->diagnostic);
    }
    public function testReportRecordsAnAppliedTargetDiagnosticAtItsSource(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        (new \Deriver\Internal\Solver\Call\TypeBinding($context))->report(new \Deriver\Internal\Solver\Call\TypeCheck(\Deriver\Value\Term::constant(1), diagnostic: true), $source);
        self::assertSame(['PHP_WARNING'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testMatchingPreservesAnExactIntegerRegardlessOfUnionOrder(): void
    {
        $types = new \Deriver\Internal\Solver\Call\TypeBinding(\Tests\Fake\SolverFixture::context());
        self::assertSame(1, $types->matching(\Deriver\Value\Term::constant(1), ['float','int'])?->value->native());
        self::assertSame(1.0, $types->matching(\Deriver\Value\Term::constant(1), ['float'])?->value->native());
        self::assertNull($types->matching(\Deriver\Value\Term::constant('1'), ['int']));
    }
}
